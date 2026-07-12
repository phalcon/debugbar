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
 * Guards data before it leaves PHP, matching keys case-insensitively through
 * nested arrays. Sensitive keys are masked (key shown, value replaced); "hidden"
 * keys are dropped entirely — mirroring the blacklist `Debug` already applies.
 * Applied by the Request/Session/Config collectors.
 */
final class Redactor
{
    public const DEFAULT_KEYS = ['password', 'secret', 'token', 'key', 'authorization', 'cookie', 'csrf'];
    public const MASK         = '***';

    /**
     * @var list<string>
     */
    private array $hidden;

    /**
     * @var list<string>
     */
    private array $keys;

    /**
     * @param list<string> $keys
     * @param list<string> $hidden
     */
    public function __construct(array $keys = self::DEFAULT_KEYS, array $hidden = [])
    {
        $this->keys   = $this->lower($keys);
        $this->hidden = $this->lower($hidden);
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
            if (is_string($key)) {
                $lower = mb_strtolower($key);

                if (in_array($lower, $this->hidden, true)) {
                    continue;
                }

                if (in_array($lower, $this->keys, true)) {
                    $result[$key] = self::MASK;

                    continue;
                }
            }

            $result[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $result;
    }

    /**
     * @param list<string> $keys
     *
     * @return list<string>
     */
    private function lower(array $keys): array
    {
        $lowered = [];
        foreach ($keys as $key) {
            $lowered[] = mb_strtolower($key);
        }

        return $lowered;
    }
}
