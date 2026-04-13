<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Providers\Concerns;

use BrunoCFalcao\AiBridge\Chat\Exceptions\ChatProviderException;
use Generator;
use Illuminate\Http\Client\Response;

trait ParsesOpenAiSse
{
    /**
     * Parse an OpenAI-compatible SSE stream and yield normalized events.
     *
     * @return Generator<int, array{type: string, content: ?string}>
     */
    protected function parseSseStream(Response $response): Generator
    {
        $body = $response->getBody();
        $buffer = '';
        $firstDelta = true;

        while (! $body->eof()) {
            $chunk = $body->read(1024);
            $buffer .= $chunk;

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                $line = trim($line);

                if ($line === 'data: [DONE]') {
                    yield ['type' => 'done', 'content' => null];

                    continue;
                }

                if ($line === '' || ! str_starts_with($line, 'data: ')) {
                    continue;
                }

                $data = json_decode(substr($line, 6), true);

                if ($data && isset($data['choices'][0]['delta']['content'])) {
                    $content = $data['choices'][0]['delta']['content'];

                    // Check the first delta for API error patterns
                    if ($firstDelta) {
                        $firstDelta = false;

                        if (ChatProviderException::isErrorResponse($content)) {
                            throw ChatProviderException::fromErrorResponse($content);
                        }
                    }

                    yield ['type' => 'delta', 'content' => $content];
                }
            }
        }

        // Handle non-streaming fallback (bridge may return full JSON)
        if ($buffer) {
            $data = json_decode(trim($buffer), true);

            if ($data && isset($data['choices'][0]['message']['content'])) {
                $content = $data['choices'][0]['message']['content'];

                if ($content) {
                    if (ChatProviderException::isErrorResponse($content)) {
                        throw ChatProviderException::fromErrorResponse($content);
                    }

                    yield ['type' => 'delta', 'content' => $content];
                    yield ['type' => 'done', 'content' => null];
                }
            }
        }
    }
}
