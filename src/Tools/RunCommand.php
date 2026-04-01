<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class RunCommand implements Tool
{
    protected array $allowedCommands = [
        'php', 'composer', 'npm', 'node', 'npx',
        'git', 'ls', 'cat', 'head', 'tail', 'wc',
        'find', 'grep', 'awk', 'sed', 'sort', 'uniq',
        'diff', 'mkdir', 'cp', 'mv', 'touch',
        'ps', 'top', 'free', 'df', 'du', 'uptime', 'whoami', 'hostname',
    ];

    /** Sudo commands allowed with specific subcommands only. */
    protected array $allowedSudoCommands = [
        'supervisorctl' => ['status', 'start', 'stop', 'restart', 'reread', 'update', 'tail'],
        'systemctl' => ['status', 'start', 'stop', 'restart', 'reload', 'list-units', 'is-active'],
        'service' => ['status', 'start', 'stop', 'restart'],
        'kill' => true,       // any args
        'killall' => true,    // any args
        'crontab' => ['-l'],  // list only
        'nginx' => ['-t', '-s'],
        'journalctl' => true, // any args (read-only)
    ];

    public function __construct(
        protected string $projectPath,
    ) {}

    public function description(): string
    {
        return 'Run a shell command in the project directory. Supports: php, composer, npm, git, common unix tools, ps, free, df. Also supports sudo for: supervisorctl, systemctl, service, kill, killall, crontab -l, nginx, journalctl. Max 30 second timeout.';
    }

    public function handle(Request $request): string
    {
        $command = trim((string) $request->string('command'));

        if (! $command) {
            return json_encode(['error' => 'Command is required.']);
        }

        $parts = preg_split('/\s+/', $command);
        $baseCommand = $parts[0];

        // Handle sudo commands
        if ($baseCommand === 'sudo') {
            $sudoTarget = $parts[1] ?? null;

            if (! $sudoTarget || ! array_key_exists($sudoTarget, $this->allowedSudoCommands)) {
                return json_encode(['error' => "sudo '{$sudoTarget}' is not allowed. Allowed sudo targets: ".implode(', ', array_keys($this->allowedSudoCommands))]);
            }

            $allowedSubcommands = $this->allowedSudoCommands[$sudoTarget];

            if (is_array($allowedSubcommands)) {
                $subcommand = $parts[2] ?? null;

                if (! $subcommand || ! in_array($subcommand, $allowedSubcommands, true)) {
                    return json_encode(['error' => "sudo {$sudoTarget} '{$subcommand}' is not allowed. Allowed: ".implode(', ', $allowedSubcommands)]);
                }
            }
        } elseif (! in_array($baseCommand, $this->allowedCommands, true)) {
            return json_encode(['error' => "Command '{$baseCommand}' is not allowed. Allowed: ".implode(', ', $this->allowedCommands).'. Use sudo for: '.implode(', ', array_keys($this->allowedSudoCommands))]);
        }

        // Block dangerous patterns
        $dangerous = ['rm -rf /', 'rm -rf ~', '> /dev/', 'mkfs', 'dd if=', ':(){', 'chmod -R 777 /'];

        foreach ($dangerous as $pattern) {
            if (str_contains($command, $pattern)) {
                return json_encode(['error' => 'Command contains a dangerous pattern and was blocked.']);
            }
        }

        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $this->projectPath,
            ['HOME' => getenv('HOME'), 'PATH' => getenv('PATH')]
        );

        if (! $process) {
            return json_encode(['error' => 'Failed to start process.']);
        }

        fclose($pipes[0]);

        // Set 30 second timeout
        $startTime = time();
        $stdout = '';
        $stderr = '';

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        while (true) {
            $status = proc_get_status($process);

            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);

            if (! $status['running']) {
                break;
            }

            if ((time() - $startTime) > 30) {
                proc_terminate($process);
                fclose($pipes[1]);
                fclose($pipes[2]);

                return json_encode([
                    'error' => 'Command timed out after 30 seconds.',
                    'stdout' => mb_substr($stdout, 0, 5000),
                ]);
            }

            usleep(50_000);
        }

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        // Truncate long output
        $stdout = mb_substr($stdout, 0, 10_000);
        $stderr = mb_substr($stderr, 0, 3_000);

        return json_encode([
            'exit_code' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr ?: null,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'command' => $schema
                ->string()
                ->description('The shell command to run. Must start with an allowed command (php, composer, npm, git, grep, find, etc.).')
                ->required(),
        ];
    }
}
