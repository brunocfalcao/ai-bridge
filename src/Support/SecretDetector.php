<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Support;

class SecretDetector
{
    protected const PATTERNS = [
        'AWS Access Key' => '/\bAKIA[0-9A-Z]{16}\b/',
        'GitHub Token' => '/\b(ghp_|gho_|ghs_|github_pat_)[A-Za-z0-9_]{20,}\b/',
        'Anthropic Key' => '/\bsk-ant-[A-Za-z0-9\-_]{20,}\b/',
        'OpenAI Key' => '/\bsk-proj-[A-Za-z0-9\-_]{20,}\b/',
        'Stripe Key' => '/\b(sk_live_|sk_test_|pk_live_|pk_test_)[A-Za-z0-9]{20,}\b/',
        'Generic SK Key' => '/\bsk-[A-Za-z0-9\-_]{32,}\b/',
        'Slack Token' => '/\b(xoxb-|xoxp-|xoxs-)[A-Za-z0-9\-]{20,}\b/',
        'Bearer Token' => '/Bearer\s+[A-Za-z0-9\-_\.]{20,}/',
        'Private Key' => '/-----BEGIN\s+(?:RSA\s+|EC\s+|DSA\s+)?PRIVATE\s+KEY-----/',
        'JWT Token' => '/eyJ[A-Za-z0-9\-_]+\.eyJ[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+/',
        'Database URL' => '/\b(mysql|postgres|mongodb):\/\/[^\s]+:([^\s@]+)@[^\s]+/',
        'Key-Value Secret' => '/(api_key|api_secret|password|secret_key|access_token)\s*[=:]\s*["\'][^\s"\']{8,}["\']/i',
    ];

    public function detect(string $text): array
    {
        $found = [];

        foreach (self::PATTERNS as $label => $pattern) {
            if (preg_match($pattern, $text)) {
                $found[] = $label;
            }
        }

        return $found;
    }

    public function containsSecrets(string $text): bool
    {
        return $this->detect($text) !== [];
    }
}
