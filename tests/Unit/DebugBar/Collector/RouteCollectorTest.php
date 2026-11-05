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

use Phalcon\DebugBar\Collector\RouteCollector;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\PanelContractTrait;

final class RouteCollectorTest extends AbstractUnitTestCase
{
    use PanelContractTrait;

    public function testNameAndPanelContract(): void
    {
        $collector = new RouteCollector();

        $this->assertSame('route', $collector->getName());
        $this->assertPanelContract($collector);
    }

    public function testRouteClosureIsCalledAtCollectTime(): void
    {
        $collector = new RouteCollector();
        $action    = 'before';

        $collector->setRoute(
            function () use (&$action): array {
                return ['Action' => $action];
            }
        );
        $action = 'after';

        $this->assertSame(['Action' => 'after'], $collector->collect()['panel']);
    }

    public function testRouteValuesAreShownInThePanel(): void
    {
        $collector = new RouteCollector();
        $values    = ['Action' => 'App\Action\GetPage', 'Attributes' => '{"id":5}'];

        $collector->setRoute($values);

        $envelope = $collector->collect();

        $this->assertSame($values, $envelope['panel']);
        $this->assertNull($envelope['badge']);
    }
}
