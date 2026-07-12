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

use Phalcon\Config\Config;
use Phalcon\DebugBar\Collector\ConfigCollector;
use Phalcon\DebugBar\Security\Redactor;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\PanelContractTrait;

final class ConfigCollectorTest extends AbstractUnitTestCase
{
    use PanelContractTrait;

    public function testEmptyPanelWhenConfigAbsent(): void
    {
        $collector = new ConfigCollector(null, new Redactor());

        $this->assertSame('config', $collector->getName());
        $this->assertSame([], $collector->collect()['panel']);
        $this->assertPanelContract($collector);
    }

    public function testConfigIsFlattenedAndRedacted(): void
    {
        $config = new Config([
            'app'      => ['name' => 'Phalcon'],
            'database' => ['host' => 'localhost', 'password' => 'hunter2'],
        ]);

        $collector = new ConfigCollector($config, new Redactor());

        $panel = $collector->collect()['panel'];

        $this->assertSame('Phalcon', $panel['app.name']);
        $this->assertSame('localhost', $panel['database.host']);
        $this->assertSame('***', $panel['database.password']);
    }
}
