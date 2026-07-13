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
use Throwable;

use function count;
use function strrpos;
use function substr;

/**
 * Records throwables handed in through the `Debug` facade / DebugBar
 * `addException()`. Keeps a compact list (class + message + location) for the
 * bar; the full report remains the Debug page's job.
 *
 * @phpstan-import-type list_envelope from \Phalcon\DebugBar\DebugBarTypes
 * @phpstan-import-type list_panel from \Phalcon\DebugBar\DebugBarTypes
 */
final class ExceptionsCollector extends AbstractCollector implements ExceptionAware
{
    public const NAME = 'exceptions';

    /**
     * @var string
     */
    protected string $icon = 'icon-exception';

    /**
     * @var string
     */
    protected string $label = 'Exceptions';

    /**
     * @var string
     */
    protected string $panel = 'list';

    /**
     * @var list_panel
     */
    private array $exceptions = [];

    /**
     * @param Throwable $throwable
     *
     * @return void
     */
    public function addThrowable(Throwable $throwable): void
    {
        $this->exceptions[] = [
            'label'   => $this->shortClass($throwable::class),
            'message' => $throwable->getMessage()
                . ' (' . $throwable->getFile() . ':' . $throwable->getLine() . ')',
        ];
    }

    /**
     * @return list_envelope
     */
    public function collect(): array
    {
        return [
            'panel' => $this->exceptions,
            'badge' => count($this->exceptions),
        ];
    }

    /**
     * @param string $class
     *
     * @return string
     */
    private function shortClass(string $class): string
    {
        $position = strrpos($class, '\\');

        return (false === $position) ? $class : substr($class, $position + 1);
    }
}
