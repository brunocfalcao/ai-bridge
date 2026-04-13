<?php

declare(strict_types=1);

use BrunoCFalcao\AiBridge\Resolver\AiResolver;

it('resolves a named connection to provider:model', function () {
    $resolver = app(AiResolver::class);

    $chain = $resolver->using('cheap');

    expect($chain)->toHaveKey('gemini')
        ->and($chain['gemini'])->toBe('gemini-2.5-flash');
});

it('falls back to default when connection not found', function () {
    $resolver = app(AiResolver::class);

    $chain = $resolver->using('nonexistent');

    expect($chain)->toHaveKey('claude-cli')
        ->and($chain['claude-cli'])->toBe('opus');
});

it('walks the fallback chain correctly', function () {
    $resolver = app(AiResolver::class);

    $chain = $resolver->using('bridge');

    // claude-cli:opus → fallback openrouter:anthropic/claude-sonnet-4 → fallback gemini:gemini-2.5-flash
    expect($chain)->toHaveCount(3)
        ->and(array_keys($chain))->toBe(['claude-cli', 'openrouter', 'gemini']);
});

it('prevents circular fallback chains', function () {
    config()->set('ai-bridge.resolver.fallbacks', [
        'claude-cli' => 'openrouter:auto',
        'openrouter' => 'claude-cli:opus',
    ]);

    $resolver = app(AiResolver::class);
    $chain = $resolver->using('bridge');

    // Should stop after seeing claude-cli twice
    expect($chain)->toHaveCount(2)
        ->and(array_keys($chain))->toBe(['claude-cli', 'openrouter']);
});

it('parses model with slashes for openrouter', function () {
    $resolver = app(AiResolver::class);

    [$provider, $model] = $resolver->primary('bridge');

    // bridge → claude-cli:opus, but let's test openrouter directly
    config()->set('ai-bridge.resolver.connections.or', 'openrouter:anthropic/claude-sonnet-4');

    [$provider, $model] = $resolver->primary('or');

    expect($provider)->toBe('openrouter')
        ->and($model)->toBe('anthropic/claude-sonnet-4');
});

it('resolves __default__ to the default connection', function () {
    $resolver = app(AiResolver::class);

    $chain = $resolver->using('__default__');

    expect($chain)->toHaveKey('claude-cli')
        ->and($chain['claude-cli'])->toBe('opus');
});
