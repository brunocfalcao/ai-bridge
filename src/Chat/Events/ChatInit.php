<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Chat\Events;

class ChatInit extends ChatEvent
{
    public function __construct(
        public readonly int $userId,
        public readonly string $conversationId,
        public readonly string $title,
        public readonly string $type = 'normal',
    ) {
        parent::__construct($userId, $conversationId);
    }

    public function broadcastAs(): string
    {
        return 'ChatInit';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'title' => $this->title,
            'type' => $this->type,
        ];
    }
}
