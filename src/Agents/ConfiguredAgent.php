<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Agents;

use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Contracts\HasProviderOptions;

/**
 * The `agent()` helper's anonymous agent, plus the provider options a named
 * connection is configured with.
 */
class ConfiguredAgent extends AnonymousAgent implements HasProviderOptions
{
    use ResolvesConnectionOptions;
}
