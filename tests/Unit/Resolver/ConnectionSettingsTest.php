<?php

declare(strict_types=1);

use BrunoCFalcao\AiBridge\Agents\ConfiguredAgent;
use BrunoCFalcao\AiBridge\Agents\ConfiguredStructuredAgent;
use BrunoCFalcao\AiBridge\Resolver\AiResolver;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;

describe('per-connection fallbacks', function (): void {
    it('uses the connection chain instead of the provider-level fallback', function (): void {
        config()->set('ai-bridge.resolver.connections.survey', 'openai:gpt-4.1-mini');
        config()->set('ai-bridge.resolver.connections.analyst', 'openai:gpt-4.1');
        config()->set('ai-bridge.resolver.fallbacks.openai', 'gemini:gemini-3-flash-preview');
        config()->set('ai-bridge.resolver.connection_fallbacks.analyst', ['anthropic:claude-sonnet-5-5']);

        $resolver = app(AiResolver::class);

        expect($resolver->using('analyst'))->toBe(['openai' => 'gpt-4.1', 'anthropic' => 'claude-sonnet-5-5'])
            ->and($resolver->using('survey'))->toBe(['openai' => 'gpt-4.1-mini', 'gemini' => 'gemini-3-flash-preview']);
    });

    it('treats an empty connection chain as no fallback at all', function (): void {
        config()->set('ai-bridge.resolver.connections.survey', 'openai:gpt-4.1-mini');
        config()->set('ai-bridge.resolver.fallbacks.openai', 'gemini:gemini-3-flash-preview');
        config()->set('ai-bridge.resolver.connection_fallbacks.survey', []);

        expect(app(AiResolver::class)->using('survey'))->toBe(['openai' => 'gpt-4.1-mini']);
    });

    it('refuses a connection chain that repeats the primary provider with another model', function (): void {
        config()->set('ai-bridge.resolver.connections.survey', 'openai:gpt-4.1-mini');
        config()->set('ai-bridge.resolver.connection_fallbacks.survey', ['openai:gpt-4.1']);

        expect(fn () => app(AiResolver::class)->using('survey'))
            ->toThrow(InvalidArgumentException::class, 'same provider');
    });
});

describe('reasoning effort', function (): void {
    it('maps one effort to each provider request shape', function (): void {
        config()->set('ai-bridge.resolver.efforts.analyst', [
            'openai' => 'high',
            'gemini' => 'low',
            'anthropic' => 'medium',
            'groq' => 'high',
        ]);

        expect(app(AiResolver::class)->connectionOptions('analyst'))->toBe([
            'openai' => ['reasoning' => ['effort' => 'high']],
            'gemini' => ['thinking_level' => 'low'],
            'anthropic' => ['output_config' => ['effort' => 'medium']],
        ]);
    });

    it('returns no options when no effort is configured', function (): void {
        expect(app(AiResolver::class)->connectionOptions('cheap'))->toBe([])
            ->and(app(AiResolver::class)->effort('cheap', 'gemini'))->toBeNull();
    });

    it('builds agents that answer each provider with its own options', function (): void {
        config()->set('ai-bridge.resolver.efforts.analyst', ['openai' => 'high', 'gemini' => 'low']);

        $agent = app(AiResolver::class)->agent('analyst', instructions: 'Be brief.');

        expect($agent)->toBeInstanceOf(ConfiguredAgent::class)
            ->and($agent->instructions())->toBe('Be brief.')
            ->and($agent->providerOptions(Lab::OpenAI))->toBe(['reasoning' => ['effort' => 'high']])
            ->and($agent->providerOptions('gemini'))->toBe(['thinking_level' => 'low'])
            ->and($agent->providerOptions('anthropic'))->toBe([]);
    });

    it('builds a structured agent when a schema is given', function (): void {
        $agent = app(AiResolver::class)->agent('analyst', schema: fn ($schema) => []);

        expect($agent)->toBeInstanceOf(ConfiguredStructuredAgent::class);
    });
});

describe('embedding size', function (): void {
    it('asks a smaller model for its own size and pads the vector to the stored size', function (): void {
        config()->set('ai-bridge.resolver.embedding', 'voyageai:voyage-4');
        config()->set('ai-bridge.resolver.embedding_dimensions', 6);
        config()->set('ai-bridge.resolver.embedding_request_dimensions', ['voyageai:voyage-4' => 4]);

        Embeddings::fake([[[0.1, 0.2, 0.3, 0.4]]]);

        $result = app(AiResolver::class)->embedWithMeta('hello');

        expect($result['vector'])->toBe([0.1, 0.2, 0.3, 0.4, 0.0, 0.0])
            ->and($result['identity'])->toBe('voyageai:voyage-4');
    });
});

describe('fakes', function (): void {
    it('hands back the plain agent class when only that class is faked', function (): void {
        AnonymousAgent::fake(['ok']);

        $agent = app(AiResolver::class)->agent('analyst', instructions: 'x');

        expect($agent::class)->toBe(AnonymousAgent::class)
            ->and($agent->prompt('hi')->text)->toBe('ok');
    });
});
