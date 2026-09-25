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

use DateTimeImmutable;
use Phalcon\DebugBar\History\FilesystemHistory;
use Phalcon\DebugBar\History\HistoryCookie;
use Phalcon\DebugBar\History\HistoryEntry;
use Phalcon\DebugBar\History\HistoryOptions;
use Phalcon\DebugBar\History\NativeHistoryFileOperations;
use Phalcon\DebugBar\History\RequestMetadata;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\History\DelegatingHistoryFileOperations;
use Phalcon\Tests\Support\DebugBar\History\FailingStreamWrapper;
use Phalcon\Tests\Support\DebugBar\History\GarbageCollectionMarkerFailingFileOperations;
use Phalcon\Tests\Support\DebugBar\History\MetadataMoveFailingHistoryFileOperations;
use Phalcon\Tests\Support\DebugBar\History\MetadataWriteFailingHistoryFileOperations;
use Phalcon\Tests\Support\DebugBar\History\PayloadMoveFailingHistoryFileOperations;
use Phalcon\Tests\Support\DebugBar\History\PayloadReadTrackingHistoryFileOperations;
use Phalcon\Tests\Support\DebugBar\History\RecordingMatchingHistoryFileOperations;
use Phalcon\Tests\Support\DebugBar\History\RenameFailingHistoryFileOperations;
use Phalcon\Tests\Support\DebugBar\History\UnwritableHistoryFileOperations;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use RuntimeException;

use function bin2hex;
use function file_exists;
use function file_put_contents;
use function glob;
use function hash;
use function is_dir;
use function is_file;
use function json_encode;
use function mkdir;
use function preg_replace;
use function random_bytes;
use function rmdir;
use function session_id;
use function session_regenerate_id;
use function session_start;
use function session_write_close;
use function str_repeat;
use function stream_wrapper_register;
use function stream_wrapper_unregister;
use function sys_get_temp_dir;
use function touch;
use function unlink;
use function usleep;

final class FilesystemHistoryTest extends AbstractUnitTestCase
{
    #[RunInSeparateProcess]
    public function testActiveBrowserWithoutStorageHasNoRequests(): void
    {
        [$path, $sessionId] = $this->startSession();

        try {
            $history = new FilesystemHistory(new HistoryOptions(true, '/_debugbar/open', $path));

            $this->assertSame([], $history->find());
            $this->assertNull($history->get('20260903120000-123456-deadbeef'));
        } finally {
            session_write_close();
            $this->removeHistory($path, $sessionId);
        }
    }

    #[RunInSeparateProcess]
    public function testChangingTheApplicationSessionIdKeepsBrowserHistory(): void
    {
        [$path, $browserId] = $this->startSession();

        try {
            $history = new FilesystemHistory(new HistoryOptions(true, '/_debugbar/open', $path));
            $id      = $history->save(
                ['data' => [], 'meta' => []],
                new RequestMetadata('GET', '/before-login', 200, false)
            );
            $this->assertIsString($id);
            $this->assertTrue(session_regenerate_id());

            $afterLogin = new FilesystemHistory(new HistoryOptions(true, '/_debugbar/open', $path));
            $this->assertCount(1, $afterLogin->find());
            $this->assertIsArray($afterLogin->get($id));
        } finally {
            session_write_close();
            $this->removeHistory($path, $browserId);
        }
    }

    #[RunInSeparateProcess]
    public function testClearRemovesTheCurrentBrowsersRequests(): void
    {
        [$path, $sessionId] = $this->startSession();
        $directory          = $path . '/' . hash('sha256', $sessionId);

        try {
            $history = new FilesystemHistory(new HistoryOptions(true, '/_debugbar/open', $path, 10, 60));
            for ($index = 0; $index < 2; $index++) {
                $history->save(
                    ['data' => [], 'meta' => ['index' => $index]],
                    new RequestMetadata('GET', '/' . $index, 200, false)
                );
            }
            $temporary = $directory . '/orphan.json.tmp-deadbeef';
            $metadata  = $directory . '/orphan.json.meta';
            $this->assertIsInt(file_put_contents($temporary, '{}'));
            $this->assertIsInt(file_put_contents($metadata, '{}'));

            $this->assertSame(2, $history->clear());
            $this->assertFalse(file_exists($temporary));
            $this->assertFalse(file_exists($metadata));
            $this->assertFalse(is_dir($directory));
            $this->assertSame([], $history->find());
            $this->assertSame(0, $history->clear());
        } finally {
            session_write_close();
            $this->removeHistory($path, $sessionId);
        }
    }

    public function testConcurrentDirectoryCreationIsRecheckedInsideTheLock(): void
    {
        $path       = $this->temporaryPath();
        $browserId  = str_repeat('1', 64);
        $directory  = $path . '/' . hash('sha256', $browserId);
        $operations = new class ($directory) extends DelegatingHistoryFileOperations {
            public function __construct(private readonly string $directory)
            {
                parent::__construct();
            }

            public function withExclusiveLock(string $file, \Closure $operation): bool
            {
                if (!$this->directoryExists($this->directory)) {
                    $this->createDirectory($this->directory, 0700, true);
                }

                return $operation();
            }
        };
        $history = new FilesystemHistory(
            new HistoryOptions(true, '/_debugbar/open', $path),
            $operations,
            new HistoryCookie($browserId)
        );

        try {
            $this->assertIsString($history->save(
                ['data' => [], 'meta' => []],
                new RequestMetadata('GET', '/', 200, false)
            ));
        } finally {
            $this->removeHistory($path, $browserId);
        }
    }

    public function testEvictionFailureRefusesANewBrowserDirectory(): void
    {
        $path       = $this->temporaryPath();
        $firstId    = str_repeat('2', 64);
        $secondId   = str_repeat('3', 64);
        $firstPath  = $path . '/' . hash('sha256', $firstId);
        $operations = new class () extends DelegatingHistoryFileOperations {
            public string $blockedDirectory = '';

            public function removeDirectory(string $directory): bool
            {
                return $directory === $this->blockedDirectory
                    ? false
                    : parent::removeDirectory($directory);
            }
        };
        $options = new HistoryOptions(true, '/_debugbar/open', $path, 10, 60, 1);

        try {
            $first = new FilesystemHistory($options, $operations, new HistoryCookie($firstId));
            $this->assertIsString($first->save(
                ['data' => [], 'meta' => []],
                new RequestMetadata('GET', '/first', 200, false)
            ));
            $operations->blockedDirectory = $firstPath;

            $second = new FilesystemHistory($options, $operations, new HistoryCookie($secondId));
            $this->assertNull($second->save(
                ['data' => [], 'meta' => []],
                new RequestMetadata('GET', '/second', 200, false)
            ));
            $this->assertTrue(is_dir($firstPath));
            $this->assertFalse(is_dir($path . '/' . hash('sha256', $secondId)));
        } finally {
            $operations->blockedDirectory = '';
            $this->removeHistory($path, $firstId);
            $this->removeHistory($path, $secondId);
        }
    }

    #[RunInSeparateProcess]
    public function testExistingBrowserDirectoryMustRemainWritable(): void
    {
        [$path, $sessionId] = $this->startSession();
        $fileOperations     = new UnwritableHistoryFileOperations();
        $history            = new FilesystemHistory(
            new HistoryOptions(true, '/_debugbar/open', $path),
            $fileOperations
        );

        try {
            $this->assertIsString($history->save(
                ['data' => [], 'meta' => []],
                new RequestMetadata('GET', '/', 200, false)
            ));

            $fileOperations->writable = false;

            $this->assertNull($history->save(
                ['data' => [], 'meta' => []],
                new RequestMetadata('GET', '/blocked', 200, false)
            ));
            $this->assertSame([], $history->find());
            $this->assertSame(0, $history->clear());
        } finally {
            session_write_close();
            $this->removeHistory($path, $sessionId);
        }
    }

    #[RunInSeparateProcess]
    public function testFindingRequestsDoesNotReadStoredPayloads(): void
    {
        [$path, $sessionId] = $this->startSession();
        $fileOperations     = new PayloadReadTrackingHistoryFileOperations();
        $history            = new FilesystemHistory(
            new HistoryOptions(true, '/_debugbar/open', $path),
            $fileOperations
        );

        try {
            $id = $history->save(
                [
                    'data' => ['database' => ['panel' => str_repeat('x', 512 * 1024), 'badge' => null]],
                    'meta' => [],
                ],
                new RequestMetadata('GET', '/large', 200, false)
            );
            $this->assertIsString($id);

            $requests = $history->find();
            $this->assertCount(1, $requests);
            $this->assertSame('/large', $requests[0]['uri']);
            $this->assertSame(0, $fileOperations->payloadReads);

            $this->assertIsArray($history->get($id));
            $this->assertSame(1, $fileOperations->payloadReads);
        } finally {
            session_write_close();
            $this->removeHistory($path, $sessionId);
        }
    }

    public function testFindRemovesAnEmptyExpiredBrowserDirectory(): void
    {
        $path      = $this->temporaryPath();
        $browserId = str_repeat('d', 64);
        $directory = $path . '/' . hash('sha256', $browserId);
        $history   = new FilesystemHistory(
            new HistoryOptions(true, '/_debugbar/open', $path, 10, 1),
            null,
            new HistoryCookie($browserId)
        );

        try {
            $id = $history->save(
                ['data' => [], 'meta' => []],
                new RequestMetadata('GET', '/', 200, false)
            );
            $this->assertIsString($id);
            $this->assertTrue(touch($directory . '/' . $id . '.json', time() - 10));

            $this->assertSame([], $history->find());
            $this->assertFalse(is_dir($directory));
        } finally {
            $this->removeHistory($path, $browserId);
        }
    }

    #[RunInSeparateProcess]
    public function testIncompatibleStoredEntryIsRejected(): void
    {
        [$path, $sessionId] = $this->startSession();
        $directory          = $path . '/' . hash('sha256', $sessionId);
        $id                 = '20260903120000-123456-deadbeef';
        $metadata           = [
            'requested_at' => '2026-09-03T12:00:00+00:00',
            'method'       => 'GET',
            'uri'          => '/stale',
            'status'       => 200,
            'ajax'         => false,
            'id'           => $id,
            'stored_at'    => '2026-09-03T12:00:00+00:00',
        ];

        try {
            $this->assertTrue(mkdir($directory, 0777, true));
            $payload = json_encode([
                'version' => 2,
                'meta'    => $metadata,
                'payload' => ['data' => [], 'meta' => []],
            ]);
            $sidecar = json_encode($metadata);
            $this->assertIsString($payload);
            $this->assertIsString($sidecar);
            $this->assertIsInt(file_put_contents($directory . '/' . $id . '.json', $payload));
            $this->assertIsInt(file_put_contents($directory . '/' . $id . '.json.meta', $sidecar));

            $history = new FilesystemHistory(new HistoryOptions(true, '/_debugbar/open', $path));

            $this->assertNull($history->get($id));
            $this->assertSame([], $history->find());
        } finally {
            session_write_close();
            $this->removeHistory($path, $sessionId);
        }
    }

    #[RunInSeparateProcess]
    public function testInvalidPayloadCannotBeEncoded(): void
    {
        [$path, $sessionId] = $this->startSession();

        try {
            $history = new FilesystemHistory(new HistoryOptions(true, '/_debugbar/open', $path));

            $this->assertNull($history->save(
                ['data' => ['invalid' => ['panel' => NAN, 'badge' => null]], 'meta' => []],
                new RequestMetadata('GET', '/', 200, false)
            ));
            $this->assertFalse(is_dir($path . '/' . hash('sha256', $sessionId)));
        } finally {
            session_write_close();
            $this->removeHistory($path, $sessionId);
        }
    }

    #[RunInSeparateProcess]
    public function testListingOnlyCollectsTheActiveBrowser(): void
    {
        [$path, $firstSessionId] = $this->startSession();
        $firstDirectory          = $path . '/' . hash('sha256', $firstSessionId);
        $secondSessionId         = bin2hex(random_bytes(32));
        $unrelatedDirectory      = $path . '/application-cache';
        $unrelatedFile           = $unrelatedDirectory . '/response.json';

        try {
            $history = new FilesystemHistory(new HistoryOptions(true, '/_debugbar/open', $path, 10, 1));
            $id      = $history->save(
                ['data' => [], 'meta' => []],
                new RequestMetadata('GET', '/expired', 200, false)
            );
            $this->assertIsString($id);
            $this->assertTrue(touch($firstDirectory . '/' . $id . '.json', time() - 10));
            $temporary = $firstDirectory . '/' . $id . '.json.tmp-deadbeef';
            $this->assertIsInt(file_put_contents($temporary, '{}'));
            $this->assertTrue(touch($temporary, time() - 10));
            $this->assertTrue(mkdir($unrelatedDirectory));
            $this->assertIsInt(file_put_contents($unrelatedFile, '{}'));
            $this->assertTrue(touch($unrelatedFile, time() - 10));

            session_write_close();
            session_id($secondSessionId);
            session_start();
            $_COOKIE[HistoryCookie::NAME] = $secondSessionId;

            $nextRequestHistory = new FilesystemHistory(
                new HistoryOptions(true, '/_debugbar/open', $path, 10, 1)
            );
            $this->assertSame([], $nextRequestHistory->find());
            $this->assertTrue(file_exists($firstDirectory . '/' . $id . '.json'));
            $this->assertTrue(file_exists($temporary));
            $this->assertTrue(is_dir($firstDirectory));

            $marker = $path . '/.gc';
            $this->assertIsString($nextRequestHistory->save(
                ['data' => [], 'meta' => []],
                new RequestMetadata('GET', '/rate-limited', 200, false)
            ));
            $this->assertTrue(file_exists($firstDirectory . '/' . $id . '.json'));
            $this->assertTrue(file_exists($temporary));

            $this->assertTrue(touch($marker, time() - 3601));
            $collectingHistory = new FilesystemHistory(
                new HistoryOptions(true, '/_debugbar/open', $path, 10, 1)
            );
            $this->assertIsString($collectingHistory->save(
                ['data' => [], 'meta' => []],
                new RequestMetadata('GET', '/collect', 200, false)
            ));
            $this->assertFalse(file_exists($firstDirectory . '/' . $id . '.json'));
            $this->assertFalse(file_exists($temporary));
            $this->assertFalse(is_dir($firstDirectory));
            $this->assertTrue(file_exists($unrelatedFile));
        } finally {
            session_write_close();
            $this->removeHistory($path, $firstSessionId);
            $this->removeHistory($path, $secondSessionId);
            if (file_exists($unrelatedFile)) {
                unlink($unrelatedFile);
            }
            if (is_dir($unrelatedDirectory)) {
                rmdir($unrelatedDirectory);
            }
            if (is_dir($path)) {
                rmdir($path);
            }
        }
    }

    public function testMalformedAndExpiredEntriesAreIgnored(): void
    {
        [$path, $sessionId] = $this->startSession();
        $directory          = $path . '/' . hash('sha256', $sessionId);
        $id                 = '20260903120000-123456-deadbeef';

        try {
            $history = new FilesystemHistory(new HistoryOptions(true, '/_debugbar/open', $path, 10, 1));
            $savedId = $history->save(
                ['data' => [], 'meta' => []],
                new RequestMetadata('GET', '/', 200, false)
            );
            $this->assertIsString($savedId);
            $this->assertTrue(touch($directory . '/' . $savedId . '.json', time() - 10));
            $this->assertNull($history->get($savedId));
            $this->assertFalse(file_exists($directory . '/' . $savedId . '.json'));

            $savedId = $history->save(
                ['data' => [], 'meta' => []],
                new RequestMetadata('GET', '/', 200, false)
            );
            $this->assertIsString($savedId);
            $this->assertTrue(touch($directory . '/' . $savedId . '.json', time() - 10));
            $this->assertSame([], $history->find());
            $this->assertFalse(file_exists($directory . '/' . $savedId . '.json'));

            $this->assertTrue(mkdir($directory, 0700, true));
            $this->assertIsInt(file_put_contents($directory . '/' . $id . '.json', '{invalid'));
            $this->assertNull($history->get($id));
            $this->assertNull($history->get('20260903120000-123456-cafebabe'));
        } finally {
            session_write_close();
            $this->removeHistory($path, $sessionId);
        }
    }

    #[RunInSeparateProcess]
    public function testMalformedEntriesDoNotConsumeTheRequestLimit(): void
    {
        [$path, $sessionId] = $this->startSession();
        $directory          = $path . '/' . hash('sha256', $sessionId);

        try {
            $writer = new FilesystemHistory(new HistoryOptions(true, '/_debugbar/open', $path, 10, 60));
            $ids    = [];
            for ($index = 0; $index < 3; $index++) {
                $id = $writer->save(
                    ['data' => [], 'meta' => ['index' => $index]],
                    new RequestMetadata('GET', '/' . $index, 200, false)
                );
                $this->assertIsString($id);
                $ids[] = $id;
                usleep(1000);
            }

            $newest = $directory . '/' . $ids[2] . '.json';
            $this->assertIsInt(file_put_contents($newest . '.meta', '{"id":123}'));

            $history  = new FilesystemHistory(new HistoryOptions(true, '/_debugbar/open', $path, 2, 60));
            $requests = $history->find();
            $this->assertCount(2, $requests);
            $this->assertSame($ids[2], $requests[0]['id']);
            $this->assertSame($ids[1], $requests[1]['id']);

            $this->assertIsInt(file_put_contents($newest, '{invalid'));
            $requests = $history->find();
            $this->assertCount(2, $requests);
            $this->assertSame($ids[1], $requests[0]['id']);
            $this->assertSame($ids[0], $requests[1]['id']);
        } finally {
            session_write_close();
            $this->removeHistory($path, $sessionId);
        }
    }

    public function testMaximumBrowserCountEvictsTheOldestDirectory(): void
    {
        $path      = $this->temporaryPath();
        $browserId = [str_repeat('a', 64), str_repeat('b', 64), str_repeat('c', 64)];
        $options   = new HistoryOptions(true, '/_debugbar/open', $path, 10, 60, 2);

        try {
            foreach ($browserId as $index => $id) {
                $history = new FilesystemHistory($options, null, new HistoryCookie($id));
                $this->assertIsString($history->save(
                    ['data' => [], 'meta' => []],
                    new RequestMetadata('GET', '/' . $index, 200, false)
                ));
                $this->assertTrue(touch($path . '/' . hash('sha256', $id), time() - (10 - $index)));
            }

            $this->assertFalse(is_dir($path . '/' . hash('sha256', $browserId[0])));
            $this->assertTrue(is_dir($path . '/' . hash('sha256', $browserId[1])));
            $this->assertTrue(is_dir($path . '/' . hash('sha256', $browserId[2])));
        } finally {
            foreach ($browserId as $id) {
                $this->removeHistory($path, $id);
            }
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
    public function testMetadataMoveFailureRollsBackPublishedPayload(): void
    {
        [$path, $sessionId] = $this->startSession();
        $history            = new FilesystemHistory(
            new HistoryOptions(true, '/_debugbar/open', $path),
            new MetadataMoveFailingHistoryFileOperations()
        );

        try {
            $this->assertNull($history->save(
                ['data' => [], 'meta' => []],
                new RequestMetadata('GET', '/', 200, false)
            ));
            $this->assertFalse(is_dir($path . '/' . hash('sha256', $sessionId)));
        } finally {
            session_write_close();
            $this->removeHistory($path, $sessionId);
        }
    }

    #[RunInSeparateProcess]
    public function testMetadataWriteFailureRemovesTemporaryFilesAndReturnsNull(): void
    {
        [$path, $sessionId] = $this->startSession();
        $history            = new FilesystemHistory(
            new HistoryOptions(true, '/_debugbar/open', $path),
            new MetadataWriteFailingHistoryFileOperations()
        );

        try {
            $this->assertNull($history->save(
                ['data' => [], 'meta' => []],
                new RequestMetadata('GET', '/', 200, false)
            ));
            $this->assertFalse(is_dir($path . '/' . hash('sha256', $sessionId)));
        } finally {
            session_write_close();
            $this->removeHistory($path, $sessionId);
        }
    }

    #[RunInSeparateProcess]
    public function testMissingBrowserCookieStoresNothing(): void
    {
        unset($_COOKIE[HistoryCookie::NAME]);
        $path    = $this->temporaryPath();
        $history = new FilesystemHistory(new HistoryOptions(true, '/_debugbar/open', $path));

        $this->assertNull($history->save(
            ['data' => [], 'meta' => []],
            new RequestMetadata('GET', '/', 200, false)
        ));
        $this->assertSame([], $history->find());
        $this->assertNull($history->get('20260903120000-123456-deadbeef'));
        $this->assertSame(0, $history->clear());
    }

    public function testNativeLockReturnsFalseWhenFileCannotBeOpened(): void
    {
        $operations = new NativeHistoryFileOperations();

        $this->assertFalse($operations->withExclusiveLock(
            $this->temporaryPath() . '/missing/lock',
            static fn (): bool => true
        ));
    }

    public function testNativeLockReturnsFalseWhenStreamCannotBeLocked(): void
    {
        $operations = new NativeHistoryFileOperations();

        $this->assertFalse($operations->withExclusiveLock(
            'php://memory',
            static fn (): bool => true
        ));
    }

    #[RunInSeparateProcess]
    public function testPayloadMoveFailureRemovesStagedFiles(): void
    {
        [$path, $sessionId] = $this->startSession();
        $history            = new FilesystemHistory(
            new HistoryOptions(true, '/_debugbar/open', $path),
            new PayloadMoveFailingHistoryFileOperations()
        );

        try {
            $this->assertNull($history->save(
                ['data' => [], 'meta' => []],
                new RequestMetadata('GET', '/', 200, false)
            ));
            $this->assertFalse(is_dir($path . '/' . hash('sha256', $sessionId)));
        } finally {
            session_write_close();
            $this->removeHistory($path, $sessionId);
        }
    }

    #[RunInSeparateProcess]
    public function testRenameFailureRemovesTemporaryFileAndReturnsNull(): void
    {
        [$path, $sessionId] = $this->startSession();
        $fileOperations     = new RenameFailingHistoryFileOperations();
        $history            = new FilesystemHistory(
            new HistoryOptions(true, '/_debugbar/open', $path),
            $fileOperations
        );

        try {
            $this->assertNull($history->save(
                ['data' => [], 'meta' => []],
                new RequestMetadata('GET', '/', 200, false)
            ));
            $this->assertSame(2, $fileOperations->removeCalls);
            $this->assertFalse(is_dir($path . '/' . hash('sha256', $sessionId)));
        } finally {
            session_write_close();
            if (file_exists($path . '/.lock')) {
                unlink($path . '/.lock');
            }
            if (is_dir($path)) {
                rmdir($path);
            }
        }
    }

    #[RunInSeparateProcess]
    public function testSaveFindAndGetAreBrowserScoped(): void
    {
        [$path, $firstSessionId] = $this->startSession();
        $secondSessionId         = bin2hex(random_bytes(32));

        try {
            $history     = new FilesystemHistory(new HistoryOptions(true, '/_debugbar/open', $path, 10, 60));
            $requestedAt = new DateTimeImmutable('2000-01-02T03:04:05+00:00');
            $id          = $history->save(
                ['data' => [], 'meta' => ['collectors' => 0]],
                new RequestMetadata('POST', '/orders', 201, true, $requestedAt)
            );

            $this->assertIsString($id);

            $requests = $history->find();
            $this->assertCount(1, $requests);
            $this->assertSame($id, $requests[0]['id']);
            $this->assertSame('POST', $requests[0]['method']);
            $this->assertSame('/orders', $requests[0]['uri']);
            $this->assertSame(201, $requests[0]['status']);
            $this->assertTrue($requests[0]['ajax']);
            $this->assertSame('2000-01-02T03:04:05+00:00', $requests[0]['requested_at']);
            $this->assertNotSame($requests[0]['requested_at'], $requests[0]['stored_at']);

            $entry = $history->get($id);
            $this->assertIsArray($entry);
            $this->assertSame(HistoryEntry::VERSION, $entry['version']);
            $payload = $entry['payload'];
            $this->assertIsArray($payload);
            $meta = $payload['meta'];
            $this->assertIsArray($meta);
            $this->assertSame(0, $meta['collectors']);
            $this->assertNull($history->get('../outside'));

            session_write_close();
            session_id($secondSessionId);
            session_start();
            $_COOKIE[HistoryCookie::NAME] = $secondSessionId;

            $secondBrowserHistory = new FilesystemHistory(
                new HistoryOptions(true, '/_debugbar/open', $path, 10, 60)
            );
            $this->assertSame([], $secondBrowserHistory->find());
            $this->assertNull($secondBrowserHistory->get($id));

            $secondId = $secondBrowserHistory->save(
                ['data' => [], 'meta' => ['collectors' => 1]],
                new RequestMetadata('GET', '/customers', 200, false)
            );
            $this->assertIsString($secondId);

            session_write_close();
            session_id($firstSessionId);
            session_start();
            $_COOKIE[HistoryCookie::NAME] = $firstSessionId;

            $firstSessionHistory = new FilesystemHistory(
                new HistoryOptions(true, '/_debugbar/open', $path, 10, 60)
            );
            $firstRequests = $firstSessionHistory->find();
            $this->assertCount(1, $firstRequests);
            $this->assertSame($id, $firstRequests[0]['id']);
            $this->assertNull($firstSessionHistory->get($secondId));
        } finally {
            session_write_close();
            $this->removeHistory($path, $firstSessionId);
            $this->removeHistory($path, $secondSessionId);
        }
    }

    #[RunInSeparateProcess]
    public function testSaveSucceedsWhenGarbageCollectionMarkerCannotBeWritten(): void
    {
        [$path, $sessionId] = $this->startSession();

        try {
            $history = new FilesystemHistory(
                new HistoryOptions(true, '/_debugbar/open', $path),
                new GarbageCollectionMarkerFailingFileOperations()
            );

            $this->assertIsString($history->save(
                ['data' => [], 'meta' => []],
                new RequestMetadata('GET', '/', 200, false)
            ));
            $this->assertCount(1, $history->find());
            $this->assertFalse(file_exists($path . '/.gc'));
        } finally {
            session_write_close();
            $this->removeHistory($path, $sessionId);
        }
    }

    #[RunInSeparateProcess]
    public function testStorageDirectoryCreationFailureStoresNothing(): void
    {
        [$path, $sessionId] = $this->startSession();
        $blockedPath        = $path . '/not-a-directory';

        try {
            $this->assertTrue(mkdir($path, 0700, true));
            $this->assertIsInt(file_put_contents($blockedPath, 'blocked'));
            $history = new FilesystemHistory(
                new HistoryOptions(true, '/_debugbar/open', $blockedPath)
            );

            $this->assertNull($history->save(
                ['data' => [], 'meta' => []],
                new RequestMetadata('GET', '/', 200, false)
            ));
        } finally {
            session_write_close();
            if (file_exists($blockedPath)) {
                unlink($blockedPath);
            }
            $this->removeHistory($path, $sessionId);
        }
    }

    public function testStorageFailuresReturnSafeEmptyResults(): void
    {
        $scheme = 'debugbar-failure';
        $this->assertTrue(stream_wrapper_register($scheme, FailingStreamWrapper::class));
        [, $sessionId] = $this->startSession();
        $history       = new FilesystemHistory(new HistoryOptions(true, '/_debugbar/open', $scheme . '://history'));
        try {
            $directory = $scheme . '://history/' . hash('sha256', $sessionId);
            $this->assertTrue(is_dir($directory));
            $this->assertTrue(is_file($directory . '/20260903120000-123456-deadbeef.json'));
            $this->assertSame([], $history->find());

            $this->assertNull($history->get('20260903120000-123456-deadbeef'));
            $this->assertNull($history->save(
                ['data' => [], 'meta' => []],
                new RequestMetadata('GET', '/', 200, false)
            ));
        } finally {
            session_write_close();
            stream_wrapper_unregister($scheme);
        }
    }

    #[RunInSeparateProcess]
    public function testStoragePathWithGlobCharactersStillListsAndPrunes(): void
    {
        $path               = $this->temporaryPath() . '[history]';
        [$path, $sessionId] = $this->startSession($path);

        try {
            $history = new FilesystemHistory(new HistoryOptions(true, '/_debugbar/open', $path, 2, 60));
            for ($index = 0; $index < 3; $index++) {
                $history->save(
                    ['data' => [], 'meta' => ['index' => $index]],
                    new RequestMetadata('GET', '/' . $index, 200, false)
                );
            }

            $this->assertCount(2, $history->find());
            $this->assertSame(2, $history->clear());
        } finally {
            session_write_close();
            $this->removeHistory($path, $sessionId);
        }
    }

    public function testStorageValidationRejectsAnUnwritableRoot(): void
    {
        $path                     = $this->temporaryPath();
        $fileOperations           = new UnwritableHistoryFileOperations();
        $fileOperations->writable = false;
        $history                  = new FilesystemHistory(
            new HistoryOptions(true, '/_debugbar/open', $path),
            $fileOperations,
            new HistoryCookie(str_repeat('f', 64))
        );

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('history.path must be writable');
            $history->validateStorage();
        } finally {
            if (is_dir($path)) {
                rmdir($path);
            }
        }
    }

    #[RunInSeparateProcess]
    public function testVersionedEntriesWithoutMetadataSidecarRemainReadable(): void
    {
        [$path, $sessionId] = $this->startSession();
        $directory          = $path . '/' . hash('sha256', $sessionId);
        $fileOperations     = new PayloadReadTrackingHistoryFileOperations();
        $history            = new FilesystemHistory(
            new HistoryOptions(true, '/_debugbar/open', $path),
            $fileOperations
        );

        try {
            $id = $history->save(
                ['data' => [], 'meta' => []],
                new RequestMetadata('GET', '/without-sidecar', 200, false)
            );
            $this->assertIsString($id);
            $this->assertTrue(unlink($directory . '/' . $id . '.json.meta'));

            $requests = $history->find();
            $this->assertCount(1, $requests);
            $this->assertSame('/without-sidecar', $requests[0]['uri']);
            $this->assertSame(1, $fileOperations->payloadReads);
        } finally {
            session_write_close();
            $this->removeHistory($path, $sessionId);
        }
    }

    public function testWindowsStoragePathsUsePortableGlobSeparators(): void
    {
        $browserId      = str_repeat('e', 64);
        $fileOperations = new RecordingMatchingHistoryFileOperations();
        $history        = new FilesystemHistory(
            new HistoryOptions(true, '/_debugbar/open', 'C:\\debug[bar]\\history'),
            $fileOperations,
            new HistoryCookie($browserId)
        );

        $this->assertSame([], $history->find());
        $this->assertSame(
            'C:/debug\\[bar\\]/history/' . hash('sha256', $browserId) . '/*.json',
            $fileOperations->patterns[0]
        );
    }

    private function removeHistory(string $path, string $sessionId): void
    {
        $directory = $path . '/' . hash('sha256', $sessionId);
        $pattern   = preg_replace('/([*?\[\]\\\\])/', '\\\\$1', $directory);
        $files     = null === $pattern ? false : glob($pattern . '/*');
        if (false !== $files) {
            foreach ($files as $file) {
                unlink($file);
            }
        }

        if (is_dir($directory)) {
            rmdir($directory);
        }
        if (file_exists($path . '/.gc')) {
            unlink($path . '/.gc');
        }
        if (file_exists($path . '/.lock')) {
            unlink($path . '/.lock');
        }
        $pathPattern = preg_replace('/([*?\[\]\\\\])/', '\\\\$1', $path);
        $remaining   = null === $pathPattern ? false : glob($pathPattern . '/*');
        if ([] === $remaining && is_dir($path)) {
            rmdir($path);
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function startSession(?string $path = null): array
    {
        $sessionId                    = bin2hex(random_bytes(32));
        $_COOKIE[HistoryCookie::NAME] = $sessionId;
        session_id($sessionId);
        session_start();

        return [$path ?? $this->temporaryPath(), $sessionId];
    }

    private function temporaryPath(): string
    {
        return sys_get_temp_dir() . '/phalcon-debugbar-test-' . bin2hex(random_bytes(8));
    }
}
