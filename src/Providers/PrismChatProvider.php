<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Providers;

use BrunoCFalcao\AiBridge\Chat\Exceptions\ChatProviderException;
use BrunoCFalcao\AiBridge\Contracts\ChatProvider;
use Generator;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

/**
 * Chat provider that wraps Prism for standard API providers
 * (anthropic, openai, openrouter, gemini, etc.).
 */
class PrismChatProvider implements ChatProvider
{
    public function __construct(
        protected string $provider,
        protected string $model,
        protected ?string $apiKey = null,
    ) {
        $this->apiKey ??= config("ai.providers.{$this->provider}.key");
    }

    public function stream(array $messages, ?string $conversationId = null): Generator
    {
        try {
            $request = Prism::text()
                ->using($this->provider, $this->model, $this->providerConfig());

            $request = $this->applyMessages($request, $messages);

            foreach ($request->asStream() as $event) {
                if ($event instanceof TextDeltaEvent) {
                    yield ['type' => 'delta', 'content' => $event->delta];
                }
            }

            yield ['type' => 'done', 'content' => null];
        } catch (ChatProviderException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ChatProviderException(
                "[{$this->provider}] {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    public function send(array $messages, ?string $conversationId = null): string
    {
        try {
            $request = Prism::text()
                ->using($this->provider, $this->model, $this->providerConfig());

            $request = $this->applyMessages($request, $messages);

            return $request->asText()->text;
        } catch (ChatProviderException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ChatProviderException(
                "[{$this->provider}] {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    public function healthy(): bool
    {
        return $this->apiKey !== null && $this->apiKey !== '';
    }

    /** @return array<string, mixed> */
    protected function providerConfig(): array
    {
        $config = [];

        if ($this->apiKey) {
            $config['api_key'] = $this->apiKey;
        }

        return $config;
    }

    /**
     * Apply OpenAI-format message array to a Prism text request.
     */
    protected function applyMessages(mixed $request, array $messages): mixed
    {
        $prismMessages = [];

        foreach ($messages as $msg) {
            match ($msg['role']) {
                'system' => $request = $request->withSystemPrompt($msg['content'] ?? ''),
                'user' => $prismMessages[] = new UserMessage($msg['content'] ?? ''),
                'assistant' => $prismMessages[] = new AssistantMessage($msg['content'] ?? ''),
                default => null,
            };
        }

        if ($prismMessages) {
            $request = $request->withMessages($prismMessages);
        }

        return $request;
    }
}
