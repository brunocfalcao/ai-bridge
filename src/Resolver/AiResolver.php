<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Resolver;

use InvalidArgumentException;
use Laravel\Ai\Ai;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Events\ProviderFailedOver;
use Laravel\Ai\Exceptions\FailoverableException;

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

            if (isset($providers[$provider]) && $providers[$provider] !== $model) {
                throw new InvalidArgumentException("Text fallback uses the same provider [{$provider}] with a different model; Laravel AI cannot represent that chain.");
            }

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
     * Generate an embedding vector for the given text.
     *
     * Resolves the provider and model from ai-bridge.resolver.embedding.
     *
     * @return array<float> The embedding vector.
     */
    public function embed(string $text, ?int $dimensions = null): array
    {
        return $this->embedWithMeta($text, $dimensions)['vector'];
    }

    /**
     * Generate an embedding AND report which provider/model produced it.
     *
     * Vectors are only comparable within the model that produced them —
     * cosine distance between two embedding spaces is noise that looks like
     * a result. Any caller that persists a vector for later comparison must
     * persist this identity alongside it and refuse to compare across it.
     *
     * Resolves the primary from `embedding` and walks `embedding_fallbacks`
     * the same way `using()` walks `fallbacks`, so an embedding provider
     * outage degrades to a different provider rather than to an exception.
     * Laravel AI fires ProviderFailedOver on each switch, so hosts that
     * listen for it are alerted.
     *
     * @return array{vector: array<float>, provider: string, model: string, identity: string}
     */
    public function embedWithMeta(string $text, ?int $dimensions = null): array
    {
        $configKey = $this->configKey();

        $dimensions ??= (int) config("{$configKey}.embedding_dimensions");

        $lastException = null;

        foreach ($this->embeddingProviders() as $candidate) {
            [$provider, $model] = $this->parse($candidate);

            try {
                // Called through Embeddings directly rather than the
                // Stringable macro: the macro returns the raw float array and
                // discards the response meta, which is exactly the
                // provider/model identity this method exists to report.
                $request = Embeddings::for([$text]);

                // Only constrain dimensions when the host actually configured
                // them. Passing 0 would override the provider's own default
                // for consumers that never set `embedding_dimensions`.
                if ($dimensions > 0) {
                    $request->dimensions($dimensions);
                }

                $response = $request->generate(provider: [$provider => $model]);

                $usedProvider = (string) ($response->meta->provider ?? $provider);
                $usedModel = (string) ($response->meta->model ?? $model);

                return [
                    'vector' => $response->first(),
                    'provider' => $usedProvider,
                    'model' => $usedModel,
                    'identity' => $usedProvider.':'.$usedModel,
                ];
            } catch (FailoverableException $e) {
                // The chain is walked here rather than handed to the SDK
                // wholesale because Laravel AI keys its failover list by
                // provider name — two models of the SAME provider collapse
                // into one entry and the primary is silently discarded.
                // Walking it candidate-by-candidate keeps model-level
                // fallbacks (the only kind available when just one embedding
                // provider is enabled on the account) working.
                $lastException = $e;

                // Re-emitted so hosts listening for failover — alerting,
                // dashboards — see embedding switches exactly as they see
                // text ones.
                event(new ProviderFailedOver(
                    Ai::fakeableEmbeddingProvider($provider),
                    $model,
                    $e,
                ));

                continue;
            }
        }

        throw $lastException ?? new \RuntimeException('No embedding providers are configured.');
    }

    /**
     * The embedding chain: primary first, then its fallbacks, in order.
     *
     * Returned as an ordered LIST of "provider:model" strings rather than a
     * provider-keyed map. A map cannot express two models of one provider —
     * the second overwrites the first — and a model-level fallback is the
     * only kind available when a single embedding provider is enabled on the
     * account. Fallbacks are keyed by the FULL "provider:model" of the entry
     * they follow, so each hop is addressed unambiguously.
     *
     * @return list<string>
     */
    public function embeddingProviders(): array
    {
        $configKey = $this->configKey();

        $chain = [];
        $seen = [];
        $current = (string) config("{$configKey}.embedding");

        while ($current !== '' && ! isset($seen[$current])) {
            $chain[] = $current;
            $seen[$current] = true;

            [$provider] = $this->parse($current);

            $next = config("{$configKey}.embedding_fallbacks.{$current}")
                ?? config("{$configKey}.embedding_fallbacks.{$provider}");

            if ($next === null || $next === $current) {
                break;
            }

            $current = (string) $next;
        }

        return $chain;
    }

    /**
     * Get the configured embedding provider and model.
     *
     * @return array{0: string, 1: string} [providerName, modelName]
     */
    public function embeddingConnection(): array
    {
        return $this->parse((string) config("{$this->configKey()}.embedding"));
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
