<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Tools;

use BrunoCFalcao\AiBridge\Tools\Concerns\ResolvesProjectPath;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListDirectory implements Tool
{
    use ResolvesProjectPath;

    public function __construct(
        protected string $projectPath,
    ) {}

    public function description(): string
    {
        return 'List files and directories in a project folder. Shows file sizes and types.';
    }

    public function handle(Request $request): string
    {
        $relativePath = $request->string('path', '.');
        $path = $this->resolvePath($relativePath);

        if (! $path) {
            return json_encode(['error' => 'Path is outside the project directory.']);
        }

        if (! is_dir($path)) {
            return json_encode(['error' => "Directory not found: {$relativePath}"]);
        }

        $entries = scandir($path);
        $items = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fullPath = $path.'/'.$entry;
            $isDir = is_dir($fullPath);

            $items[] = [
                'name' => $entry.($isDir ? '/' : ''),
                'type' => $isDir ? 'directory' : 'file',
                'size' => $isDir ? null : filesize($fullPath),
            ];
        }

        usort($items, function ($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'directory' ? -1 : 1;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        return json_encode([
            'path' => $relativePath,
            'count' => count($items),
            'entries' => $items,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema
                ->string()
                ->description('Directory path relative to the project root. Default: "." (project root).'),
        ];
    }
}
