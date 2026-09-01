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

namespace Phalcon\Tests\Unit\DebugBar\History;

use Phalcon\DebugBar\History\FilesystemHistory;
use Phalcon\DebugBar\History\HistoryOptions;
use Phalcon\DebugBar\History\RequestMetadata;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

use function bin2hex;
use function glob;
use function hash;
use function random_bytes;
use function rmdir;
use function session_id;
use function session_start;
use function session_write_close;
use function sys_get_temp_dir;
use function unlink;

final class FilesystemHistoryTest extends AbstractUnitTestCase
{
    #[RunInSeparateProcess]
    public function testClearRemovesTheCurrentSessionsRequests(): void
    {
        [$path, $sessionId] = $this->startSession();

        try {
            $history = new FilesystemHistory(new HistoryOptions(true, '/_debugbar/open', $path, 10, 60));
            for ($index = 0; $index < 2; $index++) {
                $history->save(
                    ['data' => [], 'meta' => ['index' => $index]],
                    new RequestMetadata('GET', '/' . $index, 200, false)
                );
            }

            $this->assertSame(2, $history->clear());
            $this->assertSame([], $history->find());
            $this->assertSame(0, $history->clear());
        } finally {
            session_write_close();
            $this->removeHistory($path, $sessionId);
        }
    }

    #[RunInSeparateProcess]
    public function testMaximumRequestCountIsPruned(): void
    {
        [$path, $sessionId] = $this->startSession();

        try {
            $history = new FilesystemHistory(new HistoryOptions(true, '/_debugbar/open', $path, 2, 60));
            for ($index = 0; $index < 3; $index++) {
                $history->save(
                    ['data' => [], 'meta' => ['index' => $index]],
                    new RequestMetadata('GET', '/' . $index, 200, false)
                );
            }

            $this->assertCount(2, $history->find());
        } finally {
            session_write_close();
            $this->removeHistory($path, $sessionId);
        }
    }

    #[RunInSeparateProcess]
    public function testNoActiveSessionStoresNothing(): void
    {
        $path    = $this->temporaryPath();
        $history = new FilesystemHistory(new HistoryOptions(true, '/_debugbar/open', $path));

        $this->assertNull($history->save(
            ['data' => [], 'meta' => []],
            new RequestMetadata('GET', '/', 200, false)
        ));
        $this->assertSame([], $history->find());
        $this->assertSame(0, $history->clear());
    }
    #[RunInSeparateProcess]
    public function testSaveFindAndGetAreSessionScoped(): void
    {
        [$path, $sessionId] = $this->startSession();

        try {
            $history = new FilesystemHistory(new HistoryOptions(true, '/_debugbar/open', $path, 10, 60));
            $id      = $history->save(
                ['data' => [], 'meta' => ['collectors' => 0]],
                new RequestMetadata('POST', '/orders', 201, true)
            );

            $this->assertIsString($id);

            $requests = $history->find();
            $this->assertCount(1, $requests);
            $this->assertSame($id, $requests[0]['id']);
            $this->assertSame('POST', $requests[0]['method']);
            $this->assertSame('/orders', $requests[0]['uri']);
            $this->assertSame(201, $requests[0]['status']);
            $this->assertTrue($requests[0]['ajax']);

            $entry = $history->get($id);
            $this->assertIsArray($entry);
            $payload = $entry['payload'];
            $this->assertIsArray($payload);
            $meta = $payload['meta'];
            $this->assertIsArray($meta);
            $this->assertSame(0, $meta['collectors']);
            $this->assertNull($history->get('../outside'));
        } finally {
            session_write_close();
            $this->removeHistory($path, $sessionId);
        }
    }

    private function removeHistory(string $path, string $sessionId): void
    {
        $directory = $path . '/' . hash('sha256', $sessionId);
        $files     = glob($directory . '/*');
        if (false !== $files) {
            foreach ($files as $file) {
                unlink($file);
            }
        }

        @rmdir($directory);
        @rmdir($path);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function startSession(): array
    {
        $sessionId = 'debugbar-' . bin2hex(random_bytes(8));
        session_id($sessionId);
        session_start();

        return [$this->temporaryPath(), $sessionId];
    }

    private function temporaryPath(): string
    {
        return sys_get_temp_dir() . '/phalcon-debugbar-test-' . bin2hex(random_bytes(8));
    }
}
