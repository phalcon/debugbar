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

use function array_map;
use function array_slice;
use function count;
use function explode;
use function http_build_query;
use function implode;
use function is_array;
use function mb_strtolower;
use function parse_str;
use function preg_match;
use function preg_quote;
use function preg_split;
use function rawurlencode;
use function str_replace;
use function strpos;
use function substr;

use const PREG_SPLIT_NO_EMPTY;

/**
 * Guards data before it leaves PHP. It looks at the keys through nested arrays, and
 * the match is not case-sensitive. A key matches an entry when it has the same name,
 * or when it has the word parts of the entry next to each other. The word parts come
 * from `_`, `-`, `.` and camelCase. A hidden key is dropped. A masked key keeps its
 * name and its value becomes `***`, but an unmask pattern that matches the whole key
 * stops the mask. The Request, Session, Config and Logger collectors use it, and so
 * does the URI that request history stores.
 */
final class Redactor
{
    public const DEFAULT_KEYS = [
        'authorization',
        'cookie',
        'csrf',
        'key',
        'passwd',
        'password',
        'php-auth',
        'pw',
        'pwd',
        'secret',
        'token',
    ];

    public const MASK         = '***';

    /**
     * Splits on `_`, `-` and `.`, before an upper case letter that follows a lower
     * case letter or a digit, and between an acronym and the next word.
     */
    private const PARTS = '/[_.\-]+|(?<=[a-z\d])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/';

    /**
     * @var list<array{0: string, 1: list<string>}>
     */
    private array $hidden;

    /**
     * @var list<array{0: string, 1: list<string>}>
     */
    private array $keys;

    /**
     * @var list<string>
     */
    private array $unmask;

    /**
     * @param list<string> $keys
     * @param list<string> $hidden
     * @param list<string> $unmask
     */
    public function __construct(array $keys = self::DEFAULT_KEYS, array $hidden = [], array $unmask = [])
    {
        $this->keys   = $this->entries($keys);
        $this->hidden = $this->entries($hidden);
        $this->unmask = array_map($this->pattern(...), $unmask);
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
                $parts = $this->parts($key);

                if ($this->matches($lower, $parts, $this->hidden)) {
                    continue;
                }

                if ($this->matches($lower, $parts, $this->keys) && !$this->isUnmasked($lower)) {
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
     * @param list<string> $names
     *
     * @return list<array{0: string, 1: list<string>}>
     */
    private function entries(array $names): array
    {
        $entries = [];
        foreach ($names as $name) {
            $entries[] = [mb_strtolower($name), $this->parts($name)];
        }

        return $entries;
    }

    private function isUnmasked(string $lower): bool
    {
        foreach ($this->unmask as $pattern) {
            if (1 === preg_match($pattern, $lower)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string>                            $parts
     * @param list<array{0: string, 1: list<string>}> $entries
     */
    private function matches(string $lower, array $parts, array $entries): bool
    {
        foreach ($entries as [$name, $needle]) {
            if ($lower === $name) {
                return true;
            }

            $last = count($parts) - count($needle);
            for ($index = 0; $index <= $last; $index++) {
                if (array_slice($parts, $index, count($needle)) === $needle) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Returns the word parts of a name in lower case. A name with no word part, such
     * as `_`, is one part.
     *
     * @return list<string>
     */
    private function parts(string $name): array
    {
        $parts = preg_split(self::PARTS, $name, -1, PREG_SPLIT_NO_EMPTY) ?: [$name];

        return array_map(mb_strtolower(...), $parts);
    }

    /**
     * Returns a pattern that matches the whole key in lower case. `*` matches zero or
     * more characters. Each other character matches only itself.
     */
    private function pattern(string $entry): string
    {
        $literals = array_map(
            static fn (string $literal): string => preg_quote($literal, '/'),
            explode('*', mb_strtolower($entry))
        );

        return '/\A' . implode('.*', $literals) . '\z/s';
    }
}
