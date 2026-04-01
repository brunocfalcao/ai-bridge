<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Jobs;

use BrunoCFalcao\AiBridge\Events\ChatComplete;
use BrunoCFalcao\AiBridge\Events\ChatDelta;
use BrunoCFalcao\AiBridge\Events\ChatError;
use BrunoCFalcao\AiBridge\Events\ChatInit;
use BrunoCFalcao\AiBridge\Services\ConversationSummarizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Streaming\Events\TextDelta;
use RedisException;

class ProcessChatMessage implements ShouldQueue
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
        public array $providerArray,
        public array $providerKeys = [],
        public array $embeddingConfig = [],
        public ?string $agentClass = null,
        public array $agentConfig = [],
    ) {
        $this->onQueue('chat');
    }

    public function handle(): void
    {
        $user = $this->resolveUser();
        if (! $user) {
            return;
        }

        $this->restoreProviderKeys();

        try {
            $agent = $this->resolveAgent();

            $streamMessage = $this->message;

            if (method_exists($agent, 'setCurrentMessage')) {
                $agent->setCurrentMessage($this->message);

                // Strip the /command prefix — the command instructions are
                // already injected into the system prompt by the agent.
                // The LLM only needs to see the arguments.
                if (str_starts_with(trim($this->message), '/')) {
                    $parts = explode(' ', trim($this->message), 2);
                    $commandArgs = $parts[1] ?? '';
                    $commandName = ltrim($parts[0], '/');
                    $streamMessage = $commandArgs
                        ? $commandArgs
                        : "Execute the /{$commandName} command.";
                }
            }

            $stream = $agent
                ->continue($this->conversationId, as: $user)
                ->stream($streamMessage, provider: $this->providerArray);

            $title = DB::table('ai_conversations')
                ->where('id', $this->conversationId)
                ->value('title') ?? Str::limit($this->message, 50);

            $this->resilientBroadcast(new ChatInit(
                userId: $this->userId,
                conversationId: $this->conversationId,
                title: $title,
            ));

            $afterToolResult = false;

            foreach ($stream as $event) {
                if ($this->isCancelled()) {
                    DB::table('ai_conversation_messages')->insert([
                        'id' => (string) Str::uuid(),
                        'conversation_id' => $this->conversationId,
                        'user_id' => $this->userId,
                        'agent' => $this->agentClass ?? 'default',
                        'role' => 'assistant',
                        'content' => '*Chat stopped. Go ahead.*',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    break;
                }

                if ($event instanceof TextDelta) {
                    $delta = $event->delta;

                    if ($afterToolResult) {
                        $delta = "\n\n".$delta;
                        $afterToolResult = false;
                    }

                    $this->resilientBroadcast(new ChatDelta(
                        userId: $this->userId,
                        conversationId: $this->conversationId,
                        delta: $delta,
                    ));
                } else {
                    // After tool calls, inject separator
                    $afterToolResult = true;
                }
            }

            $this->resilientBroadcast(new ChatComplete(
                userId: $this->userId,
                conversationId: $this->conversationId,
            ));

            // Summarize asynchronously with a cheap model
            $cheapProvider = $this->cheapProviderArray();
            $conversationId = $this->conversationId;
            dispatch(function () use ($conversationId, $cheapProvider) {
                (new ConversationSummarizer)->summarizeIfNeeded(
                    $conversationId,
                    20,
                    $cheapProvider
                );
            })->onQueue('default');

        } catch (\Throwable $e) {
            report($e);

            $this->resilientBroadcast(new ChatError(
                userId: $this->userId,
                conversationId: $this->conversationId,
                message: $this->resolveErrorMessage($e),
            ));
        }
    }

    protected function resolveUser(): ?object
    {
        $userModel = config('auth.providers.users.model');

        return $userModel::find($this->userId);
    }

    protected function restoreProviderKeys(): void
    {
        foreach ($this->providerKeys as $provider => $key) {
            config(["ai.providers.{$provider}.key" => $key]);
        }

        if (! empty($this->embeddingConfig)) {
            $provider = $this->embeddingConfig['provider'];
            $model = $this->embeddingConfig['model'] ?? null;

            config([
                "ai.providers.{$provider}.key" => $this->embeddingConfig['key'],
                'ai.default_for_embeddings' => $provider,
            ]);

            if ($model) {
                config([
                    "ai.providers.{$provider}.models.embeddings.default" => $model,
                ]);
            }
        }
    }

    protected function resolveAgent(): object
    {
        if ($this->agentClass && class_exists($this->agentClass)) {
            $agent = new ($this->agentClass);

            // Pass any config to the agent (e.g. project context)
            if (method_exists($agent, 'configure')) {
                $agent->configure($this->agentConfig);
            }

            return $agent;
        }

        // Fallback: plain agent with no tools
        return agent(instructions: 'You are a helpful AI assistant.');
    }

    protected function cheapProviderArray(): array
    {
        $cheapModels = config('ai-bridge.cheap_models', []);
        $result = [];

        foreach ($this->providerArray as $provider => $model) {
            $result[$provider] = $cheapModels[$provider] ?? $model;
        }

        return $result;
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

    protected function resolveErrorMessage(\Throwable $e): string
    {
        $msg = $e->getMessage();

        $patterns = [
            'authentication' => 'Authentication failed. Please check your API key.',
            'rate limit' => 'Rate limit exceeded. Please wait a moment and try again.',
            'timeout' => 'The request timed out. Try a shorter message or simpler task.',
            '500' => 'The AI provider is experiencing issues. Please try again later.',
            'overloaded' => 'The AI model is overloaded. Please try again in a few minutes.',
            'network' => 'Network error. Please check your connection.',
        ];

        foreach ($patterns as $needle => $message) {
            if (stripos($msg, (string) $needle) !== false) {
                return $message;
            }
        }

        return Str::limit($msg, 120);
    }
}
