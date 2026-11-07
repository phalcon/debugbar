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
use Phalcon\DebugBar\Json;
use Phalcon\DebugBar\Security\Redactor;

use function count;
use function is_array;
use function is_string;
use function json_decode;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Accumulates entries captured from a `Phalcon\Logger` through the logger
 * adapter. Distinct from `MessagesCollector`: this is the automatic, level
 * tagged application log, not the manual `Debug::info()`/`debug()`/… messages
 * the developer drops in by hand.
 *
 * Each entry carries the log level, the message, and the redacted PSR-3 context,
 * which the `logs` panel shows as a collapsible detail per row.
 *
 * @phpstan-import-type log_envelope from DebugBarTypes
 * @phpstan-import-type log_panel from DebugBarTypes
 */
final class LoggerCollector extends AbstractCollector implements LoggerAware
{
    public const NAME = 'logger';

    protected string $label = 'Logs';

    protected string $panel = 'logs';

    /**
     * @var log_panel
     */
    private array $logs = [];

    public function __construct(private readonly Redactor $redactor)
    {
    }

    /**
     * @param array<array-key, mixed> $context
     */
    public function addLog(string $message, string $level, array $context = []): void
    {
        $this->logs[] = [
            'label'   => $level,
            'message' => $message,
            'context' => $this->stringifyContext($this->redactor->redact($this->normalize($context))),
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
     * Changes the objects in the context to arrays, as `json_encode()` writes
     * them, so that the redactor sees their keys. A context that cannot be
     * encoded becomes an empty array.
     *
     * @param array<array-key, mixed> $context
     *
     * @return array<array-key, mixed>
     */
    private function normalize(array $context): array
    {
        $decoded = json_decode((string) Json::encode($context), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Renders the PSR-3 context as pretty-printed JSON for the panel's
     * collapsible detail. An empty context yields an empty string, which the
     * renderer shows as a plain (non-collapsible) row.
     *
     * @param array<array-key, mixed> $context
     */
    private function stringifyContext(array $context): string
    {
        if ([] === $context) {
            return '';
        }

        $json = Json::encode(
            $context,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return is_string($json) ? $json : '';
    }
}
