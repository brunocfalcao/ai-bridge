<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Knowledge\Mcp\Tools;

use BrunoCFalcao\AiBridge\Knowledge\Models\KnowledgeChunk;
use BrunoCFalcao\AiBridge\Knowledge\SystemContext;
use Illuminate\Support\Facades\Cache;
use Laravel\Mcp\Attributes\IsReadOnly;
use Laravel\Mcp\Attributes\Name;
use Laravel\Mcp\Server\Request;
use Laravel\Mcp\Server\Response;
use Laravel\Mcp\Server\Tool;

#[Name('list-topics')]
#[IsReadOnly]
class ListTopicsTool extends Tool
{
    /** @return array<string, mixed> */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [],
        ];
    }

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
