<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Chat\Exceptions;

use RuntimeException;

class ChatProviderException extends RuntimeException
{
    protected const ERROR_PATTERNS = [
        'API Error:',
        'out of extra usage',
        '"type":"error"',
        '"type":"invalid_request_error"',
        'rate_limit',
        'overloaded_error',
        'insufficient_quota',
    ];

    public static function fromErrorResponse(string $content): self
    {
        return new self("Provider returned an error: {$content}");
    }

    public static function unavailable(string $provider): self
    {
        return new self("Provider [{$provider}] is unavailable");
    }

    public static function allFailed(self $lastException): self
    {
        return new self(
            'All providers in the fallback chain failed. Last error: '.$lastException->getMessage(),
            previous: $lastException,
        );
    }

    /**
     * Check if a response content string contains known API error patterns.
     */
    public static function isErrorResponse(string $content): bool
    {
        foreach (self::ERROR_PATTERNS as $pattern) {
            if (str_contains($content, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
