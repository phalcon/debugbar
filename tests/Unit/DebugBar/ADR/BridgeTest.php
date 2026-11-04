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

namespace Phalcon\Tests\Unit\DebugBar\ADR;

use Closure;
use Phalcon\DataMapper\Pdo\Connection;
use Phalcon\DebugBar\ADR\Bridge;
use Phalcon\DebugBar\Collector\DatabaseCollector;
use Phalcon\DebugBar\DebugBar;
use Phalcon\Events\Manager;
use Phalcon\Events\ManagerInterface;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

#[RequiresPhpExtension('pdo_sqlite')]
final class BridgeTest extends AbstractUnitTestCase
{
    /**
     * @return array<string, array{Closure(Connection): mixed}>
     */
    public static function operations(): array
    {
        return [
            'exec'    => [static fn (Connection $connection): mixed => $connection->exec('SELECT 1')],
            'perform' => [static fn (Connection $connection): mixed => $connection->perform('SELECT 1')],
            'query'   => [static fn (Connection $connection): mixed => $connection->query('SELECT 1')],
        ];
    }

    public function testAfterEventWithInvalidValuesShowsOnlyTheStatement(): void
    {
        $manager   = new Manager();
        $collector = $this->collector($manager);

        $manager->fire('dm:afterPerform', $this, ['statement' => 'SELECT 1', 'values' => 'invalid']);

        $this->assertSame('SELECT 1', $collector->collect()['panel'][0]['message']);
    }

    public function testAfterEventWithNoStatementIsIgnored(): void
    {
        $manager   = new Manager();
        $collector = $this->collector($manager);

        $manager->fire('dm:afterPerform', $this, ['values' => []]);
        $manager->fire('dm:afterPerform', $this, ['statement' => 42]);
        $manager->fire('dm:afterPerform', $this);

        $this->assertSame(0, $collector->collect()['badge']);
    }

    public function testBarWithNoDatabaseCollectorAttachesNothing(): void
    {
        $manager = new Manager();

        (new Bridge(new DebugBar()))->subscribe($manager);

        foreach (['Exec', 'Perform', 'Query'] as $operation) {
            $this->assertFalse($manager->hasListeners('dm:before' . $operation));
            $this->assertFalse($manager->hasListeners('dm:after' . $operation));
        }
    }

    public function testFetchAllRecordsOneQuery(): void
    {
        $manager   = new Manager();
        $collector = $this->collector($manager);

        $this->connection($manager)->fetchAll('SELECT :value AS answer', ['value' => 42]);

        $this->assertSame(1, $collector->collect()['badge']);
    }

    public function testPerformShowsTheStatementAndTheBindings(): void
    {
        $manager   = new Manager();
        $collector = $this->collector($manager);

        $this->connection($manager)->perform('SELECT :value', ['value' => 42]);

        $this->assertSame('SELECT :value  {"value":42}', $collector->collect()['panel'][0]['message']);
    }

    /**
     * With no `before` listener the time is the full clock value, not the time of
     * the operation. Each case has a new collector, so no earlier start can hide this.
     *
     * @param Closure(Connection): mixed $operation
     */
    #[DataProvider('operations')]
    public function testRunsAndRecordsTheOperationWithItsTime(Closure $operation): void
    {
        $manager   = new Manager();
        $collector = $this->collector($manager);

        $operation($this->connection($manager));

        $row = $collector->collect()['panel'][0];

        $this->assertSame('SELECT 1', $row['message']);
        $this->assertLessThan(60000.0, (float) $row['label']);
    }

    private function collector(ManagerInterface $manager): DatabaseCollector
    {
        $collector = new DatabaseCollector();

        (new Bridge((new DebugBar())->addCollector($collector)))->subscribe($manager);

        return $collector;
    }

    private function connection(ManagerInterface $manager): Connection
    {
        $connection = new Connection('sqlite::memory:');
        $connection->setEventsManager($manager);

        return $connection;
    }
}
