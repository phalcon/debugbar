<?php

/**
 * This file is part of the Phalcon Framework.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Phalcon\DebugBar;

/**
 * Central home for the phpdoc array-shape aliases shared across the debug bar.
 * Pull what you need with `@phpstan-import-type <name> from \Phalcon\DebugBar\DebugBarTypes`.
 *
 * @phpstan-type list_row array{label: string, message: string}
 * @phpstan-type list_panel list<list_row>
 * @phpstan-type grid_panel array<string, scalar>
 * @phpstan-type exception_row array{label: string, message: string, trace: string}
 * @phpstan-type exception_panel list<exception_row>
 * @phpstan-type log_row array{label: string, message: string, context: string}
 * @phpstan-type log_panel list<log_row>
 * @phpstan-type widget array{label: string, icon: string, panel: string}
 * @phpstan-type summary_row array{label: string, value: scalar}
 * @phpstan-type collector_summary list<summary_row>
 * @phpstan-type envelope array{panel: mixed, badge: scalar|null, summary?: collector_summary}
 * @phpstan-type list_envelope array{panel: list_panel, badge: scalar|null, summary?: collector_summary}
 * @phpstan-type grid_envelope array{panel: grid_panel, badge: scalar|null, summary?: collector_summary}
 * @phpstan-type exception_envelope array{panel: exception_panel, badge: scalar|null, summary?: collector_summary}
 * @phpstan-type log_envelope array{panel: log_panel, badge: scalar|null, summary?: collector_summary}
 * @phpstan-type payload array{data: array<string, envelope>, meta: array<string, mixed>}
 * @phpstan-type db_query array{sql: string, bindings: array<array-key, mixed>, time: int|float}
 * @phpstan-type database_row array{label: string, message: string, occurrences: int|null}
 * @phpstan-type database_panel list<database_row>
 * @phpstan-type database_envelope array{panel: database_panel, badge: int, summary: collector_summary}
 * @phpstan-type time_measure array{label: string, start: int|float, end: int|float|null}
 * @phpstan-type view_pending array{path: string, start: int|float}
 * @phpstan-type view_render array{path: string, time: int|float}
 * @phpstan-type request_context array{0: string|null, 1: bool}
 * @phpstan-type provider_config array{
 *     env?: array{var?: string, blocked?: list<string>, strict?: bool},
 *     enabled?: bool,
 *     assets?: array{nonce?: string|null},
 *     access?: array{allow_ips?: list<string>, callback?: (\Closure(): bool)|null},
 *     collectors?: array<string, bool>,
 *     headers?: bool,
 *     redact?: array{mask?: list<string>, hidden?: list<string>}
 * }
 */
final class DebugBarTypes
{
}
