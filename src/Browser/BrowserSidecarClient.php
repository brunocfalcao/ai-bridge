<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Browser;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class BrowserSidecarClient
{
    public function __construct(
        protected string $baseUrl,
        protected string $defaultSessionId,
        protected int $timeout,
    ) {}

    /**
     * Capture a screenshot of the given URL and return base64-encoded PNG data.
     *
     * Navigates the sidecar's pooled Playwright session to the URL, then
     * requests a PNG screenshot. Session isolation is enforced via the
     * X-Session-ID header on the sidecar.
     */
    public function screenshot(string $url, bool $fullPage = false, ?string $sessionId = null): string
    {
        $client = $this->client($sessionId);

        $navigate = $client->post('/navigate', ['url' => $url]);

        if ($navigate->failed()) {
            throw new RuntimeException("Sidecar navigate failed ({$navigate->status()}): {$navigate->body()}");
        }

        $shot = $client->post('/screenshot', ['fullPage' => $fullPage]);

        if ($shot->failed()) {
            throw new RuntimeException("Sidecar screenshot failed ({$shot->status()}): {$shot->body()}");
        }

        $base64 = $shot->json('base64');

        if (! is_string($base64) || $base64 === '') {
            throw new RuntimeException('Sidecar returned empty screenshot payload.');
        }

        return $base64;
    }

    /**
     * Check sidecar reachability. Returns true if the /status endpoint responds.
     */
    public function isAvailable(?string $sessionId = null): bool
    {
        try {
            return $this->client($sessionId)->get('/status')->successful();
        } catch (Throwable) {
            return false;
        }
    }

    protected function client(?string $sessionId): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->withHeaders(['X-Session-ID' => $sessionId ?: $this->defaultSessionId])
            ->acceptJson();
    }
}
