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
 * @phpstan-type widget array{label: string, icon: string, panel: string}
 * @phpstan-type envelope array{panel: mixed, badge: scalar|null}
 * @phpstan-type list_envelope array{panel: list_panel, badge: scalar|null}
 * @phpstan-type grid_envelope array{panel: grid_panel, badge: scalar|null}
 * @phpstan-type exception_envelope array{panel: exception_panel, badge: scalar|null}
 * @phpstan-type payload array{data: array<string, envelope>, meta: array<string, mixed>}
 */
final class DebugBarTypes
{
}
