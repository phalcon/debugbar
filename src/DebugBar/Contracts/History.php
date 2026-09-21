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

namespace Phalcon\DebugBar\Contracts;

use Phalcon\DebugBar\DebugBarTypes;
use Phalcon\DebugBar\History\RequestMetadata;

/**
 * Request storage isolated by the debug bar's browser cookie. Without a valid
 * cookie, reads return empty results and writes do nothing. Unavailable storage
 * and missing or expired entries are represented by empty results, not exceptions.
 *
 * @phpstan-import-type payload from DebugBarTypes
 */
interface History
{
    /**
     * Removes the current browser's entries and returns the number removed.
     */
    public function clear(): int;

    /**
     * Returns unexpired request metadata, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function find(): array;

    /**
     * Returns a stored entry, or null for an invalid, missing or expired ID.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $id): ?array;

    /**
     * @param payload $payload
     *
     * @return string|null The stored ID, or null when persistence is unavailable.
     */
    public function save(array $payload, RequestMetadata $request): ?string;
}
