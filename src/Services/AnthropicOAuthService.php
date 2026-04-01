<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Services;

use Illuminate\Support\Facades\Http;

class AnthropicOAuthService
{
    private const TOKEN_URL = 'https://console.anthropic.com/v1/oauth/token';

    private const CLIENT_ID = '9d1c250a-e61b-44d9-88ed-5944d1962f5e';

    /**
     * Refresh an expired Anthropic OAuth token.
     *
     * @return array{access_token: string, refresh_token: ?string, expires_in: ?int}
     */
    public function refreshToken(string $refreshToken): array
    {
        $response = Http::asJson()->post(self::TOKEN_URL, [
            'grant_type' => 'refresh_token',
            'client_id' => self::CLIENT_ID,
            'refresh_token' => $refreshToken,
        ]);

        $response->throw();

        return $response->json();
    }
}
