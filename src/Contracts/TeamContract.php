<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Contracts;

/**
 * Contract for the host application's Team model.
 *
 * Implement this interface on your Team model to enable
 * per-team API configuration in AiApiConfig.
 */
interface TeamContract
{
    public function getKey(): mixed;
}
