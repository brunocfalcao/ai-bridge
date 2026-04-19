<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Browser\Mcp;

use BrunoCFalcao\AiBridge\Browser\Mcp\Tools\CaptureScreenshotTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Browser Server')]
#[Version('1.0.0')]
#[Instructions('A browser automation MCP server. Use capture-screenshot to take a visual PNG screenshot of any URL via a pooled Playwright sidecar (no chrome process leaks).')]
class BrowserServer extends Server
{
    /** @var array<int, class-string> */
    protected array $tools = [
        CaptureScreenshotTool::class,
    ];
}
