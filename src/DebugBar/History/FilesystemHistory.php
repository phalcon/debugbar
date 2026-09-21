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
use Phalcon\DebugBar\Contracts\History;

use function array_slice;
use function basename;
use function bin2hex;
use function count;
use function hash;
use function is_array;
use function json_decode;
use function json_encode;
use function min;
use function preg_match;
use function preg_replace;
use function random_bytes;
use function rsort;
use function str_ends_with;
use function time;

use const GLOB_ONLYDIR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const SORT_STRING;

/**
 * Persists request payloads in a browser-scoped directory. Callers only learn
 * save/find/get/clear; atomic writes, pruning, path validation, and JSON failures stay
 * inside the module.
 *
 * @phpstan-import-type payload from \Phalcon\DebugBar\DebugBarTypes
 * @phpstan-import-type history_meta from HistoryEntry
 * @phpstan-import-type stored_entry from HistoryEntry
 */
final class FilesystemHistory implements History
{
    private const GARBAGE_COLLECTION_MARKER               = '.gc';

    private const GARBAGE_COLLECTION_MAX_INTERVAL_SECONDS = 3600;

    private const METADATA_SUFFIX                         = '.meta';

    private readonly HistoryCookie $cookie;

    private readonly HistoryFileOperations $fileOperations;

    private bool $garbageCollectionAttempted = false;

    public function __construct(
        private readonly HistoryOptions $options,
        ?HistoryFileOperations $fileOperations = null,
        ?HistoryCookie $cookie = null
    ) {
        $this->fileOperations = $fileOperations ?? new NativeHistoryFileOperations();
        $this->cookie         = $cookie ?? HistoryCookie::fromGlobals();
    }

    /**
     * Removes every stored request belonging to the active debug bar browser.
     *
     * @return int Number of files successfully removed.
     */
    public function clear(): int
    {
        $directory = $this->browserDirectory(false);
        if (null === $directory) {
            return 0;
        }

        $removed = 0;
        foreach ($this->files($directory) as $file) {
            if ($this->fileOperations->remove($file)) {
                $removed++;
            }
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
        $directory = $this->browserDirectory(false);
        if (null === $directory) {
            return [];
        }
        $files = $this->removeExpired($this->files($directory));
        rsort($files, SORT_STRING);

        $requests = [];
        foreach ($files as $file) {
            $metadata = $this->readMetadata($file);
            if (null !== $metadata) {
                $requests[] = $metadata;
            }
            if (count($requests) >= $this->options->maxRequests) {
                break;
            }
        }

        return $requests;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $id): ?array
    {
        if (1 !== preg_match('/^[0-9]{14}-[0-9]{6}-[a-f0-9]{8}$/D', $id)) {
            return null;
        }

        $directory = $this->browserDirectory(false);
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
     * @param payload $payload
     */
    public function save(array $payload, RequestMetadata $request): ?string
    {
        $directory = $this->browserDirectory(true);
        if (null === $directory) {
            return null;
        }

        $storedAt    = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $id          = $storedAt->format('YmdHis-u-') . bin2hex(random_bytes(4));
        $entry       = HistoryEntry::create($payload, $request, $id, $storedAt);
        $storedEntry = $entry->toArray();

        $flags        = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        $json         = json_encode($storedEntry, $flags);
        $metadataJson = json_encode([
            'version' => HistoryEntry::VERSION,
            'meta'    => $entry->metadata(),
        ], $flags);
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
     * @return list<string>
     */
    private function browserDirectories(): array
    {
        $directories        = $this->matching($this->options->path, '*', GLOB_ONLYDIR);
        $browserDirectories = [];
        foreach ($directories as $directory) {
            if (1 === preg_match('/^[a-f0-9]{64}$/D', basename($directory))) {
                $browserDirectories[] = $directory;
            }
        }

        return $browserDirectories;
    }

    private function browserDirectory(bool $create): ?string
    {
        $browserId = $this->cookie->id();
        if (null === $browserId) {
            return null;
        }

        $directory = $this->options->path . '/' . hash('sha256', $browserId);
        if ($this->fileOperations->directoryExists($directory)) {
            return $this->fileOperations->isWritable($directory) ? $directory : null;
        }

        if (
            !$create
            || (
                !$this->fileOperations->createDirectory($directory, 0700, true)
                && !$this->fileOperations->directoryExists($directory)
            )
        ) {
            return null;
        }

        return $this->fileOperations->isWritable($directory) ? $directory : null;
    }

    /**
     * @return list<string>
     */
    private function files(string $directory): array
    {
        return $this->matching($directory, '*.json');
    }

    private function garbageCollect(): void
    {
        if ($this->garbageCollectionAttempted) {
            return;
        }

        $this->garbageCollectionAttempted = true;
        $marker                           = $this->options->path . '/' . self::GARBAGE_COLLECTION_MARKER;
        $modified                         = $this->fileOperations->modifiedAt($marker);
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

        foreach ($this->browserDirectories() as $directory) {
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
        $modified = $this->fileOperations->modifiedAt($file);

        return false !== $modified && $modified < time() - $this->options->ttlSeconds;
    }

    /**
     * @return list<string>
     */
    private function matching(string $directory, string $pattern, int $flags = 0): array
    {
        $escapedDirectory = (string) preg_replace('/([*?\[\]\\\\])/', '\\\\$1', $directory);

        return $this->fileOperations->matching($escapedDirectory . '/' . $pattern, $flags);
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
        return $this->matching($directory, '*.json' . self::METADATA_SUFFIX);
    }

    /**
     * @param history_meta $metadata
     */
    private function metadataMatchesFile(array $metadata, string $file): bool
    {
        return basename($file, '.json') === $metadata['id'];
    }

    private function prune(string $directory): void
    {
        $files = $this->removeExpired($this->files($directory));
        rsort($files, SORT_STRING);
        foreach (array_slice($files, $this->options->maxRequests) as $file) {
            $this->removeEntry($file);
        }
    }

    /**
     * @return stored_entry|null
     */
    private function read(string $file): ?array
    {
        if (!$this->fileOperations->fileExists($file)) {
            return null;
        }

        $json = $this->fileOperations->read($file);
        if (false === $json) {
            return null;
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        $entry = HistoryEntry::fromArray($decoded);

        return $entry?->toArray();
    }

    /**
     * @return history_meta|null
     */
    private function readMetadata(string $file): ?array
    {
        $metadataFile = $this->metadataFile($file);
        if ($this->fileOperations->fileExists($metadataFile)) {
            $json = $this->fileOperations->read($metadataFile);
            if (false !== $json) {
                $metadataEntry = json_decode($json, true);
                if (
                    is_array($metadataEntry)
                    && HistoryEntry::VERSION === ($metadataEntry['version'] ?? null)
                    && is_array($metadataEntry['meta'] ?? null)
                ) {
                    /** @var array<string, mixed> $metadataEntryData */
                    $metadataEntryData = $metadataEntry['meta'];
                    $metadata          = HistoryEntry::metadataFromArray($metadataEntryData);
                    if (null !== $metadata && $this->metadataMatchesFile($metadata, $file)) {
                        return $metadata;
                    }
                }
            }
        }

        $entry = $this->read($file);
        if (null === $entry || !$this->metadataMatchesFile($entry['meta'], $file)) {
            return null;
        }

        return $entry['meta'];
    }

    private function removeDirectoryIfEmpty(string $directory): void
    {
        if ([] === $this->matching($directory, '*')) {
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
        if (!$this->fileOperations->move($temporary, $target)) {
            $this->fileOperations->remove($temporary);
            $this->fileOperations->remove($metadataTemporary);

            return false;
        }
        if (!$this->fileOperations->move($metadataTemporary, $metadataTarget)) {
            $this->fileOperations->remove($metadataTemporary);
            $this->fileOperations->remove($target);

            return false;
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function temporaryFiles(string $directory): array
    {
        return $this->matching($directory, '*.tmp-*');
    }
}
