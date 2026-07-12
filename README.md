# Phalcon DebugBar

[![Latest Version][packagist-version-badge]][packagist-version-link]
[![PHP Version][php-version-badge]][packagist-version-link]
[![Total Downloads][packagist-downloads-badge]][packagist-downloads-link]
[![License][license-badge]][license-link]

[![DebugBar CI][debugbar-ci-badge]][debugbar-ci-link]
[![Quality Gate Status][sonar-quality-badge]][sonar-link]
[![Coverage][sonar-coverage-badge]][sonar-link]
[![PDS Skeleton][pds-skeleton-badge]][pds-skeleton-link]

[![Discord][discord-badge]][discord-link]
[![Contributors][contributors-badge]][contributors-link]

A web debug bar and debugger for the [Phalcon Framework](https://phalcon.io) -
a status bar injected into your application's HTML that surfaces per-request
diagnostics (messages, timing, database queries, request, route, and more),
plus the migrated Phalcon debug/exception page.

> **Status:** under active development. Fork of
> [snowair's Phalcon Debugbar](https://github.com/snowair/phalcon-debugbar),
> rebuilt for Phalcon 5 & 6.

## Requirements

- PHP >= 8.1
- Phalcon 5 (the `ext-phalcon` C extension) **or** Phalcon 6 (the
  `phalcon/phalcon` package)

## Installation

```bash
composer require phalcon/debugbar
```

## Development

This repository mirrors the Phalcon library conventions and ships a Docker dev
environment.

```bash
cp resources/.env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app composer test
```

Useful scripts: `composer test`, `composer analyze`, `composer cs`,
`composer cs-fixer`. Switch the Phalcon major with the `PHALCON_VARIANT`
build arg (`v5` default, or `v6`).

## License

Phalcon DebugBar is open-sourced software licensed under the
[BSD 3-Clause license](LICENSE.txt).

[packagist-version-badge]:   https://img.shields.io/packagist/v/phalcon/debugbar?include_prereleases&style=flat-square&logo=packagist&logoColor=white
[packagist-version-link]:    https://packagist.org/packages/phalcon/debugbar
[packagist-downloads-badge]: https://img.shields.io/packagist/dt/phalcon/debugbar?style=flat-square&logo=packagist&logoColor=white
[packagist-downloads-link]:  https://packagist.org/packages/phalcon/debugbar/stats
[php-version-badge]:          https://img.shields.io/packagist/php-v/phalcon/debugbar?style=flat-square&logo=php&logoColor=white
[license-badge]:             https://img.shields.io/github/license/phalcon/debugbar?style=flat-square&logo=opensourceinitiative&logoColor=white
[license-link]:              https://github.com/phalcon/debugbar/blob/master/LICENSE.txt
[debugbar-ci-badge]:         https://github.com/phalcon/debugbar/actions/workflows/main.yml/badge.svg?branch=master
[debugbar-ci-link]:          https://github.com/phalcon/debugbar/actions/workflows/main.yml
[sonar-quality-badge]:       https://sonarcloud.io/api/project_badges/measure?project=phalcon_debugbar&metric=alert_status
[sonar-coverage-badge]:      https://sonarcloud.io/api/project_badges/measure?project=phalcon_debugbar&metric=coverage
[sonar-link]:                https://sonarcloud.io/summary/new_code?id=phalcon_debugbar
[pds-skeleton-badge]:        https://img.shields.io/badge/pds-skeleton-blue.svg?style=flat-square
[pds-skeleton-link]:         https://github.com/php-pds/skeleton
[discord-badge]:             https://img.shields.io/discord/310910488152375297?label=Discord&logo=discord&style=flat-square
[discord-link]:              https://phalcon.io/discord
[contributors-badge]:        https://img.shields.io/github/contributors/phalcon/debugbar?style=flat-square&logo=github&logoColor=white
[contributors-link]:         https://github.com/phalcon/debugbar/graphs/contributors
