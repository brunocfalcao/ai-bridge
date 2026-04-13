<?php

declare(strict_types=1);

use BrunoCFalcao\AiBridge\Chat\Exceptions\ChatProviderException;
use BrunoCFalcao\AiBridge\Providers\ClaudeCli\ClaudeCliProvider;
use Illuminate\Support\Facades\Http;

it('streams SSE deltas correctly', function () {
    $sseBody = "data: {\"choices\":[{\"delta\":{\"content\":\"Hello\"}}]}\n\n"
        ."data: {\"choices\":[{\"delta\":{\"content\":\" world\"}}]}\n\n"
        ."data: [DONE]\n\n";

    Http::fake([
        'localhost:3456/v1/chat/completions' => Http::response($sseBody, 200, [
            'Content-Type' => 'text/event-stream',
        ]),
    ]);

    $provider = new ClaudeCliProvider(
        url: 'http://localhost:3456',
        model: 'opus',
        timeout: 10,
    );

    $events = iterator_to_array($provider->stream([
        ['role' => 'user', 'content' => 'Hi'],
    ]));

    $deltas = array_filter($events, fn ($e) => $e['type'] === 'delta');
    $content = implode('', array_column($deltas, 'content'));

    expect($content)->toBe('Hello world');
});

it('injects agent name into system message', function () {
    Http::fake([
        'localhost:3456/v1/chat/completions' => Http::response(
            "data: {\"choices\":[{\"delta\":{\"content\":\"ok\"}}]}\n\ndata: [DONE]\n\n",
            200,
            ['Content-Type' => 'text/event-stream'],
        ),
    ]);

    $provider = new ClaudeCliProvider(
        url: 'http://localhost:3456',
        model: 'opus',
        timeout: 10,
        agentName: 'Codiant',
    );

    iterator_to_array($provider->stream([
        ['role' => 'system', 'content' => 'You are helpful.'],
        ['role' => 'user', 'content' => 'Hi'],
    ]));

    Http::assertSent(function ($request) {
        $body = $request->data();
        $systemMsg = collect($body['messages'])->firstWhere('role', 'system');

        return str_contains($systemMsg['content'], '**Name:** Codiant');
    });
});

it('includes noop tool in payload', function () {
    Http::fake([
        'localhost:3456/v1/chat/completions' => Http::response(
            "data: {\"choices\":[{\"delta\":{\"content\":\"ok\"}}]}\n\ndata: [DONE]\n\n",
            200,
            ['Content-Type' => 'text/event-stream'],
        ),
    ]);

    $provider = new ClaudeCliProvider(
        url: 'http://localhost:3456',
        model: 'opus',
        timeout: 10,
    );

    iterator_to_array($provider->stream([
        ['role' => 'user', 'content' => 'Hi'],
    ]));

    Http::assertSent(function ($request) {
        $body = $request->data();

        return isset($body['tools'][0]['function']['name'])
            && $body['tools'][0]['function']['name'] === 'noop';
    });
});

it('detects API error in response and throws ChatProviderException', function () {
    Http::fake([
        'localhost:3456/v1/chat/completions' => Http::response(json_encode([
            'choices' => [['message' => ['content' => 'API Error: out of extra usage']]],
        ])),
    ]);

    $provider = new ClaudeCliProvider(
        url: 'http://localhost:3456',
        model: 'opus',
        timeout: 10,
    );

    $provider->send([['role' => 'user', 'content' => 'Hi']]);
})->throws(ChatProviderException::class);

it('returns true for healthy when bridge responds ok', function () {
    Http::fake([
        'localhost:3456/health' => Http::response(['status' => 'ok']),
    ]);

    $provider = new ClaudeCliProvider(
        url: 'http://localhost:3456',
        model: 'opus',
        timeout: 10,
    );

    expect($provider->healthy())->toBeTrue();
});

it('returns false for healthy when bridge is down', function () {
    Http::fake([
        'localhost:3456/health' => Http::response('', 500),
    ]);

    $provider = new ClaudeCliProvider(
        url: 'http://localhost:3456',
        model: 'opus',
        timeout: 10,
    );

    expect($provider->healthy())->toBeFalse();
});
