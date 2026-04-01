<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Knowledge;

use RuntimeException;

class SystemContext
{
    protected ?string $slug = null;

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function getSlug(): string
    {
        if (! $this->slug) {
            throw new RuntimeException('System context slug not set.');
        }

        return $this->slug;
    }

    public function getConnection(): string
    {
        return config("ai-bridge.mcp_systems.{$this->getSlug()}.connection");
    }

    public function getName(): string
    {
        return config("ai-bridge.mcp_systems.{$this->getSlug()}.name", $this->getSlug());
    }

    public function isSet(): bool
    {
        return $this->slug !== null;
    }
}
