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

use Phalcon\DebugBar\Security\Redactor;

use function is_string;
use function session_name;
use function session_status;
use function str_starts_with;

use const PHP_SESSION_ACTIVE;

/**
 * Snapshots the session into a redacted grid. The session manager drives
 * `$_SESSION` for every adapter, so the data and identity are read straight from
 * PHP's session state — nothing is resolved from the container. Reports nothing
 * when no session is active.
 */
final class SessionCollector extends AbstractCollector
{
    use FlattensToGrid;

    public const NAME = 'session';

    /**
     * @var string
     */
    protected string $icon = 'icon-session';

    /**
     * @var string
     */
    protected string $label = 'Session';

    /**
     * @var string
     */
    protected string $panel = 'grid';

    /**
     * @param Redactor $redactor
     */
    public function __construct(private readonly Redactor $redactor)
    {
    }

    /**
     * @return array{panel: array<string, scalar>, badge: scalar|null}
     */
    public function collect(): array
    {
        if (PHP_SESSION_ACTIVE !== session_status()) {
            return [
                'panel' => [],
                'badge' => null,
            ];
        }

        $grid = [
            'Name' => session_name() ?: '',
        ];

        foreach ($this->flatten($this->redactor->redact($this->applicationData())) as $key => $value) {
            $grid['Data.' . $key] = $value;
        }

        return [
            'panel' => $grid,
            'badge' => null,
        ];
    }

    /**
     * Session data with Phalcon's internal keys (such as the CSRF token) removed
     * — those are framework internals, not application state, and must never be
     * shown. The raw session id is likewise omitted (session-hijacking risk).
     *
     * @return array<array-key, mixed>
     */
    private function applicationData(): array
    {
        $data = [];
        foreach ($_SESSION as $key => $value) {
            if (is_string($key) && str_starts_with($key, '$PHALCON/')) {
                continue;
            }

            $data[$key] = $value;
        }

        return $data;
    }
}
