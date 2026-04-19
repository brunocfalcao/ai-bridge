<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Knowledge\Mcp\Resources;

use BrunoCFalcao\AiBridge\Knowledge\Models\KnowledgeChunk;
use BrunoCFalcao\AiBridge\Knowledge\SystemContext;
use Illuminate\Support\Facades\Cache;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

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
