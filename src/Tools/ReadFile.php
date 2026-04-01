<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ReadFile implements Tool
{
    public function __construct(
        protected string $projectPath,
    ) {}

    public function description(): string
    {
        return 'Read the contents of a file in the project. Returns the file content with line numbers.';
    }

    public function handle(Request $request): string
    {
        $path = $this->resolvePath($request->string('path'));

        if (! $path) {
            return json_encode(['error' => 'Path is outside the project directory.']);
        }

        if (! file_exists($path)) {
            return json_encode(['error' => "File not found: {$request->string('path')}"]);
        }

        if (! is_file($path)) {
            return json_encode(['error' => 'Path is a directory, not a file. Use list_directory instead.']);
        }

        if (filesize($path) > 512_000) {
            return json_encode(['error' => 'File is too large (> 500KB). Try reading a specific range with offset and limit.']);
        }

        $offset = $request->integer('offset', 0);
        $limit = $request->integer('limit', 0);

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        $total = count($lines);

        if ($offset > 0) {
            $lines = array_slice($lines, $offset);
        }

        if ($limit > 0) {
            $lines = array_slice($lines, 0, $limit);
        }

        $numbered = [];
        $startLine = $offset + 1;

        foreach ($lines as $i => $line) {
            $lineNum = $startLine + $i;
            $numbered[] = sprintf('%4d | %s', $lineNum, $line);
        }

        return json_encode([
            'file' => $request->string('path'),
            'total_lines' => $total,
            'showing' => count($numbered),
            'content' => implode("\n", $numbered),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema
                ->string()
                ->description('File path relative to the project root.')
                ->required(),
            'offset' => $schema
                ->integer()
                ->description('Start reading from this line number (0-based). Default: 0.'),
            'limit' => $schema
                ->integer()
                ->description('Maximum number of lines to read. Default: 0 (all lines).'),
        ];
    }

    protected function resolvePath(string $relativePath): ?string
    {
        $full = realpath($this->projectPath.'/'.ltrim($relativePath, '/'));

        if (! $full || ! str_starts_with($full, realpath($this->projectPath))) {
            return null;
        }

        return $full;
    }
}
