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
use function file_get_contents;
use function file_put_contents;
use function glob;
use function hash;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function json_decode;
use function json_encode;
use function mkdir;
use function preg_match;
use function random_bytes;
use function rename;
use function rsort;
use function session_id;
use function session_status;
use function time;
use function unlink;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const LOCK_EX;
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
    /**
     * @param HistoryOptions $options
     */
    public function __construct(private readonly HistoryOptions $options)
    {
    }

    /**
     * Removes every stored request belonging to the active PHP session.
     *
     * @return int Number of files successfully removed.
     */
    public function clear(): int
    {
        $directory = $this->sessionDirectory(false);
        if (null === $directory) {
            return 0;
        }

        $removed = 0;
        foreach ($this->files($directory) as $file) {
            if (@unlink($file)) {
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function find(): array
    {
        $directory = $this->sessionDirectory(false);
        if (null === $directory) {
            return [];
        }

        $files = $this->files($directory);
        $this->removeExpired($files);
        $files = array_slice($this->files($directory), 0, $this->options->maxRequests);

        $requests = [];
        foreach ($files as $file) {
            $entry = $this->read($file);
            if (null !== $entry) {
                $requests[] = $entry['meta'];
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

        $directory = $this->sessionDirectory(false);
        if (null === $directory) {
            return null;
        }

        return $this->read($directory . '/' . basename($id) . '.json');
    }

    /**
     * @param payload         $payload
     * @param RequestMetadata $request
     *
     * @return string|null
     */
    public function save(array $payload, RequestMetadata $request): ?string
    {
        $directory = $this->sessionDirectory(true);
        if (null === $directory) {
            return null;
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $id  = $now->format('YmdHis-u-') . bin2hex(random_bytes(4));

        $entry = [
            'meta' => [
                'requested_at' => $now->format(DATE_ATOM),
                'method'       => $request->method,
                'uri'          => $request->uri,
                'status'       => $request->status,
                'ajax'         => $request->ajax,
                'id'           => $id,
                'stored_at'    => $now->format(DATE_ATOM),
            ],
            'payload' => $payload,
        ];

        $json = json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (false === $json) {
            return null;
        }

        $target    = $directory . '/' . $id . '.json';
        $temporary = $target . '.tmp-' . bin2hex(random_bytes(4));
        if (false === file_put_contents($temporary, $json, LOCK_EX)) {
            return null;
        }

        if (!rename($temporary, $target)) {
            @unlink($temporary);

            return null;
        }

        $this->prune($directory);

        return $id;
    }

    /**
     * @param string $directory
     *
     * @return list<string>
     */
    private function files(string $directory): array
    {
        $files = glob($directory . '/*.json');
        if (false === $files) {
            return [];
        }

        rsort($files, SORT_STRING);

        return $files;
    }

    /**
     * @param string $directory
     *
     * @return void
     */
    private function prune(string $directory): void
    {
        $files = $this->files($directory);
        $this->removeExpired($files);

        foreach (array_slice($this->files($directory), $this->options->maxRequests) as $file) {
            @unlink($file);
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

        $json = file_get_contents($file);
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
     * @param list<string> $files
     *
     * @return void
     */
    private function removeExpired(array $files): void
    {
        $oldest = time() - $this->options->ttlSeconds;

        foreach ($files as $file) {
            $modified = @filemtime($file);
            if (false !== $modified && $modified < $oldest) {
                @unlink($file);
            }
        }
    }

    /**
     * @param bool $create
     *
     * @return string|null
     */
    private function sessionDirectory(bool $create): ?string
    {
        if (PHP_SESSION_ACTIVE !== session_status()) {
            return null;
        }

        $sessionId = session_id();
        if (!is_string($sessionId) || '' === $sessionId) {
            return null;
        }

        $directory = $this->options->path . '/' . hash('sha256', $sessionId);
        if (is_dir($directory)) {
            return $directory;
        }

        if (!$create || (!@mkdir($directory, 0700, true) && !is_dir($directory))) {
            return null;
        }

        return $directory;
    }
}
