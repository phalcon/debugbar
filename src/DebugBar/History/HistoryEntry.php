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
use Phalcon\DebugBar\DebugBarTypes;

use function is_array;
use function is_bool;
use function is_int;
use function is_string;

/**
 * Versioned storage boundary for request-history payloads.
 *
 * @phpstan-import-type payload from DebugBarTypes
 * @phpstan-type history_meta array{
 *     requested_at: string,
 *     method: string,
 *     uri: string,
 *     status: int,
 *     ajax: bool,
 *     id: string,
 *     stored_at: string
 * }
 * @phpstan-type stored_entry array{version: int, meta: history_meta, payload: payload}
 */
final class HistoryEntry
{
    public const VERSION = 1;

    /**
     * @param history_meta $metadata
     * @param payload      $payload
     */
    private function __construct(
        private readonly array $metadata,
        private readonly array $payload
    ) {
    }

    /**
     * @param payload $payload
     */
    public static function create(
        array $payload,
        RequestMetadata $request,
        string $id,
        DateTimeImmutable $storedAt
    ): self {
        $requestedAt = $request->requestedAt ?? $storedAt;

        return new self(
            [
                'requested_at' => $requestedAt->format(DATE_ATOM),
                'method'       => $request->method,
                'uri'          => $request->uri,
                'status'       => $request->status,
                'ajax'         => $request->ajax,
                'id'           => $id,
                'stored_at'    => $storedAt->format(DATE_ATOM),
            ],
            $payload
        );
    }

    /**
     * @param array<string, mixed> $entry
     */
    public static function fromArray(array $entry): ?self
    {
        $metadata = $entry['meta'] ?? null;
        $payload  = $entry['payload'] ?? null;
        if (self::VERSION !== ($entry['version'] ?? null) || !is_array($metadata) || !is_array($payload)) {
            return null;
        }

        /** @var array<string, mixed> $metadata */
        /** @var array<string, mixed> $payload */
        if (
            !self::metadataIsValid($metadata)
            || !is_array($payload['data'] ?? null)
            || !is_array($payload['meta'] ?? null)
        ) {
            return null;
        }

        /** @var history_meta $metadata */
        /** @var payload $payload */
        return new self($metadata, $payload);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private static function metadataIsValid(array $metadata): bool
    {
        return is_string($metadata['requested_at'] ?? null)
            && is_string($metadata['method'] ?? null)
            && is_string($metadata['uri'] ?? null)
            && is_int($metadata['status'] ?? null)
            && is_bool($metadata['ajax'] ?? null)
            && is_string($metadata['id'] ?? null)
            && is_string($metadata['stored_at'] ?? null);
    }

    /**
     * @return history_meta
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return stored_entry
     */
    public function toArray(): array
    {
        return [
            'version' => self::VERSION,
            'meta'    => $this->metadata,
            'payload' => $this->payload,
        ];
    }
}
