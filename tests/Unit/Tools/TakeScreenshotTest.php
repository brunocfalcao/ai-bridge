<?php

declare(strict_types=1);

use BrunoCFalcao\AiBridge\Browser\BrowserSidecarClient;
use BrunoCFalcao\AiBridge\Tools\TakeScreenshot;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->client = Mockery::mock(BrowserSidecarClient::class);
    $this->tool = new TakeScreenshot($this->client);
});

it('returns JSON with url, mime type, full_page flag, and base64 payload on success', function () {
    $this->client->shouldReceive('screenshot')
        ->once()
        ->with('https://example.com', true, null)
        ->andReturn('iVBORw0KGgoAAAANSUhEUgAA==');

    $result = $this->tool->handle(new Request([
        'url' => 'https://example.com',
        'full_page' => true,
    ]));

    $decoded = json_decode($result, associative: true);

    expect($decoded)->toBe([
        'url' => 'https://example.com',
        'mime_type' => 'image/png',
        'full_page' => true,
        'base64' => 'iVBORw0KGgoAAAANSUhEUgAA==',
    ]);
});

it('passes an explicit session_id through to the sidecar client', function () {
    $this->client->shouldReceive('screenshot')
        ->once()
        ->with('https://example.com', false, 'run-42')
        ->andReturn('ZGF0YQ==');

    $this->tool->handle(new Request([
        'url' => 'https://example.com',
        'session_id' => 'run-42',
    ]));
});

it('passes null session_id to the client when the param is omitted', function () {
    $this->client->shouldReceive('screenshot')
        ->once()
        ->with('https://example.com', false, null)
        ->andReturn('ZGF0YQ==');

    $this->tool->handle(new Request(['url' => 'https://example.com']));
});

it('defaults full_page to false when omitted', function () {
    $this->client->shouldReceive('screenshot')
        ->once()
        ->with('https://example.com', false, null)
        ->andReturn('ZGF0YQ==');

    $result = $this->tool->handle(new Request(['url' => 'https://example.com']));

    expect(json_decode($result, true)['full_page'])->toBeFalse();
});

it('returns an error JSON when url is missing', function () {
    $this->client->shouldNotReceive('screenshot');

    $result = $this->tool->handle(new Request([]));

    expect(json_decode($result, true))->toBe(['error' => 'The "url" parameter is required.']);
});

it('returns an error JSON when the sidecar client throws', function () {
    $this->client->shouldReceive('screenshot')
        ->once()
        ->andThrow(new RuntimeException('Connection refused'));

    $result = $this->tool->handle(new Request(['url' => 'https://example.com']));

    expect(json_decode($result, true))->toBe(['error' => 'Screenshot failed: Connection refused']);
});

it('exposes the url param as required in the schema', function () {
    $factory = Illuminate\JsonSchema\JsonSchema::object($this->tool->schema(...));
    $schema = $factory->toArray();

    expect($schema['required'] ?? [])->toContain('url');
    expect($schema['properties'])->toHaveKeys(['url', 'full_page', 'session_id']);
});
