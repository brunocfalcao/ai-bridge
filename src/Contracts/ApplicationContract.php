<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Contracts;

/**
 * Contract for the host application's Application model.
 *
 * Implement this interface on your Application model to enable
 * MCP knowledge server registration and API key authentication.
 */
interface ApplicationContract
{
    public function getKey(): mixed;

    public function getAttribute(string $key): mixed;
}
