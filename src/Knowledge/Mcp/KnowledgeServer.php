<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Knowledge\Mcp;

use BrunoCFalcao\AiBridge\Knowledge\Mcp\Resources\ChunkResource;
use BrunoCFalcao\AiBridge\Knowledge\Mcp\Resources\TopicsResource;
use BrunoCFalcao\AiBridge\Knowledge\Mcp\Tools\ListTopicsTool;
use BrunoCFalcao\AiBridge\Knowledge\Mcp\Tools\SearchKnowledgeTool;
use BrunoCFalcao\AiBridge\Knowledge\Mcp\Tools\StoreKnowledgeTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Knowledge Server')]
#[Version('1.0.0')]
#[Instructions('A knowledge base MCP server. Use search-knowledge to find documentation by semantic similarity, store-knowledge to ingest new content, and list-topics to see available topics.')]
class KnowledgeServer extends Server
{
    /** @var array<int, class-string> */
    protected array $tools = [
        SearchKnowledgeTool::class,
        StoreKnowledgeTool::class,
        ListTopicsTool::class,
    ];

    /** @var array<int, class-string> */
    protected array $resources = [
        TopicsResource::class,
        ChunkResource::class,
    ];
}
