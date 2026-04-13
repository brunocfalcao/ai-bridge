<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Chat;

use BrunoCFalcao\AiBridge\Chat\Events\ChatComplete;
use BrunoCFalcao\AiBridge\Chat\Events\ChatDelta;
use BrunoCFalcao\AiBridge\Chat\Events\ChatError;
use BrunoCFalcao\AiBridge\Chat\Events\ChatInit;
use BrunoCFalcao\AiBridge\Chat\Models\Conversation;
use BrunoCFalcao\AiBridge\Chat\Models\ConversationMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RedisException;

/**
 * Queued job that streams a chat message through the Claude bridge
 * and broadcasts deltas via WebSocket events.
 *
 * This is the bridge equivalent of ProcessChatMessage — same event
 * contract (ChatInit, ChatDelta, ChatComplete, ChatError) so the
 * frontend works identically regardless of which provider is used.
 */
class ProcessBridgeChatMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 3;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function __construct(
        public int $userId,
        public string $message,
        public ?string $conversationId,
        public string $systemPrompt = '',
        public ?string $connection = null,
    ) {
        $this->onQueue(config('ai-bridge.chat.queue', 'chat'));
    }

    public function handle(): void
    {
        $user = $this->resolveUser();
        if (! $user) {
            return;
        }

        try {
            $chat = app(ChatManager::class);

            // Build message history
            $messages = $this->buildMessages();

            $title = Conversation::query()
                ->where('id', $this->conversationId)
                ->value('title') ?? Str::limit($this->message, 50);

            $this->resilientBroadcast(new ChatInit(
                userId: $this->userId,
                conversationId: $this->conversationId,
                title: $title,
            ));

            $fullContent = '';

            foreach ($chat->stream($messages, $this->connection, $this->conversationId) as $event) {
                if ($this->isCancelled()) {
                    $this->insertAssistantMessage('*Chat stopped. Go ahead.*');

                    break;
                }

                if ($event['type'] === 'delta' && $event['content']) {
                    $fullContent .= $event['content'];

                    $this->resilientBroadcast(new ChatDelta(
                        userId: $this->userId,
                        conversationId: $this->conversationId,
                        delta: $event['content'],
                    ));
                }

                if ($event['type'] === 'done') {
                    break;
                }
            }

            if ($fullContent) {
                $this->insertAssistantMessage($fullContent);
            }

            $this->resilientBroadcast(new ChatComplete(
                userId: $this->userId,
                conversationId: $this->conversationId,
            ));

        } catch (\Throwable $e) {
            report($e);

            $this->resilientBroadcast(new ChatError(
                userId: $this->userId,
                conversationId: $this->conversationId,
                message: Str::limit($e->getMessage(), 120),
            ));
        }
    }

    /**
     * Build the OpenAI-format messages array with system prompt + history.
     *
     * @return array<int, array{role: string, content: string}>
     */
    protected function buildMessages(): array
    {
        $messages = [];

        if ($this->systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $this->systemPrompt];
        }

        $history = ConversationMessage::query()
            ->where('conversation_id', $this->conversationId)
            ->orderBy('created_at')
            ->get(['role', 'content']);

        foreach ($history as $msg) {
            $messages[] = ['role' => $msg->role, 'content' => $msg->content ?? ''];
        }

        return $messages;
    }

    protected function resolveUser(): ?object
    {
        $userModel = config('auth.providers.users.model');

        return $userModel::find($this->userId);
    }

    protected function isCancelled(): bool
    {
        try {
            $key = "chat:cancel:{$this->userId}:{$this->conversationId}";

            if (Cache::has($key)) {
                Cache::forget($key);

                return true;
            }
        } catch (RedisException) {
            // Ignore Redis errors for cancellation
        }

        return false;
    }

    protected function insertAssistantMessage(string $content): void
    {
        ConversationMessage::query()->insert([
            'id' => (string) Str::uuid(),
            'conversation_id' => $this->conversationId,
            'user_id' => $this->userId,
            'role' => 'assistant',
            'content' => $content,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function resilientBroadcast(object $event): void
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                broadcast($event);

                return;
            } catch (RedisException) {
                if ($attempt < 3) {
                    usleep(200_000 * $attempt);
                }
            }
        }
    }
}
