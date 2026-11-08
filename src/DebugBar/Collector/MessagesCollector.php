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

use Phalcon\DebugBar\Contracts\MessageAware;
use Phalcon\DebugBar\DebugBarTypes;

use function count;
use function is_scalar;
use function is_string;

/**
 * Accumulates the messages fed through the `Debug` facade (`info`, `debug`,
 * `warning`, `error`, …). A logger adapter for auto-capturing app logs is a
 * separate, optional piece.
 *
 * @phpstan-import-type list_envelope from DebugBarTypes
 * @phpstan-import-type list_panel from DebugBarTypes
 */
final class MessagesCollector extends AbstractCollector implements MessageAware
{
    use EncodesJson;
    use LimitsRows;

    public const NAME = 'messages';

    protected string $label = 'Messages';

    protected string $panel = 'list';

    /**
     * @var list_panel
     */
    private array $messages = [];

    public function __construct(private readonly int $maxRows = self::DEFAULT_MAX_ROWS)
    {
    }

    public function addMessage(mixed $message, string $label): void
    {
        if (!$this->acceptsRow(count($this->messages))) {
            return;
        }

        $this->messages[] = [
            'label'   => $label,
            'message' => $this->valueToString($message),
        ];
    }

    /**
     * @return list_envelope
     */
    public function collect(): array
    {
        $rows = $this->messages;
        if (0 < $this->droppedRows) {
            $rows[] = $this->droppedRow();
        }

        return [
            'panel' => $rows,
            'badge' => count($this->messages) + $this->droppedRows,
        ];
    }

    private function valueToString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return $this->jsonOrEmpty($value);
    }
}
