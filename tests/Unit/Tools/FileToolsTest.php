<?php

declare(strict_types=1);

use BrunoCFalcao\AiBridge\Tools\ListDirectory;
use BrunoCFalcao\AiBridge\Tools\ReadFile;
use Laravel\Ai\Tools\Request;

/**
 * @param  array<string, int|string>  $input
 * @return array<string, array|int|string|null>
 */
function runFileTool(ReadFile|ListDirectory $tool, array $input): array
{
    return json_decode($tool->handle(new Request($input)), associative: true);
}

beforeEach(function (): void {
    $this->root = sys_get_temp_dir().'/ai-bridge-file-tools-'.bin2hex(random_bytes(6));
    mkdir($this->root.'/sub', recursive: true);
    file_put_contents($this->root.'/notes.txt', "alpha\nbeta\ngamma\n");
    file_put_contents($this->root.'/sub/inner.txt', 'x');
});

afterEach(function (): void {
    unlink($this->root.'/sub/inner.txt');
    unlink($this->root.'/notes.txt');
    rmdir($this->root.'/sub');
    rmdir($this->root);
});

describe('ReadFile', function (): void {
    it('reads a file named by a path string under strict types', function (): void {
        $result = runFileTool(new ReadFile($this->root), ['path' => 'notes.txt']);

        expect($result)->toBe([
            'file' => 'notes.txt',
            'total_lines' => 3,
            'showing' => 3,
            'content' => "   1 | alpha\n   2 | beta\n   3 | gamma",
        ]);
    });

    it('honours offset and limit', function (): void {
        $result = runFileTool(new ReadFile($this->root), ['path' => 'notes.txt', 'offset' => 1, 'limit' => 1]);

        expect($result['showing'])->toBe(1)
            ->and($result['content'])->toBe('   2 | beta');
    });

    it('reports a missing file as outside the project, because the path cannot be resolved', function (): void {
        $result = runFileTool(new ReadFile($this->root), ['path' => 'missing.txt']);

        expect($result)->toBe(['error' => 'Path is outside the project directory.']);
    });

    it('refuses a path outside the project directory', function (): void {
        $result = runFileTool(new ReadFile($this->root.'/sub'), ['path' => '../notes.txt']);

        expect($result)->toBe(['error' => 'Path is outside the project directory.']);
    });

    it('points a directory path at list_directory', function (): void {
        $result = runFileTool(new ReadFile($this->root), ['path' => 'sub']);

        expect($result['error'])->toContain('list_directory');
    });
});

describe('ListDirectory', function (): void {
    it('lists a directory named by a path string, directories first', function (): void {
        $result = runFileTool(new ListDirectory($this->root), ['path' => '.']);

        expect($result)->toBe([
            'path' => '.',
            'count' => 2,
            'entries' => [
                ['name' => 'sub/', 'type' => 'directory', 'size' => null],
                ['name' => 'notes.txt', 'type' => 'file', 'size' => 17],
            ],
        ]);
    });

    it('defaults to the project root when no path is given', function (): void {
        $result = runFileTool(new ListDirectory($this->root), []);

        expect($result['path'])->toBe('.')
            ->and($result['count'])->toBe(2);
    });

    it('lists a subdirectory', function (): void {
        $result = runFileTool(new ListDirectory($this->root), ['path' => 'sub']);

        expect($result['entries'])->toBe([['name' => 'inner.txt', 'type' => 'file', 'size' => 1]]);
    });

    it('refuses a path outside the project directory', function (): void {
        $result = runFileTool(new ListDirectory($this->root.'/sub'), ['path' => '..']);

        expect($result)->toBe(['error' => 'Path is outside the project directory.']);
    });

    it('reports an unresolvable directory as outside the project', function (): void {
        $result = runFileTool(new ListDirectory($this->root), ['path' => 'nope']);

        expect($result)->toBe(['error' => 'Path is outside the project directory.']);
    });
});
