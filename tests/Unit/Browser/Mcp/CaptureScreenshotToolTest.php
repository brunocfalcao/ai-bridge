<?php

declare(strict_types=1);

use BrunoCFalcao\AiBridge\Browser\BrowserSidecarClient;
use BrunoCFalcao\AiBridge\Browser\Mcp\Tools\CaptureScreenshotTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Content\Image;
use Laravel\Mcp\Server\Content\Text;

beforeEach(function () {
    $this->client = Mockery::mock(BrowserSidecarClient::class);
    $this->tool = new CaptureScreenshotTool;
});

it('returns an MCP image response with the sidecar base64 payload on success', function () {
    $binary = random_bytes(32);
    $base64 = base64_encode($binary);

    $this->client->shouldReceive('screenshot')
        ->once()
        ->with('https://example.com', true, 'sess-1')
        ->andReturn($base64);

    $response = $this->tool->handle(new Request([
        'url' => 'https://example.com',
        'full_page' => true,
        'session_id' => 'sess-1',
    ]), $this->client);

    expect($response->isError())->toBeFalse();

    $content = $response->content();

    expect($content)->toBeInstanceOf(Image::class);
    expect($content->toArray())->toMatchArray([
        'type' => 'image',
        'data' => $base64,
        'mimeType' => 'image/png',
    ]);
});

it('returns an error response when the sidecar payload is not valid base64', function () {
    $this->client->shouldReceive('screenshot')
        ->once()
        ->andReturn('not valid base64 !!!');

    $response = $this->tool->handle(new Request(['url' => 'https://example.com']), $this->client);

    expect($response->isError())->toBeTrue();
    expect($response->content()->toArray()['text'])->toBe('Sidecar returned a payload that is not valid base64.');
});

it('passes null session_id to the client when param is omitted', function () {
    $this->client->shouldReceive('screenshot')
        ->once()
        ->with('https://example.com', false, null)
        ->andReturn('ZGF0YQ==');

    $this->tool->handle(new Request(['url' => 'https://example.com']), $this->client);
});

it('defaults full_page to false when omitted', function () {
    $this->client->shouldReceive('screenshot')
        ->once()
        ->with('https://example.com', false, null)
        ->andReturn('ZGF0YQ==');

    $this->tool->handle(new Request(['url' => 'https://example.com']), $this->client);
});

it('returns an error response when url is missing', function () {
    $this->client->shouldNotReceive('screenshot');

    $response = $this->tool->handle(new Request([]), $this->client);

    expect($response->isError())->toBeTrue();
    expect($response->content())->toBeInstanceOf(Text::class);
    expect($response->content()->toArray())->toMatchArray([
        'type' => 'text',
        'text' => 'The "url" parameter is required.',
    ]);
});

it('returns an error response when the sidecar client throws', function () {
    $this->client->shouldReceive('screenshot')
        ->once()
        ->andThrow(new RuntimeException('Connection refused'));

    $response = $this->tool->handle(new Request(['url' => 'https://example.com']), $this->client);

    expect($response->isError())->toBeTrue();
    expect($response->content()->toArray()['text'])->toBe('Screenshot failed: Connection refused');
});

it('marks the tool as read-only via the IsReadOnly annotation', function () {
    $reflection = new ReflectionClass(CaptureScreenshotTool::class);
    $attributes = $reflection->getAttributes(Laravel\Mcp\Server\Tools\Annotations\IsReadOnly::class);

    expect($attributes)->not->toBeEmpty();
});

it('declares url as a required schema property', function () {
    $factory = Illuminate\JsonSchema\JsonSchema::object($this->tool->schema(...));
    $schema = $factory->toArray();

    expect($schema['required'] ?? [])->toContain('url');
    expect($schema['properties'])->toHaveKeys(['url', 'full_page', 'session_id']);
});
