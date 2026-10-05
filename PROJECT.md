# packages/brunocfalcao/ai-bridge — Project Guidance

Shared project instructions for Codex and Claude.

## Purpose and boundaries

Named AI connections for Laravel AI: provider/model resolution, failover chains, reasoning effort and embedding identity. Five source files; everything else Laravel AI already does is used directly, not wrapped.

Consumers differ, and that matters before any change. Admin, Feedz and Me symlink this checkout (and share one copy under `/home/development/packages/` in production), so an edit here changes all three at once. `olloma.com` names the package but vendors its own copy from tag v1.4.1, and `friday.test` is still pinned to v1.4.3-p1 with its own migration pending — see `~/Herd/friday.test/refactoring-guidelines.md`. Neither is affected by editing this checkout, but both are affected by what gets published.

## Documentation

Start with `README.md`, `docs/README.md`. No matching canonical `~/Herd/docs/<project>` directory was found. Use local documentation and current source; do not borrow another product’s requirements.

## Working rules

- Address Bruno directly and use concise caveman full communication; code, commits, and PR descriptions remain professional language.
- Read relevant source, callers, configuration, tests, and scoped instructions before editing. Current code wins over unsupported documentation; planned and historical behavior must be labeled.
- Follow the closest working implementation. Keep changes within Bruno's requested product behavior; preserve unrelated dirty work and user data.
- Bruno owns product decisions. The agent owns technical choices. Ask about material product ambiguity, not class names or file placement.
- Never commit, push, deploy, send messages, or perform destructive operations unless the task authorizes them. Never expose credentials.
- Run the smallest relevant existing executable checks. Ordinary implementation defers new coverage to the release pass; authentication, privacy, billing, data integrity, concurrency, and production regressions require immediate coverage. Do not claim browser/device/provider acceptance without observing it.
- Resolve shared `do` workflows from `~/Herd/.dynamic-commands/` freshly. Read applicable global rules and project/nested instructions. Keep handoffs concise and outcome-first.

## Dependency contract

Manifest requirements: PHP `^8.4`, `laravel/framework` `^12.0 || ^13.0`, `laravel/ai` `^1.0`. Exact resolved versions come from `composer.lock`; runtime versions require a fresh check.

## Project constraints

- 2.0.0 (2026-10-05) is a breaking release: the Prism-backed chat providers, the Claude CLI and OpenClaw providers, the knowledge/MCP server, the browser sidecar, the seven agent tools, the conversation and API-config models and their migrations are gone, along with the `prism-php/prism` and `laravel/mcp` dependencies. Do not reintroduce any of them.
- Laravel AI keys its own failover list by provider name, which collapses two models of one provider into a single entry and silently drops the primary. That is why the embedding chain is walked candidate by candidate here instead of being handed to the SDK, and why a text chain reusing one provider with a different model throws. Do not "simplify" either.
- Laravel AI fakes agents by exact class, so `agent()` returns the plain `AnonymousAgent` when only that class is faked. Keep that behaviour; it is what lets host test suites keep passing.
- This is the current named-connection resolver for Laravel AI. Keep provider/model resolution, failover, reasoning options, and embedding identity here; use Laravel AI directly for agents and streaming. Removed Prism/CLI/OpenClaw wrappers are not current interfaces.

## Code map

`src/`, `tests/`. Inspect these entrypoints and their actual callers for the area being changed.

## Release

Published before any consuming application tag: README, a versioned `CHANGELOG.md` entry, an immutable tag and a GitHub Release, through `/do release-owned-package-tag`. Each consumer's `composer.lock` must then record the published commit. Because Admin, Feedz and Me share one server copy, a version they all need is shipped in one window with `quanamo-ship --shipping-together`, Admin first.

## Verification

Available entrypoints: `composer test`, `composer test:full`, `composer quality`. Choose the narrowest relevant check; a listed full-suite, build, install, or packaging command is not an instruction to run it for every edit. Inspect test environment and side effects first.

Orientation examples read: `src/AiBridgeServiceProvider.php`, `tests/Unit/Resolver/AiResolverTest.php`. Re-read the closest example for the actual task rather than copying an unrelated one.

## Keep this file current

Please update `PROJECT.md` whenever project guidelines, architecture, workflows,
or verified constraints change. Correct documentation that is not supported by
current codebase evidence; codebase wins. Distinguish implemented behavior from
plans and historical records. Keep `AGENTS.md` and `CLAUDE.md` as thin pointers
to this shared file so Codex and Claude follow the same project guidance.
