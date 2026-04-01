<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Chat\Events;

class ChatError extends ChatEvent
{
    public function __construct(
        public readonly int $userId,
        public readonly string $conversationId,
        public readonly string $message,
    ) {
        parent::__construct($userId, $conversationId);
    }

    public function broadcastAs(): string
    {
        return 'ChatError';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'message' => $this->message,
        ];
    }
}
