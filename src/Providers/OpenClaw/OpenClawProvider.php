<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Providers\OpenClaw;

use BrunoCFalcao\AiBridge\Chat\Exceptions\ChatProviderException;
use BrunoCFalcao\AiBridge\Contracts\ChatProvider;
use BrunoCFalcao\AiBridge\Providers\Concerns\ParsesOpenAiSse;
use Generator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Chat provider that routes through an OpenClaw gateway.
 * OpenClaw handles agent routing, tools, and session management.
 */
class OpenClawProvider implements ChatProvider
{
    use ParsesOpenAiSse;

    public function __construct(
        protected string $url,
        protected string $model,
        protected int $timeout = 120,
        protected ?string $token = null,
    ) {}

    public function stream(array $messages, ?string $conversationId = null): Generator
    {
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'stream' => true,
        ];

        try {
            $response = $this->httpClient()
                ->withOptions(['stream' => true])
                ->post("{$this->url}/v1/chat/completions", $payload);

            yield from $this->parseSseStream($response);
        } catch (ChatProviderException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ChatProviderException(
                "OpenClaw provider error: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    public function send(array $messages, ?string $conversationId = null): string
    {
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'stream' => false,
        ];

        try {
            $response = $this->httpClient()
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
                "OpenClaw provider error: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    public function healthy(): bool
    {
        try {
            $response = $this->httpClient()->timeout(5)->get("{$this->url}/v1/models");

            return $response->ok();
        } catch (\Throwable) {
            return false;
        }
    }

    protected function httpClient(): PendingRequest
    {
        $client = Http::timeout($this->timeout);

        if ($this->token) {
            $client = $client->withToken($this->token);
        }

        return $client;
    }
}
