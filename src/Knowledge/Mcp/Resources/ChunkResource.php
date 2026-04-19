<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Knowledge\Mcp\Resources;

use BrunoCFalcao\AiBridge\Knowledge\Models\KnowledgeChunk;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Name('chunk')]
#[MimeType('application/json')]
class ChunkResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('knowledge://chunks/{id}');
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
