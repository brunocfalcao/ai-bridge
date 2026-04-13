<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Providers\ClaudeCli;

use BrunoCFalcao\AiBridge\Chat\Exceptions\ChatProviderException;
use BrunoCFalcao\AiBridge\Contracts\ChatProvider;
use BrunoCFalcao\AiBridge\Providers\Concerns\ParsesOpenAiSse;
use Generator;
use Illuminate\Support\Facades\Http;

/**
 * Chat provider that routes through the openclaw-claude-bridge Node.js proxy.
 * The bridge spawns Claude Code CLI processes, using the Claude subscription.
 */
class ClaudeCliProvider implements ChatProvider
{
    use ParsesOpenAiSse;

    public function __construct(
        protected string $url,
        protected string $model,
        protected int $timeout = 120,
        protected ?string $agentName = null,
    ) {}

    public function stream(array $messages, ?string $conversationId = null): Generator
    {
        $payload = [
            'model' => $this->model,
            'messages' => $this->injectSessionIdentity($messages, $conversationId),
            'stream' => true,
            'tools' => $this->noopTool(),
        ];

        try {
            $response = Http::timeout($this->timeout)
                ->withOptions(['stream' => true])
                ->post("{$this->url}/v1/chat/completions", $payload);

            yield from $this->parseSseStream($response);
        } catch (ChatProviderException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ChatProviderException(
                "Claude CLI provider error: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    public function send(array $messages, ?string $conversationId = null): string
    {
        $payload = [
            'model' => $this->model,
            'messages' => $this->injectSessionIdentity($messages, $conversationId),
            'stream' => false,
            'tools' => $this->noopTool(),
        ];

        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->url}/v1/chat/completions", $payload);

            $response->throw();
            $content = $response->json('choices.0.message.content') ?? '';

            if (ChatProviderException::isErrorResponse($content)) {
                throw ChatProviderException::fromErrorResponse($content);
            }

            return $content;
        } catch (ChatProviderException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ChatProviderException(
                "Claude CLI provider error: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    public function healthy(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->url}/health");

            return $response->ok() && ($response->json('status') === 'ok');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Inject session identity markers so the bridge reuses CLI sessions.
     */
    protected function injectSessionIdentity(array $messages, ?string $conversationId): array
    {
        if ($this->agentName) {
            foreach ($messages as $i => $msg) {
                if ($msg['role'] === 'system') {
                    $messages[$i]['content'] = "**Name:** {$this->agentName}\n\n".$msg['content'];

                    break;
                }
            }
        }

        if ($conversationId) {
            $meta = json_encode([
                'conversation_label' => $conversationId,
                'sender' => 'user',
            ]);

            foreach ($messages as $i => $msg) {
                if ($msg['role'] === 'user') {
                    $messages[$i]['content'] = "Conversation info (untrusted metadata):\n```json\n{$meta}\n```\n\n".$msg['content'];

                    break;
                }
            }
        }

        return $messages;
    }

    /**
     * Noop tool required by the bridge.
     */
    protected function noopTool(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'noop',
                    'description' => 'No-op placeholder',
                    'parameters' => ['type' => 'object', 'properties' => new \stdClass],
                ],
            ],
        ];
    }
}
