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

use function count;
use function is_scalar;
use function is_string;
use function json_encode;

/**
 * Accumulates the messages fed through the `Debug` facade (`info`, `debug`,
 * `warning`, `error`, …). A logger adapter for auto-capturing app logs is a
 * separate, optional piece.
 */
final class MessagesCollector extends AbstractCollector implements MessageAware
{
    public const NAME = 'messages';

    /**
     * @var string
     */
    protected string $icon = 'icon-messages';

    /**
     * @var string
     */
    protected string $label = 'Messages';

    /**
     * @var string
     */
    protected string $panel = 'list';

    /**
     * @var list<array{label: string, message: string}>
     */
    private array $messages = [];

    /**
     * @param mixed  $message
     * @param string $label
     *
     * @return void
     */
    public function addMessage(mixed $message, string $label): void
    {
        $this->messages[] = [
            'label'   => $label,
            'message' => $this->stringify($message),
        ];
    }

    /**
     * @return array{panel: list<array{label: string, message: string}>, badge: scalar|null}
     */
    public function collect(): array
    {
        return [
            'panel' => $this->messages,
            'badge' => count($this->messages),
        ];
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    private function stringify(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        $json = json_encode($value);

        return is_string($json) ? $json : '';
    }
}
