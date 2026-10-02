<?php

declare(strict_types=1);

use BrunoCFalcao\AiBridge\Resolver\AiResolver;
use Laravel\Ai\Embeddings;

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
