<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Quality;

use JsonException;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

require __DIR__.'/vendor/autoload.php';

final class QualityGate
{
    private const SOURCE_PATHS = [
        'src',
        'config',
        'tests',
        'phpinsights.php',
        'quality-gate.php',
    ];

    public static function run(): int
    {
        try {
            $failures = [
                ...self::checkInsights(self::SOURCE_PATHS),
                ...self::checkPhpmd(self::SOURCE_PATHS),
                ...self::checkPhpstan(),
                ...self::checkAdvisories(),
            ];
        } catch (Throwable $exception) {
            fwrite(STDERR, $exception->getMessage().PHP_EOL);

            return 1;
        }

        foreach ($failures as $failure) {
            fwrite(STDERR, $failure.PHP_EOL);
        }

        return $failures === [] ? 0 : 1;
    }

    /**
     * @param  list<string>  $command
     * @return array{process: Process, report: array<string, mixed>}
     */
    private static function jsonReport(array $command): array
    {
        $process = new Process($command, __DIR__);
        $process->setTimeout(300);
        $process->run();

        try {
            $report = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(sprintf(
                '%s produced no valid JSON: %s %s',
                $command[1],
                $exception->getMessage(),
                trim($process->getErrorOutput()),
            ), previous: $exception);
        }

        if (! is_array($report)) {
            throw new RuntimeException($command[1].' produced an invalid report.');
        }

        return ['process' => $process, 'report' => $report];
    }

    /** @return array<string, mixed> */
    private static function baseline(string $file): array
    {
        $contents = file_get_contents(__DIR__.'/'.$file);

        if ($contents === false) {
            throw new RuntimeException("Cannot read {$file}.");
        }

        $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($data)) {
            throw new RuntimeException("{$file} is not a JSON object.");
        }

        return $data;
    }

    /**
     * Identifies one violation by category, rule, file, message and function. The line is excluded
     * so unrelated edits above a baselined violation do not turn it into a new one; repeated
     * identical violations in one file are counted instead.
     *
     * @param  array<string, mixed>  $issue
     */
    private static function insightKey(array $issue): string
    {
        return json_encode([
            $issue['category'] ?? null,
            $issue['rule'] ?? null,
            $issue['file'] ?? null,
            $issue['message'] ?? null,
            $issue['function'] ?? null,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  list<string>  $sourcePaths
     * @return list<string>
     */
    private static function checkInsights(array $sourcePaths): array
    {
        $result = self::jsonReport([
            PHP_BINARY, '-d', 'memory_limit=2G', 'vendor/bin/phpinsights', 'analyse',
            ...$sourcePaths,
            '--composer=composer.json', '--config-path=phpinsights.php',
            '--format=json', '--no-interaction', '--disable-security-check',
        ]);
        $report = $result['report'];
        $scores = $report['summary'] ?? [];

        printf(
            "PHP Insights: quality %s, complexity %s, architecture %s, style %s\n",
            $scores['code'] ?? '?',
            $scores['complexity'] ?? '?',
            $scores['architecture'] ?? '?',
            $scores['style'] ?? '?',
        );

        $existing = self::baseline('phpinsights-baseline.json')['issues'] ?? null;

        if (! is_array($existing)) {
            throw new RuntimeException('phpinsights-baseline.json has no issues list.');
        }

        $allowances = [];

        foreach ($existing as $issue) {
            $key = self::insightKey($issue);
            $allowances[$key] = ($allowances[$key] ?? 0) + 1;
        }

        $failures = [];

        foreach (['Code', 'Complexity', 'Architecture', 'Style'] as $category) {
            foreach ($report[$category] ?? [] as $issue) {
                $item = [
                    'category' => $category,
                    'rule' => $issue['insightClass'] ?? null,
                    'file' => $issue['file'] ?? null,
                    'line' => $issue['line'] ?? null,
                    'message' => $issue['message'] ?? null,
                    'function' => $issue['function'] ?? null,
                ];
                $key = self::insightKey($item);

                if (($allowances[$key] ?? 0) > 0) {
                    $allowances[$key]--;

                    continue;
                }

                $location = ($item['file'] ?? 'project').(isset($item['line']) ? ':'.$item['line'] : '');
                $failures[] = sprintf(
                    'PHP Insights %s %s: %s (%s)',
                    $location,
                    $category,
                    $issue['title'] ?? $item['rule'],
                    $item['function'] ?? $item['message'] ?? 'new finding',
                );
            }
        }

        if (! $result['process']->isSuccessful()) {
            $failures[] = 'PHP Insights scores or execution failed: '.trim($result['process']->getErrorOutput());
        }

        return $failures;
    }

    /**
     * @param  list<string>  $sourcePaths
     * @return list<string>
     */
    private static function checkPhpmd(array $sourcePaths): array
    {
        $files = [];

        foreach (self::phpmdPathGroups($sourcePaths) as $paths) {
            $command = [
                'vendor/bin/phpmd', 'analyze', ...$paths,
                '--format=json', '--ruleset=phpmd.xml', '--no-progress', '--no-cache',
            ];
            $raw = self::rawPhpmdReport($command);
            $files = [...$files, ...($raw['report']['files'] ?? [])];
        }

        $existing = self::baseline('phpmd-complexity-baseline.json')['methods'] ?? null;

        if (! is_array($existing)) {
            throw new RuntimeException('phpmd-complexity-baseline.json has no methods list.');
        }

        return self::complexityFailures(['files' => $files], $existing);
    }

    /**
     * PDepend reports incorrect method scores when source paths share a scan.
     *
     * @param  list<string>  $sourcePaths
     * @return list<list<string>>
     */
    private static function phpmdPathGroups(array $sourcePaths): array
    {
        return array_map(static fn (string $path): array => [$path], $sourcePaths);
    }

    /**
     * PHPMD loads its native baseline by default; an empty temporary baseline exposes measured scores.
     *
     * @param  list<string>  $command
     * @return array{process: Process, report: array<string, mixed>}
     */
    private static function rawPhpmdReport(array $command): array
    {
        $emptyBaseline = tempnam(sys_get_temp_dir(), 'ai-bridge-phpmd-');

        if ($emptyBaseline === false) {
            throw new RuntimeException('Cannot prepare an empty PHPMD baseline.');
        }

        try {
            if (file_put_contents($emptyBaseline, '<phpmd-baseline><note/></phpmd-baseline>') === false) {
                throw new RuntimeException('Cannot write an empty PHPMD baseline.');
            }

            $result = self::jsonReport([...$command, '--baseline-file='.$emptyBaseline]);

            if (! in_array($result['process']->getExitCode(), [0, 2], true)) {
                throw new RuntimeException('PHPMD failed: '.trim($result['process']->getErrorOutput()));
            }

            return $result;
        } finally {
            unlink($emptyBaseline);
        }
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  list<array{file: string, class: string, method: string, complexity: int}>  $existing
     * @return list<string>
     */
    private static function complexityFailures(array $report, array $existing): array
    {
        $allowedComplexity = [];

        foreach ($existing as $method) {
            $key = json_encode([$method['file'], $method['class'], $method['method']], JSON_THROW_ON_ERROR);
            $allowedComplexity[$key] = $method['complexity'];
        }

        $failures = [];
        $violations = 0;

        foreach ($report['files'] ?? [] as $file) {
            foreach ($file['violations'] ?? [] as $violation) {
                $violations++;
                $key = json_encode([
                    $file['relativePath'], $violation['class'], $violation['method'],
                ], JSON_THROW_ON_ERROR);

                if (! preg_match('/Cyclomatic Complexity of (\d+)/', $violation['description'], $matches)) {
                    $failures[] = sprintf(
                        'PHPMD returned an unrecognized complexity for %s:%d',
                        $file['relativePath'],
                        $violation['beginLine'],
                    );

                    continue;
                }

                $complexity = (int) $matches[1];

                if (! isset($allowedComplexity[$key]) || $complexity > $allowedComplexity[$key]) {
                    $failures[] = sprintf(
                        'PHPMD %s:%d %s::%s complexity %d (baseline %s)',
                        $file['relativePath'],
                        $violation['beginLine'],
                        $violation['class'],
                        $violation['method'],
                        $complexity,
                        $allowedComplexity[$key] ?? 'none',
                    );
                }
            }
        }

        printf("PHPMD: %d methods above complexity 10 checked\n", $violations);

        return $failures;
    }

    /** @return list<string> */
    private static function checkPhpstan(): array
    {
        $result = self::jsonReport([
            'vendor/bin/phpstan', 'analyse', '--configuration=phpstan.neon',
            '--memory-limit=2G', '--no-progress', '--error-format=json', '-v',
        ]);

        if ($result['process']->isSuccessful()) {
            echo "PHPStan/Larastan: passed\n";

            return [];
        }

        $report = $result['report'];
        $files = $report['error_details'] ?? $report['files'] ?? [];
        $failures = [];

        foreach ($files as $file => $details) {
            foreach ($details['messages'] ?? $details as $message) {
                $failures[] = sprintf(
                    'PHPStan %s:%s %s',
                    str_replace(__DIR__.'/', '', $file),
                    $message['line'] ?? '?',
                    $message['message'] ?? 'unknown error',
                );
            }
        }

        return $failures === [] ? ['PHPStan failed: '.json_encode($report)] : $failures;
    }

    /**
     * PHP Insights aborts on any dependency advisory, so advisories are compared with their own baseline.
     *
     * @return list<string>
     */
    private static function checkAdvisories(): array
    {
        $result = self::jsonReport([
            'composer', 'audit', '--format=json', '--abandoned=ignore', '--no-interaction',
        ]);
        $allowed = self::baseline('composer-audit-baseline.json')['advisories'] ?? null;

        if (! is_array($allowed)) {
            throw new RuntimeException('composer-audit-baseline.json has no advisories list.');
        }

        $allowedKeys = array_map(
            static fn (array $advisory): string => $advisory['package'].'#'.$advisory['advisoryId'],
            $allowed,
        );
        $failures = [];
        $checked = 0;

        foreach ($result['report']['advisories'] ?? [] as $package => $advisories) {
            foreach ($advisories as $advisory) {
                $checked++;

                if (! in_array($package.'#'.$advisory['advisoryId'], $allowedKeys, true)) {
                    $failures[] = sprintf(
                        'Composer audit %s %s: %s (affected %s)',
                        $package,
                        $advisory['advisoryId'],
                        $advisory['title'],
                        $advisory['affectedVersions'],
                    );
                }
            }
        }

        printf("Composer audit: %d advisories checked\n", $checked);

        return $failures;
    }
}

exit(QualityGate::run());
