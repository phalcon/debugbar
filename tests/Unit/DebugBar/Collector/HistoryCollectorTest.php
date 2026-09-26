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

use Phalcon\DebugBar\Collector\HistoryCollector;
use Phalcon\DebugBar\History\HistoryEndpoint;
use Phalcon\DebugBar\History\HistoryOptions;
use Phalcon\Di\FactoryDefault;
use Phalcon\Http\Request;
use Phalcon\Mvc\Url;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\PanelContractTrait;

final class HistoryCollectorTest extends AbstractUnitTestCase
{
    use PanelContractTrait;

    public function testCollectCarriesTheInternalEndpoint(): void
    {
        $collector = $this->collector();

        $this->assertSame('history', $collector->getName());
        $this->assertSame('history', $collector->getWidget()['panel']);
        $this->assertSame(
            ['panel' => ['url' => '/_debugbar/open'], 'badge' => null],
            $collector->collect()
        );
        $this->assertPanelContract($collector);
    }

    public function testWidgetDoesNotDefineOtherCollectors(): void
    {
        $widget = $this->collector()->getWidget();

        $this->assertArrayNotHasKey('indicator', $widget);
        $this->assertArrayNotHasKey('indicators', $widget);
    }

    private function collector(): HistoryCollector
    {
        $container = new FactoryDefault();
        $url       = new Url();
        $url->setBaseUri('/');
        $container->setShared('request', new Request());
        $container->setShared('url', $url);

        return new HistoryCollector(new HistoryEndpoint(new HistoryOptions(true), $container));
    }
}
