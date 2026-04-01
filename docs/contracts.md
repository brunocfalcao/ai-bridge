# Contracts

## Overview

The package defines two interfaces that host applications must implement to enable features requiring application/team model access (MCP knowledge server, per-team API configs).

If your application does not use these features, set `config('ai-bridge.models.application')` and `config('ai-bridge.models.team')` to `null`. The package will gracefully skip MCP registration and use safe no-op fallbacks for model relationships.

## ApplicationContract

**Namespace:** `BrunoCFalcao\AiBridge\Contracts\ApplicationContract`

```php
interface ApplicationContract
{
    public function getKey(): mixed;
    public function getAttribute(string $key): mixed;
}
```

### Required Attributes

The following attributes are accessed via `getAttribute()` (standard Eloquent accessor):

| Attribute | Type | Used By | Purpose |
|---|---|---|---|
| `slug` | string | ServiceProvider, AuthenticateApiKey | URL segment for MCP routes |
| `name` | string | ServiceProvider | Human-readable application name |
| `status` | string | AuthenticateApiKey | Must be `'active'` |
| `knowledge_connection` | string | ServiceProvider | DB connection name for knowledge |
| `knowledge_database` | string | ServiceProvider | Database name for knowledge |
| `knowledge_db_username` | string | ServiceProvider | DB username |
| `knowledge_db_password` | string | ServiceProvider | DB password |
| `mcp_api_key` | string | AuthenticateApiKey | Bearer token for MCP authentication |
| `id` | int | ProviderResolver | Primary key |
| `team_id` | int | ProviderResolver | Team foreign key |

### Implementation Example

```php
namespace App\Models;

use BrunoCFalcao\AiBridge\Contracts\ApplicationContract;
use Illuminate\Database\Eloquent\Model;

class Application extends Model implements ApplicationContract
{
    // getKey() and getAttribute() are already provided by Eloquent Model
}
```

### Configuration

```php
// config/ai-bridge.php
'models' => [
    'application' => \App\Models\Application::class,
],
```

## TeamContract

**Namespace:** `BrunoCFalcao\AiBridge\Contracts\TeamContract`

```php
interface TeamContract
{
    public function getKey(): mixed;
}
```

### Implementation Example

```php
namespace App\Models;

use BrunoCFalcao\AiBridge\Contracts\TeamContract;
use Illuminate\Database\Eloquent\Model;

class Team extends Model implements TeamContract
{
    // getKey() is already provided by Eloquent Model
}
```

### Configuration

```php
// config/ai-bridge.php
'models' => [
    'team' => \App\Models\Team::class,
],
```

## Graceful Degradation

When model configs are `null`:

| Feature | Behavior |
|---|---|
| MCP route registration | Skipped entirely (no routes, no DB queries at boot) |
| `AuthenticateApiKey` middleware | Returns 404 (no model to look up) |
| `AiApiConfig::team()` | Returns a self-referencing no-op BelongsTo |
| `AiApiConfig::application()` | Returns a self-referencing no-op BelongsTo |
| `ProviderResolver::resolveForApplication()` | Still works if called with any ApplicationContract implementation |
| `AiResolver` (scope-based) | Fully functional (no model dependency) |
