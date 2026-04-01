<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Tools;

use BrunoCFalcao\AiBridge\Tools\Concerns\ResolvesProjectPath;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class WriteFile implements Tool
{
    use ResolvesProjectPath;

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

        $realBase = realpath($this->projectPath);
        $full = $this->projectPath.'/'.ltrim($relativePath, '/');
        $dir = dirname($full);

        // Validate the target directory path before touching the filesystem.
        // Use the normalized string path when the directory does not yet exist.
        $checkDir = is_dir($dir) ? realpath($dir) : realpath(dirname($dir)).'/'.basename($dir);

        if (! $checkDir || ! str_starts_with($checkDir, $realBase)) {
            return json_encode(['error' => 'Path is outside the project directory.']);
        }

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
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
