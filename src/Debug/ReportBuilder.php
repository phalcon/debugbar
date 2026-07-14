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

namespace Phalcon\Debug;

use Phalcon\Debug\Report\BacktraceItem;
use Phalcon\Debug\Report\CodeFragment;
use Phalcon\Debug\Report\ExceptionReport;
use Phalcon\Debug\Report\ReportOptions;
use Phalcon\Debug\Report\Superglobals;
use Phalcon\Traits\Php\InfoTrait;
use Phalcon\Traits\Support\Helper\Arr\GetTrait;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use Throwable;

use function count;
use function explode;
use function file;
use function get_class;
use function get_included_files;
use function mb_strtolower;
use function memory_get_peak_usage;
use function memory_get_usage;
use function str_replace;
use function str_starts_with;

/**
 * Collects the runtime data for an exception (backtrace, superglobals, included
 * files, memory, variables) into an ExceptionReport. Holds no presentation
 * logic.
 */
class ReportBuilder
{
    use GetTrait;
    use InfoTrait;

    /**
     * @param Throwable     $exception
     * @param ReportOptions $options
     * @param Superglobals  $superglobals
     *
     * @return ExceptionReport
     * @throws ReflectionException
     */
    public function build(
        Throwable $exception,
        ReportOptions $options,
        Superglobals $superglobals
    ): ExceptionReport {
        $showBackTrace = $options->getShowBackTrace();

        $report = new ExceptionReport(
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $showBackTrace,
            $options->getUri()
        );

        if (true !== $showBackTrace) {
            return $report;
        }

        $items = [];
        foreach ($exception->getTrace() as $trace) {
            $items[] = $this->buildItem(
                $trace,
                $options->getShowFiles(),
                $options->getShowFileFragment()
            );
        }

        $blacklist = $options->getBlacklist();
        $request   = $this->filter(
            $superglobals->getRequest(),
            (array) $this->getArrVal($blacklist, 'request', [])
        );
        $server    = $this->filter(
            $superglobals->getServer(),
            (array) $this->getArrVal($blacklist, 'server', [])
        );

        $report
            ->setBacktrace($items)
            ->setRequest($request)
            ->setServer($server)
            ->setIncludedFiles(get_included_files())
            ->setMemoryUsage(memory_get_usage(true))
            ->setPeakMemoryUsage(memory_get_peak_usage(true))
            ->setVariables($options->getData());

        return $report;
    }

    /**
     * @param string $file
     * @param int    $line
     * @param bool   $showFileFragment
     *
     * @return CodeFragment
     */
    private function buildFragment(string $file, int $line, bool $showFileFragment): CodeFragment
    {
        $lines = file($file);
        if (false === $lines) {
            $lines = [];
        }

        $numberLines = count($lines);

        if (true === $showFileFragment) {
            $beforeLine = $line - 7;
            $firstLine  = ($beforeLine < 1) ? 1 : $beforeLine;
            $afterLine  = $line + 5;
            $lastLine   = ($afterLine > $numberLines) ? $numberLines : $afterLine;
            $mode       = 'fragment';
        } else {
            $firstLine = 1;
            $lastLine  = $numberLines;
            $mode      = 'full';
        }

        return new CodeFragment($mode, $firstLine, $line, $lastLine, $lines);
    }

    /**
     * @param array{
     *     function: string,
     *     line?: int,
     *     file?: string,
     *     class?: class-string,
     *     type?: '->'|'::',
     *     args?: array<array-key, mixed>,
     *     object?: object
     * } $trace
     * @param bool  $showFiles
     * @param bool  $showFileFragment
     *
     * @return BacktraceItem
     * @throws ReflectionException
     */
    private function buildItem(array $trace, bool $showFiles, bool $showFileFragment): BacktraceItem
    {
        $className    = null;
        $classLink    = null;
        $type         = null;
        $functionLink = null;

        if (isset($trace['class'])) {
            $className = $trace['class'];
            $type      = $trace['type'] ?? null;
            $classLink = $this->resolveClassLink($className);
        }

        $functionName = $trace['function'];
        if (!isset($trace['class'])) {
            $functionLink = $this->resolveFunctionLink($functionName);
        }

        $hasArgs = isset($trace['args']);
        $args    = $trace['args'] ?? [];

        $file     = null;
        $line     = null;
        $fragment = null;
        if (isset($trace['file'], $trace['line'])) {
            $file = $trace['file'];
            $line = $trace['line'];

            if (true === $showFiles) {
                $fragment = $this->buildFragment($file, $line, $showFileFragment);
            }
        }

        return new BacktraceItem(
            $functionName,
            $type,
            $className,
            $classLink,
            $functionLink,
            $hasArgs,
            $args,
            $file,
            $line,
            $fragment
        );
    }

    /**
     * @param array<array-key, mixed> $source
     * @param array<array-key, mixed> $blacklist
     *
     * @return array<array-key, mixed>
     */
    private function filter(array $source, array $blacklist): array
    {
        $result = [];
        foreach ($source as $key => $value) {
            if (!isset($blacklist[mb_strtolower((string) $key)])) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * @param class-string $className
     *
     * @return string|null
     * @throws ReflectionException
     */
    private function resolveClassLink(string $className): string | null
    {
        if (str_starts_with($className, 'Phalcon')) {
            $parts = explode('\\', $className);

            return 'https://docs.phalcon.io/6.0/en/api/' . $parts[0] . '_' . $parts[1];
        }

        $reflection = new ReflectionClass($className);
        if (true === $reflection->isInternal()) {
            $prepared = str_replace('_', '-', mb_strtolower($className));

            return 'https://secure.php.net/manual/en/class.' . $prepared . '.php';
        }

        return null;
    }

    /**
     * @param string $functionName
     *
     * @return string|null
     * @throws ReflectionException
     */
    private function resolveFunctionLink(string $functionName): string | null
    {
        if (true !== $this->phpFunctionExists($functionName)) {
            return null;
        }

        $reflection = new ReflectionFunction($functionName);
        if (true !== $reflection->isInternal()) {
            return null;
        }

        $prepared = str_replace('_', '-', $functionName);

        return 'https://secure.php.net/manual/en/function.' . $prepared . '.php';
    }
}
