# Security

## Credential Encryption

All sensitive fields in `AiApiConfig` use Laravel's `encrypted` cast:
- `api_key`
- `oauth_access_token`
- `oauth_refresh_token`

Values are encrypted at rest in the database and decrypted transparently via Eloquent.

## RunCommand Hardening

The `RunCommand` tool provides shell access to AI agents with multiple security layers:

### 1. Chaining Detection

Commands containing shell chaining operators are rejected before any further processing:

```
;   — command separator
&   — background execution / AND
|   — pipe
`   — backtick subshell
$(  — subshell expansion
```

**Regex:** `/[;&|`]|\$\(/`

### 2. Command Allowlist

Only whitelisted base commands are permitted. The allowlist is configurable via `config('ai-bridge.tools.allowed_commands')`.

### 3. Sudo Restrictions

For `sudo` commands, both the target command and optionally the subcommand are validated against `config('ai-bridge.tools.allowed_sudo_commands')`. Each entry maps a command to either:
- `null` — any subcommand allowed
- `string[]` — only listed subcommands allowed

### 4. Dangerous Pattern Blocking

Commands matching any pattern in `config('ai-bridge.tools.dangerous_patterns')` are rejected. Default patterns include `rm -rf /`, `mkfs`, `dd if=`, etc.

### 5. Timeout Enforcement

Commands are killed after `config('ai-bridge.tools.command_timeout')` seconds (default 30).

### 6. Output Truncation

- stdout: 10KB max
- stderr: 3KB max

## WriteFile Path Traversal Protection

The `WriteFile` tool validates that the target path is within the project boundary BEFORE creating directories:

1. Build the normalized target path from the project root + relative path.
2. Verify the normalized path starts with the project root.
3. Only then create parent directories and write the file.

This prevents path traversal via `../` sequences that could write files outside the project.

## ReadFile / ListDirectory / SearchCode Protection

All filesystem tools use the `ResolvesProjectPath` trait which:
1. Resolves the full path via `realpath()`.
2. Verifies it starts with `realpath($this->projectPath)`.
3. Returns `null` if the path escapes the project boundary.

## MCP Authentication

The `AuthenticateApiKey` middleware protects MCP knowledge endpoints:
1. Extracts the application slug from the URL.
2. Looks up the Application model by slug (must be active, must have `knowledge_connection`).
3. Validates the `Authorization: Bearer` token against the application's `mcp_api_key`.
4. Returns 404 if app not found, 401 if key mismatch.

## Secret Detection

The `SecretDetector` class scans text for 12 types of credentials:

| Type | Pattern Example |
|---|---|
| AWS Access Key | `AKIA...` |
| GitHub Token | `ghp_`, `gho_`, `ghs_`, `github_pat_` |
| Anthropic Key | `sk-ant-` |
| OpenAI Key | `sk-proj-` |
| Stripe Key | `sk_live_`, `sk_test_`, `pk_live_`, `pk_test_` |
| Generic SK Key | `sk-` + 32+ chars |
| Slack Token | `xoxb-`, `xoxp-`, `xoxs-` |
| Bearer Token | `Bearer ey...` / `Authorization: Bearer` |
| Private Key | `-----BEGIN ... PRIVATE KEY-----` |
| JWT Token | `eyJ...` (3 base64 segments) |
| Database URL | `mysql://user:pass@`, `postgres://...`, `mongodb://...` |
| Key-Value Secret | `api_key=`, `password:`, `secret=`, etc. |

### Usage

```php
use BrunoCFalcao\AiBridge\Support\SecretDetector;

$detector = new SecretDetector();

$detector->containsSecrets($text);  // bool
$detector->detect($text);           // ['AWS Access Key', 'OpenAI Key']
```

## OAuth Token Security

- OAuth tokens are stored encrypted in the database.
- Token refresh uses HTTPS with the official Anthropic endpoint.
- Client ID is configurable via environment variable (not hardcoded).
- Expired tokens are auto-refreshed before use.
- The `BearerAnthropic` provider uses `Authorization: Bearer` (standard OAuth pattern) instead of `x-api-key`.
