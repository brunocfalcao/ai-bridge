<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Mcp\Resources;

use BrunoCFalcao\AiBridge\Mcp\Models\KnowledgeChunk;
use BrunoCFalcao\AiBridge\Mcp\Services\SystemContext;
use Illuminate\Support\Facades\Cache;
use Laravel\Mcp\Attributes\MimeType;
use Laravel\Mcp\Attributes\Name;
use Laravel\Mcp\Attributes\Uri;
use Laravel\Mcp\Server\Request;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Response;

#[Name('topics')]
#[Uri('knowledge://topics')]
#[MimeType('application/json')]
class TopicsResource extends Resource
{
    public function handle(Request $request, SystemContext $context): Response
    {
        $topics = Cache::remember(
            "knowledge_topics.{$context->getSlug()}",
            300,
            fn () => KnowledgeChunk::query()
                ->selectRaw('title, COUNT(*) as chunk_count')
                ->groupBy('title')
                ->orderBy('title')
                ->get()
                ->toArray()
        );

        return Response::json(['topics' => $topics]);
    }
}
