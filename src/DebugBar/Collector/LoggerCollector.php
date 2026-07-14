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

use Phalcon\DebugBar\Contracts\LoggerAware;
use Phalcon\DebugBar\DebugBarTypes;

use function count;
use function is_string;
use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Accumulates entries captured from a `Phalcon\Logger` through the logger
 * adapter. Distinct from `MessagesCollector`: this is the automatic, level
 * tagged application log, not the manual `Debug::info()`/`debug()`/… messages
 * the developer drops in by hand.
 *
 * Each entry carries the log level, the message, and the PSR-3 context, which
 * the `logs` panel shows as a collapsible detail per row.
 *
 * @phpstan-import-type log_envelope from DebugBarTypes
 * @phpstan-import-type log_panel from DebugBarTypes
 */
final class LoggerCollector extends AbstractCollector implements LoggerAware
{
    public const NAME = 'logger';

    /**
     * @var string
     */
    protected string $icon = 'icon-messages';

    /**
     * @var string
     */
    protected string $label = 'Logs';

    /**
     * @var string
     */
    protected string $panel = 'logs';

    /**
     * @var log_panel
     */
    private array $logs = [];

    /**
     * @param string                  $message
     * @param string                  $level
     * @param array<array-key, mixed> $context
     *
     * @return void
     */
    public function addLog(string $message, string $level, array $context = []): void
    {
        $this->logs[] = [
            'label'   => $level,
            'message' => $message,
            'context' => $this->stringifyContext($context),
        ];
    }

    /**
     * @return log_envelope
     */
    public function collect(): array
    {
        return [
            'panel' => $this->logs,
            'badge' => count($this->logs),
        ];
    }

    /**
     * Renders the PSR-3 context as pretty-printed JSON for the panel's
     * collapsible detail. An empty context yields an empty string, which the
     * renderer shows as a plain (non-collapsible) row.
     *
     * @param array<array-key, mixed> $context
     *
     * @return string
     */
    private function stringifyContext(array $context): string
    {
        if ([] === $context) {
            return '';
        }

        $json = json_encode(
            $context,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return is_string($json) ? $json : '';
    }
}
