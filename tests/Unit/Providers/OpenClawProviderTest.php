<?php

declare(strict_types=1);

use BrunoCFalcao\AiBridge\Chat\Exceptions\ChatProviderException;
use BrunoCFalcao\AiBridge\Providers\OpenClaw\OpenClawProvider;
use Illuminate\Support\Facades\Http;

it('sends Bearer auth header when token provided', function () {
    Http::fake([
        'localhost:18789/v1/chat/completions' => Http::response(json_encode([
            'choices' => [['message' => ['content' => 'Hello!']]],
        ])),
    ]);

    $provider = new OpenClawProvider(
        url: 'http://localhost:18789',
        model: 'codiant',
        timeout: 10,
        token: 'my-secret-token',
    );

    $provider->send([['role' => 'user', 'content' => 'Hi']]);

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization', 'Bearer my-secret-token');
    });
});

it('streams SSE deltas correctly', function () {
    $sseBody = "data: {\"choices\":[{\"delta\":{\"content\":\"One\"}}]}\n\n"
        ."data: {\"choices\":[{\"delta\":{\"content\":\" Two\"}}]}\n\n"
        ."data: [DONE]\n\n";

    Http::fake([
        'localhost:18789/v1/chat/completions' => Http::response($sseBody, 200, [
            'Content-Type' => 'text/event-stream',
        ]),
    ]);

    $provider = new OpenClawProvider(
        url: 'http://localhost:18789',
        model: 'codiant',
        timeout: 10,
    );

    $events = iterator_to_array($provider->stream([
        ['role' => 'user', 'content' => 'Hi'],
    ]));

    $deltas = array_filter($events, fn ($e) => $e['type'] === 'delta');
    $content = implode('', array_column($deltas, 'content'));

    expect($content)->toBe('One Two');
});

it('detects API error in send response', function () {
    Http::fake([
        'localhost:18789/v1/chat/completions' => Http::response(json_encode([
            'choices' => [['message' => ['content' => 'API Error: rate limited']]],
        ])),
    ]);

    $provider = new OpenClawProvider(
        url: 'http://localhost:18789',
        model: 'codiant',
        timeout: 10,
    );

    $provider->send([['role' => 'user', 'content' => 'Hi']]);
})->throws(ChatProviderException::class);

it('reports healthy when /v1/models returns 200', function () {
    Http::fake([
        'localhost:18789/v1/models' => Http::response(['data' => []]),
    ]);

    $provider = new OpenClawProvider(
        url: 'http://localhost:18789',
        model: 'codiant',
        timeout: 10,
    );

    expect($provider->healthy())->toBeTrue();
});

it('reports unhealthy when /v1/models fails', function () {
    Http::fake([
        'localhost:18789/v1/models' => Http::response('', 500),
    ]);

    $provider = new OpenClawProvider(
        url: 'http://localhost:18789',
        model: 'codiant',
        timeout: 10,
    );

    expect($provider->healthy())->toBeFalse();
});
