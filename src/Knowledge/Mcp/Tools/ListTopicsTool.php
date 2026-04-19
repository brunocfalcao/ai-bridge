<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Knowledge\Mcp\Tools;

use BrunoCFalcao\AiBridge\Knowledge\Models\KnowledgeChunk;
use BrunoCFalcao\AiBridge\Knowledge\SystemContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Cache;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list-topics')]
#[IsReadOnly]
class ListTopicsTool extends Tool
{
    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [];
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
