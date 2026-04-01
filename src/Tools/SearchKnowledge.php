<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Tools;

use BrunoCFalcao\AiBridge\Mcp\Models\KnowledgeChunk;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SearchKnowledge implements Tool
{
    public function __construct(
        protected ?object $user = null,
        protected ?string $connection = null,
    ) {}

    public function description(): string
    {
        return 'Search the project knowledge base for relevant documentation and information.';
    }

    public function handle(Request $request): string
    {
        $query = $request->string('query');

        if (! $this->connection) {
            return json_encode(['results' => [], 'message' => 'No knowledge base configured for this project.']);
        }

        try {
            $minSimilarity = config('ai-bridge.knowledge.vector_min_similarity', 0.4);
            $limit = config('ai-bridge.knowledge.vector_search_limit', 5);

            $results = KnowledgeChunk::on($this->connection)
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

            DB::disconnect($this->connection);

            if (empty($results)) {
                return json_encode(['results' => [], 'message' => 'No relevant knowledge found.']);
            }

            return json_encode(['results' => $results]);

        } catch (\Throwable $e) {
            return json_encode(['results' => [], 'message' => 'Knowledge search unavailable: '.$e->getMessage()]);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema
                ->string()
                ->description('The search query to find relevant documentation.')
                ->required(),
        ];
    }
}
