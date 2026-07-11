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

namespace Phalcon\DebugBar\Security;

use function in_array;
use function is_array;
use function mb_strtolower;

/**
 * Replaces the values of sensitive keys with a mask before data leaves PHP.
 * Applied by the Request/Session/Config/Database collectors (Phase 3), matching
 * keys case-insensitively through nested arrays.
 */
final class Redactor
{
    public const MASK = '***';

    /**
     * @var list<string>
     */
    private array $keys;

    /**
     * @param list<string> $keys
     */
    public function __construct(
        array $keys = ['password', 'secret', 'token', 'key', 'authorization', 'cookie', 'csrf']
    ) {
        $lowered = [];
        foreach ($keys as $key) {
            $lowered[] = mb_strtolower($key);
        }

        $this->keys = $lowered;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    public function redact(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(mb_strtolower($key), $this->keys, true)) {
                $result[$key] = self::MASK;

                continue;
            }

            $result[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $result;
    }
}
