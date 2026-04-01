<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class WriteFile implements Tool
{
    public function __construct(
        protected string $projectPath,
    ) {}

    public function description(): string
    {
        return 'Create or overwrite a file in the project. Use for creating new files or replacing entire file contents.';
    }

    public function handle(Request $request): string
    {
        $relativePath = (string) $request->string('path');
        $content = (string) $request->string('content');

        $full = $this->projectPath.'/'.ltrim($relativePath, '/');
        $realBase = realpath($this->projectPath);

        // Ensure the resolved path stays within the project
        $dir = dirname($full);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $realDir = realpath($dir);

        if (! $realDir || ! str_starts_with($realDir, $realBase)) {
            return json_encode(['error' => 'Path is outside the project directory.']);
        }

        file_put_contents($full, $content);

        return json_encode([
            'success' => true,
            'path' => $relativePath,
            'bytes' => strlen($content),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema
                ->string()
                ->description('File path relative to the project root.')
                ->required(),
            'content' => $schema
                ->string()
                ->description('The full content to write to the file.')
                ->required(),
        ];
    }
}
