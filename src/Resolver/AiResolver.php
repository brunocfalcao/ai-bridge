<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Resolver;

use BrunoCFalcao\AiBridge\Agents\ConfiguredAgent;
use BrunoCFalcao\AiBridge\Agents\ConfiguredStructuredAgent;
use Closure;
use Generator;
use InvalidArgumentException;
use Laravel\Ai\Ai;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Events\ProviderFailedOver;
use Laravel\Ai\Exceptions\FailoverableException;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\StructuredAnonymousAgent;

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

        // A connection can name its own fallback chain, which then replaces
        // the provider-level `fallbacks` for that connection only. Two
        // connections on the same provider can so fail over to different
        // places.
        $own = config("{$configKey}.connection_fallbacks.{$name}");

        if ($own !== null) {
            return $this->chainOf([(string) $primary, ...array_map('strval', (array) $own)]);
        }

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
     * An ad-hoc agent for a named connection, carrying the provider options
     * (reasoning effort, thinking level, …) configured for that connection.
     * Prompt it with `->prompt($text, provider: $resolver->using($connection))`.
     */
    public function agent(
        string|\BackedEnum $connection,
        string $instructions = '',
        iterable $messages = [],
        iterable $tools = [],
        ?Closure $schema = null,
    ): AnonymousAgent {
        // Laravel AI fakes agents by exact class. Code under test that fakes
        // the plain `agent()` classes keeps working: when only those are
        // faked, hand back the plain class (options do not matter to a fake).
        $plain = $schema ? StructuredAnonymousAgent::class : AnonymousAgent::class;
        $configured = $schema ? ConfiguredStructuredAgent::class : ConfiguredAgent::class;

        if (Ai::hasFakeGatewayFor($plain) && ! Ai::hasFakeGatewayFor($configured)) {
            return $schema
                ? new StructuredAnonymousAgent($instructions, $messages, $tools, $schema)
                : new AnonymousAgent($instructions, $messages, $tools);
        }

        $agent = $schema
            ? new ConfiguredStructuredAgent($instructions, $messages, $tools, $schema)
            : new ConfiguredAgent($instructions, $messages, $tools);

        return $agent->withConnectionOptions($this->connectionOptions($connection));
    }

    /**
     * Stream a chat exchange through a named connection.
     *
     * Messages arrive in the OpenAI shape every caller already builds
     * (`[['role' => 'system'|'user'|'assistant', 'content' => '…'], …]`). The
     * trailing user turn is the prompt; a leading system turn becomes the
     * agent's instructions and everything between is carried as history, so
     * the whole exchange reaches the provider in one agent call with the
     * connection's fallback chain and reasoning effort applied.
     *
     * Yields `['type' => 'delta', 'content' => '…']` per text chunk and one
     * closing `['type' => 'done', 'content' => null]`.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return Generator<int, array{type: string, content: ?string}>
     */
    public function stream(array $messages, string|\BackedEnum $connection): Generator
    {
        [$instructions, $history, $prompt] = $this->splitMessages($messages);

        $response = $this->agent($connection, instructions: $instructions, messages: $history)
            ->stream($prompt, provider: $this->using($connection));

        foreach ($response as $event) {
            if ($event instanceof TextDelta) {
                yield ['type' => 'delta', 'content' => $event->delta];
            }
        }

        yield ['type' => 'done', 'content' => null];
    }

    /**
     * Split an OpenAI-shaped message list into the three parts an agent takes.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{0: string, 1: list<UserMessage|AssistantMessage>, 2: string}
     */
    private function splitMessages(array $messages): array
    {
        $instructions = '';
        $history = [];
        $prompt = '';

        foreach ($messages as $message) {
            $content = (string) ($message['content'] ?? '');

            match ($message['role'] ?? '') {
                'system' => $instructions = trim($instructions."\n\n".$content),
                'assistant' => $history[] = new AssistantMessage($content),
                default => $history[] = new UserMessage($content),
            };
        }

        // The last user turn is the prompt, not history: an agent prompted with
        // an empty string would ask the provider to answer nothing.
        for ($i = count($history) - 1; $i >= 0; $i--) {
            if ($history[$i] instanceof UserMessage) {
                $prompt = $history[$i]->content;
                array_splice($history, $i, 1);

                break;
            }
        }

        return [$instructions, array_values($history), $prompt];
    }

    /**
     * The reasoning effort configured for one provider of a connection
     * (`efforts.{connection}.{provider}`: low | medium | high), or null for
     * the model's own default.
     */
    public function effort(string|\BackedEnum $connection, string $provider): ?string
    {
        $name = $connection instanceof \BackedEnum ? $connection->value : $connection;
        $effort = config("{$this->configKey()}.efforts.{$name}.{$provider}");

        return is_string($effort) && $effort !== '' ? $effort : null;
    }

    /**
     * Laravel AI provider options for every provider of a connection: the
     * configured effort in each provider's own request shape, merged with
     * any raw `options.{connection}.{provider}` override.
     *
     * @return array<string, array<string, mixed>>
     */
    public function connectionOptions(string|\BackedEnum $connection): array
    {
        $name = $connection instanceof \BackedEnum ? $connection->value : $connection;
        $configKey = $this->configKey();
        $options = [];

        foreach ((array) config("{$configKey}.efforts.{$name}", []) as $provider => $effort) {
            $mapped = is_string($effort) && $effort !== '' ? $this->effortOptions((string) $provider, $effort) : [];

            if ($mapped !== []) {
                $options[$provider] = $mapped;
            }
        }

        foreach ((array) config("{$configKey}.options.{$name}", []) as $provider => $raw) {
            $options[$provider] = array_replace_recursive($options[$provider] ?? [], (array) $raw);
        }

        return $options;
    }

    /**
     * Provider options for one provider of a connection.
     *
     * @return array<string, mixed>
     */
    public function providerOptions(string|\BackedEnum $connection, string $provider): array
    {
        return (array) ($this->connectionOptions($connection)[$provider] ?? []);
    }

    /**
     * A reasoning effort in the request shape each provider's API expects
     * through Laravel AI: OpenAI and OpenRouter `reasoning.effort` (Responses
     * API), Gemini `generation_config.thinking_level` (Interactions API),
     * Anthropic `output_config.effort`. Providers without an effort setting
     * get nothing.
     *
     * @return array<string, mixed>
     */
    public function effortOptions(string $provider, string $effort): array
    {
        return match ($provider) {
            'openai', 'openrouter' => ['reasoning' => ['effort' => $effort]],
            'gemini' => ['thinking_level' => $effort],
            'anthropic' => ['output_config' => ['effort' => $effort]],
            default => [],
        };
    }

    /**
     * @param  list<string>  $entries  "provider:model" in order
     * @return array<string, string>
     */
    private function chainOf(array $entries): array
    {
        $providers = [];

        foreach ($entries as $entry) {
            if ($entry === '') {
                continue;
            }

            [$provider, $model] = $this->parse($entry);

            if (isset($providers[$provider])) {
                if ($providers[$provider] !== $model) {
                    throw new InvalidArgumentException("Text fallback uses the same provider [{$provider}] with a different model; Laravel AI cannot represent that chain.");
                }

                continue;
            }

            $providers[$provider] = $model;
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

                // A model that cannot produce the stored size is asked for its own
                // size (`embedding_request_dimensions`, read as a map: model ids may
                // hold dots) and the vector is zero-padded below; padding with zeros
                // leaves cosine distances between that model's vectors unchanged.
                $requested = (int) (config("{$configKey}.embedding_request_dimensions")[$candidate] ?? $dimensions);

                // Only constrain dimensions when the host actually configured
                // them. Passing 0 would override the provider's own default
                // for consumers that never set `embedding_dimensions`.
                if ($requested > 0) {
                    $request->dimensions($requested);
                }

                $response = $request->generate(provider: [$provider => $model]);

                $usedProvider = (string) ($response->meta->provider ?? $provider);
                $usedModel = (string) ($response->meta->model ?? $model);

                return [
                    'vector' => $this->padVector($response->first(), $dimensions),
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
     * @param  array<float>  $vector
     * @return array<float>
     */
    private function padVector(array $vector, int $dimensions): array
    {
        $missing = $dimensions - count($vector);

        return $missing > 0 ? [...$vector, ...array_fill(0, $missing, 0.0)] : $vector;
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
