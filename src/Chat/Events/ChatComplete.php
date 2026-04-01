<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Chat\Events;

class ChatComplete extends ChatEvent
{
    public function broadcastAs(): string
    {
        return 'ChatComplete';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
        ];
    }
}
