<?php

declare(strict_types=1);

use BrunoCFalcao\AiBridge\Browser\BrowserSidecarClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->client = new BrowserSidecarClient(
        baseUrl: 'http://127.0.0.1:3100',
        defaultSessionId: 'test-session',
        timeout: 5,
    );
});

it('navigates then screenshots and returns the base64 payload', function () {
    Http::fake([
        'http://127.0.0.1:3100/navigate' => Http::response(['url' => 'https://example.com', 'title' => 'Example'], 200),
        'http://127.0.0.1:3100/screenshot' => Http::response(['base64' => 'iVBORw0KGgoAAAANSUhEUgAA=='], 200),
    ]);

    $payload = $this->client->screenshot('https://example.com', fullPage: true, sessionId: 'custom-session');

    expect($payload)->toBe('iVBORw0KGgoAAAANSUhEUgAA==');

    Http::assertSent(function ($request) {
        return $request->url() === 'http://127.0.0.1:3100/navigate'
            && $request->header('X-Session-ID')[0] === 'custom-session'
            && $request['url'] === 'https://example.com';
    });

    Http::assertSent(function ($request) {
        return $request->url() === 'http://127.0.0.1:3100/screenshot'
            && $request->header('X-Session-ID')[0] === 'custom-session'
            && $request['fullPage'] === true;
    });
});

it('uses the configured default session id when none provided', function () {
    Http::fake([
        '*' => Http::sequence()
            ->push(['url' => 'https://a.test'], 200)
            ->push(['base64' => 'ZGF0YQ=='], 200),
    ]);

    $this->client->screenshot('https://a.test');

    Http::assertSent(fn ($req) => $req->header('X-Session-ID')[0] === 'test-session');
});

it('defaults full_page to false when omitted', function () {
    Http::fake([
        'http://127.0.0.1:3100/navigate' => Http::response(['ok' => true], 200),
        'http://127.0.0.1:3100/screenshot' => Http::response(['base64' => 'eA=='], 200),
    ]);

    $this->client->screenshot('https://example.com');

    Http::assertSent(fn ($req) => $req->url() === 'http://127.0.0.1:3100/screenshot' && $req['fullPage'] === false);
});

it('throws when navigate returns a non-2xx status', function () {
    Http::fake([
        'http://127.0.0.1:3100/navigate' => Http::response(['error' => 'bad url'], 500),
    ]);

    $this->client->screenshot('https://broken.test');
})->throws(RuntimeException::class, 'Sidecar navigate failed (500)');

it('throws when screenshot returns a non-2xx status', function () {
    Http::fake([
        'http://127.0.0.1:3100/navigate' => Http::response(['ok' => true], 200),
        'http://127.0.0.1:3100/screenshot' => Http::response(['error' => 'render crash'], 502),
    ]);

    $this->client->screenshot('https://example.com');
})->throws(RuntimeException::class, 'Sidecar screenshot failed (502)');

it('throws when sidecar returns a 200 with an empty base64 field', function () {
    Http::fake([
        'http://127.0.0.1:3100/navigate' => Http::response(['ok' => true], 200),
        'http://127.0.0.1:3100/screenshot' => Http::response(['base64' => ''], 200),
    ]);

    $this->client->screenshot('https://example.com');
})->throws(RuntimeException::class, 'empty screenshot payload');

it('reports the sidecar as available when /status responds 200', function () {
    Http::fake([
        'http://127.0.0.1:3100/status' => Http::response(['status' => 'connected'], 200),
    ]);

    expect($this->client->isAvailable())->toBeTrue();
});

it('reports the sidecar as unavailable when /status errors', function () {
    Http::fake([
        'http://127.0.0.1:3100/status' => Http::response('', 503),
    ]);

    expect($this->client->isAvailable())->toBeFalse();
});
