# Changelog

All notable changes to `phalcon/debugbar` are documented here. The format is
based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Migrated the `Phalcon\Debug` exception/error page component (`Debug`, `Dump`,
  `ReportBuilder`, `HtmlRenderer`, the `Report` value objects, `Contracts`, and
  the exception classes) out of the framework's `Phalcon\Support\Debug`, with its
  full test suite carried over green under PHPStan level max, plus the
  `debug.css` / `debug.js` page assets.
