# Tools - Sandboxed Agent Tools

## Overview

Six tools implement `Laravel\Ai\Contracts\Tool`, giving AI agents controlled access to the filesystem, shell commands, code search, and knowledge base search. All filesystem tools use the `ResolvesProjectPath` trait for path traversal protection.

## ResolvesProjectPath Trait

**Namespace:** `BrunoCFalcao\AiBridge\Tools\Concerns\ResolvesProjectPath`

Shared path resolution with traversal protection:

```php
protected function resolvePath(string $relativePath): ?string
```

Resolves a relative path against `$this->projectPath`. Returns `null` if the resolved path escapes the project directory boundary (`realpath` + `str_starts_with` check).

## ListDirectory

**Constructor:** `(string $projectPath)`
**Schema:** `{ path: string (optional, default ".") }`

Lists directory contents with type and size. Directories are marked with trailing `/` and sorted before files.

**Output:** JSON array of `{ name, type: 'dir'|'file', size }`

## ReadFile

**Constructor:** `(string $projectPath)`
**Schema:** `{ path: string (required), offset: int (optional), limit: int (optional) }`

Reads file content with line numbers. Enforces max file size from `config('ai-bridge.tools.max_file_size')` (default 512KB).

**Output:** JSON with `{ file, total_lines, showing, content }`

Line number format: `%4d | %s`

## WriteFile

**Constructor:** `(string $projectPath)`
**Schema:** `{ path: string (required), content: string (required) }`

Creates or overwrites a file. Path traversal validation runs BEFORE directory creation.

**Security:**
1. Validates the target path is within the project boundary (even for non-existent paths).
2. Creates parent directories with `0755` permissions if needed.
3. Writes content via `file_put_contents`.

**Output:** JSON with `{ success, path, bytes }`

## RunCommand

**Constructor:** `(string $projectPath)` -- loads allowlists from config.

**Schema:** `{ command: string (required) }`

Executes a shell command within the project directory with multiple security layers.

### Security Layers

**1. Chaining detection** -- Rejects commands containing shell operators:
- `;` (command separator)
- `&` (background/AND)
- `|` (pipe)
- `` ` `` (backtick subshell)
- `$(` (subshell)

**2. Command allowlist** -- Only whitelisted base commands are allowed. Configurable via `config('ai-bridge.tools.allowed_commands')`.

Default allowed commands:
```
php, composer, npm, node, npx, git, ls, cat, head, tail, wc, find, grep,
awk, sed, sort, uniq, diff, mkdir, cp, mv, touch, ps, top, free, df, du,
uptime, whoami, hostname
```

**3. Sudo restrictions** -- For `sudo` commands, validates both the target command and optionally the subcommand. Configurable via `config('ai-bridge.tools.allowed_sudo_commands')`.

Default sudo allowlist:
```php
'supervisorctl' => null,        // any subcommand
'systemctl' => ['status', 'start', 'stop', 'restart', 'reload'],
'service' => ['status', 'start', 'stop', 'restart'],
'kill' => null,
'killall' => null,
'journalctl' => null,
'nginx' => ['-s', '-t'],
```

**4. Dangerous pattern blocking** -- Rejects commands matching any dangerous pattern. Configurable via `config('ai-bridge.tools.dangerous_patterns')`.

Default blocked patterns:
```
rm -rf /, rm -rf ~, > /dev/, mkfs, dd if=, :(){, chmod -R 777 /
```

**5. Execution** -- Uses `proc_open` with non-blocking pipes, 50ms poll interval, configurable timeout (default 30s from `config('ai-bridge.tools.command_timeout')`).

**6. Output limits** -- stdout truncated to 10KB, stderr to 3KB.

**Output:** JSON with `{ exit_code, stdout, stderr }`

## SearchCode

**Constructor:** `(string $projectPath)`
**Schema:** `{ pattern: string (required), glob: string (optional), limit: int (optional, default 50) }`

Searches project files using `grep -rn`. Default file types:
```
*.php, *.js, *.ts, *.vue, *.blade.php, *.json, *.yaml, *.yml, *.md, *.css, *.env.example
```

If a custom `glob` is provided, only that pattern is searched.

**Output:** Relative paths with line numbers and matching content. Limited to `$limit` results.

## SearchKnowledge

**Constructor:** `(?object $user = null, ?string $connection = null)`
**Schema:** `{ query: string (required) }`

Searches the project knowledge base via pgvector cosine similarity. If no connection is configured, returns "No knowledge base configured."

Uses `KnowledgeChunk::on($connection)->whereVectorSimilarTo(...)`. Disconnects the connection after use to prevent connection pool issues.

**Output:** JSON array of `{ title, content, source_type, source_url }` or error message.
