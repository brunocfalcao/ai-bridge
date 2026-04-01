<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Mcp\Models;

use BrunoCFalcao\AiBridge\Mcp\Services\SystemContext;
use Illuminate\Database\Eloquent\Model;

class KnowledgeChunk extends Model
{
    protected $fillable = [
        'title',
        'content',
        'source_type',
        'source_url',
        'embedding',
        'metadata',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'embedding' => 'array',
            'metadata' => 'array',
        ];
    }

    public function getConnectionName(): ?string
    {
        $context = app(SystemContext::class);

        return $context->isSet() ? $context->getConnection() : parent::getConnectionName();
    }
}
