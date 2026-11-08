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

namespace Phalcon\DebugBar\Collector;

use Phalcon\DebugBar\Contracts\ExceptionAware;
use Phalcon\DebugBar\DebugBarTypes;
use Throwable;

use function count;
use function strrpos;
use function substr;

/**
 * Records throwables that `addThrowable()` receives, from a bridge or through the
 * `Debug` facade / DebugBar `addException()`. Each entry keeps a compact summary
 * plus the stack trace; the full report remains the Debug page's job.
 *
 * @phpstan-import-type exception_envelope from DebugBarTypes
 * @phpstan-import-type exception_panel from DebugBarTypes
 */
final class ExceptionsCollector extends AbstractCollector implements ExceptionAware
{
    use LimitsRows;

    public const NAME = 'exceptions';

    protected string $label = 'Exceptions';

    protected string $panel = 'exceptions';

    /**
     * @var exception_panel
     */
    private array $exceptions = [];

    public function __construct(private readonly int $maxRows = self::DEFAULT_MAX_ROWS)
    {
    }

    public function addThrowable(Throwable $throwable): void
    {
        if (!$this->acceptsRow(count($this->exceptions))) {
            return;
        }

        $this->exceptions[] = [
            'label'   => $this->shortClass($throwable::class),
            'message' => $throwable->getMessage()
                . ' (' . $throwable->getFile() . ':' . $throwable->getLine() . ')',
            'trace'   => $throwable->getTraceAsString(),
        ];
    }

    /**
     * @return exception_envelope
     */
    public function collect(): array
    {
        $rows = $this->exceptions;
        if (0 < $this->droppedRows) {
            $rows[] = $this->droppedRow() + ['trace' => ''];
        }

        return [
            'panel' => $rows,
            'badge' => count($this->exceptions) + $this->droppedRows,
        ];
    }

    private function shortClass(string $class): string
    {
        $position = strrpos($class, '\\');

        return (false === $position) ? $class : substr($class, $position + 1);
    }
}
