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

namespace Phalcon\Tests\Unit\DebugBar\Collector;

use Phalcon\DebugBar\Collector\SessionCollector;
use Phalcon\DebugBar\Security\Redactor;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\PanelContractTrait;

use function session_name;
use function session_start;
use function session_status;
use function session_write_close;

use const PHP_SESSION_ACTIVE;

final class SessionCollectorTest extends AbstractUnitTestCase
{
    use PanelContractTrait;

    protected function tearDown(): void
    {
        if (PHP_SESSION_ACTIVE === session_status()) {
            session_write_close();
        }

        $_SESSION = [];

        parent::tearDown();
    }

    public function testActiveSessionIsSnapshotAndRedacted(): void
    {
        session_start(['use_cookies' => false, 'cache_limiter' => '']);
        $_SESSION = [
            '$PHALCON/CSRF$' => 'token-value',
            'user'           => 'sarah-connor',
            'password'       => 'secret',
        ];

        $panel = (new SessionCollector(new Redactor()))->collect()['panel'];

        $this->assertSame(session_name(), $panel['Name']);
        $this->assertSame('sarah-connor', $panel['Data.user']);
        $this->assertSame('***', $panel['Data.password']);
        $this->assertArrayNotHasKey('ID', $panel);
        $this->assertArrayNotHasKey('Data.$PHALCON/CSRF$', $panel);
    }

    public function testEmptyPanelWhenNoSession(): void
    {
        $collector = new SessionCollector(new Redactor());

        $this->assertSame('session', $collector->getName());
        $this->assertSame([], $collector->collect()['panel']);
        $this->assertPanelContract($collector);
    }
}
