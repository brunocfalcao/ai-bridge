<?php

declare(strict_types=1);

use BrunoCFalcao\AiBridge\Chat\ChatManager;
use BrunoCFalcao\AiBridge\Chat\Exceptions\ChatProviderException;
use BrunoCFalcao\AiBridge\Contracts\ChatProvider;
use BrunoCFalcao\AiBridge\Providers\ClaudeCli\ClaudeCliProvider;
use BrunoCFalcao\AiBridge\Providers\OpenClaw\OpenClawProvider;
use BrunoCFalcao\AiBridge\Providers\PrismChatProvider;
use BrunoCFalcao\AiBridge\Resolver\AiResolver;

it('resolves claude-cli to ClaudeCliProvider', function () {
    $manager = app(ChatManager::class);

    expect($manager->resolve('claude-cli', 'opus'))->toBeInstanceOf(ClaudeCliProvider::class);
});

it('resolves openclaw to OpenClawProvider', function () {
    $manager = app(ChatManager::class);

    expect($manager->resolve('openclaw', 'codiant'))->toBeInstanceOf(OpenClawProvider::class);
});

it('resolves standard providers to PrismChatProvider', function () {
    $manager = app(ChatManager::class);

    expect($manager->resolve('openrouter', 'anthropic/claude-sonnet-4'))->toBeInstanceOf(PrismChatProvider::class);
    expect($manager->resolve('anthropic', 'claude-opus-4-6'))->toBeInstanceOf(PrismChatProvider::class);
    expect($manager->resolve('gemini', 'gemini-2.5-flash'))->toBeInstanceOf(PrismChatProvider::class);
});

it('falls back to next provider when primary throws ChatProviderException', function () {
    config()->set('ai-bridge.resolver', [
        'connections' => [],
        'fallbacks' => [
            'claude-cli' => 'gemini:gemini-2.5-flash',
        ],
        'default' => 'claude-cli:opus',
    ]);

    $resolver = app(AiResolver::class);

    $failingProvider = Mockery::mock(ChatProvider::class);
    $failingProvider->shouldReceive('send')
        ->andThrow(new ChatProviderException('Primary failed'));

    $successProvider = Mockery::mock(ChatProvider::class);
    $successProvider->shouldReceive('send')
        ->andReturn('Fallback response');

    $manager = Mockery::mock(ChatManager::class, [$resolver])->makePartial();

    $manager->shouldReceive('resolve')
        ->with('claude-cli', 'opus')
        ->andReturn($failingProvider);

    $manager->shouldReceive('resolve')
        ->with('gemini', 'gemini-2.5-flash')
        ->andReturn($successProvider);

    $result = $manager->send([['role' => 'user', 'content' => 'Hi']]);

    expect($result)->toBe('Fallback response');
});

it('throws when all providers in chain fail', function () {
    config()->set('ai-bridge.resolver', [
        'connections' => [],
        'fallbacks' => [
            'claude-cli' => 'gemini:gemini-2.5-flash',
        ],
        'default' => 'claude-cli:opus',
    ]);

    $resolver = app(AiResolver::class);

    $failingProvider1 = Mockery::mock(ChatProvider::class);
    $failingProvider1->shouldReceive('send')
        ->andThrow(new ChatProviderException('Primary failed'));

    $failingProvider2 = Mockery::mock(ChatProvider::class);
    $failingProvider2->shouldReceive('send')
        ->andThrow(new ChatProviderException('Fallback also failed'));

    $manager = Mockery::mock(ChatManager::class, [$resolver])->makePartial();

    $manager->shouldReceive('resolve')
        ->with('claude-cli', 'opus')
        ->andReturn($failingProvider1);

    $manager->shouldReceive('resolve')
        ->with('gemini', 'gemini-2.5-flash')
        ->andReturn($failingProvider2);

    $manager->send([['role' => 'user', 'content' => 'Hi']]);
})->throws(ChatProviderException::class);

it('delegates healthy check to primary provider', function () {
    $manager = app(ChatManager::class);

    expect($manager->healthy())->toBeBool();
});
