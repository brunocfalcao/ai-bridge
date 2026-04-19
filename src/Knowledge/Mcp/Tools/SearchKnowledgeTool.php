<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Knowledge\Mcp\Tools;

use BrunoCFalcao\AiBridge\Knowledge\Models\KnowledgeChunk;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search-knowledge')]
#[IsReadOnly]
class SearchKnowledgeTool extends Tool
{
    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema
                ->string()
                ->description('The search query for finding relevant knowledge.')
                ->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $query = $request->get('query', '');

        if (empty($query)) {
            return Response::error('Query is required.');
        }

        try {
            $minSimilarity = config('ai-bridge.knowledge.vector_min_similarity', 0.4);
            $limit = config('ai-bridge.knowledge.vector_search_limit', 5);

            $results = KnowledgeChunk::query()
                ->whereVectorSimilarTo('embedding', $query, minSimilarity: $minSimilarity)
                ->limit($limit)
                ->get()
                ->map(fn (KnowledgeChunk $chunk) => [
                    'title' => $chunk->title,
                    'content' => Str::limit($chunk->content, 1500),
                    'source_type' => $chunk->source_type,
                    'source_url' => $chunk->source_url,
                ])
                ->values()
                ->toArray();

            if (empty($results)) {
                return Response::text('No relevant knowledge found for this query.');
            }

            return Response::json(['results' => $results]);

        } catch (\Throwable $e) {
            return Response::error('Knowledge search is unavailable: '.$e->getMessage());
        }
    }
}
