<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Browser\Mcp\Tools;

use BrunoCFalcao\AiBridge\Browser\BrowserSidecarClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

#[Name('capture-screenshot')]
#[IsReadOnly]
class CaptureScreenshotTool extends Tool
{
    public function description(): string
    {
        return 'Capture a visual PNG screenshot of a web page. Routes through a pooled Playwright sidecar so no browser processes leak between calls. Returns the screenshot as an inline image for vision-capable models.';
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'url' => $schema
                ->string()
                ->description('The URL of the page to screenshot.')
                ->required(),
            'full_page' => $schema
                ->boolean()
                ->description('Capture the full scrollable page. Default: false (viewport only).'),
            'session_id' => $schema
                ->string()
                ->description('Optional browser session ID for isolation. Defaults to the configured session.'),
        ];
    }

    public function handle(Request $request, BrowserSidecarClient $client): Response
    {
        $url = (string) $request->get('url', '');

        if ($url === '') {
            return Response::error('The "url" parameter is required.');
        }

        $fullPage = (bool) $request->get('full_page', false);
        $sessionId = $request->get('session_id') ?: null;

        try {
            $base64 = $client->screenshot($url, $fullPage, $sessionId);
        } catch (Throwable $e) {
            return Response::error("Screenshot failed: {$e->getMessage()}");
        }

        // Response::image re-encodes to base64 internally, so decode the sidecar payload here to avoid double-encoding.
        $binary = base64_decode($base64, strict: true);

        if ($binary === false) {
            return Response::error('Sidecar returned a payload that is not valid base64.');
        }

        return Response::image($binary, 'image/png');
    }
}
