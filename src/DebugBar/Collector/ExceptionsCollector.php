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
use Phalcon\DebugBar\Contracts\Subscriber;
use Phalcon\DebugBar\DebugBarTypes;
use Phalcon\Events\EventInterface;
use Phalcon\Events\ManagerInterface;
use Throwable;

use function count;
use function strrpos;
use function substr;

/**
 * Records throwables - automatically via `dispatch:beforeException` (the
 * throwable is only recorded, never swallowed) and manually through the `Debug`
 * facade / DebugBar `addException()`. Each entry keeps a compact summary plus the
 * stack trace; the full report remains the Debug page's job.
 *
 * @phpstan-import-type exception_envelope from DebugBarTypes
 * @phpstan-import-type exception_panel from DebugBarTypes
 */
final class ExceptionsCollector extends AbstractCollector implements ExceptionAware, Subscriber
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
    protected string $panel = 'exceptions';

    /**
     * @var exception_panel
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
            'trace'   => $throwable->getTraceAsString(),
        ];
    }

    /**
     * @return exception_envelope
     */
    public function collect(): array
    {
        return [
            'panel' => $this->exceptions,
            'badge' => count($this->exceptions),
        ];
    }

    /**
     * Auto-captures dispatch exceptions. The throwable is recorded and left to
     * bubble - the bar never handles or swallows it.
     *
     * @param ManagerInterface $eventsManager
     *
     * @return void
     */
    public function subscribe(ManagerInterface $eventsManager): void
    {
        $eventsManager->attach(
            'dispatch:beforeException',
            function (EventInterface $event, mixed $dispatcher, mixed $exception): void {
                if ($exception instanceof Throwable) {
                    $this->addThrowable($exception);
                }
            }
        );
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
