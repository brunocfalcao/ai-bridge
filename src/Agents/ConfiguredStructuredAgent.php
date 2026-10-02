<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Agents;

use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\StructuredAnonymousAgent;

/**
 * The `agent(schema: …)` helper's structured agent, plus the provider
 * options a named connection is configured with.
 */
class ConfiguredStructuredAgent extends StructuredAnonymousAgent implements HasProviderOptions
{
    use ResolvesConnectionOptions;
}
