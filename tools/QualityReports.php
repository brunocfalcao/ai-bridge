<?php

declare(strict_types=1);

namespace AiBridgeTools;

use JsonException;
use RuntimeException;

/** Development-only report parsing; commands and acceptance policies stay in each gate. */
final class QualityReports
{
    /** @return array<string, mixed> */
    public static function decode(string $output, string $tool, string $errors = ''): array
    {
        try {
            $report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(sprintf('%s produced no valid JSON: %s %s', $tool, $exception->getMessage(), trim($errors)), previous: $exception);
        }
        if (! is_array($report)) {
            throw new RuntimeException($tool.' produced an invalid report.');
        }

        return $report;
    }

    /** @return array<string, mixed> */
    public static function baseline(string $root, string $file): array
    {
        $contents = file_get_contents($root.'/'.$file);
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
     * @param  array<string, mixed>  $report
     * @param  list<array{file: string, class: string, method: string, complexity: int}>  $existing
     * @return list<string>
     */
    public static function complexityFailures(array $report, array $existing): array
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
                    $failures[] = 'PHPMD returned an unrecognized complexity for '.$file['relativePath'].':'.$violation['beginLine'];

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
}
