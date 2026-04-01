<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Mcp\Tools;

use BrunoCFalcao\AiBridge\Mcp\Models\KnowledgeChunk;
use BrunoCFalcao\AiBridge\Mcp\Services\SystemContext;
use BrunoCFalcao\AiBridge\Services\ContentChunker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Mcp\Attributes\Name;
use Laravel\Mcp\Server\Request;
use Laravel\Mcp\Server\Response;
use Laravel\Mcp\Server\Tool;

#[Name('store-knowledge')]
class StoreKnowledgeTool extends Tool
{
    /** @return array<string, mixed> */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => [
                    'type' => 'string',
                    'description' => 'Title/topic for this knowledge entry.',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'The content to store as knowledge.',
                ],
                'source_type' => [
                    'type' => 'string',
                    'enum' => ['web', 'file', 'chat'],
                    'description' => 'Source type: web, file, or chat.',
                ],
                'source_url' => [
                    'type' => 'string',
                    'description' => 'Optional source URL.',
                ],
            ],
            'required' => ['title', 'content', 'source_type'],
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
