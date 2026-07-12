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

namespace Phalcon\DebugBar;

use Throwable;

/**
 * Static entry point for manual instrumentation (`Debug::info()`,
 * `Debug::startMeasure()`, …). It holds a reference to the active bar, set by
 * the registration `Provider`; every call is `self::$bar?->…`, so it no-ops
 * when the bar was never registered (any blocked environment).
 */
final class Debug
{
    /**
     * @var DebugBar|null
     */
    private static ?DebugBar $bar = null;

    private function __construct()
    {
    }

    /**
     * @param Throwable $throwable
     *
     * @return void
     */
    public static function addException(Throwable $throwable): void
    {
        self::$bar?->addException($throwable);
    }

    /**
     * @param mixed ...$args
     *
     * @return void
     */
    public static function debug(mixed ...$args): void
    {
        self::$bar?->debug(...$args);
    }

    /**
     * @param mixed ...$args
     *
     * @return void
     */
    public static function error(mixed ...$args): void
    {
        self::$bar?->error(...$args);
    }

    /**
     * @return DebugBar|null
     */
    public static function getBar(): ?DebugBar
    {
        return self::$bar;
    }

    /**
     * @param mixed ...$args
     *
     * @return void
     */
    public static function info(mixed ...$args): void
    {
        self::$bar?->info(...$args);
    }

    /**
     * @param mixed  $message
     * @param string $label
     *
     * @return void
     */
    public static function message(mixed $message, string $label = 'info'): void
    {
        self::$bar?->message($message, $label);
    }

    /**
     * @param mixed ...$args
     *
     * @return void
     */
    public static function notice(mixed ...$args): void
    {
        self::$bar?->notice(...$args);
    }

    /**
     * @param DebugBar|null $bar
     *
     * @return void
     */
    public static function setBar(?DebugBar $bar): void
    {
        self::$bar = $bar;
    }

    /**
     * @param string      $name
     * @param string|null $label
     *
     * @return void
     */
    public static function startMeasure(string $name, ?string $label = null): void
    {
        self::$bar?->startMeasure($name, $label);
    }

    /**
     * @param string $name
     *
     * @return void
     */
    public static function stopMeasure(string $name): void
    {
        self::$bar?->stopMeasure($name);
    }

    /**
     * @param mixed ...$args
     *
     * @return void
     */
    public static function warning(mixed ...$args): void
    {
        self::$bar?->warning(...$args);
    }
}
