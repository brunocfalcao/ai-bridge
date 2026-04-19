<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Knowledge\Mcp\Tools;

use BrunoCFalcao\AiBridge\Knowledge\ContentChunker;
use BrunoCFalcao\AiBridge\Knowledge\Models\KnowledgeChunk;
use BrunoCFalcao\AiBridge\Knowledge\SystemContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('store-knowledge')]
class StoreKnowledgeTool extends Tool
{
    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema
                ->string()
                ->description('Title/topic for this knowledge entry.')
                ->required(),
            'content' => $schema
                ->string()
                ->description('The content to store as knowledge.')
                ->required(),
            'source_type' => $schema
                ->string()
                ->enum(['web', 'file', 'chat'])
                ->description('Source type: web, file, or chat.')
                ->required(),
            'source_url' => $schema
                ->string()
                ->description('Optional source URL.'),
        ];
    }

    public function handle(Request $request, ContentChunker $chunker, SystemContext $context): Response
    {
        $title = $request->get('title', '');
        $content = $request->get('content', '');
        $sourceType = $request->get('source_type', 'chat');
        $sourceUrl = $request->get('source_url');

        if (empty($title) || empty($content)) {
            return Response::error('Title and content are required.');
        }

        try {
            $chunks = $chunker->chunk($content);
            $connection = $context->getConnection();
            $stored = 0;

            foreach ($chunks as $chunk) {
                KnowledgeChunk::on($connection)->create([
                    'title' => $title,
                    'content' => $chunk,
                    'source_type' => $sourceType,
                    'source_url' => $sourceUrl,
                    'embedding' => Str::of($chunk)->toEmbeddings(),
                ]);
                $stored++;
            }

            Cache::forget("knowledge_topics.{$context->getSlug()}");

            return Response::json([
                'stored' => $stored,
                'system' => $context->getName(),
                'title' => $title,
            ]);

        } catch (\Throwable $e) {
            return Response::error('Failed to store knowledge: '.$e->getMessage());
        }
    }
}
