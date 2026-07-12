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
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

use function session_start;

final class SessionCollectorTest extends AbstractUnitTestCase
{
    use PanelContractTrait;

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testActiveSessionIsSnapshotAndRedacted(): void
    {
        session_start();
        $_SESSION = ['user' => 'nikos', 'password' => 'secret'];

        $panel = (new SessionCollector(new Redactor()))->collect()['panel'];

        $this->assertArrayHasKey('ID', $panel);
        $this->assertSame('nikos', $panel['Data.user']);
        $this->assertSame('***', $panel['Data.password']);
    }

    public function testEmptyPanelWhenNoSession(): void
    {
        $collector = new SessionCollector(new Redactor());

        $this->assertSame('session', $collector->getName());
        $this->assertSame([], $collector->collect()['panel']);
        $this->assertPanelContract($collector);
    }
}
