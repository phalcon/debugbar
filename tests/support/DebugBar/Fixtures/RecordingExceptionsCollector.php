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

namespace Phalcon\Tests\Support\DebugBar\Fixtures;

use Phalcon\DebugBar\Collector\AbstractCollector;
use Phalcon\DebugBar\Contracts\ExceptionAware;
use Throwable;

use function count;

/**
 * An `exceptions` collector that records the throwables the convenience API
 * delegates to it.
 */
final class RecordingExceptionsCollector extends AbstractCollector implements ExceptionAware
{
    public const NAME = 'exceptions';

    /**
     * @var string
     */
    protected string $panel = 'list';

    /**
     * @var list<Throwable>
     */
    private array $throwables = [];

    public function addThrowable(Throwable $throwable): void
    {
        $this->throwables[] = $throwable;
    }

    /**
     * @return array{panel: list<string>, badge: scalar|null}
     */
    public function collect(): array
    {
        $messages = [];
        foreach ($this->throwables as $throwable) {
            $messages[] = $throwable->getMessage();
        }

        return [
            'panel' => $messages,
            'badge' => count($this->throwables),
        ];
    }

    /**
     * @return list<Throwable>
     */
    public function getThrowables(): array
    {
        return $this->throwables;
    }
}
