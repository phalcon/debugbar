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

use function http_build_query;
use function in_array;
use function is_array;
use function mb_strtolower;
use function parse_str;
use function rawurlencode;
use function str_replace;
use function strpos;
use function substr;

/**
 * Guards data before it leaves PHP, matching keys case-insensitively through
 * nested arrays. Sensitive keys are masked (key shown, value replaced); "hidden"
 * keys are dropped entirely - mirroring the blacklist `Debug` already applies.
 * Applied by the Request/Session/Config/Logger collectors, and to the URI that
 * request history stores.
 */
final class Redactor
{
    public const DEFAULT_KEYS = [
        'authorization',
        'cookie',
        'csrf',
        'key',
        'password',
        'secret',
        'token',
    ];

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
     * Applies the same rule to the query of a URI. A URI that has nothing to redact
     * stays unchanged. A rebuilt query can encode other values in a different way.
     */
    public function redactUri(string $uri): string
    {
        $position = strpos($uri, '?');
        if (false === $position) {
            return $uri;
        }

        parse_str(substr($uri, $position + 1), $values);
        $redacted = $this->redact($values);
        if ($redacted === $values) {
            return $uri;
        }

        $path  = substr($uri, 0, $position);
        $query = str_replace(rawurlencode(self::MASK), self::MASK, http_build_query($redacted));

        return '' === $query ? $path : $path . '?' . $query;
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
