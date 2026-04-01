<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Knowledge\Mcp\Tools;

use BrunoCFalcao\AiBridge\Knowledge\Models\KnowledgeChunk;
use Illuminate\Support\Str;
use Laravel\Mcp\Attributes\IsReadOnly;
use Laravel\Mcp\Attributes\Name;
use Laravel\Mcp\Server\Request;
use Laravel\Mcp\Server\Response;
use Laravel\Mcp\Server\Tool;

#[Name('search-knowledge')]
#[IsReadOnly]
class SearchKnowledgeTool extends Tool
{
    /** @return array<string, mixed> */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'The search query for finding relevant knowledge.',
                ],
            ],
            'required' => ['query'],
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
