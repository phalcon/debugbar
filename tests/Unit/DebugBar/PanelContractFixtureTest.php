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

namespace Phalcon\Tests\Unit\DebugBar;

use DateTimeImmutable;
use Phalcon\Config\Config;
use Phalcon\DebugBar\Collector\CacheCollector;
use Phalcon\DebugBar\Collector\ConfigCollector;
use Phalcon\DebugBar\Collector\DatabaseCollector;
use Phalcon\DebugBar\Collector\ExceptionsCollector;
use Phalcon\DebugBar\Collector\HistoryCollector;
use Phalcon\DebugBar\Collector\LoggerCollector;
use Phalcon\DebugBar\Collector\MemoryCollector;
use Phalcon\DebugBar\Collector\MessagesCollector;
use Phalcon\DebugBar\Collector\RequestCollector;
use Phalcon\DebugBar\Collector\RouteCollector;
use Phalcon\DebugBar\Collector\TimeCollector;
use Phalcon\DebugBar\Collector\VersionCollector;
use Phalcon\DebugBar\Collector\ViewCollector;
use Phalcon\DebugBar\Contracts\Collector;
use Phalcon\DebugBar\DebugBar;
use Phalcon\DebugBar\History\HistoryEndpoint;
use Phalcon\DebugBar\History\HistoryEntry;
use Phalcon\DebugBar\History\HistoryOptions;
use Phalcon\DebugBar\History\RequestMetadata;
use Phalcon\DebugBar\Security\Redactor;
use Phalcon\Http\RequestInterface;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use RuntimeException;

use function file_put_contents;
use function getenv;
use function json_encode;
use function preg_replace;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * Keeps the fixture that `tests/JavaScript/debugbar.test.js` reads equal to the
 * output of the real collectors and of `HistoryEntry`, so that each suite tests the
 * same contract. The session collector is not in the fixture because it needs an
 * active session, and the `config` panel covers the same grid shape.
 *
 * To accept a change to the contract, refresh the fixture and examine the diff:
 * `DEBUGBAR_REFRESH_PANEL_FIXTURE=1 vendor/bin/phpunit -c resources/phpunit.xml.dist
 * --filter PanelContractFixtureTest`
 */
final class PanelContractFixtureTest extends AbstractUnitTestCase
{
    private const FIXTURE = __DIR__ . '/../../support/assets/DebugBar/panel-contract.json';

    private const REFRESH = 'DEBUGBAR_REFRESH_PANEL_FIXTURE';

    public function testFixtureEqualsTheOutputOfTheCollectors(): void
    {
        $bar = new DebugBar();
        foreach ($this->collectors() as $collector) {
            $bar->addCollector($collector);
        }

        $json = $this->withStableValues(
            json_encode(
                ['payload' => $bar->collect(), 'storedEntry' => $this->storedEntry()],
                JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
            )
        ) . "\n";

        if (false !== getenv(self::REFRESH)) {
            file_put_contents(self::FIXTURE, $json);
        }

        $this->assertStringEqualsFile(self::FIXTURE, $json);
    }

    /**
     * @return list<Collector>
     */
    private function collectors(): array
    {
        $redactor = new Redactor();

        $messages = new MessagesCollector();
        $messages->addMessage('user resolved', 'info');

        $logger = new LoggerCollector($redactor);
        $logger->addLog('user login', 'info', ['user_id' => 7]);
        $logger->addLog('ping', 'debug');

        $exceptions = new ExceptionsCollector();
        $exceptions->addThrowable(new RuntimeException('boom'));

        $time = new TimeCollector();
        $time->startMeasure('render', 'Render');
        $time->stopMeasure('render');

        $database = new DatabaseCollector();
        foreach (['SELECT * FROM orders WHERE id = :id', 'SELECT * FROM orders WHERE id = :id'] as $sql) {
            $database->startQuery();
            $database->finishQuery($sql, ['id' => 42]);
        }
        $database->startQuery();
        $database->finishQuery('SELECT 1');

        $view = new ViewCollector();
        $view->startRender('orders/index');
        $view->finishRender();

        $route = new RouteCollector();
        $route->setRoute(['Action' => 'GetOrders', 'Attributes' => '{"id":"42"}']);

        $cache = new CacheCollector();
        $cache->addOperation('Get', 'orders');

        return [
            new VersionCollector(),
            $messages,
            $logger,
            $exceptions,
            $time,
            new MemoryCollector(),
            $database,
            $view,
            $route,
            $cache,
            new RequestCollector($this->request(), $redactor),
            new ConfigCollector(new Config(['app' => ['name' => 'demo']]), $redactor),
            new HistoryCollector(
                new HistoryEndpoint(
                    new HistoryOptions(true),
                    fn (): ?RequestInterface => null,
                    fn (): string => '/'
                )
            ),
        ];
    }

    private function request(): RequestInterface
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getURI')->willReturn('/orders?page=2');
        $request->method('getQuery')->willReturn(['page' => '2']);
        $request->method('getPost')->willReturn([]);
        $request->method('getHeaders')->willReturn(['Accept' => 'text/html']);

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    private function storedEntry(): array
    {
        return HistoryEntry::create(
            ['data' => [], 'meta' => []],
            new RequestMetadata(
                'GET',
                '/orders?page=2',
                200,
                false,
                new DateTimeImmutable('2026-01-02T03:04:05+00:00')
            ),
            '20260102030405-000000-deadbeef',
            new DateTimeImmutable('2026-01-02T03:04:06+00:00')
        )->toArray();
    }

    /**
     * Replaces the values that change on each run: durations, memory, versions, and
     * the file and the trace of the exception.
     */
    private function withStableValues(string $json): string
    {
        return (string) preg_replace(
            [
                '/"\d+(\.\d+)?ms"/',
                '/"\d+(\.\d+)?(B|KB|MB|GB|TB)"/',
                '/"PHP": "[^"]*"/',
                '/"Phalcon": "[^"]*"/',
                '/boom \([^"]*"/',
                '/"trace": "(?:[^"\\\\]|\\\\.)*"/',
            ],
            [
                '"1ms"',
                '"1MB"',
                '"PHP": "8.0.0"',
                '"Phalcon": "6.0.0"',
                'boom (file.php:1)"',
                '"trace": "#0 {main}"',
            ],
            $json
        );
    }
}
