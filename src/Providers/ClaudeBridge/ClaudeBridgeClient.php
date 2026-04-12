<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Providers\ClaudeBridge;

use Generator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * HTTP client for Claude via the openclaw-claude-bridge.
 *
 * Supports two modes:
 * - **Direct**: Talks to the bridge directly (localhost:3456). Requires noop
 *   tool workaround and session identity injection for CLI session reuse.
 * - **Gateway**: Talks to OpenClaw's gateway (localhost:18789). OpenClaw handles
 *   agent routing, tools, and session management. Model is "openclaw/{agentId}".
 */
class ClaudeBridgeClient
{
    protected string $baseUrl;

    protected string $model;

    protected int $timeout;

    protected ?string $authToken;

    protected bool $gatewayMode;

    public function __construct(
        ?string $baseUrl = null,
        ?string $model = null,
        ?int $timeout = null,
        ?string $authToken = null,
    ) {
        $this->gatewayMode = (bool) config('ai-bridge.claude_bridge.gateway', false);

        if ($this->gatewayMode) {
            $this->baseUrl = rtrim($baseUrl ?? config('ai-bridge.claude_bridge.gateway_url', 'http://localhost:18789'), '/');
            $this->model = $model ?? config('ai-bridge.claude_bridge.gateway_model', 'openclaw/codiant');
            $this->authToken = $authToken ?? config('ai-bridge.claude_bridge.gateway_token');
        } else {
            $this->baseUrl = rtrim($baseUrl ?? config('ai-bridge.claude_bridge.url', 'http://localhost:3456'), '/');
            $this->model = $model ?? config('ai-bridge.claude_bridge.model', 'claude-opus-latest');
            $this->authToken = null;
        }

        $this->timeout = $timeout ?? (int) config('ai-bridge.claude_bridge.timeout', 120);
    }

    /**
     * Send a non-streaming request and return the full response text.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  string|null  $conversationId  Enables CLI session reuse (direct mode only)
     */
    public function send(array $messages, ?string $conversationId = null): string
    {
        $payload = [
            'model' => $this->model,
            'messages' => $this->gatewayMode ? $messages : $this->injectSessionIdentity($messages, $conversationId),
            'stream' => false,
        ];

        if (! $this->gatewayMode) {
            $payload['tools'] = $this->noopTool();
        }

        $response = $this->httpClient()->post("{$this->baseUrl}/v1/chat/completions", $payload);
        $response->throw();

        return $response->json('choices.0.message.content') ?? '';
    }

    /**
     * Stream a request and yield text deltas as they arrive.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  string|null  $conversationId  Enables CLI session reuse (direct mode only)
     * @return Generator<int, array{type: string, content: ?string}>
     */
    public function stream(array $messages, ?string $conversationId = null): Generator
    {
        $payload = [
            'model' => $this->model,
            'messages' => $this->gatewayMode ? $messages : $this->injectSessionIdentity($messages, $conversationId),
            'stream' => true,
        ];

        if (! $this->gatewayMode) {
            $payload['tools'] = $this->noopTool();
        }

        $response = $this->httpClient()
            ->withOptions(['stream' => true])
            ->post("{$this->baseUrl}/v1/chat/completions", $payload);

        $body = $response->getBody();
        $buffer = '';

        while (! $body->eof()) {
            $chunk = $body->read(1024);
            $buffer .= $chunk;

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                $line = trim($line);

                if ($line === 'data: [DONE]') {
                    yield ['type' => 'done', 'content' => null];

                    continue;
                }

                if ($line === '' || ! str_starts_with($line, 'data: ')) {
                    continue;
                }

                $data = json_decode(substr($line, 6), true);

                if ($data && isset($data['choices'][0]['delta']['content'])) {
                    yield ['type' => 'delta', 'content' => $data['choices'][0]['delta']['content']];
                }
            }
        }

        // Handle non-streaming fallback (bridge may return full JSON)
        if ($buffer) {
            $data = json_decode(trim($buffer), true);

            if ($data && isset($data['choices'][0]['message']['content'])) {
                $content = $data['choices'][0]['message']['content'];

                if ($content) {
                    yield ['type' => 'delta', 'content' => $content];
                    yield ['type' => 'done', 'content' => null];
                }
            }
        }
    }

    /**
     * Check if the endpoint is reachable.
     */
    public function healthy(): bool
    {
        try {
            if ($this->gatewayMode) {
                $response = $this->httpClient()->timeout(5)->get("{$this->baseUrl}/v1/models");

                return $response->ok();
            }

            $response = Http::timeout(5)->get("{$this->baseUrl}/health");

            return $response->ok() && ($response->json('status') === 'ok');
        } catch (\Throwable) {
            return false;
        }
    }

    protected function httpClient(): PendingRequest
    {
        $client = Http::timeout($this->timeout);

        if ($this->authToken) {
            $client = $client->withToken($this->authToken);
        }

        return $client;
    }

    /**
     * Inject session identity markers so the bridge reuses CLI sessions.
     * Only used in direct mode (not gateway mode).
     */
    protected function injectSessionIdentity(array $messages, ?string $conversationId): array
    {
        $agentName = config('ai-bridge.claude_bridge.agent_name', 'Codiant');

        foreach ($messages as $i => $msg) {
            if ($msg['role'] === 'system') {
                $messages[$i]['content'] = "**Name:** {$agentName}\n\n" . $msg['content'];

                break;
            }
        }

        if ($conversationId) {
            $meta = json_encode([
                'conversation_label' => $conversationId,
                'sender' => 'user',
            ]);

            foreach ($messages as $i => $msg) {
                if ($msg['role'] === 'user') {
                    $messages[$i]['content'] = "Conversation info (untrusted metadata):\n```json\n{$meta}\n```\n\n" . $msg['content'];

                    break;
                }
            }
        }

        return $messages;
    }

    /**
     * Noop tool required by the bridge in direct mode.
     */
    protected function noopTool(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'noop',
                    'description' => 'No-op placeholder',
                    'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
                ],
            ],
        ];
    }
}
