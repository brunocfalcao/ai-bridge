<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Agents;

use Laravel\Ai\Enums\Lab;

/**
 * Per-provider request options (reasoning effort, thinking level, …) for an
 * agent built by AiResolver::agent(). Laravel AI asks the agent once per
 * provider it tries, so the primary and every fallback each get their own
 * options from the same map.
 */
trait ResolvesConnectionOptions
{
    /**
     * @var array<string, array<string, mixed>>
     */
    public array $connectionOptions = [];

    /**
     * @param  array<string, array<string, mixed>>  $options  provider => options
     */
    public function withConnectionOptions(array $options): static
    {
        $this->connectionOptions = $options;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        $name = $provider instanceof Lab ? $provider->value : $provider;

        return $this->connectionOptions[$name] ?? [];
    }
}
