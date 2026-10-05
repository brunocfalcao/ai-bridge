<?php

declare(strict_types=1);

use BrunoCFalcao\AiBridge\Agents\ConfiguredAgent;
use BrunoCFalcao\AiBridge\Resolver\AiResolver;
use Laravel\Ai\Ai;
use Laravel\Ai\Messages\MessageRole;
use Laravel\Ai\Prompts\AgentPrompt;

it('streams text deltas and closes the stream', function (): void {
    ConfiguredAgent::fake(['Queue times are the top complaint.'])->preventStrayPrompts();

    $events = iterator_to_array(app(AiResolver::class)->stream([
        ['role' => 'system', 'content' => 'You are an analyst.'],
        ['role' => 'user', 'content' => 'What is the top complaint?'],
    ], 'cheap'));

    expect(end($events))->toBe(['type' => 'done', 'content' => null])
        ->and(collect($events)->where('type', 'delta')->pluck('content')->join(''))
        ->toBe('Queue times are the top complaint.');
});

it('sends the system turn as instructions, the last user turn as the prompt and the rest as history', function (): void {
    ConfiguredAgent::fake(['Noted.'])->preventStrayPrompts();

    iterator_to_array(app(AiResolver::class)->stream([
        ['role' => 'system', 'content' => 'You are an analyst.'],
        ['role' => 'user', 'content' => 'How many sessions yesterday?'],
        ['role' => 'assistant', 'content' => 'Fourteen.'],
        ['role' => 'user', 'content' => 'And today?'],
    ], 'cheap'));

    Ai::assertAgentWasPrompted(ConfiguredAgent::class, function (AgentPrompt $prompt): bool {
        $history = collect($prompt->agent->messages())
            ->map(fn ($message): array => [
                $message->role instanceof MessageRole ? $message->role->value : (string) $message->role,
                $message->content,
            ])
            ->all();

        return $prompt->prompt === 'And today?'
            && $prompt->agent->instructions() === 'You are an analyst.'
            && $history === [
                ['user', 'How many sessions yesterday?'],
                ['assistant', 'Fourteen.'],
            ];
    });
});

it('carries the connection reasoning effort into the streamed request', function (): void {
    config()->set('ai-bridge.resolver.efforts.cheap', ['gemini' => 'high']);

    ConfiguredAgent::fake(['Noted.'])->preventStrayPrompts();

    iterator_to_array(app(AiResolver::class)->stream([
        ['role' => 'user', 'content' => 'Anything urgent?'],
    ], 'cheap'));

    Ai::assertAgentWasPrompted(
        ConfiguredAgent::class,
        fn (AgentPrompt $prompt): bool => $prompt->agent->providerOptions('gemini') === ['thinking_level' => 'high'],
    );
});
