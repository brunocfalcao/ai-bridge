<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Resolver;

class AiResolver
{
    /**
     * Resolve a named AI connection to a provider array for Promptable::prompt(provider: $array).
     *
     * The returned array is ordered: primary provider first, then the fallback chain.
     * Laravel AI's withModelFailover() will iterate this array, catching
     * FailoverableException (InsufficientCreditsException, RateLimitedException,
     * ProviderOverloadedException) and trying the next provider automatically.
     *
     * @return array<string, string> Keys are provider names, values are model strings.
     */
    public function using(string|\BackedEnum $connection): array
    {
        $name = $connection instanceof \BackedEnum ? $connection->value : $connection;
        $configKey = $this->configKey();

        $primary = config("{$configKey}.connections.{$name}")
            ?? config("{$configKey}.default");

        $providers = [];
        $seen = [];
        $current = (string) $primary;

        while ($current !== '' && ! isset($seen[$current])) {
            [$provider, $model] = $this->parse($current);
            $providers[$provider] = $model;
            $seen[$current] = true;

            $next = config("{$configKey}.fallbacks.{$provider}");

            if ($next === null) {
                break;
            }

            $current = (string) $next;
        }

        return $providers;
    }

    /**
     * Resolve only the primary provider+model for direct Prism usage.
     *
     * @return array{0: string, 1: string} [providerName, modelName]
     */
    public function primary(string|\BackedEnum $connection): array
    {
        $name = $connection instanceof \BackedEnum ? $connection->value : $connection;
        $configKey = $this->configKey();

        $primary = config("{$configKey}.connections.{$name}")
            ?? config("{$configKey}.default");

        return $this->parse((string) $primary);
    }

    /**
     * The config key where AI connection resolution lives.
     * Projects can override this by setting 'ai-bridge.ai_config_key'.
     */
    protected function configKey(): string
    {
        return config('ai-bridge.ai_config_key', 'ai-bridge.resolver');
    }

    /**
     * Parse a "provider:model" string into [provider, model].
     *
     * Provider is the segment before the first colon.
     * Model is everything after the first colon (may contain slashes or colons).
     *
     * @return array{0: string, 1: string}
     */
    private function parse(string $value): array
    {
        $pos = strpos($value, ':');

        if ($pos === false) {
            return [$value, ''];
        }

        return [substr($value, 0, $pos), substr($value, $pos + 1)];
    }
}
