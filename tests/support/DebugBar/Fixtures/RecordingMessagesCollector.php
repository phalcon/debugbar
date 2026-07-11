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
use Phalcon\DebugBar\Contracts\MessageAware;

use function count;

/**
 * A `messages` collector that records what the convenience API delegates to it.
 */
final class RecordingMessagesCollector extends AbstractCollector implements MessageAware
{
    public const NAME = 'messages';

    /**
     * @var string
     */
    protected string $panel = 'list';

    /**
     * @var list<array{message: mixed, label: string}>
     */
    private array $messages = [];

    public function addMessage(mixed $message, string $label): void
    {
        $this->messages[] = [
            'message' => $message,
            'label'   => $label,
        ];
    }

    /**
     * @return array{panel: list<array{message: mixed, label: string}>, badge: scalar|null}
     */
    public function collect(): array
    {
        return [
            'panel' => $this->messages,
            'badge' => count($this->messages),
        ];
    }

    /**
     * @return list<array{message: mixed, label: string}>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }
}
