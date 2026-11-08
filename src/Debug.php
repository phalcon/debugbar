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

namespace Phalcon;

use Phalcon\Debug\Contracts\Renderer;
use Phalcon\Debug\Exceptions\RequestHalted;
use Phalcon\Debug\Exceptions\RuntimeWarning;
use Phalcon\Debug\Renderer\HtmlRenderer;
use Phalcon\Debug\Report\ReportOptions;
use Phalcon\Debug\Report\Superglobals;
use Phalcon\Debug\ReportBuilder;
use Phalcon\Traits\Support\Helper\Arr\GetTrait;
use ReflectionException;
use Throwable;

use function htmlspecialchars;
use function mb_strtolower;

use const DEBUG_BACKTRACE_IGNORE_ARGS;
use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * Listens for uncaught exceptions and renders them. Acts as a thin coordinator
 * delegating data collection to ReportBuilder and presentation to a Renderer.
 *
 * @property array<array-key, mixed> $blacklist
 * @property array<array-key, mixed> $data
 * @property bool                    $isActive
 * @property int                     $outputLevel
 * @property Renderer                $renderer
 * @property ReportBuilder           $reportBuilder
 * @property bool                    $showBackTrace
 * @property bool                    $showFileFragment
 * @property bool                    $showFiles
 * @property Superglobals|null       $superglobals
 * @property string                  $uri
 */
class Debug
{
    use GetTrait;

    protected static bool $isActive = false;

    /**
     * @var array<array-key, mixed>
     */
    protected array $blacklist = ["request" => [], "server" => []];

    /**
     * @var array<array-key, mixed>
     */
    protected array $data = [];

    protected Renderer $renderer;

    protected ReportBuilder $reportBuilder;

    protected bool $showBackTrace = true;

    protected bool $showFileFragment = false;

    protected bool $showFiles = true;

    protected string $uri = "https://assets.phalcon.io/debug/6.0.x/";

    private int $outputLevel;

    private ?Superglobals $superglobals = null;

    public function __construct()
    {
        $this->outputLevel   = ob_get_level();
        $this->renderer      = new HtmlRenderer();
        $this->reportBuilder = new ReportBuilder();
    }

    /**
     * Clears the variables added previously
     *
     * @return $this
     */
    public function clearVars(): static
    {
        $this->data = [];

        return $this;
    }

    /**
     * Adds a variable to the debug output
     *
     * @return $this
     */
    public function debugVar(mixed $variable): static
    {
        $this->data[] = [
            $variable,
            debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
            time(),
        ];

        return $this;
    }

    /**
     * Returns the CSS sources
     */
    public function getCssSources(): string
    {
        return $this->renderer->getCssSources($this->uri);
    }

    /**
     * Returns the JavaScript sources
     */
    public function getJsSources(): string
    {
        return $this->renderer->getJsSources($this->uri);
    }

    /**
     * Returns the renderer used to produce the output
     */
    public function getRenderer(): Renderer
    {
        return $this->renderer;
    }

    /**
     * Generates a link to the current version documentation
     */
    public function getVersion(): string
    {
        return $this->renderer->getVersion();
    }

    /**
     * Halts the request showing a backtrace
     *
     * @throws RequestHalted
     */
    public function halt(): void
    {
        throw new RequestHalted();
    }

    /**
     * Listen for uncaught exceptions and non silent notices or warnings
     *
     * @return $this
     */
    public function listen(
        bool $exceptions = true,
        bool $lowSeverity = false
    ): static {
        if (true === $exceptions) {
            $this->listenExceptions();
        }

        if (true === $lowSeverity) {
            $this->listenLowSeverity();
        }

        return $this;
    }

    /**
     * Listen for uncaught exceptions
     *
     * @return $this
     */
    public function listenExceptions(): static
    {
        // @phpstan-ignore argument.type (bool return is unused by set_exception_handler)
        set_exception_handler($this->onUncaughtException(...));

        return $this;
    }

    /**
     * Listen for non silent notices or warnings
     *
     * @return $this
     */
    public function listenLowSeverity(): static
    {
        // @phpstan-ignore argument.type (a void return is treated as a non-false return)
        set_error_handler($this->onUncaughtLowSeverity(...));
        // @phpstan-ignore argument.type (bool return is unused by set_exception_handler)
        set_exception_handler($this->onUncaughtException(...));

        return $this;
    }

    /**
     * Handles uncaught exceptions
     *
     * @throws ReflectionException
     */
    public function onUncaughtException(Throwable $exception): bool
    {
        /**
         * Cancel the output buffers that started after this component was
         * created. Stop if a buffer cannot be removed.
         */
        $discarded = true;
        while ($discarded && ob_get_level() > $this->outputLevel) {
            $discarded = ob_end_clean();
        }

        /**
         * Avoid that multiple exceptions being showed
         */
        if (true !== self::$isActive) {
            /**
             * Globally block the debug component to avoid other exceptions to be shown
             */
            self::$isActive = true;

            try {
                /**
                 * Print the HTML, @TODO, add an option to store the HTML
                 */
                echo $this->renderHtml($exception);
            } finally {
                /**
                 * Unlock the exception renderer, also when rendering fails
                 */
                self::$isActive = false;
            }

            return true;
        }

        echo htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return false;
    }

    /**
     * Throws an exception when a notice or warning is raised
     *
     * @throws RuntimeWarning
     */
    public function onUncaughtLowSeverity(
        int $severity,
        string $message,
        string $file,
        int $line
    ): void {
        if (error_reporting() & $severity) {
            throw new RuntimeWarning($message, 0, $severity, $file, $line);
        }
    }

    /**
     * Render exception to html format.
     *
     * @throws ReflectionException
     */
    public function renderHtml(Throwable $exception): string
    {
        $options = new ReportOptions(
            $this->blacklist,
            $this->showBackTrace,
            $this->showFiles,
            $this->showFileFragment,
            $this->uri,
            $this->data
        );

        $superglobals = $this->superglobals ?? Superglobals::fromGlobals();

        return $this->renderer->render(
            $this->reportBuilder->build($exception, $options, $superglobals)
        );
    }

    /**
     * Sets the keys to remove from the request and the server data. The
     * array has a `request` and a `server` list of keys. A key matches at
     * every depth.
     *
     * @param array<array-key, mixed> $blacklist
     *
     * @return $this
     */
    public function setBlacklist(array $blacklist): static
    {
        /** @var array<int, string> $request */
        $request = $this->getArrVal($blacklist, 'request', []);
        /** @var array<int, string> $server */
        $server = $this->getArrVal($blacklist, 'server', []);

        $this->blacklist = [
            'request' => $this->normalizeBlacklistArea($request),
            'server'  => $this->normalizeBlacklistArea($server),
        ];

        return $this;
    }

    /**
     * Sets the renderer used to produce the output
     *
     * @return $this
     */
    public function setRenderer(Renderer $renderer): static
    {
        $this->renderer = $renderer;

        return $this;
    }

    /**
     * Sets if files the exception's backtrace must be showed
     *
     * @return $this
     */
    public function setShowBackTrace(bool $showBackTrace): static
    {
        $this->showBackTrace = $showBackTrace;

        return $this;
    }

    /**
     * Sets if files must be completely opened and showed in the output
     * or just the fragment related to the exception
     *
     * @return $this
     */
    public function setShowFileFragment(bool $showFileFragment): static
    {
        $this->showFileFragment = $showFileFragment;

        return $this;
    }

    /**
     * Set if files part of the backtrace must be shown in the output
     *
     * @return $this
     */
    public function setShowFiles(bool $showFiles): static
    {
        $this->showFiles = $showFiles;

        return $this;
    }

    /**
     * Injects a fixed superglobals snapshot, bypassing the lazy read from
     * $_REQUEST/$_SERVER performed at render time.
     *
     * @return $this
     */
    public function setSuperglobals(Superglobals $superglobals): static
    {
        $this->superglobals = $superglobals;

        return $this;
    }

    /**
     * Change the base URI for static resources
     *
     * @return $this
     */
    public function setUri(string $uri): static
    {
        $this->uri = $uri;

        return $this;
    }

    /**
     * Normalizes a single blacklist area into a lowercased lookup map.
     *
     * @param array<int, string> $area
     *
     * @return array<string, int>
     */
    private function normalizeBlacklistArea(array $area): array
    {
        $result = [];
        foreach ($area as $value) {
            $result[mb_strtolower($value)] = 1;
        }

        return $result;
    }
}
