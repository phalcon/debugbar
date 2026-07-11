# Phalcon DebugBar Documentation

Phalcon DebugBar provides two things from one install:

- **The debug bar** - a status bar injected into your app's HTML with
  per-request diagnostics, extensible via collectors.
- **The debug page** - the migrated `Phalcon\Debug` exception/error page.

The debug page is the framework's exception/error page, migrated out of
`Phalcon\Support\Debug` into this package as `Phalcon\Debug`. The public API is
unchanged, so an existing application moves over by swapping the namespace:

```php
(new Phalcon\Debug())->listen();
```

Documentation is written alongside each implementation phase. See the project
[README](../README.md) for installation and development setup.
