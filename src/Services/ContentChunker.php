<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Services;

class ContentChunker
{
    public function chunk(string $content, ?int $maxChars = null, ?int $overlapChars = null): array
    {
        $maxChars ??= config('ai-bridge.knowledge.chunk_max_chars', 3200);
        $overlapChars ??= config('ai-bridge.knowledge.chunk_overlap_chars', 400);

        if (strlen($content) <= $maxChars) {
            return [$content];
        }

        $chunks = [];
        $offset = 0;
        $length = strlen($content);

        while ($offset < $length) {
            $slice = substr($content, $offset, $maxChars);

            if ($offset + $maxChars < $length) {
                $sentenceBreak = strrpos($slice, '. ');
                $newlineBreak = strrpos($slice, "\n");
                $breakPoint = max($sentenceBreak, $newlineBreak);

                if ($breakPoint !== false && $breakPoint > (int) ($maxChars * 0.5)) {
                    $slice = substr($slice, 0, $breakPoint + 1);
                }
            }

            $trimmed = trim($slice);
            if (strlen($trimmed) >= 50) {
                $chunks[] = $trimmed;
            }

            $advance = strlen($slice) - $overlapChars;
            if ($advance <= 0) {
                break;
            }

            $offset += $advance;
        }

        return $chunks;
    }
}
