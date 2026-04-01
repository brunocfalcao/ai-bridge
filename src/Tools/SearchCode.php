<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SearchCode implements Tool
{
    public function __construct(
        protected string $projectPath,
    ) {}

    public function description(): string
    {
        return 'Search for text patterns in the project codebase using grep. Returns matching lines with file paths and line numbers.';
    }

    public function handle(Request $request): string
    {
        $pattern = (string) $request->string('pattern');
        $glob = (string) $request->string('glob', '');
        $limit = $request->integer('limit', 50);

        if (! $pattern) {
            return json_encode(['error' => 'Pattern is required.']);
        }

        $command = [
            'grep', '-rn', '--include=*.php', '--include=*.js', '--include=*.ts',
            '--include=*.vue', '--include=*.blade.php', '--include=*.json',
            '--include=*.yaml', '--include=*.yml', '--include=*.md',
            '--include=*.css', '--include=*.env.example',
        ];

        if ($glob) {
            $command = ['grep', '-rn', "--include={$glob}"];
        }

        $command[] = '-e';
        $command[] = $pattern;
        $command[] = $this->projectPath;

        $process = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->projectPath
        );

        if (! $process) {
            return json_encode(['error' => 'Failed to execute search.']);
        }

        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        $lines = array_filter(explode("\n", $output));
        $total = count($lines);
        $lines = array_slice($lines, 0, $limit);

        // Make paths relative
        $results = array_map(function ($line) {
            return str_replace($this->projectPath.'/', '', $line);
        }, $lines);

        if (empty($results)) {
            return json_encode(['results' => [], 'message' => 'No matches found.']);
        }

        return json_encode([
            'total_matches' => $total,
            'showing' => count($results),
            'results' => implode("\n", $results),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'pattern' => $schema
                ->string()
                ->description('The text or regex pattern to search for.')
                ->required(),
            'glob' => $schema
                ->string()
                ->description('Optional file glob filter (e.g., "*.php", "*.vue"). If omitted, searches common code files.'),
            'limit' => $schema
                ->integer()
                ->description('Maximum number of results to return. Default: 50.'),
        ];
    }
}
