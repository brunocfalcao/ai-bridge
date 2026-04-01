<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Chat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $table = 'ai_conversations';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'title',
        'summary',
        'type',
        'project_id',
        'cleared_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cleared_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class, 'conversation_id');
    }
}
