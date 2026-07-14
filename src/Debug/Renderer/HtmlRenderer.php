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

namespace Phalcon\Debug\Renderer;

use Phalcon\Debug\Contracts\Renderer;
use Phalcon\Debug\Report\BacktraceItem;
use Phalcon\Debug\Report\CodeFragment;
use Phalcon\Debug\Report\ExceptionReport;
use Phalcon\Debug\Template\HtmlTemplateCatalog;
use Phalcon\Debug\Template\TemplateStore;
use Phalcon\Support\Version;
use Phalcon\Traits\Support\Helper\Str\InterpolateTrait;

use function count;
use function htmlentities;
use function implode;
use function number_format;
use function rtrim;
use function str_replace;
use function strpos;

use const ENT_COMPAT;
use const PHP_VERSION;

/**
 * Renders an ExceptionReport as the HTML debug page using embedded, overridable
 * template strings filled by the interpolator. Assembly only: the template
 * strings live in HtmlTemplateCatalog (via TemplateStore) and value formatting
 * in ValueDumper. All styling and interactivity (theme, tabs, syntax
 * highlighting, copy/editor links) are provided by the external
 * debug.css / debug.js assets.
 */
class HtmlRenderer implements Renderer
{
    use InterpolateTrait;

    /**
     * @var TemplateStore
     */
    private TemplateStore $templates;

    /**
     * @var ValueDumper
     */
    private ValueDumper $values;

    public function __construct()
    {
        $this->templates = new TemplateStore(new HtmlTemplateCatalog());
        $this->values    = new ValueDumper();
    }

    /**
     * @param string $uri
     *
     * @return string
     */
    public function getCssSources(string $uri): string
    {
        return $this->toInterpolate(
            $this->getTemplate('cssLink'),
            ['uri' => $uri, 'path' => 'debug.css']
        );
    }

    /**
     * @param string $uri
     *
     * @return string
     */
    public function getJsSources(string $uri): string
    {
        return $this->toInterpolate(
            $this->getTemplate('jsLink'),
            ['uri' => $uri, 'path' => 'debug.js']
        );
    }

    /**
     * @param string $name
     *
     * @return string
     */
    public function getTemplate(string $name): string
    {
        return $this->templates->get($name);
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        $version = new Version();
        $link    = "https://docs.phalcon.io/"
            . $version->getPart(Version::VERSION_MAJOR)
            . "."
            . $version->getPart(Version::VERSION_MEDIUM)
            . "/";

        return $this->toInterpolate(
            $this->getTemplate('version'),
            [
                'link'    => $link,
                'version' => $version->get(),
            ]
        );
    }

    /**
     * @param ExceptionReport $report
     *
     * @return string
     */
    public function render(ExceptionReport $report): string
    {
        $className      = $report->getClassName();
        $escapedMessage = $this->values->escape($report->getMessage());

        $html = $this->toInterpolate(
            $this->getTemplate('document'),
            [
                'className'      => $className,
                'escapedMessage' => $escapedMessage,
                'cssSources'     => $this->getCssSources($report->getUri()),
            ]
        );

        $html .= $this->toInterpolate(
            $this->getTemplate('masthead'),
            ['version' => $this->getVersion()]
        );

        $html .= $this->toInterpolate(
            $this->getTemplate('errorMain'),
            [
                'className'      => $className,
                'escapedMessage' => $escapedMessage,
                'file'           => $report->getFile(),
                'line'           => (string)$report->getLine(),
                'phpVersion'     => PHP_VERSION,
            ]
        );

        if (true === $report->isShowBackTrace()) {
            $html .= $this->renderTabs($report);
            $html .= $this->renderBacktrace($report->getBacktrace());
            $html .= $this->renderSuperglobal('request', $report->getRequest());
            $html .= $this->renderSuperglobal('server', $report->getServer());
            $html .= $this->renderIncludedFiles($report->getIncludedFiles());
            $html .= $this->renderMemory($report);
            $html .= $this->renderVariables($report->getVariables());
        }

        return $html
            . $this->getTemplate('wrapClose')
            . $this->getJsSources($report->getUri())
            . $this->getTemplate('documentClose');
    }

    /**
     * @param string $name
     * @param string $template
     *
     * @return static
     */
    public function setTemplate(string $name, string $template): static
    {
        $this->templates->set($name, $template);

        return $this;
    }

    /**
     * @param int $bytes
     *
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        return number_format($bytes / 1048576, 1);
    }

    /**
     * Frames whose file lives outside a vendor directory are application code.
     *
     * @param string|null $file
     *
     * @return bool
     */
    private function isApp(string | null $file): bool
    {
        return null !== $file && false === strpos($file, '/vendor/');
    }

    /**
     * @param BacktraceItem[] $backtrace
     *
     * @return string
     */
    private function renderBacktrace(array $backtrace): string
    {
        $html = $this->getTemplate('backtracePanel');
        foreach ($backtrace as $index => $item) {
            $html .= $this->renderTraceItem($index, $item);
        }

        return $html . $this->getTemplate('panelClose');
    }

    /**
     * @param CodeFragment $fragment
     *
     * @return string
     */
    private function renderFragment(CodeFragment $fragment): string
    {
        $firstLine = $fragment->getFirstLine();
        $lastLine  = $fragment->getLastLine();
        $line      = $fragment->getLine();
        $lines     = $fragment->getLines();

        $html    = $this->getTemplate('codeOpen');
        $counter = $firstLine;
        while ($counter <= $lastLine) {
            $currentLine = rtrim($lines[$counter - 1] ?? '', "\r\n");

            $html .= $this->toInterpolate(
                $this->getTemplate('codeRow'),
                [
                    'hlClass' => ($counter === $line) ? " class='hl'" : '',
                    'num'     => (string)$counter,
                    'src'     => htmlentities(
                        str_replace("\t", '  ', $currentLine),
                        ENT_COMPAT,
                        'UTF-8'
                    ),
                ]
            );

            $counter++;
        }

        return $html . $this->getTemplate('codeClose');
    }

    /**
     * @param list<string> $files
     *
     * @return string
     */
    private function renderIncludedFiles(array $files): string
    {
        $html = $this->toInterpolate($this->getTemplate('panelOpen'), ['id' => 'files'])
            . $this->toInterpolate(
                $this->getTemplate('tableOpen'),
                ['headerOne' => '#', 'headerTwo' => 'Path']
            );

        foreach ($files as $key => $value) {
            $html .= $this->toInterpolate(
                $this->getTemplate('gridRow'),
                [
                    'key'   => (string)$key,
                    'value' => $this->values->escape((string)$value),
                ]
            );
        }

        return $html . $this->getTemplate('tableClose') . $this->getTemplate('panelClose');
    }

    /**
     * @param ExceptionReport $report
     *
     * @return string
     */
    private function renderMemory(ExceptionReport $report): string
    {
        return $this->toInterpolate($this->getTemplate('panelOpen'), ['id' => 'memory'])
            . $this->toInterpolate(
                $this->getTemplate('memory'),
                [
                    'memory' => $this->formatBytes($report->getMemoryUsage()),
                    'peak'   => $this->formatBytes($report->getPeakMemoryUsage()),
                ]
            )
            . $this->getTemplate('panelClose');
    }

    /**
     * @param BacktraceItem $item
     *
     * @return string
     */
    private function renderSignature(BacktraceItem $item): string
    {
        $html = '';

        if (null !== $item->getClassName()) {
            $name = $this->values->escape($item->getClassName());
            $link = $item->getClassLink();
            $classHtml = (null !== $link)
                ? $this->toInterpolate($this->getTemplate('link'), ['url' => $link, 'name' => $name])
                : $name;

            $html .= "<span class='cls'>" . $classHtml . "</span>";
            $html .= "<span class='op'>" . (string)$item->getType() . "</span>";
        }

        $fnName = $this->values->escape($item->getFunctionName());
        $fnLink = $item->getFunctionLink();
        $functionHtml = (null !== $fnLink)
            ? $this->toInterpolate($this->getTemplate('link'), ['url' => $fnLink, 'name' => $fnName])
            : $fnName;

        $html .= "<span class='fn'>" . $functionHtml . "</span>";

        if (true === $item->hasArgs()) {
            $arguments = [];
            foreach ($item->getArgs() as $argument) {
                $arguments[] = $this->values->dump($argument);
            }

            $html .= "<span class='op'>(</span>" . implode(', ', $arguments) . "<span class='op'>)</span>";
        }

        return $html;
    }

    /**
     * @param string                  $div
     * @param array<array-key, mixed> $source
     *
     * @return string
     */
    private function renderSuperglobal(string $div, array $source): string
    {
        $html = $this->toInterpolate($this->getTemplate('panelOpen'), ['id' => $div])
            . $this->toInterpolate(
                $this->getTemplate('tableOpen'),
                ['headerOne' => 'Key', 'headerTwo' => 'Value']
            );

        foreach ($source as $key => $value) {
            $html .= $this->toInterpolate(
                $this->getTemplate('gridRow'),
                [
                    'key'   => $this->values->escape((string)$key),
                    'value' => $this->values->dump($value),
                ]
            );
        }

        return $html . $this->getTemplate('tableClose') . $this->getTemplate('panelClose');
    }

    /**
     * @param ExceptionReport $report
     *
     * @return string
     */
    private function renderTabs(ExceptionReport $report): string
    {
        $variablesTab = '';
        if (true === $report->hasVariables()) {
            $variablesTab = $this->toInterpolate(
                $this->getTemplate('variablesTab'),
                ['variablesCount' => (string)count($report->getVariables())]
            );
        }

        return $this->toInterpolate(
            $this->getTemplate('tabs'),
            [
                'backtraceCount' => (string)count($report->getBacktrace()),
                'requestCount'   => (string)count($report->getRequest()),
                'serverCount'    => (string)count($report->getServer()),
                'filesCount'     => (string)count($report->getIncludedFiles()),
                'variablesTab'   => $variablesTab,
            ]
        );
    }

    /**
     * @param int           $index
     * @param BacktraceItem $item
     *
     * @return string
     */
    private function renderTraceItem(int $index, BacktraceItem $item): string
    {
        $isApp = $this->isApp($item->getFile());

        $html = $this->toInterpolate(
            $this->getTemplate('frameOpen'),
            [
                'appClass'  => $isApp ? 'app' : 'vendor',
                'open'      => (0 === $index) ? ' open' : '',
                'num'       => (string)$index,
                'signature' => $this->renderSignature($item),
                'appTag'    => $isApp ? $this->getTemplate('appTag') : '',
            ]
        );

        if (null !== $item->getFile()) {
            $html .= $this->toInterpolate(
                $this->getTemplate('frameFile'),
                [
                    'file' => $item->getFile(),
                    'line' => (string)$item->getLine(),
                ]
            );

            $fragment = $item->getFragment();
            if (null !== $fragment) {
                $html .= $this->renderFragment($fragment);
            }
        }

        return $html . $this->getTemplate('frameClose');
    }

    /**
     * @param array<array-key, mixed> $variables
     *
     * @return string
     */
    private function renderVariables(array $variables): string
    {
        if (empty($variables)) {
            return '';
        }

        $html = $this->toInterpolate($this->getTemplate('panelOpen'), ['id' => 'variables'])
            . $this->toInterpolate(
                $this->getTemplate('tableOpen'),
                ['headerOne' => 'Key', 'headerTwo' => 'Value']
            );

        foreach ($variables as $key => $value) {
            $valueArray = (array)$value;

            $html .= $this->toInterpolate(
                $this->getTemplate('gridRow'),
                [
                    'key'   => $this->values->escape((string)$key),
                    'value' => $this->values->dump($valueArray[0]),
                ]
            );
        }

        return $html . $this->getTemplate('tableClose') . $this->getTemplate('panelClose');
    }
}
