<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Providers;

use Illuminate\Http\Client\PendingRequest;
use Prism\Prism\Providers\Anthropic\Anthropic;

/**
 * Anthropic provider variant that uses Authorization: Bearer for OAuth tokens
 * obtained via `claude setup-token`. Matches the Claude Code CLI request shape.
 *
 * Standard Anthropic uses x-api-key header. This variant supports Bearer token
 * authentication for OAuth access tokens (sk-ant-oat-*).
 */
class BearerAnthropic extends Anthropic
{
    protected function client(array $options = [], array $retry = [], ?string $baseUrl = null): PendingRequest
    {
        return $this->baseClient()
            ->withHeaders(array_filter([
                'anthropic-version' => $this->apiVersion,
                'anthropic-beta' => implode(',', array_filter([
                    'oauth-2025-04-20',
                    $this->betaFeatures,
                ])),
                'User-Agent' => config('ai-bridge.oauth.anthropic.user_agent', 'ai-bridge/1.0'),
            ]))
            ->withToken($this->apiKey)
            ->withOptions($options)
            ->when($retry !== [], fn ($client) => $client->retry(...$retry))
            ->baseUrl($baseUrl ?? $this->url);
    }
}
