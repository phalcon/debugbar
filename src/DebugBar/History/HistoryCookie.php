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

use Closure;

use function bin2hex;
use function is_string;
use function preg_match;
use function random_bytes;
use function setcookie;

/**
 * Browser identity used only by request history. It never reads, starts, or
 * changes the host application's PHP session.
 */
final class HistoryCookie
{
    public const NAME = 'phalcon-debugbar-history';

    private const ID_PATTERN = '/^[a-f0-9]{64}$/D';

    private readonly Closure $setCookie;

    public function __construct(private readonly ?string $value, ?callable $setCookie = null)
    {
        $this->setCookie = null === $setCookie
            ? setcookie(...)
            : Closure::fromCallable($setCookie);
    }

    public static function fromGlobals(): self
    {
        $value = $_COOKIE[self::NAME] ?? null;

        return new self(is_string($value) ? $value : null);
    }

    public function id(): ?string
    {
        return 1 === preg_match(self::ID_PATTERN, $this->value ?? '') ? $this->value : null;
    }

    public function queue(string $path, bool $secure): void
    {
        if (null !== $this->id()) {
            return;
        }

        ($this->setCookie)(
            self::NAME,
            bin2hex(random_bytes(32)),
            [
                'path'     => $path,
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }
}
