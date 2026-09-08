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

namespace Phalcon\DebugBar\History;

use DateTimeImmutable;
use DateTimeZone;

use function array_slice;
use function basename;
use function bin2hex;
use function glob;
use function hash;
use function is_array;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function min;
use function mkdir;
use function preg_match;
use function random_bytes;
use function rsort;
use function session_id;
use function session_status;
use function str_ends_with;
use function time;

use const GLOB_ONLYDIR;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const PHP_SESSION_ACTIVE;

/**
 * Persists request payloads in a session-scoped directory. Callers only learn
 * save/find/get/clear; atomic writes, pruning, path validation, and JSON failures stay
 * inside the module.
 *
 * @phpstan-import-type payload from \Phalcon\DebugBar\DebugBarTypes
 * @phpstan-type history_meta array{
 *     requested_at: string,
 *     method: string,
 *     uri: string,
 *     status: int,
 *     ajax: bool,
 *     id: string,
 *     stored_at: string
 * }
 * @phpstan-type history_entry array{meta: history_meta, payload: payload}
 */
final class FilesystemHistory
{
    private const GARBAGE_COLLECTION_MARKER               = '.gc';
    private const GARBAGE_COLLECTION_MAX_INTERVAL_SECONDS = 3600;
    private const METADATA_SUFFIX                         = '.meta';

    private readonly HistoryFileOperations $fileOperations;
    private bool $garbageCollectionAttempted = false;

    /**
     * @param HistoryOptions $options
     */
    public function __construct(
        private readonly HistoryOptions $options,
        ?HistoryFileOperations $fileOperations = null
    ) {
        $this->fileOperations = $fileOperations ?? new NativeHistoryFileOperations();
    }

    /**
     * Removes every stored request belonging to the active PHP session.
     *
     * @return int Number of files successfully removed.
     */
    public function clear(): int
    {
        if (PHP_SESSION_ACTIVE !== session_status()) {
            return 0;
        }

        $directory = $this->sessionDirectory(false);
        if (null === $directory) {
            return 0;
        }

        $removed = 0;
        foreach ($this->files($directory) as $file) {
            if ($this->fileOperations->remove($file)) {
                $removed++;
            }
            $this->fileOperations->remove($this->metadataFile($file));
        }
        foreach ($this->metadataFiles($directory) as $file) {
            $this->fileOperations->remove($file);
        }
        foreach ($this->temporaryFiles($directory) as $file) {
            $this->fileOperations->remove($file);
        }
        $this->removeDirectoryIfEmpty($directory);

        return $removed;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function find(): array
    {
        if (PHP_SESSION_ACTIVE !== session_status()) {
            return [];
        }

        $directory = $this->sessionDirectory(false);
        if (null === $directory) {
            return [];
        }
        $files = $this->removeExpired($this->files($directory));
        rsort($files, SORT_STRING);
        $files = array_slice($files, 0, $this->options->maxRequests);

        $requests = [];
        foreach ($files as $file) {
            $metadata = $this->readMetadata($file);
            if (null !== $metadata) {
                $requests[] = $metadata;
            }
        }

        return $requests;
    }

    /**
     * @param string $id
     *
     * @return array<string, mixed>|null
     */
    public function get(string $id): ?array
    {
        if (1 !== preg_match('/^[0-9]{14}-[0-9]{6}-[a-f0-9]{8}$/D', $id)) {
            return null;
        }

        if (PHP_SESSION_ACTIVE !== session_status()) {
            return null;
        }

        $directory = $this->sessionDirectory(false);
        if (null === $directory) {
            return null;
        }
        $file = $directory . '/' . basename($id) . '.json';
        if ($this->isExpired($file)) {
            $this->removeEntry($file);
            $this->removeDirectoryIfEmpty($directory);

            return null;
        }

        return $this->read($file);
    }

    /**
     * @param payload         $payload
     * @param RequestMetadata $request
     *
     * @return string|null
     */
    public function save(array $payload, RequestMetadata $request): ?string
    {
        if (PHP_SESSION_ACTIVE !== session_status()) {
            return null;
        }

        $directory = $this->sessionDirectory(true);
        if (null === $directory) {
            return null;
        }

        $storedAt   = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $requestedAt = $request->requestedAt ?? $storedAt;
        $id         = $storedAt->format('YmdHis-u-') . bin2hex(random_bytes(4));

        $entry = [
            'meta' => [
                'requested_at' => $requestedAt->format(DATE_ATOM),
                'method'       => $request->method,
                'uri'          => $request->uri,
                'status'       => $request->status,
                'ajax'         => $request->ajax,
                'id'           => $id,
                'stored_at'    => $storedAt->format(DATE_ATOM),
            ],
            'payload' => $payload,
        ];

        $flags        = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        $json         = json_encode($entry, $flags);
        $metadataJson = json_encode($entry['meta'], $flags);
        if (false === $json || false === $metadataJson) {
            $this->removeDirectoryIfEmpty($directory);

            return null;
        }

        $target = $directory . '/' . $id . '.json';
        if (!$this->saveFiles($target, $json, $metadataJson)) {
            $this->removeDirectoryIfEmpty($directory);

            return null;
        }

        $this->prune($directory);
        $this->garbageCollect();

        return $id;
    }

    /**
     * @param string $directory
     *
     * @return list<string>
     */
    private function files(string $directory): array
    {
        return @glob($directory . '/*.json') ?: [];
    }

    private function garbageCollect(): void
    {
        if ($this->garbageCollectionAttempted) {
            return;
        }

        $this->garbageCollectionAttempted = true;
        $marker                           = $this->options->path . '/' . self::GARBAGE_COLLECTION_MARKER;
        $modified                         = @filemtime($marker);
        $interval                         = min(
            $this->options->ttlSeconds,
            self::GARBAGE_COLLECTION_MAX_INTERVAL_SECONDS
        );
        if (false !== $modified && $modified >= time() - $interval) {
            return;
        }

        if (!$this->fileOperations->write($marker, (string) time())) {
            return;
        }

        foreach ($this->sessionDirectories() as $directory) {
            $storedFiles = [
                ...$this->files($directory),
                ...$this->metadataFiles($directory),
                ...$this->temporaryFiles($directory),
            ];
            foreach ($storedFiles as $file) {
                if ($this->isExpired($file)) {
                    if (str_ends_with($file, '.json')) {
                        $this->removeEntry($file);
                    } else {
                        $this->fileOperations->remove($file);
                    }
                }
            }

            $this->removeDirectoryIfEmpty($directory);
        }
    }

    private function isExpired(string $file): bool
    {
        $modified = @filemtime($file);

        return false !== $modified && $modified < time() - $this->options->ttlSeconds;
    }

    private function metadataFile(string $file): string
    {
        return $file . self::METADATA_SUFFIX;
    }

    /**
     * @return list<string>
     */
    private function metadataFiles(string $directory): array
    {
        return @glob($directory . '/*.json' . self::METADATA_SUFFIX) ?: [];
    }

    /**
     * @param string $directory
     *
     * @return void
     */
    private function prune(string $directory): void
    {
        $files = $this->removeExpired($this->files($directory));
        rsort($files, SORT_STRING);
        foreach (array_slice($files, $this->options->maxRequests) as $file) {
            $this->removeEntry($file);
        }
    }

    /**
     * @param string $file
     *
     * @return array{meta: array<string, mixed>, payload: array<string, mixed>}|null
     */
    private function read(string $file): ?array
    {
        if (!is_file($file)) {
            return null;
        }

        $json = $this->fileOperations->read($file);
        if (false === $json) {
            return null;
        }

        $entry = json_decode($json, true);
        if (!is_array($entry) || !is_array($entry['meta'] ?? null) || !is_array($entry['payload'] ?? null)) {
            return null;
        }

        /** @var array<string, mixed> $meta */
        $meta = $entry['meta'];
        /** @var array<string, mixed> $payload */
        $payload = $entry['payload'];

        return [
            'meta'    => $meta,
            'payload' => $payload,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readMetadata(string $file): ?array
    {
        $metadataFile = $this->metadataFile($file);
        if (is_file($metadataFile)) {
            $json = $this->fileOperations->read($metadataFile);
            if (false !== $json) {
                $metadata = json_decode($json, true);
                if (is_array($metadata)) {
                    /** @var array<string, mixed> $metadata */
                    return $metadata;
                }
            }
        }

        $entry = $this->read($file);

        return null === $entry ? null : $entry['meta'];
    }

    private function removeDirectoryIfEmpty(string $directory): void
    {
        if ([] === (@glob($directory . '/*') ?: [])) {
            $this->fileOperations->removeDirectory($directory);
        }
    }

    private function removeEntry(string $file): void
    {
        $this->fileOperations->remove($file);
        $this->fileOperations->remove($this->metadataFile($file));
    }

    /**
     * @param list<string> $files
     *
     * @return list<string>
     */
    private function removeExpired(array $files): array
    {
        $remaining = [];
        foreach ($files as $file) {
            if ($this->isExpired($file)) {
                $this->removeEntry($file);
                continue;
            }

            $remaining[] = $file;
        }

        return $remaining;
    }

    private function saveFiles(string $target, string $json, string $metadataJson): bool
    {
        $metadataTarget    = $this->metadataFile($target);
        $metadataTemporary = $metadataTarget . '.tmp-' . bin2hex(random_bytes(4));
        $temporary         = $target . '.tmp-' . bin2hex(random_bytes(4));
        if (!$this->fileOperations->write($temporary, $json)) {
            $this->fileOperations->remove($temporary);

            return false;
        }
        if (!$this->fileOperations->write($metadataTemporary, $metadataJson)) {
            $this->fileOperations->remove($metadataTemporary);
            $this->fileOperations->remove($temporary);

            return false;
        }
        if (!$this->fileOperations->move($metadataTemporary, $metadataTarget)) {
            $this->fileOperations->remove($metadataTemporary);
            $this->fileOperations->remove($temporary);

            return false;
        }
        if (!$this->fileOperations->move($temporary, $target)) {
            $this->fileOperations->remove($temporary);
            $this->fileOperations->remove($metadataTarget);

            return false;
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function sessionDirectories(): array
    {
        return @glob($this->options->path . '/*', GLOB_ONLYDIR) ?: [];
    }

    /**
     * @param bool $create
     *
     * @return string|null
     */
    private function sessionDirectory(bool $create): ?string
    {
        $directory = $this->options->path . '/' . hash('sha256', (string) session_id());
        if (is_dir($directory)) {
            return $directory;
        }

        if (!$create || (!@mkdir($directory, 0700, true) && !is_dir($directory))) {
            return null;
        }

        return $directory;
    }

    /**
     * @return list<string>
     */
    private function temporaryFiles(string $directory): array
    {
        return @glob($directory . '/*.tmp-*') ?: [];
    }
}
