# Changelog

All notable changes to `phalcon/debugbar` are documented here. The format is based on [Keep a Changelog][keep_a_changelog] and this project adheres to [Semantic Versioning][semantic_versioning].

## [0.2.0](https://github.com/phalcon/debugbar/releases/tag/v0.2.0) (2026-07-13)

### Changed

- `Provider::boot()` no longer throws `CannotUseInProduction` in a blocked or undefined environment; it returns without registering anything, so the bar is safe to boot unconditionally and never takes down the host application.

### Added

- `env.strict` configuration key (default `false`). When `true`, `Provider::boot()` throws `CannotUseInProduction` in a blocked or undefined environment instead of returning silently, restoring the previous fail-fast behavior for those who want it.

### Fixed

### Removed

## [0.1.0](https://github.com/phalcon/debugbar/releases/tag/v0.1.0) (2026-07-13)

### Changed

### Added

- `Security\Redactor` with two tiers - masking the values of sensitive keys and dropping configured "never shown" keys entirely - applied to the request, config, and session collectors, plus `Security\AccessGate` (IP allowlist and optional callback) restricting who receives the bar.
- A static `Debug` facade for manual instrumentation - `Debug::info()`, `Debug::debug()`, `Debug::notice()`, `Debug::warning()`, `Debug::error()`, `Debug::message()`, `Debug::startMeasure()` / `Debug::stopMeasure()`, and `Debug::addException()` - that no-ops when no bar is registered.
- Assets are minified with `matthiasmullie/minify` and injected inline, so the bar carries no external asset dependency.
- Eleven collectors - `version`, `messages`, `time`, `exceptions`, `request`, `route`, `database`, `view`, `cache`, `config`, and `session`. Streamed collectors (database, view, route, cache) read their data off the event source and never resolve services; snapshot collectors read injected objects (`request`, `config`) or PHP state (`session`). Durations are measured with `hrtime()`.
- Migrated the `Phalcon\Debug` exception/error page component (`Debug`, `Dump`, `ReportBuilder`, `HtmlRenderer`, the `Report` value objects, `Contracts`, and the exception classes) out of the framework's `Phalcon\Support\Debug`, with its full test suite carried over green under PHPStan level max, plus the `debug.css` / `debug.js` page assets.
- Per-collector enable/disable, asset, header, environment, access, and redaction (`mask` / `hidden`) configuration on the `Provider`. The bar ships under PHPStan level max with 100% line, method, and class coverage.
- The `database` collector renders each query's bound parameters next to the statement; the `session` collector omits the session id and Phalcon's internal keys (such as the CSRF token).
- The `exceptions` collector auto-captures throwables via `dispatch:beforeException` (in addition to the `Debug` facade) and shows each one's stack trace in a collapsible panel entry.
- The bar's front-end (`debugbar.js` / `debugbar.css`): a tabbed bottom bar with a panel per collector, badges, and a collapse toggle that shrinks it to a corner handle so it never covers the host page's own controls.
- The web debug bar core: a `Provider` that boots the bar against an MVC `Application` purely through its EventsManager - no DI service and no container-specific wiring - and refuses to boot in blocked/production environments. It attaches a `ResponseListener` on `application:beforeSendResponse` that aggregates the collectors and injects the bar into HTML responses, backed by the `DebugBar` aggregator and the `Renderer` / `Injector` / `BarOptions` output pipeline.

### Fixed

### Removed


[keep_a_changelog]: https://keepachangelog.com/en/1.1.0/
[semantic_versioning]: https://semver.org/spec/v2.0.0.html
