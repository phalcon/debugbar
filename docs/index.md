# Phalcon DebugBar Documentation

Phalcon DebugBar provides two things from one install:

- **The debug bar** - a status bar injected into your application's HTML that surfaces per-request diagnostics through collectors.
- **The debug page** - the framework's exception/error page, migrated out of `Phalcon\Support\Debug` into this package as `Phalcon\Debug`.

The bar runs only in development. It refuses to boot when the environment is undefined or blocked, and it renders nothing on non-HTML responses. The bar goes in before the last `</body>` of an HTML page. A response with no `</body>`, such as a file download, an empty response, JSON, or an HTML fragment, does not change. A compressed response (with a `Content-Encoding` header) does not change either. When the bar goes in, a `Content-Length` header gets the new length.

## Requirements

- PHP 8.1 or later.
- A Phalcon runtime: the Phalcon 5 C extension, or the Phalcon 6 PHP implementation (`phalcon/phalcon`). ADR support needs Phalcon `v5.18.0` or later, or Phalcon 6. The Database panel in an ADR application needs Phalcon `v5.19.0` or later, or Phalcon 6.
- `matthiasmullie/minify`, pulled in automatically by Composer. It minifies the bar's CSS and JavaScript before they are inlined.

## Installation

```bash
composer require phalcon/debugbar
```

## The Debug Page

The debug page is `Phalcon\Debug`, migrated from `Phalcon\Support\Debug` with its public API unchanged. An existing application moves over by swapping the namespace:

```php
<?php

use Phalcon\Debug;

(new Debug())->listen();
```

`listen()` does not see the exceptions of an ADR application, because the application catches them first. For an ADR application, see [ADR](#adr).

The remainder of this document covers the debug bar.

## Registering the Debug Bar

In an MVC application, the bar is booted by `Phalcon\DebugBar\Provider`. It takes the MVC application and an optional configuration array. The application must have an events manager set before the bar boots. The provider does not add or modify application routes. For other applications, see [ADR and Non-MVC Applications](#adr-and-non-mvc-applications).

```php
<?php

use Phalcon\DebugBar\Provider;
use Phalcon\Events\Manager as EventsManager;
use Phalcon\Mvc\Application;

$application = new Application($container);
$application->setEventsManager(new EventsManager());

(new Provider($application))->boot();

echo $application->handle($_SERVER['REQUEST_URI'])->getContent();
```

!!! warning "NOTE"

   Outside a permitted environment the bar does nothing: `boot()` returns without registering anything, so it is safe to call unconditionally. Set `env.strict` to `true` to make it throw `Phalcon\DebugBar\Exceptions\CannotUseInProduction` instead. The environment is read from `APP_ENV` (`getenv()`, then `$_ENV`, then `$_SERVER`).

!!! danger "WARNING"

   `production` and `prod` are always blocked. `env.blocked` adds values to them. It cannot remove `production` or `prod`. The compare trims the values and is not case-sensitive, so `Production` and `" prod "` are also blocked.

If the application has no events manager, `boot()` still registers the `Debug` facade but attaches no response listener, so nothing is injected. Set an events manager to enable the bar.

## ADR and Non-MVC Applications

### ADR

`Phalcon\DebugBar\ADR\Provider` boots the bar in a `Phalcon\ADR` application. It is a container service provider, and it takes an optional configuration array. It needs Phalcon `v5.18.0` or later, or Phalcon 6.

```php
<?php

use Phalcon\ADR\Front\AbstractHttpFront;
use Phalcon\Container\Container;
use Phalcon\DebugBar\ADR\Provider as DebugBarProvider;

class AppFront extends AbstractHttpFront
{
    protected function registerProviders(Container $container): void
    {
        parent::registerProviders($container);

        // Register the services of the application here.

        // The debug bar provider must be the last provider.
        (new DebugBarProvider())->provide($container);
    }
}
```

The provider reads the events manager (`Phalcon\Contracts\Events\Manager`) and the request (`Phalcon\Contracts\Http\AttributeRequest`) from the container. It attaches the response listener to `application:afterHandle`. The application fires this event for a successful response and for an error response, and it emits the response after this event.

!!! warning "NOTE"

   Register the provider after all other providers. It resolves the events manager and the request during registration. After that, `extend()` on these services throws `CannotExtendResolved`, and `set()` has no effect. In a blocked environment, the provider resolves no service.

The `database` panel reads the `dm:*` events of `Phalcon\DataMapper\Pdo\Connection`. It needs Phalcon `v5.19.0` or later, or Phalcon 6. A connection fires these events only when it has an events manager. In `registerProviders()`, give the connection the events manager from the container:

```php
<?php

use Phalcon\Contracts\Events\Manager as EventsManager;
use Phalcon\DataMapper\Pdo\Connection;

$container->set(
    Connection::class,
    function () use ($container): Connection {
        $connection = new Connection('mysql:host=localhost;dbname=app', 'user', 'secret');
        $connection->setEventsManager($container->get(EventsManager::class));

        return $connection;
    }
);
```

The panel shows the values that you give to `perform()` and to the `fetch*()` methods. `exec()` and `query()` take no values, so the panel shows only their statements.

The `route` panel shows the Action class and the request attributes. The application gives this data in the `adr:beforeExecuteAction` event, so you do not have to do anything. The panel is empty when a middleware returns a response before the Action runs, and when no route matches the URL.

The `view` and `cache` panels also need the events manager. In `registerProviders()`, give it to the `Phalcon\Mvc\View\Simple` instance and to the `Phalcon\Cache\Cache` instance:

```php
<?php

use Phalcon\Cache\Adapter\Memory;
use Phalcon\Cache\Cache;
use Phalcon\Contracts\Events\Manager as EventsManager;
use Phalcon\Contracts\View\Renderer;
use Phalcon\Mvc\View\Simple;
use Phalcon\Storage\SerializerFactory;

$container->set(
    Renderer::class,
    function () use ($container): Renderer {
        $view = new Simple();
        $view->setViewsDir('/path/to/views/');
        $view->setEventsManager($container->get(EventsManager::class));

        return $view;
    }
);

$container->set(
    Cache::class,
    function () use ($container): Cache {
        $cache = new Cache(new Memory(new SerializerFactory()));
        $cache->setEventsManager($container->get(EventsManager::class));

        return $cache;
    }
);
```

Give the events manager to the cache and not to its adapter. If you give it to both, the `cache` panel shows each operation two times. The panel shows `get()`, `set()`, `has()` and `delete()`. It also shows the keys of `getMultiple()` and `setMultiple()`. It does not show `deleteMultiple()` or `clear()`.

The `exceptions` panel reads the logger. `Phalcon\ADR\ErrorResponder` logs each exception that it handles and fires no event. Add `Phalcon\DebugBar\Logger\ExceptionAdapter` to your logger. The bar cannot do this for you, because `Phalcon\Contracts\Logger\Logger` has no `addAdapter()` method. The bar injects only into HTML responses and the default error response is JSON. To see the panel on an error page, give `ErrorResponder` a responder that returns HTML:

```php
<?php

use Phalcon\ADR\ErrorResponder;
use Phalcon\ADR\Responder\StatusMapper;
use Phalcon\ADR\Responder\ViewResponder;
use Phalcon\Contracts\Logger\Logger as LoggerContract;
use Phalcon\Contracts\View\Renderer;
use Phalcon\DebugBar\Logger\ExceptionAdapter;
use Phalcon\Logger\Adapter\Stream;
use Phalcon\Logger\Logger;

$container->set(
    LoggerContract::class,
    function (): LoggerContract {
        return new Logger(
            'app',
            [
                'main'     => new Stream('/var/log/app.log'),
                'debugbar' => new ExceptionAdapter(),
            ]
        );
    }
);

$container->set(
    ErrorResponder::class,
    function () use ($container): ErrorResponder {
        return new ErrorResponder(
            new ViewResponder($container->get(Renderer::class), new StatusMapper(), 'errors/error'),
            $container->get(LoggerContract::class)
        );
    }
);
```

The adapter reads the active bar each time the logger writes, so you can create the logger before the provider boots the bar. An exception that the logger writes before the boot is lost. The adapter does not change the response and does not keep the exception from the error responder. The panel also shows `RouteNotFound` for a URL with no route, because the error responder logs this too. If the error responder itself fails, the application returns a bare 500 response and logs nothing, so the panel does not show the exception.

The `history` keys turn on request history. You do not have to add a route. Set `history.base_uri` when the application is not at the root of the host, because an ADR application has no `url` service by default. The history URL wins over an application route with the same path, for each HTTP method.

```php
<?php

use Phalcon\DebugBar\ADR\Provider as DebugBarProvider;

(new DebugBarProvider([
    'history' => [
        'enabled'  => true,
        'path'     => dirname(__DIR__) . '/runtime/debugbar',
        'base_uri' => '/',
    ],
]))->provide($container);
```

The provider decorates the router (`Phalcon\Contracts\ADR\Router\Router`) with `extend()`. The history URL goes to `Phalcon\DebugBar\ADR\HistoryAction`, and all other URLs go to the router of the application. The history URL replaces an application route with the same path. The global middleware of the application also runs for this Action. Route middleware does not run.

!!! warning "NOTE"

   Do not resolve the router during registration. `extend()` on a resolved service throws `CannotExtendResolved`. In a blocked environment, the provider does not change the router.

The `debug_page` key shows the debug page (`Phalcon\Debug`) for a server error. It is off by default. The provider reads the exception from the bar, so add `ExceptionAdapter` to your logger.

```php
<?php

use Phalcon\DebugBar\ADR\Provider as DebugBarProvider;

(new DebugBarProvider(['debug_page' => true]))->provide($container);
```

The debug page replaces the content of the response when the status is 500 or higher, the response is an HTML page and the access gate allows the client. The status does not change. A JSON response, an AJAX request and a 404 response keep their content. The bar is not in the debug page. If the debug page fails, the response keeps the error page of the application.

!!! warning "NOTE"

   The debug page shows `$_SERVER`, which can hold passwords and keys. To remove these values, give a configured `Phalcon\Debug` to the provider.

```php
<?php

use Phalcon\Debug;
use Phalcon\DebugBar\ADR\Provider as DebugBarProvider;

$debug = (new Debug())->setBlacklist(['server' => ['DB_PASSWORD', 'APP_CRYPT_SALT']]);

(new DebugBarProvider(['debug_page' => $debug]))->provide($container);
```

These limits apply in an ADR application:

- **One request for each process.** `Phalcon\ADR\Application::handle()` supports a reused request in a long-lived worker, but collectors have no reset method. In a worker, their data grows from request to request. `HistoryCookie::fromGlobals()` also reads only at boot, so history uses the cookie of the first browser for all requests in the worker. The request data of one browser can then appear in the history of another browser.
- **Events manager.** The events manager must implement `Phalcon\Events\ManagerInterface`. The default manager does. A manager that implements only `Phalcon\Contracts\Events\Manager` gets no listener, so the bar is not injected.
- **Empty panels.** The `database`, `view` and `cache` panels get data only when the application gives the events manager to these services. The `exceptions` panel gets data only when the application adds `ExceptionAdapter` to its logger. The provider reads no `config` service, so the `config` panel is empty.

### A Host with No Events Manager

`Phalcon\DebugBar\Core` boots the bar with no framework application. It takes the configuration array. All the arguments of `boot()` are optional: the events manager, the request and the config source. Call `handle()` when you have the response. The response must implement `Phalcon\Http\ResponseInterface`.

```php
<?php

use Phalcon\DebugBar\Core;

$core = new Core(['access' => ['allow_ips' => ['127.0.0.1']]]);
$core->boot(null, $request);

// The application creates the response here.

$core->handle($response);
```

Give a request when you set `access.allow_ips`. With no request the client address is unknown, so the access gate stops the bar.

If the host has an events manager, attach the listener that `boot()` returns to the response event of the host. `boot()` returns `null` when the bar cannot boot. In this example, `host:response` is a placeholder and Phalcon does not fire it. Replace it with the event that your host fires with the response as the event data.

```php
<?php

$listener = $core->boot($eventsManager, $request);
if (null !== $listener) {
    // "host:response" is a placeholder. Replace it with the response event of your host.
    $eventsManager->attach('host:response', $listener);
}
```

To use request history, create the recorder from the `history` keys and give it to `boot()`. `createHistory()` returns `null` when the bar cannot boot or history is off. It does not route the URL: send `history.url` to `Phalcon\DebugBar\History\HistoryHandler` in your host, with `$recorder->history()` and `$core->getAccessGate()`.

```php
<?php

$recorder = $core->createHistory(fn () => $request);
$core->boot(null, $request, null, $recorder);
```

## Configuration

The second argument to `Provider` is a nested array. Every key is optional. `Phalcon\DebugBar\ADR\Provider` and `Phalcon\DebugBar\Core` take the same array as their only argument. `Core` does not read the `debug_page` key.

| Key                | Type                      | Default                  | Purpose                                                          |
|--------------------|---------------------------|--------------------------|------------------------------------------------------------------|
| `access.allow_ips` | `list<string>`            | `[]` (any client)        | Client IP allowlist. Empty allows any address.                   |
| `access.callback`  | `(Closure(): bool)\|null` | `null`                   | Extra gate. When present, it must also return `true`.            |
| `assets.nonce`     | `string\|null`            | `null`                   | CSP nonce stamped on the injected `<style>` and `<script>` tags. |
| `collectors`       | `array<string, bool>`     | all applicable enabled   | Per-collector switch, keyed by collector name.                   |
| `debug_page`       | `bool\|Phalcon\Debug`     | `false`                  | Show the debug page for a server error. Only the ADR provider reads it. |
| `enabled`          | `bool`                    | `true`                   | Master switch. When `false`, `boot()` returns after the gate.    |
| `env.blocked`      | `list<string>`            | `[]`                     | More environment values that block the bar. `production` and `prod` are always blocked. Trimmed, not case-sensitive. |
| `env.strict`       | `bool`                    | `false`                  | When `true`, `boot()` throws in a blocked/undefined environment instead of returning silently. |
| `env.var`          | `string`                  | `APP_ENV`                | Environment variable inspected by the gate.                      |
| `headers`          | `bool`                    | `true`                   | Emit the `X-Debug-Bar` diagnostic header.                        |
| `history.base_uri` | `string`                  | `/`                      | Base URI of an ADR application. The MVC provider reads the `url` service. |
| `history.enabled`  | `bool`                    | `false`                  | Store and browse recent requests for the active browser.         |
| `history.url`      | `string`                  | `/_debugbar/open`        | Internal GET/DELETE endpoint path, relative to the application's base URI. |
| `history.path`     | `string`                  | required when enabled    | Absolute writable storage directory; keep it outside the document root. |
| `history.max_requests` | `int`                 | `100`                    | Maximum stored requests per browser.                             |
| `history.max_browsers` | `int`                 | `10`                     | Maximum browsers with stored requests. A browser is one history cookie, so its windows and tabs count as one. The least recently updated is evicted first. |
| `history.ttl_seconds` | `int`                  | `86400`                  | Lifetime in seconds; active-browser entries are checked immediately. |
| `redact.hidden`    | `list<string>`            | `[]`                     | Keys dropped from the output entirely.                           |
| `redact.mask`      | `list<string>`            | `[]`                     | Extra keys whose values are masked (added to the defaults).      |
| `redact.unmask`    | `list<string>`            | `[]`                     | Patterns for masked keys whose values show. `*` matches any characters. |

```php
<?php

use Phalcon\DebugBar\Provider;

(new Provider($application, [
    // `production` and `prod` are always blocked. `staging` is added to them.
    'env'        => ['var' => 'APP_ENV', 'blocked' => ['staging']],
    'access'     => ['allow_ips' => ['127.0.0.1', '10.0.0.5']],
    'collectors' => ['cache' => false, 'view' => false],
    'history'    => [
        'enabled'      => true,
        'path'         => dirname(__DIR__) . '/runtime/debugbar',
        'max_requests' => 100,
        'max_browsers' => 10,
        'ttl_seconds'  => 86400,
    ],
    'redact'     => ['mask' => ['iban'], 'hidden' => ['secret_question'], 'unmask' => ['primary_key']],
]))->boot();
```

When history is enabled, the provider registers its internal history controller
in the DI container and selects it on `application:beforeHandleRequest` for
`GET /_debugbar/open` and `DELETE /_debugbar/open`. The controller receives its
storage and access gate directly; no separate history services or routes are
registered. The `url` service is read only when a request is handled, after app
providers and module services have registered their final definitions. If its
base URI is `/app1/` or `https://example.test/app1/`, the endpoint path is
`/app1/_debugbar/open`. The internal controller creates its own response instead
of resolving the application's shared `response` during boot. `history.url` must
start with `/` and cannot contain a scheme, host, query, or fragment. It cannot be
`/`, because the endpoint would replace the home page of the application. The
endpoint wins over an application route with the same path, for each HTTP method,
so use a path that the application does not use.
The endpoint uses the application's normal MVC lifecycle, so
module initialization and application event listeners still run. A GET
without an `id` returns the recent request metadata; `?id=<request-id>` returns
a stored payload. DELETE clears the active browser's stored requests.
Only an actual HTTP `DELETE` is accepted for clearing; request method overrides
such as `_method=DELETE` are rejected.

Dispatcher ACL plugins also see the internal controller. Allow its namespace
before applying application controller rules:

```php
<?php

use Phalcon\DebugBar\Mvc\HistoryListener;

if (HistoryListener::CONTROLLER_NAMESPACE === $dispatcher->getNamespaceName()) {
    return true;
}
```

History endpoint responses include `X-Content-Type-Options: nosniff` and are
excluded from collection, diagnostic headers, HTML injection, and persistence.

The request indicator on the right (search icon, HTTP method, and URI) replaces a
dedicated History tab. It initially identifies the current request. Clicking it
closes any open collector panel and opens the history browser; clicking it again
closes the browser. Opening a collector panel also closes history, so only one
panel is visible at a time. The browser provides refresh and clear controls.
Selecting a stored request closes history, replaces the complete collector payload,
and updates the controls, request time, current memory usage, method, and URI
without navigating away from the page. The collector panel that was open before
History is reopened with the selected request's data. If request metadata is unavailable, the
history control uses `History` as its fallback label. An empty history displays
`No stored requests` and leaves the clear control disabled.

Request history is disabled by default. When enabled, it can still be switched
off through `collectors => ['history' => false]`; in that case no endpoint or storage is
registered. `history.path` must be an absolute path, configured explicitly, and
writable by the web-server user. The provider creates the directory when needed
and verifies its writability during boot, after the environment and collector
gates allow History to start. Storage is isolated by a SHA-256 hash
of a random, HttpOnly, SameSite=Lax debug bar cookie. The cookie is also marked
Secure on HTTPS and lasts for the browser session. It is sent through PHP's native
cookie mechanism so it does not replace cookies queued by the application. The bar
never reads, starts, or changes the application's PHP session, so session ID
regeneration does not hide earlier requests. The first allowed response for a
browser sets the cookie but is not stored; storage begins with the next request
carrying that cookie. If output starts before the response, the bar cannot set the
history cookie, and request history does not start for that browser. Closing the browser discards the identity while its files
remain eligible for cleanup until `history.ttl_seconds` expires. Clients that do
not retain cookies never create storage directories. The number of browser
directories is capped by `history.max_browsers`; creation is serialized and evicts
the least recently updated browser directory to admit another identity. The bar
evicts only after the first request of the new identity is stored, so a request
that cannot be stored does not remove the history of another browser.
This bounds forged or repeatedly rotated cookie identities even when the access
allowlist is empty. A browser here is one history cookie. All the windows and
tabs of a browser share the cookie, so they count as one browser. A private
window gets its own cookie, so it counts as another browser. Each stored
entry distinguishes the request start time (`requested_at`) from the time it was
persisted (`stored_at`). If the server does not expose `REQUEST_TIME_FLOAT`, the
persistence time is used for both values. Each payload has a small metadata sidecar,
so listing requests does not read the full collector payload. If a sidecar is
missing, the versioned payload is used as a fallback; incompatible stored payloads
are rejected. Reads clean expired entries only from the active browser so opening the browser remains
fast on network filesystems. A rate-limited collection during request storage
removes expired entries, abandoned temporary files, and empty directories from all
browsers at most once per hour (or once per configured TTL when it is shorter).
For a debug host reachable by other users, restrict History with
`access.allow_ips` and/or `access.callback`.

## Collectors

Collectors contribute data to the bar. Most renderable collectors appear as tabs.
Time and Memory are enabled by default and appear once as compact indicators on
the right; clicking an indicator opens or closes its collector panel. Their
placement does not depend on request history.
A collector reads its data in one of four ways:

- **Snapshot** - reads state when the response is assembled.
- **Streamed** - fed from framework events by a bridge, and accumulates as they fire.
- **Manual** - fed through the `Debug` facade.
- **Adapter** - captured from a `Phalcon\Logger` through the debug bar's logger adapter.

| Name         | Source                                                   | Inflow            |
|--------------|----------------------------------------------------------|-------------------|
| `cache`      | Cache operations and their keys                          | streamed          |
| `config`     | The `config` service, flattened and redacted             | snapshot          |
| `database`   | SQL statements, bindings, timings, and query summary     | streamed          |
| `exceptions` | Throwables, with stack traces                            | manual + streamed |
| `logger`     | Log entries captured from a `Phalcon\Logger` adapter     | adapter           |
| `memory`     | Current and peak memory used by the PHP request            | snapshot          |
| `messages`   | Messages recorded through the facade                     | manual            |
| `request`    | Request method, URI, query, post, and headers (redacted) | snapshot          |
| `route`      | Matched module, controller, action, and parameters       | streamed          |
| `session`    | `$_SESSION`, flattened and redacted                      | snapshot          |
| `version`    | PHP and Phalcon versions                                 | snapshot          |
| `time`       | Total request time and named measures                    | manual + snapshot |
| `view`       | Rendered view paths and timings                          | streamed          |

The bridges read the data off the event source (the connection, view, router, or cache handed to them by the event). They never resolve a service from the container. `Phalcon\DebugBar\ServiceBridge` handles the `db:*`, `view:*` and `cache:*` events in all applications. `Phalcon\DebugBar\Mvc\Bridge` and `Phalcon\DebugBar\ADR\Bridge` handle the events of their application type.

## Custom Collectors

To add a panel, extend `Phalcon\DebugBar\Collector\AbstractCollector` and set `NAME`, `$label` and `$panel`. `collect()` returns the data of the panel and the badge. To get data from events, also implement `Phalcon\DebugBar\Contracts\Subscriber` and attach your listeners in `subscribe()`.

!!! danger "WARNING"

   The `html` panel type puts the string of the panel into the page as HTML, with no escaping. Use it only for HTML that your collector builds, and escape each value that comes from the request, the session, or the database, for example with `htmlspecialchars()`. Request history shows the panel again later. No other panel type puts HTML into the page. A collector that declares no panel type (it does not implement `Phalcon\DebugBar\Contracts\Renderable`) shows a string panel as text.

```php
<?php

use Phalcon\DebugBar\Collector\AbstractCollector;
use Phalcon\DebugBar\Contracts\Subscriber;
use Phalcon\Events\EventInterface;
use Phalcon\Events\ManagerInterface;

final class OrdersCollector extends AbstractCollector implements Subscriber
{
    public const NAME = 'orders';

    protected string $label = 'Orders';

    protected string $panel = 'list';

    /**
     * @var list<array{label: string, message: string}>
     */
    private array $rows = [];

    public function collect(): array
    {
        return ['panel' => $this->rows, 'badge' => count($this->rows)];
    }

    public function subscribe(ManagerInterface $eventsManager): void
    {
        $eventsManager->attach(
            'orders:afterCreate',
            function (EventInterface $event, mixed $source, mixed $id): void {
                $this->rows[] = ['label' => 'Created', 'message' => is_string($id) ? $id : ''];
            }
        );
    }
}
```

Add the collector after the provider boots and before the response event. `getBar()` returns `null` when the bar did not boot, for example in a blocked environment.

```php
<?php

use Phalcon\DebugBar\Debug;

Debug::getBar()?->addCollector(new OrdersCollector());
```

`addCollector()` calls `subscribe()` when the bar has an events manager. A collector with no events does not implement `Subscriber`, and you add it the same way. A collector with the name of a built-in collector replaces it. The bridges do not feed the replacement, so it must attach its own listeners.

The bar does not redact the data of a custom collector. Run sensitive data through `Phalcon\DebugBar\Security\Redactor` in the collector. The collector does not receive the `redact.mask`, `redact.hidden` and `redact.unmask` values, so give them to its `Redactor`.

## Sharing the Events Manager

Streamed collectors only receive events from components that fire on the same events manager the bar is subscribed to. Phalcon components fire events only on an events manager set on them explicitly; they do not read one from the container. Register a single events manager as a shared service and hand it to the application and to each component whose events you want.

```php
<?php

use Phalcon\Events\Manager as EventsManager;

$container->setShared('eventsManager', function () {
    return new EventsManager();
});

// On the application, so the bar can attach its response listener.
$application->setEventsManager($container->getShared('eventsManager'));

// On each component whose events feed a collector.
$connection->setEventsManager($container->getShared('eventsManager')); // database
$view->setEventsManager($container->getShared('eventsManager'));       // view
$router->setEventsManager($container->getShared('eventsManager'));     // route
$dispatcher->setEventsManager($container->getShared('eventsManager')); // exceptions
```

A collector whose component does not share the events manager reports nothing. Snapshot and manual collectors are unaffected.

## Manual Instrumentation

`Phalcon\DebugBar\Debug` is a static facade. It forwards to the active bar and no-ops when the bar was never registered, so instrumentation left in the code is safe in any environment.

```php
<?php

use Phalcon\DebugBar\Debug;

Debug::message('user resolved', 'auth');
Debug::warning($payload);

Debug::startMeasure('render');
// ... work ...
Debug::stopMeasure('render');

try {
    $service->charge($order);
} catch (\Throwable $exception) {
    Debug::addException($exception);
}
```

| Method                                                      | Collector    | Effect                                         |
|-------------------------------------------------------------|--------------|------------------------------------------------|
| `addException(\Throwable $throwable)`                       | `exceptions` | Records a throwable and its trace.             |
| `info()` / `debug()` / `notice()` / `warning()` / `error()` | `messages`   | Record one message per argument at that level. |
| `message(mixed $value, string $label = 'info')`             | `messages`   | Records a labelled message.                    |
| `startMeasure(string $name, ?string $label = null)`         | `time`       | Opens a named measure.                         |
| `stopMeasure(string $name)`                                 | `time`       | Closes the named measure.                      |

The `exceptions` collector also captures throwables automatically. In an MVC application, `Phalcon\DebugBar\Mvc\Bridge` listens to `dispatch:beforeException`, records the throwable, and leaves it to bubble - the bar never handles or swallows it. A throwable appears in the panel only when the request still finishes with a rendered response; a caught exception, or one the application forwards to an error page, qualifies, while a fully unhandled one aborts before the bar renders.

## Capturing Logs

`Phalcon\DebugBar\Logger\Adapter` is a `Phalcon\Logger` adapter. Attach it to the application logger and every logged item is captured in the bar's `logger` collector, shown in the `Logs` panel. This is separate from the `messages` collector: `logger` is the automatic application log, while `messages` is what the code records by hand through the `Debug` facade.

- Phalcon 5 (the C extension) exposes the `Phalcon\Logger` adapter contract the adapter builds on.
- Phalcon 6 (`phalcon/phalcon`) exposes the same contract. The adapter attaches the same way on either runtime.

The adapter forwards each `Phalcon\Logger\Item` to the active bar through `Phalcon\DebugBar\Debug::getBar()`, reading the item's level name, message, and PSR-3 context. Forwarding no-ops when the `logger` collector is absent or disabled, so the adapter is safe to leave attached. The adapter reads the active bar each time the logger writes, so you can attach it before the bar boots. Items that the logger writes before the boot are lost.

```php
<?php

use Phalcon\DebugBar\Logger\Adapter;
use Phalcon\Logger\Adapter\Stream;
use Phalcon\Logger\Logger;

$logger = new Logger('messages', ['main' => new Stream('/var/log/app.log')]);
$logger->addAdapter('debugbar', new Adapter());

$logger->info('user login', ['user_id' => 42]);
$logger->error('payment declined', ['order_id' => 1001]);
```

Each entry keeps its log level as the label and its message beside it. An entry logged with PSR-3 context becomes collapsible: the level and message stay visible and expand to the context, rendered as pretty-printed JSON. An entry logged without context renders as a plain row. The context is redacted. Objects in the context are changed to arrays first, the same way `json_encode()` writes them, so their keys are redacted too.

The `logger` collector is registered by default. Disable it, like any collector, through the `collectors` key:

```php
<?php

use Phalcon\DebugBar\Provider;

(new Provider($application, [
    'collectors' => ['logger' => false],
]))->boot();
```

## Redaction and Access Control

`Phalcon\DebugBar\Security\Redactor` guards data before it leaves PHP. It matches keys case-insensitively through nested arrays and applies two tiers:

- **Masked** keys keep their key but replace the value with `***`.
- **Hidden** keys are dropped entirely.

A key matches an entry when it has the same name, or when it contains the word parts of the entry next to each other. The redactor splits a name into word parts at `_`, `-`, `.` and camelCase. For example, `token` matches `reset_token` and `accessToken`, and `card_number` matches `customer_card_number`. `token` does not match `tokens`, and `key` does not match `monkey`.

The default masked keys are `authorization`, `cookie`, `csrf`, `key`, `passwd`, `password`, `php-auth`, `pw`, `pwd`, `secret`, and `token`. `php-auth` masks the `Php-Auth-User`, `Php-Auth-Pw` and `Php-Auth-Digest` headers that Phalcon adds for Basic and Digest auth. Add other keys to `redact.mask`, and keys to drop to `redact.hidden`.

A word part can mask a key that is not sensitive, such as `primary_key`. To show its value, add a pattern to `redact.unmask`. A pattern must match the whole key, and the match is not case-sensitive. `*` matches zero or more characters, and each other character matches only itself. For example, `card` matches only `card`, `card*` also matches `card_number`, and `*card*` also matches `customer_card_number` and `discard`. A pattern does not use word parts. A hidden key stays hidden, even when a pattern matches it. A pattern of `*` alone shows all masked values.

The `request`, `config`, `session`, and `logger` collectors run their data through the redactor. The `request` panel and request history also redact the query of the URI. When the redactor changes a value in a query, it rebuilds the query, so the encoding of the other values can change. The `session` collector also omits the raw session id and every `$PHALCON/*` internal key, including the CSRF token. This also applies when the session manager has a `uniqueId`, which adds `uniqueId#` before each key.

The `database` panel and request history show and store query bindings as they are, because generated names such as `:APL0` do not match a key. The redactor does not change log messages, exception messages, or stack traces.

`Phalcon\DebugBar\Security\AccessGate` decides who receives the bar within an allowed environment. An empty IP allowlist admits any client. When both an allowlist and a callback are given, both must pass. The callback can run more than once for a request, for example for a server error in an ADR application with `debug_page` on. Do not put side effects in the callback.

```php
<?php

use Phalcon\DebugBar\Provider;

(new Provider($application, [
    'access' => [
        'allow_ips' => ['127.0.0.1'],
        'callback'  => fn (): bool => 'admin' === ($_SESSION['role'] ?? null),
    ],
    'redact' => ['hidden' => ['card_number']],
]))->boot();
```

## The Bar in the Browser

The bar's CSS and JavaScript are minified and injected inline; the bar has no external asset to host or serve. On CSP-restricted pages, set `assets.nonce` so the inline tags carry a nonce.

The bar sits at the bottom of the page. Interactive collectors appear as tabs on
the left; a tab shows a badge when the collector reports a count or summary value.
Time and Memory appear as compact indicators on the right instead of duplicate tabs.
Their indicators remain available whether History is enabled or disabled.
Each participating collector declares its own indicator and semantic value path;
the History collector does not depend on the Time or Memory payload shape.
The `icon` of an indicator must be `clock`, `cogs`, or `search`. With any other name,
the indicator shows its value and no icon.
The rightmost control combines
a search icon with the current HTTP method and URI; it opens or closes request
history instead of using a dedicated History tab. The history browser and collector
panels are mutually exclusive, so opening either closes the other. Selecting a
stored request closes history and updates the entire bar with that request's data.

Interactive tabs use the following panel types:

- **grid** panels (version, request, config, session, route, memory) render a key and value table.
- **list** panels (time, messages, database, view, cache) render labelled rows.
- **exceptions** panels render one collapsible entry per throwable; the summary line stays visible and expands to the stack trace.
- **logs** panels (logger) render one entry per logged item; an entry with context is collapsible - the level and message stay visible and expand to the context.
- **code** panels render text in a `<pre>` block. A string panel with no panel type renders as `code`.
- **html** panels put a string into the page as trusted HTML. Only custom collectors use this type. See [Custom Collectors](#custom-collectors).

A byte that is not valid UTF-8 shows as the replacement character `�` (U+FFFD). The other data of the request still shows, and request history still stores it.

Time and Memory open their panels from the indicators on the right.

Collectors may expose a summary above their panel. The database collector reports the total query count, duplicate runs, and accumulated SQL time. A duplicate run is each execution of a normalized statement after its first execution: running the same statement three times contributes two duplicate runs. These metrics remain visible with zero values when no queries are executed. Duplicate detection deliberately ignores binding values; repeated statements are highlighted and show their total execution count.

A handle at the right of the tab row collapses the whole bar to a corner button, so it never covers the host page's own controls. The collapsed state is remembered across page loads.
