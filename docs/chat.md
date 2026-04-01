# Chat - Real-Time Streaming Pipeline

## Overview

The Chat subsystem provides a queued job that streams AI agent responses over private WebSocket channels. It supports custom agents, slash commands, cancellation, and automatic conversation summarization.

## ProcessChatMessage

**Namespace:** `BrunoCFalcao\AiBridge\Chat\ProcessChatMessage`
**Implements:** `ShouldQueue`
**Queue:** Configurable via `config('ai-bridge.chat.queue')`, defaults to `'chat'`
**Timeout:** 1800 seconds (30 minutes)
**Retries:** 3 with backoff `[30, 120, 300]` seconds

### Constructor

```php
new ProcessChatMessage(
    userId: 1,
    message: 'Explain quantum computing',
    conversationId: 'uuid-here',           // null for new conversations
    providerArray: ['anthropic' => 'claude-sonnet-4-6'],
    providerKeys: ['anthropic' => 'sk-...'],  // injected into config at runtime
    embeddingConfig: [],                    // optional embedding provider config
    agentClass: MyCustomAgent::class,       // null for default agent
    agentConfig: ['key' => 'value'],        // passed to agent->configure()
);
```

### Lifecycle

1. **User resolution** -- Finds user via `config('auth.providers.users.model')::find($userId)`.
2. **Key restoration** -- Injects `providerKeys` into `config('ai.providers.{provider}.key')` so the AI SDK can authenticate.
3. **Agent resolution** -- If `agentClass` is set, instantiates it and calls `configure($agentConfig)` if the method exists. Otherwise creates a default agent with `agent(instructions: config('ai-bridge.chat.default_instructions'))`.
4. **Slash command handling** -- If the agent has `setCurrentMessage()` and the message starts with `/`, strips the command prefix from the user-visible message (the agent handles command routing internally).
5. **Streaming** -- Calls `$agent->continue($conversationId, as: $user)->stream($message, provider: $providerArray)`.
6. **Broadcasting** -- Emits `ChatInit` with conversation title, then `ChatDelta` for each text fragment. After tool call results, injects a `"\n\n"` separator before the next text.
7. **Cancellation** -- Checks `Cache::has("chat:cancel:{userId}:{conversationId}")` on each iteration. If set, inserts a "Chat stopped" sentinel message and breaks.
8. **Completion** -- Broadcasts `ChatComplete`.
9. **Summarization** -- Dispatches `ConversationSummarizer::summarizeIfNeeded()` on the `'default'` queue using cheap models.
10. **Error handling** -- Catches all exceptions, broadcasts `ChatError` with a user-friendly message.

### Cancellation

To cancel a running chat from the host app:

```php
Cache::put("chat:cancel:{$userId}:{$conversationId}", true, 60);
```

### Resilient Broadcasting

All broadcast calls retry up to 3 times with 200ms/400ms/600ms backoff on `RedisException`.

## ConversationSummarizer

**Namespace:** `BrunoCFalcao\AiBridge\Chat\ConversationSummarizer`

### `summarizeIfNeeded(string $conversationId, int $maxMessages, array $providerArray): void`

When total messages exceed `$maxMessages`:
1. Takes the oldest `(total - maxMessages)` messages.
2. Builds a transcript: `[ROLE]: content\n[TOOLS USED]: tool1, tool2`.
3. If an existing summary exists, prompts the AI to update it. Otherwise creates a new summary.
4. Saves to `Conversation.summary`.

Errors are caught and logged (not thrown) since summarization is non-critical.

### `getSummary(string $conversationId): ?string`

Static helper that returns the summary for a conversation.

## Events

All events extend the abstract `ChatEvent` base class and broadcast on `PrivateChannel("{prefix}.{userId}")` where prefix is `config('ai-bridge.chat.channel_prefix')`.

### ChatEvent (abstract base)

```php
abstract class ChatEvent implements ShouldBroadcastNow
{
    public function __construct(
        public readonly int $userId,
        public readonly string $conversationId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("{$prefix}.{$this->userId}")];
    }
}
```

### ChatInit

Fires at the start of streaming.

```php
new ChatInit(userId: 1, conversationId: 'uuid', title: 'My Chat', type: 'normal')
```

**Payload:** `conversation_id`, `title`, `type`

### ChatDelta

Fires for each streamed text fragment.

```php
new ChatDelta(userId: 1, conversationId: 'uuid', delta: 'Hello')
```

**Payload:** `conversation_id`, `delta`

### ChatComplete

Fires when streaming ends successfully.

**Payload:** `conversation_id`

### ChatError

Fires on any exception during streaming.

```php
new ChatError(userId: 1, conversationId: 'uuid', message: 'Rate limit reached')
```

**Payload:** `conversation_id`, `message`

## Models

### Conversation

**Table:** `ai_conversations` (UUID primary key)

| Column | Type | Description |
|---|---|---|
| `id` | uuid | Primary key |
| `user_id` | FK nullable | Owner |
| `title` | string | Display title |
| `summary` | longText nullable | AI-generated conversation summary |
| `type` | string(50) | Conversation type (default: `'normal'`) |
| `project_id` | bigint nullable | Associated project |
| `cleared_at` | timestamp nullable | Soft-clear timestamp |

**Relationships:** `messages()` hasMany ConversationMessage

### ConversationMessage

**Table:** `ai_conversation_messages` (UUID primary key)

| Column | Type | Description |
|---|---|---|
| `id` | uuid | Primary key |
| `conversation_id` | uuid | Parent conversation |
| `user_id` | FK nullable | Message author |
| `agent` | string | Agent class name |
| `role` | string(25) | `user`, `assistant`, `system` |
| `content` | text nullable | Message text |
| `attachments` | text nullable | File attachments |
| `tool_calls` | text (cast: array) | Tools invoked |
| `tool_results` | text (cast: array) | Tool responses |
| `usage` | text (cast: array) | Token usage |
| `meta` | text (cast: array) | Additional metadata |

**Relationships:** `conversation()` belongsTo Conversation

## Frontend Integration

Listen for events on the user's private channel:

```javascript
Echo.private(`chat.${userId}`)
    .listen('.ChatInit', (e) => {
        // e.conversation_id, e.title, e.type
    })
    .listen('.ChatDelta', (e) => {
        // e.conversation_id, e.delta — append to UI
    })
    .listen('.ChatComplete', (e) => {
        // e.conversation_id — streaming done
    })
    .listen('.ChatError', (e) => {
        // e.conversation_id, e.message — show error
    });
```
