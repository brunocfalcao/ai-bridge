<?php

declare(strict_types=1);

use BrunoCFalcao\AiBridge\Resolver\AiResolver;
use Laravel\Ai\Embeddings;

it('keeps exact embedding identities through fallback hops and cycles', function (
    string $primary,
    array $fallbacks,
    array $expected,
): void {
    config()->set('ai-bridge.resolver.embedding', $primary);
    config()->set('ai-bridge.resolver.embedding_fallbacks', $fallbacks);

    expect(app(AiResolver::class)->embeddingProviders())->toBe($expected);
})->with([
    'dotted identity' => [
        'voyageai:voyage-3.5',
        ['voyageai:voyage-3.5' => 'gemini:embedding'],
        ['voyageai:voyage-3.5', 'gemini:embedding'],
    ],
    'undotted identity control' => [
        'voyageai:voyage-4',
        ['voyageai:voyage-4' => 'gemini:embedding'],
        ['voyageai:voyage-4', 'gemini:embedding'],
    ],
    'provider fallback' => [
        'voyageai:voyage-3.5',
        ['voyageai' => 'gemini:embedding'],
        ['voyageai:voyage-3.5', 'gemini:embedding'],
    ],
    'exact identity precedes provider' => [
        'voyageai:voyage-3.5',
        [
            'voyageai:voyage-3.5' => 'gemini:embedding',
            'voyageai' => 'openai:embedding',
        ],
        ['voyageai:voyage-3.5', 'gemini:embedding'],
    ],
    'multi-hop cycle' => [
        'voyageai:voyage-3.5',
        [
            'voyageai:voyage-3.5' => 'gemini:embedding',
            'gemini:embedding' => 'openai:embedding',
            'openai:embedding' => 'voyageai:voyage-3.5',
        ],
        ['voyageai:voyage-3.5', 'gemini:embedding', 'openai:embedding'],
    ],
]);

describe('embedding request size', function (): void {
    it('reads the request size for a model id that contains a dot', function (): void {
        config()->set('ai-bridge.resolver.embedding', 'voyageai:voyage-3.5');
        config()->set('ai-bridge.resolver.embedding_dimensions', 6);
        config()->set('ai-bridge.resolver.embedding_request_dimensions', ['voyageai:voyage-3.5' => 4]);

        Embeddings::fake([[[0.1, 0.2, 0.3, 0.4]]]);

        $result = app(AiResolver::class)->embedWithMeta('hello');

        Embeddings::assertGenerated(fn ($prompt) => $prompt->dimensions === 4 && $prompt->model === 'voyage-3.5');

        expect($result['vector'])->toBe([0.1, 0.2, 0.3, 0.4, 0.0, 0.0])
            ->and($result['identity'])->toBe('voyageai:voyage-3.5');
    });

    it('keeps a dotted id and an undotted id separate in the same map', function (): void {
        config()->set('ai-bridge.resolver.embedding', 'voyageai:voyage-4');
        config()->set('ai-bridge.resolver.embedding_dimensions', 6);
        config()->set('ai-bridge.resolver.embedding_request_dimensions', [
            'voyageai:voyage-3.5' => 3,
            'voyageai:voyage-4' => 4,
        ]);

        Embeddings::fake([[[0.1, 0.2, 0.3, 0.4]]]);

        app(AiResolver::class)->embedWithMeta('hello');

        Embeddings::assertGenerated(fn ($prompt) => $prompt->dimensions === 4);
    });

    it('asks for the stored size when the model has no request size configured', function (): void {
        config()->set('ai-bridge.resolver.embedding', 'voyageai:voyage-3.5');
        config()->set('ai-bridge.resolver.embedding_dimensions', 6);
        config()->set('ai-bridge.resolver.embedding_request_dimensions', ['voyageai:voyage-4' => 4]);

        Embeddings::fake([[[0.1, 0.2, 0.3, 0.4, 0.5, 0.6]]]);

        $result = app(AiResolver::class)->embedWithMeta('hello');

        Embeddings::assertGenerated(fn ($prompt) => $prompt->dimensions === 6);

        expect($result['vector'])->toBe([0.1, 0.2, 0.3, 0.4, 0.5, 0.6]);
    });

    it('asks for the stored size when no request size map exists at all', function (): void {
        config()->set('ai-bridge.resolver.embedding', 'voyageai:voyage-3.5');
        config()->set('ai-bridge.resolver.embedding_dimensions', 6);
        config()->set('ai-bridge.resolver.embedding_request_dimensions', null);

        Embeddings::fake([[[0.1, 0.2, 0.3, 0.4, 0.5, 0.6]]]);

        app(AiResolver::class)->embedWithMeta('hello');

        Embeddings::assertGenerated(fn ($prompt) => $prompt->dimensions === 6);
    });
});
