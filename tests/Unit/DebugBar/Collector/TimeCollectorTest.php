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

use Phalcon\DebugBar\Collector\TimeCollector;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\PanelContractTrait;
use ReflectionProperty;

use function array_column;
use function microtime;

final class TimeCollectorTest extends AbstractUnitTestCase
{
    use PanelContractTrait;

    /**
     * @var array<array-key, mixed>
     */
    private array $serverBackup = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->serverBackup = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;

        parent::tearDown();
    }

    public function testBadgeMatchesRequestDurationAndFormat(): void
    {
        $collector = new TimeCollector();

        /**
         * Pin the request start ~1000.0001234s in the past so the reported
         * duration is a stable ~1,000,000.12ms with a non-zero fractional part.
         */
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true) - 1000.0001234;

        $collect = $collector->collect();
        $badge   = $collect['badge'];

        $this->assertSame('Request', $collect['panel'][0]['label']);

        /**
         * Format: digits, optional decimals, then the `ms` suffix. Kills the
         * concat-order and concat-operand-removal mutants and the float badge.
         */
        $this->assertIsString($badge);
        $this->assertMatchesRegularExpression('/^\d+(\.\d+)?ms$/', $badge);

        /**
         * Magnitude: ~1,000,000ms. Kills the arithmetic mutants on the request
         * calculation (`+`, `* 999`, `* 1001`, `/ 1000`) and the requestStart
         * ternary (which would collapse the duration to ~0).
         */
        $this->assertGreaterThan(999500.0, (float) $badge);
        $this->assertLessThan(1000500.0, (float) $badge);

        /**
         * The badge is exactly the Request row's formatted time (both are
         * `round($ms, 2) . 'ms'`). Kills the round-precision, floor/ceil and
         * concat mutants applied to the badge alone.
         */
        $this->assertSame($collect['panel'][0]['message'], $badge);
    }

    public function testMeasureDurationIsFormattedExactly(): void
    {
        $collector = new TimeCollector();

        /**
         * Inject deterministic hrtime nanosecond bounds so the duration is an
         * exact (124456789 - 1000000) / 1e6 = 123.456789ms -> "123.46ms".
         */
        $measures = new ReflectionProperty(TimeCollector::class, 'measures');
        $measures->setAccessible(true);
        $measures->setValue($collector, [
            'alpha' => [
                'label' => 'Alpha',
                'start' => 1000000,
                'end'   => 124456789,
            ],
        ]);

        $row = $collector->collect()['panel'][1];

        $this->assertSame('Alpha', $row['label']);
        $this->assertSame('123.46ms', $row['message']);
    }

    public function testMeasuresAreRecordedAlongsideRequest(): void
    {
        $collector = new TimeCollector();
        $collector->startMeasure('boot', 'Boot');
        $collector->stopMeasure('boot');

        $labels = array_column($collector->collect()['panel'], 'label');

        $this->assertContains('Request', $labels);
        $this->assertContains('Boot', $labels);
    }

    public function testNameAndPanelContract(): void
    {
        $collector = new TimeCollector();
        $collector->startMeasure('x');

        $this->assertSame('time', $collector->getName());
        $this->assertPanelContract($collector);
    }
}
