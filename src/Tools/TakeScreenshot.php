<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Tools;

use BrunoCFalcao\AiBridge\Browser\BrowserSidecarClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Throwable;

class TakeScreenshot implements Tool
{
    public function __construct(
        protected BrowserSidecarClient $client,
    ) {}

    public function description(): string
    {
        return 'Capture a visual screenshot of a web page as a base64-encoded PNG. Use for visual inspection, layout verification, or when HTML content alone is insufficient.';
    }

    public function handle(Request $request): string
    {
        $url = (string) $request->string('url');

        if ($url === '') {
            return json_encode(['error' => 'The "url" parameter is required.']);
        }

        $fullPage = $request->boolean('full_page', false);
        $sessionId = (string) $request->string('session_id', '') ?: null;

        try {
            $base64 = $this->client->screenshot($url, $fullPage, $sessionId);
        } catch (Throwable $e) {
            return json_encode(['error' => "Screenshot failed: {$e->getMessage()}"]);
        }

        return json_encode([
            'url' => $url,
            'mime_type' => 'image/png',
            'full_page' => $fullPage,
            'base64' => $base64,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'url' => $schema
                ->string()
                ->description('The URL of the page to screenshot.')
                ->required(),
            'full_page' => $schema
                ->boolean()
                ->description('Capture the full scrollable page (true) or only the viewport (false). Default: false.'),
            'session_id' => $schema
                ->string()
                ->description('Optional browser session ID for session isolation. Defaults to the configured session.'),
        ];
    }
}
