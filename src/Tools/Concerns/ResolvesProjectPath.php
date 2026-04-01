<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Tools\Concerns;

trait ResolvesProjectPath
{
    protected function resolvePath(string $relativePath): ?string
    {
        $fullPath = realpath($this->projectPath.'/'.ltrim($relativePath, '/'));

        if ($fullPath === false || ! str_starts_with($fullPath, realpath($this->projectPath))) {
            return null;
        }

        return $fullPath;
    }
}
