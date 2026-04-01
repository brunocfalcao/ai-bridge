<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Providers;

use Illuminate\Support\Facades\Http;

class AnthropicOAuthService
{
    /**
     * Refresh an expired Anthropic OAuth token.
     *
     * @return array{access_token: string, refresh_token: ?string, expires_in: ?int}
     */
    public function refreshToken(string $refreshToken): array
    {
        $response = Http::asJson()->post(config('ai-bridge.oauth.anthropic.token_url'), [
            'grant_type' => 'refresh_token',
            'client_id' => config('ai-bridge.oauth.anthropic.client_id'),
            'refresh_token' => $refreshToken,
        ]);

        $response->throw();

        return $response->json();
    }
}
