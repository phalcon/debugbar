# Changelog

All notable changes to `phalcon/debugbar` are documented here. The format is based on [Keep a Changelog][keep_a_changelog] and this project adheres to [Semantic Versioning][semantic_versioning].

## Unreleased

### Added

- `memory` collector for current and peak PHP memory use. Memory opens from an indicator on the right.
- Optional collector summaries show headline metrics above a panel. The database panel shows total queries, duplicate runs and SQL time.
- Request history for each browser, at `GET/DELETE /_debugbar/open`. It is disabled by default and needs an absolute, writable `history.path`.
- `ResponseListener::handle()` injects the bar with no event. [#39](https://github.com/phalcon/debugbar/issues/39)
- `Core` boots the bar with no MVC application and no DI container. [#40](https://github.com/phalcon/debugbar/issues/40)
- `ADR\Provider` boots the bar in `Phalcon\ADR` applications (Phalcon `v5.18.0` or later, or Phalcon 6). [#14](https://github.com/phalcon/debugbar/issues/14)
- The database, view, cache and route collectors take data through public methods, with no events manager. [#44](https://github.com/phalcon/debugbar/issues/44)
- The Database panel shows `Phalcon\DataMapper` queries in ADR applications (Phalcon `v5.19.0` or later, or Phalcon 6). [#16](https://github.com/phalcon/debugbar/issues/16)
- The Route panel shows the Action class and the request attributes in ADR applications. [#45](https://github.com/phalcon/debugbar/issues/45)
- Documentation and tests for the View and Cache panels in ADR applications. [#46](https://github.com/phalcon/debugbar/issues/46)
- `Logger\ExceptionAdapter` feeds the Exceptions panel from the logger in ADR applications. [#47](https://github.com/phalcon/debugbar/issues/47)
- `History\HistoryEndpoint` takes the request and the base URI with no DI container, and `Mvc\HistoryListener` selects the MVC controller. [#41](https://github.com/phalcon/debugbar/issues/41)
- `History\HistoryHandler` lists, reads and clears the request history with no MVC types. [#42](https://github.com/phalcon/debugbar/issues/42)
- Request history in ADR applications, with the `history.base_uri` option. [#43](https://github.com/phalcon/debugbar/issues/43)
- The `debug_page` option shows the debug page for a server error in ADR applications. [#48](https://github.com/phalcon/debugbar/issues/48)
- `Mvc\Bridge` and `ServiceBridge` feed the built-in collectors, which no longer hold framework event names. [#15](https://github.com/phalcon/debugbar/issues/15)
- `DebugBar::addCollector()` subscribes a collector that implements `Contracts\Subscriber`. [#15](https://github.com/phalcon/debugbar/issues/15)
- `Core::createHistory()` creates the request history from the `history` config, and `History\HistoryRecorder` holds its workflow. [#62](https://github.com/phalcon/debugbar/issues/62)
- `Core::getBar()` returns the bar that `boot()` created. [#62](https://github.com/phalcon/debugbar/issues/62)
- `RequestContext` holds the client address and the AJAX and HTTPS flags for each decision, and `Injector::isHtml()` tells if a response is an HTML page. [#62](https://github.com/phalcon/debugbar/issues/62)
- `Security\Redactor::redactUri()` applies the redaction rule to the query of a URI. [#62](https://github.com/phalcon/debugbar/issues/62)
- A shared fixture, `tests/support/assets/DebugBar/panel-contract.json`, makes the PHP and JavaScript tests use the same payload. [#62](https://github.com/phalcon/debugbar/issues/62)
- `redact.unmask` takes patterns for masked keys whose values show, and `Security\Redactor` takes them as an optional third argument. A pattern matches the whole key, and `*` matches zero or more characters. A hidden key stays hidden. [#67](https://github.com/phalcon/debugbar/issues/67)
- The documentation states the effect of a long-lived worker on request history. [#62](https://github.com/phalcon/debugbar/issues/62)
- The documentation states that `access.callback` can run more than once for a request.
- Request history does not set its cookie when output started before the response, so `setcookie()` gives no warning. [#32](https://github.com/phalcon/debugbar/issues/32)
- `history.url` cannot be `/`. The documentation states that the history endpoint wins over an application route with the same path, for each HTTP method. [#32](https://github.com/phalcon/debugbar/issues/32)
- The documentation states that a browser in request history is one history cookie, so all its windows and tabs count as one for `history.max_browsers`. [#32](https://github.com/phalcon/debugbar/issues/32)

### Changed

- **Breaking:** the built-in collectors no longer implement `Contracts\Subscriber` and have no `subscribe()` method. [#15](https://github.com/phalcon/debugbar/issues/15)
- **Breaking:** `Logger\Adapter` and `Logger\ExceptionAdapter` have no constructor argument. They read the active bar each time the logger writes, so you can create them before the bar boots. Replace `new Adapter(Debug::getBar())` with `new Adapter()`. [#62](https://github.com/phalcon/debugbar/issues/62)
- **Breaking:** `LoggerCollector` takes a `Security\Redactor`, and it redacts the log context. [#62](https://github.com/phalcon/debugbar/issues/62)
- `Core::boot()` and `ResponseListener` take one `?History\HistoryRecorder` in place of the history, endpoint and cookie arguments. [#62](https://github.com/phalcon/debugbar/issues/62)
- `History\HistoryEndpoint::CONTROLLER_NAMESPACE` is now `Mvc\HistoryListener::CONTROLLER_NAMESPACE`. [#62](https://github.com/phalcon/debugbar/issues/62)
- The providers validate the `history` config after they check for the events manager, and for the container or the router. An invalid `history.path` does not throw when history cannot run. [#62](https://github.com/phalcon/debugbar/issues/62)
- A collector no longer sends `widget.icon` and `AbstractCollector` has no `$icon` property, because the browser client never read them. `indicator.icon` does not change. [#62](https://github.com/phalcon/debugbar/issues/62)
- `DebugBarTypes` holds the wire contract and the config shapes, and it lists the panel names, so static analysis rejects an unknown panel name. [#62](https://github.com/phalcon/debugbar/issues/62)
- The debug page in ADR applications uses `Injector::isHtml()` and no longer depends on the injection rules of the bar. [#62](https://github.com/phalcon/debugbar/issues/62)
- `DebugBarTypes` lists the icon names as `icon_type`, so static analysis rejects an unknown `indicator.icon`. A test keeps the list equal to the drawings in `debugbar.js`. An indicator with an unknown icon name now shows no icon, not the gears. [#64](https://github.com/phalcon/debugbar/issues/64)
- `redact.mask` and `redact.hidden` also match the word parts of a key. The redactor splits a key at `_`, `-`, `.` and camelCase, so `token` now masks `reset_token` and `accessToken`. Some values that showed before now show `***`. [#67](https://github.com/phalcon/debugbar/issues/67)
- The Time panel opens from an indicator on the right, not from a tab.
- The bar sends `meta.widgets`, so each tab shows the label of its collector and each panel uses its declared type. The `logger` tab now reads "Logs", not "Logger".

### Fixed

- The `request` panel and request history no longer show or store a query value under a masked key in the URI. [#62](https://github.com/phalcon/debugbar/issues/62)
- Escape array keys and property names in the debug page. [#70](https://github.com/phalcon/debugbar/issues/70)
- Mask the `Php-Auth-*` headers and the `pw`, `pwd` and `passwd` keys by default. [#71](https://github.com/phalcon/debugbar/issues/71)
- Redact objects in the log context. [#72](https://github.com/phalcon/debugbar/issues/72)
- Hide the CSRF token in the session panel when the session has a `uniqueId`. [#73](https://github.com/phalcon/debugbar/issues/73)
- `env.blocked` always blocks `production` and `prod`, and ignores case and spaces. [#74](https://github.com/phalcon/debugbar/issues/74)
- Render a string panel as text unless the collector sets the `html` panel type. [#75](https://github.com/phalcon/debugbar/issues/75)
- Inject the bar only into HTML pages that have a `</body>` tag. [#76](https://github.com/phalcon/debugbar/issues/76)
- Update `Content-Length` after injection, and skip compressed responses. [#77](https://github.com/phalcon/debugbar/issues/77)
- Replace invalid UTF-8 with U+FFFD, so the bar and request history keep the data. [#78](https://github.com/phalcon/debugbar/issues/78)
- Evict another browser from request history only after the new request is stored. [#79](https://github.com/phalcon/debugbar/issues/79)
- Escape quotes, invalid UTF-8, the class name and file paths in the debug page. [#80](https://github.com/phalcon/debugbar/issues/80)
- Stop warnings while the debug page renders, and escape its fallback message. [#81](https://github.com/phalcon/debugbar/issues/81)
- Print `*RECURSION*` in `Dump` for an object or an array that contains itself. [#82](https://github.com/phalcon/debugbar/issues/82)
- Print `[uninitialized]` in `Dump`, and skip a `dump()` method that the debug page cannot call. [#83](https://github.com/phalcon/debugbar/issues/83)
- Apply the debug page blacklist to nested keys too. [#84](https://github.com/phalcon/debugbar/issues/84)
- Discard all output buffers that start after `Debug` is created, not only one. [#85](https://github.com/phalcon/debugbar/issues/85)
- Require `phalcon/traits`, and document Phalcon `v5.17.0` as the minimum Phalcon 5 version. [#86](https://github.com/phalcon/debugbar/issues/86)
- Throw `InvalidConfig` and `StorageNotWritable` for history errors, so every exception of the bar implements `DebugBarThrowable`. [#87](https://github.com/phalcon/debugbar/issues/87)
- Stop the request control from showing `[object Object]` when there is no request panel. [#88](https://github.com/phalcon/debugbar/issues/88)
- Enable a history row again when its request fails after another row was clicked. [#89](https://github.com/phalcon/debugbar/issues/89)
- Use the same JSON flags in each panel, show a `Throwable` in a log context, and keep the context when one value cannot be encoded. [#91](https://github.com/phalcon/debugbar/issues/91)
- Keep at most `max_rows` rows (default 1000) in the cache, database, exceptions, logger, messages and view collectors, and show the number of dropped rows. [#92](https://github.com/phalcon/debugbar/issues/92)
- Redact the ADR request attributes in the route panel. [#93](https://github.com/phalcon/debugbar/issues/93)
- Use the name constants of the collectors and the history controller, and inject the `Injector` into the ADR debug page listener. [#94](https://github.com/phalcon/debugbar/issues/94)
- Accept only `true` from the access callback, and leave the response without a bar when the callback or a collector fails. [#96](https://github.com/phalcon/debugbar/issues/96)
- Inline the shipped minified assets instead of minifying them for each response, escape the nonce, and remove `defer` from the inline script. [#97](https://github.com/phalcon/debugbar/issues/97)
- Test HTML writes, the collapse state and `localStorage` errors in the JS tests, and keep every listener in the fake DOM. [#103](https://github.com/phalcon/debugbar/issues/103)

## [0.4.0](https://github.com/phalcon/debugbar/releases/tag/v0.4.0) (2026-07-14)

### Changed

- Decomposed the `Debug\Dump` and `Debug\Renderer\HtmlRenderer` god-methods: `Dump::output()` is now a `formatValue()` dispatcher over per-type formatters, and `HtmlRenderer` is assembly-only, delegating value formatting to `Renderer\ValueDumper` and templates to `Template\TemplateStore`. [#4](https://github.com/phalcon/debugbar/issues/4)
- `ReportBuilder::build()` now takes `(Throwable, ReportOptions, Superglobals)` and no longer reads superglobals directly; `BacktraceItem`'s fragment is now a `CodeFragment`. [#4](https://github.com/phalcon/debugbar/issues/4)

### Added

- `Report\Superglobals` (its `fromGlobals()` is the single `$_REQUEST` / `$_SERVER` boundary), `Report\CodeFragment`, and `Report\ReportOptions` value objects.
- `Template\TemplateStore` with `Contracts\TemplateCatalog` (`HtmlTemplateCatalog`, `DumpTemplateCatalog`), `Renderer\ValueDumper`, and `Debug::setSuperglobals()`. [#4](https://github.com/phalcon/debugbar/issues/4)

### Fixed

- `Dump`'s "[already listed]" method guard no longer leaks across separate top-level dumps; it is now a per-dump set threaded through the recursion instead of instance state. [#4](https://github.com/phalcon/debugbar/issues/4)

### Removed

- `Debug\Traits\TemplateAwareTrait` (replaced by `Template\TemplateStore` composition) and the unused `Debug::$hideDocumentRoot` property. [#4](https://github.com/phalcon/debugbar/issues/4)

## [0.3.0](https://github.com/phalcon/debugbar/releases/tag/v0.3.0) (2026-07-14)

### Changed

### Added

- A `logger` collector and `Phalcon\DebugBar\Logger\Adapter`. Attach the adapter to the application logger and every item logged through `Phalcon\Logger` is captured in the `Logs` panel, separate from the manual `messages` collector. Each entry keeps the log level, message, and PSR-3 context; the context renders as a collapsible, pretty-printed JSON detail. Forwarding runs through the new `DebugBar::addLog()` and no-ops when the collector is disabled. [#6](https://github.com/phalcon/debugbar/issues/6)

### Fixed

### Removed

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
