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

use Phalcon\DebugBar\Collector\RequestCollector;
use Phalcon\DebugBar\Security\Redactor;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\PanelContractTrait;
use PHPUnit\Framework\Attributes\BackupGlobals;

#[BackupGlobals(true)]
final class RequestCollectorTest extends AbstractUnitTestCase
{
    use PanelContractTrait;

    public function testNameAndPanelContract(): void
    {
        $collector = new RequestCollector(new Redactor());

        $this->assertSame('request', $collector->getName());
        $this->assertPanelContract($collector);
    }

    public function testRequestDataIsFlattenedAndRedacted(): void
    {
        $_GET                      = ['q' => 'phalcon'];
        $_POST                     = ['username' => 'nikos', 'password' => 'secret-value'];
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $collector = new RequestCollector(new Redactor());

        $panel = $collector->collect()['panel'];

        $this->assertSame('POST', $panel['Method']);
        $this->assertSame('phalcon', $panel['Query.q']);
        $this->assertSame('nikos', $panel['Post.username']);
        $this->assertSame('***', $panel['Post.password']);
    }
}
