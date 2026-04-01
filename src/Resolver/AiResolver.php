<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Resolver;

class AiResolver
{
    /**
     * Resolve an AI scope to a provider array for Promptable::prompt(provider: $array).
     *
     * The returned array is ordered: primary provider first, then the fallback chain.
     * Laravel AI's withModelFailover() will iterate this array, catching
     * FailoverableException (InsufficientCreditsException, RateLimitedException,
     * ProviderOverloadedException) and trying the next provider automatically.
     *
     * @return array<string, string> Keys are provider names, values are model strings.
     */
    public function resolve(string|\BackedEnum $scope): array
    {
        $scopeValue = $scope instanceof \BackedEnum ? $scope->value : $scope;
        $configKey = $this->configKey();

        $primary = config("{$configKey}.scopes.{$scopeValue}")
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
     * Resolve only the primary provider+model for direct Prism usage (e.g. OsintAgent).
     *
     * @return array{0: string, 1: string} [providerName, modelName]
     */
    public function resolveUsing(string|\BackedEnum $scope): array
    {
        $scopeValue = $scope instanceof \BackedEnum ? $scope->value : $scope;
        $configKey = $this->configKey();

        $primary = config("{$configKey}.scopes.{$scopeValue}")
            ?? config("{$configKey}.default");

        return $this->parse((string) $primary);
    }

    /**
     * The config key where AI scope resolution lives.
     * Projects can override this by setting 'ai-bridge.ai_config_key'.
     * Defaults to 'ai-bridge.ai'.
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
