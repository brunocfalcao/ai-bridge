<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Knowledge\Mcp\Resources;

use BrunoCFalcao\AiBridge\Knowledge\Models\KnowledgeChunk;
use Laravel\Mcp\Attributes\MimeType;
use Laravel\Mcp\Attributes\Name;
use Laravel\Mcp\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Request;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Response;

#[Name('chunk')]
#[MimeType('application/json')]
class ChunkResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): string
    {
        return 'knowledge://chunks/{id}';
    }

    public function handle(Request $request): Response
    {
        $chunk = KnowledgeChunk::find($request->get('id'));

        if (! $chunk) {
            return Response::error('Knowledge chunk not found.');
        }

        return Response::json([
            'id' => $chunk->id,
            'title' => $chunk->title,
            'content' => $chunk->content,
            'source_type' => $chunk->source_type,
            'source_url' => $chunk->source_url,
            'metadata' => $chunk->metadata,
            'created_at' => $chunk->created_at->toIso8601String(),
        ]);
    }
}
