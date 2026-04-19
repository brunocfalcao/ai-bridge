# Browser — Pooled Screenshot & Automation

## Overview

The browser subsystem exposes visual screenshot capture (and the plumbing to add
more browser automation later) without spawning a chrome process per call.
Requests are proxied to an external **Playwright sidecar** that keeps a pooled
browser alive and reuses tabs per session.

Two consumers share the same `BrowserSidecarClient`:

- **Laravel AI agent tool** — `BrunoCFalcao\AiBridge\Tools\TakeScreenshot`,
  given to agents alongside the other sandboxed tools.
- **MCP tool** — `capture-screenshot`, registered on `BrowserServer` and served
  at the configurable path `config('ai-bridge.browser.mcp_path')` (default
  `/mcp/browser`).

## Why a sidecar

Every stdio MCP server that launches its own chrome (`@just-every/mcp-screenshot-website-fast`,
`@modelcontextprotocol/server-puppeteer`, etc.) leaks a browser process per
call on long-running hosts. A single long-lived sidecar with a pooled session
manager is cheaper, faster (no cold start), and leak-free.

The reference implementation is Friday's Playwright sidecar
(`friday-browser-sidecar.service`), but any service that speaks the same HTTP
contract will do.

## Sidecar Contract

The client talks to two endpoints:

| Method | Path | Body | Notes |
|---|---|---|---|
| `POST` | `/navigate` | `{ "url": "..." }` | Navigates the pooled session to the URL. |
| `POST` | `/screenshot` | `{ "fullPage": true\|false }` | Returns `{ "base64": "..." }`. |
| `GET` | `/status` | — | Health check used by `isAvailable()`. |

All requests send `X-Session-ID` for per-session browser isolation.

## Configuration

Add the following to your `.env`:

```dotenv
AI_BRIDGE_BROWSER_SIDECAR_URL=http://127.0.0.1:3100
AI_BRIDGE_BROWSER_SESSION_ID=ai-bridge
AI_BRIDGE_BROWSER_TIMEOUT=30
AI_BRIDGE_BROWSER_MCP_PATH=/mcp/browser
```

`config/ai-bridge.php`:

```php
'browser' => [
    'sidecar_url' => env('AI_BRIDGE_BROWSER_SIDECAR_URL', 'http://127.0.0.1:3100'),
    'default_session_id' => env('AI_BRIDGE_BROWSER_SESSION_ID', 'ai-bridge'),
    'timeout' => (int) env('AI_BRIDGE_BROWSER_TIMEOUT', 30),
    'mcp_path' => env('AI_BRIDGE_BROWSER_MCP_PATH', '/mcp/browser'),
],
```

Set `mcp_path` to `null` to skip registering the MCP route entirely.

## Laravel AI Tool

**Class:** `BrunoCFalcao\AiBridge\Tools\TakeScreenshot`
**Constructor:** `(BrowserSidecarClient $client)` — resolved from the container.
**Schema:** `{ url: string (required), full_page: bool (optional), session_id: string (optional) }`

Returned JSON payload:

```json
{
    "url": "https://example.com",
    "mime_type": "image/png",
    "full_page": false,
    "base64": "iVBORw0KGgoAAAANSUhEUgAA..."
}
```

On error, returns `{ "error": "..." }`. Errors include missing `url`, sidecar
transport failures, and empty responses.

Use it like any other Laravel AI tool:

```php
use BrunoCFalcao\AiBridge\Tools\TakeScreenshot;

$agent = Agent::make()
    ->withTools([
        app(TakeScreenshot::class),
    ]);
```

## MCP Tool

**Tool name:** `capture-screenshot`
**Server:** `BrunoCFalcao\AiBridge\Browser\Mcp\BrowserServer`
**Route:** `config('ai-bridge.browser.mcp_path')` (default `/mcp/browser`)
**Annotation:** `IsReadOnly` — tells MCP clients the tool has no side effects.

The MCP tool returns a native MCP `image` content block (not JSON), so
vision-capable models can consume it directly:

```json
{
    "type": "image",
    "data": "iVBORw0KGgoAAAANSUhEUgAA...",
    "mimeType": "image/png"
}
```

### Wiring Claude Code

Swap any leaky screenshot MCP (for example `@just-every/mcp-screenshot-website-fast`)
with an HTTP entry pointing at a Laravel host that loads ai-bridge:

```json
{
    "mcpServers": {
        "browser": {
            "type": "http",
            "url": "http://127.0.0.1:8000/mcp/browser"
        }
    }
}
```

## Session Isolation

Each request carries an `X-Session-ID` header so the sidecar can keep browser
state separated per caller. If the caller does not pass `session_id`, the
client falls back to `config('ai-bridge.browser.default_session_id')`.

Use distinct session IDs to prevent cross-talk between concurrent flows
(for example, different users or different conversations).

## Direct Client Access

You may inject the `BrowserSidecarClient` directly for non-tool code paths:

```php
use BrunoCFalcao\AiBridge\Browser\BrowserSidecarClient;

public function __construct(protected BrowserSidecarClient $browser) {}

public function capture(string $url): string
{
    return $this->browser->screenshot($url, fullPage: true);
}
```

Available methods:

- `screenshot(string $url, bool $fullPage = false, ?string $sessionId = null): string` — returns base64 PNG.
- `isAvailable(?string $sessionId = null): bool` — lightweight `/status` ping.

Both methods throw `RuntimeException` on transport failure, non-2xx responses,
or empty payloads.
