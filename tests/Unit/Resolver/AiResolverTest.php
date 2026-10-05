<?php

declare(strict_types=1);

use BrunoCFalcao\AiBridge\Resolver\AiResolver;

it('resolves a named connection to provider:model', function () {
    $resolver = app(AiResolver::class);

    $chain = $resolver->using('cheap');

    expect($chain)->toHaveKey('gemini')
        ->and($chain['gemini'])->toBe('gemini-3-flash-preview');
});

it('falls back to default when connection not found', function () {
    $resolver = app(AiResolver::class);

    $chain = $resolver->using('nonexistent');

    expect($chain)->toHaveKey('anthropic')
        ->and($chain['anthropic'])->toBe('claude-opus-4-6');
});

it('walks the fallback chain correctly', function () {
    $resolver = app(AiResolver::class);

    $chain = $resolver->using('bridge');

    // anthropic:claude-opus-4-6 → fallback openrouter:anthropic/claude-sonnet-4 → fallback gemini:gemini-3-flash-preview
    expect($chain)->toHaveCount(3)
        ->and(array_keys($chain))->toBe(['anthropic', 'openrouter', 'gemini']);
});

it('prevents circular fallback chains', function () {
    config()->set('ai-bridge.resolver.fallbacks', [
        'anthropic' => 'openrouter:auto',
        'openrouter' => 'anthropic:claude-opus-4-6',
    ]);

    $resolver = app(AiResolver::class);
    $chain = $resolver->using('bridge');

    // Should stop after seeing anthropic twice
    expect($chain)->toHaveCount(2)
        ->and(array_keys($chain))->toBe(['anthropic', 'openrouter']);
});

it('rejects a different fallback model on the same provider instead of replacing the primary', function () {
    config()->set('ai-bridge.resolver.fallbacks.gemini', 'gemini:gemini-3.1-pro-preview');

    expect(fn () => app(AiResolver::class)->using('cheap'))
        ->toThrow(InvalidArgumentException::class, 'same provider');
});

it('parses model with slashes for openrouter', function () {
    $resolver = app(AiResolver::class);

    [$provider, $model] = $resolver->primary('bridge');

    // bridge → anthropic:claude-opus-4-6, but let's test openrouter directly
    config()->set('ai-bridge.resolver.connections.or', 'openrouter:anthropic/claude-sonnet-4');

    [$provider, $model] = $resolver->primary('or');

    expect($provider)->toBe('openrouter')
        ->and($model)->toBe('anthropic/claude-sonnet-4');
});

it('resolves __default__ to the default connection', function () {
    $resolver = app(AiResolver::class);

    $chain = $resolver->using('__default__');

    expect($chain)->toHaveKey('anthropic')
        ->and($chain['anthropic'])->toBe('claude-opus-4-6');
});
