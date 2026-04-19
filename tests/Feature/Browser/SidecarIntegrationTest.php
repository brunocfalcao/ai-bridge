<?php

declare(strict_types=1);

use BrunoCFalcao\AiBridge\Browser\BrowserSidecarClient;
use BrunoCFalcao\AiBridge\Browser\Mcp\BrowserServer;
use BrunoCFalcao\AiBridge\Browser\Mcp\Tools\CaptureScreenshotTool;
use BrunoCFalcao\AiBridge\Tools\TakeScreenshot;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $url = 'http://127.0.0.1:3100';

    $this->targetUrl = 'https://example.com';
    $this->sessionId = 'ai-bridge-integration';

    $probe = Mockery::mock();
    $client = new BrowserSidecarClient(
        baseUrl: $url,
        defaultSessionId: $this->sessionId,
        timeout: 15,
    );

    if (! $client->isAvailable($this->sessionId)) {
        $this->markTestSkipped("Friday browser sidecar not reachable at {$url}.");
    }

    $this->client = $client;
});

it('actually captures a PNG screenshot from the live sidecar', function () {
    $base64 = $this->client->screenshot($this->targetUrl);

    expect($base64)->toBeString()->not->toBeEmpty();

    $binary = base64_decode($base64, strict: true);

    expect($binary)->not->toBeFalse('Sidecar payload was not valid strict base64.');
    expect(substr($binary, 0, 8))->toBe("\x89PNG\r\n\x1a\n", 'Payload is not a PNG (magic bytes mismatch).');
    expect(strlen($binary))->toBeGreaterThan(1000);
});

it('resolves BrowserSidecarClient from the container with config defaults', function () {
    config()->set('ai-bridge.browser', [
        'sidecar_url' => 'http://127.0.0.1:3100',
        'default_session_id' => $this->sessionId,
        'timeout' => 15,
        'mcp_path' => '/mcp/browser',
    ]);

    $this->app->forgetInstance(BrowserSidecarClient::class);

    $resolved = app(BrowserSidecarClient::class);

    expect($resolved)->toBeInstanceOf(BrowserSidecarClient::class);

    $base64 = $resolved->screenshot($this->targetUrl);

    expect(base64_decode($base64, strict: true))->not->toBeFalse();
});

it('runs the TakeScreenshot Laravel AI tool end-to-end', function () {
    $tool = new TakeScreenshot($this->client);

    $raw = $tool->handle(new Request([
        'url' => $this->targetUrl,
        'full_page' => false,
        'session_id' => $this->sessionId,
    ]));

    $payload = json_decode($raw, associative: true);

    expect($payload)->not->toHaveKey('error');
    expect($payload['url'])->toBe($this->targetUrl);
    expect($payload['mime_type'])->toBe('image/png');
    expect($payload['full_page'])->toBeFalse();

    $binary = base64_decode($payload['base64'], strict: true);

    expect(substr($binary, 0, 8))->toBe("\x89PNG\r\n\x1a\n");
});

it('runs the capture-screenshot MCP tool end-to-end against the live sidecar', function () {
    $tool = new CaptureScreenshotTool;

    $response = $tool->handle(new Laravel\Mcp\Request([
        'url' => $this->targetUrl,
        'full_page' => false,
        'session_id' => $this->sessionId,
    ]), $this->client);

    expect($response->isError())->toBeFalse();

    $content = $response->content()->toArray();

    expect($content['type'])->toBe('image');
    expect($content['mimeType'])->toBe('image/png');
    expect(substr(base64_decode($content['data'], strict: true), 0, 8))->toBe("\x89PNG\r\n\x1a\n");
});

it('registers the /mcp/browser route via Laravel\'s MCP facade', function () {
    $registered = collect(app('router')->getRoutes())->pluck('uri');

    expect($registered->contains(fn ($uri) => str_contains((string) $uri, 'mcp/browser')))
        ->toBeTrue('Expected /mcp/browser route to be registered by AiBridgeServiceProvider.');
});

it('exposes CaptureScreenshotTool in the BrowserServer tools list', function () {
    $reflection = new ReflectionClass(BrowserServer::class);
    $property = $reflection->getProperty('tools');
    $property->setAccessible(true);

    $tools = $property->getDefaultValue();

    expect($tools)->toContain(CaptureScreenshotTool::class);
});
