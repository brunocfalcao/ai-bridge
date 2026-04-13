<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Contracts;

use Generator;

interface ChatProvider
{
    /**
     * Stream a chat response, yielding deltas as they arrive.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return Generator<int, array{type: string, content: ?string}>
     */
    public function stream(array $messages, ?string $conversationId = null): Generator;

    /**
     * Send a non-streaming chat request and return the full response text.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function send(array $messages, ?string $conversationId = null): string;

    /**
     * Check if the provider endpoint is reachable.
     */
    public function healthy(): bool;
}
