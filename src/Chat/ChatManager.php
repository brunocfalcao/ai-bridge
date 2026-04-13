<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Chat;

use BrunoCFalcao\AiBridge\Chat\Exceptions\ChatProviderException;
use BrunoCFalcao\AiBridge\Contracts\ChatProvider;
use BrunoCFalcao\AiBridge\Providers\ClaudeCli\ClaudeCliProvider;
use BrunoCFalcao\AiBridge\Providers\OpenClaw\OpenClawProvider;
use BrunoCFalcao\AiBridge\Providers\PrismChatProvider;
use BrunoCFalcao\AiBridge\Resolver\AiResolver;
use Generator;

class ChatManager
{
    public function __construct(
        protected AiResolver $resolver,
    ) {}

    /**
     * Stream a chat response through the connection chain with automatic fallback.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return Generator<int, array{type: string, content: ?string}>
     */
    public function stream(array $messages, ?string $connection = null, ?string $conversationId = null): Generator
    {
        $chain = $this->resolveChain($connection);
        $lastException = null;

        foreach ($chain as $provider => $model) {
            try {
                $client = $this->resolve($provider, $model);

                yield from $client->stream($messages, $conversationId);

                return;
            } catch (ChatProviderException $e) {
                $lastException = $e;

                continue;
            }
        }

        throw $lastException
            ? ChatProviderException::allFailed($lastException)
            : new ChatProviderException('No providers configured');
    }

    /**
     * Send a non-streaming chat request through the connection chain with automatic fallback.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function send(array $messages, ?string $connection = null, ?string $conversationId = null): string
    {
        $chain = $this->resolveChain($connection);
        $lastException = null;

        foreach ($chain as $provider => $model) {
            try {
                $client = $this->resolve($provider, $model);

                return $client->send($messages, $conversationId);
            } catch (ChatProviderException $e) {
                $lastException = $e;

                continue;
            }
        }

        throw $lastException
            ? ChatProviderException::allFailed($lastException)
            : new ChatProviderException('No providers configured');
    }

    /**
     * Check if the primary provider for a connection is healthy.
     */
    public function healthy(?string $connection = null): bool
    {
        [$provider, $model] = $this->resolver->primary($connection ?? '__default__');

        return $this->resolve($provider, $model)->healthy();
    }

    /**
     * Resolve a provider instance from provider name and model.
     */
    public function resolve(string $provider, string $model): ChatProvider
    {
        return match ($provider) {
            'claude-cli' => new ClaudeCliProvider(
                url: (string) config('ai-bridge.claude_cli.url', 'http://localhost:3456'),
                model: $model,
                timeout: (int) config('ai-bridge.claude_cli.timeout', 120),
                agentName: config('ai-bridge.claude_cli.agent_name'),
            ),
            'openclaw' => new OpenClawProvider(
                url: (string) config('ai-bridge.openclaw.url', 'http://localhost:18789'),
                model: $model,
                timeout: (int) config('ai-bridge.openclaw.timeout', 120),
                token: config('ai-bridge.openclaw.token'),
            ),
            default => new PrismChatProvider(
                provider: $provider,
                model: $model,
            ),
        };
    }

    /**
     * Resolve the full connection chain (primary + fallbacks) from a connection name.
     *
     * @return array<string, string>
     */
    protected function resolveChain(?string $connection): array
    {
        return $this->resolver->using($connection ?? '__default__');
    }
}
