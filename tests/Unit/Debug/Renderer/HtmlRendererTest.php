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

namespace Phalcon\Tests\Unit\Debug\Renderer;

use Phalcon\Debug\Renderer\HtmlRenderer;
use Phalcon\Debug\Report\BacktraceItem;
use Phalcon\Debug\Report\ExceptionReport;
use Phalcon\Debug\ReportBuilder;
use Phalcon\Support\Exception;
use Phalcon\Support\Version;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\Fixtures\DumpableFixture;

final class HtmlRendererTest extends AbstractUnitTestCase
{
    private const URI = 'https://assets.phalcon.io/debug/6.0.x/';

    public function testGetCssSources(): void
    {
        $uri      = self::URI;
        $expected = "
    <link href='{$uri}debug.css'
          rel='stylesheet'
          type='text/css' />";

        $this->assertSame($expected, (new HtmlRenderer())->getCssSources($uri));
    }

    public function testGetJsSources(): void
    {
        $uri      = self::URI;
        $expected = "
    <script src='{$uri}debug.js'></script>";

        $this->assertSame($expected, (new HtmlRenderer())->getJsSources($uri));
    }

    public function testRenderExactBacktraceSections(): void
    {
        $actual = (new HtmlRenderer())->render($this->buildReport());

        $frameApp = "\n        <details class='frame app' open>"
            . "\n            <summary><div class='frame-head'>"
            . "\n                <span class='frame-num'>#0</span>"
            . "\n                <span class='frame-call'>"
            . "<span class='cls'>"
            . "<a href='https://docs.example/My_Service' target='_new'>My\\Service</a>"
            . "</span><span class='op'>-></span>"
            . "<span class='fn'>methodA</span>"
            . "<span class='op'>(</span>argOne, 42<span class='op'>)</span>"
            . "</span><span class='tag-app'>app</span>"
            . "\n                <span class='chev'>&#9656;</span>"
            . "\n            </div></summary>"
            . "\n            <div class='frame-file' data-file='/app/x.php' data-line='5'>"
            . "\n                <span class='path'><b>/app/x.php</b> : 5</span>"
            . "\n            </div>"
            . "\n            <div class='code'><table>"
            . "<tr><td class='ln'>3</td><td class='src'>AAA  tabbed</td></tr>"
            . "<tr class='hl'><td class='ln'>4</td><td class='src'>line&lt;four&gt;</td></tr>"
            . "<tr><td class='ln'>5</td><td class='src'>line five</td></tr>"
            . "</table></div>"
            . "\n        </details>";

        $frameVendor = "\n        <details class='frame vendor'>"
            . "\n            <summary><div class='frame-head'>"
            . "\n                <span class='frame-num'>#1</span>"
            . "\n                <span class='frame-call'><span class='fn'>"
            . "<a href='https://secure.php.net/manual/en/function.array-map.php'"
            . " target='_new'>array_map</a></span></span>"
            . "\n                <span class='chev'>&#9656;</span>"
            . "\n            </div></summary>"
            . "\n        </details>";

        $this->assertStringContainsString($frameApp, $actual);
        $this->assertStringContainsString($frameVendor, $actual);
        $this->assertStringContainsString(
            "\n        </details>\n    </div>\n    <div class='panel' id='request'>",
            $actual
        );
    }

    public function testRenderExactDataSections(): void
    {
        $actual = (new HtmlRenderer())->render($this->buildReport());

        $request = "\n    <div class='panel' id='request'>"
            . "<table class='grid'><thead><tr><th>Key</th><th>Value</th></tr></thead><tbody>"
            . "<tr><td class='k'>reqKey</td><td class='v'>reqVal</td></tr>"
            . "<tr><td class='k'>7</td><td class='v'>intKeyed</td></tr>"
            . "</tbody></table>"
            . "\n    </div>";

        $server = "\n    <div class='panel' id='server'>"
            . "<table class='grid'><thead><tr><th>Key</th><th>Value</th></tr></thead><tbody>"
            . "<tr><td class='k'>SRV</td><td class='v'>srvVal</td></tr>"
            . "</tbody></table>"
            . "\n    </div>";

        $files = "\n    <div class='panel' id='files'>"
            . "<table class='grid'><thead><tr><th>#</th><th>Path</th></tr></thead><tbody>"
            . "<tr><td class='k'>0</td><td class='v'>/app/a.php</td></tr>"
            . "<tr><td class='k'>1</td><td class='v'>/vendor/b.php</td></tr>"
            . "</tbody></table>"
            . "\n    </div>";

        $memory = "\n    <div class='panel' id='memory'>"
            . "\n        <div class='stats'>"
            . "\n            <div class='stat'><div class='label'>Memory usage (real)</div>"
            . "<div class='value'>1.5 <small>MB</small></div></div>"
            . "\n            <div class='stat'><div class='label'>Peak usage</div>"
            . "<div class='value'>2.5 <small>MB</small></div></div>"
            . "\n        </div>"
            . "\n    </div>";

        $variables = "\n    <div class='panel' id='variables'>"
            . "<table class='grid'><thead><tr><th>Key</th><th>Value</th></tr></thead><tbody>"
            . "<tr><td class='k'>myVar</td><td class='v'>hello</td></tr>"
            . "</tbody></table>"
            . "\n    </div>";

        $this->assertStringContainsString($request, $actual);
        $this->assertStringContainsString($server, $actual);
        $this->assertStringContainsString($files, $actual);
        $this->assertStringContainsString($memory, $actual);
        $this->assertStringContainsString($variables, $actual);
    }

    public function testRenderExactHeadSections(): void
    {
        $actual = (new HtmlRenderer())->render($this->buildReport());

        $errorMain = "\n    <div class='error-card'>"
            . "\n        <span class='error-type'>My\\Ex</span>"
            . "\n        <h1 class='error-message'>boom\\nsecond</h1>"
            . "\n        <div class='meta'>"
            . "\n            <span class='item'><code>/app/x.php</code> : <code>5</code></span>"
            . "\n            <span class='sep'>|</span><span class='item'>PHP <code>"
            . PHP_VERSION
            . "</code></span>"
            . "\n        </div>"
            . "\n    </div>";

        $tabs = "\n    <div class='tabs' role='tablist'>"
            . "\n        <button class='tab is-active' data-tab='backtrace'>"
            . "Backtrace <span class='count'>2</span></button>"
            . "\n        <button class='tab' data-tab='request'>"
            . "Request <span class='count'>2</span></button>"
            . "\n        <button class='tab' data-tab='server'>"
            . "Server <span class='count'>1</span></button>"
            . "\n        <button class='tab' data-tab='files'>"
            . "Included Files <span class='count'>2</span></button>"
            . "\n        <button class='tab' data-tab='memory'>Memory</button>"
            . "\n        <button class='tab' data-tab='variables'>"
            . "Variables <span class='count'>1</span></button>"
            . "\n    </div>";

        $this->assertStringStartsWith('<!DOCTYPE html>', $actual);
        $this->assertStringContainsString($errorMain, $actual);
        $this->assertStringContainsString($tabs, $actual);
    }

    public function testRenderNoBacktraceDocument(): void
    {
        $exception = new Exception('exception message', 1234);
        $report    = (new ReportBuilder())->build(
            $exception,
            ['request' => [], 'server' => []],
            false,
            true,
            false,
            self::URI,
            []
        );

        $version = new Version();
        $link    = $version->getPart(Version::VERSION_MAJOR)
            . '.'
            . $version->getPart(Version::VERSION_MEDIUM);

        $actual = (new HtmlRenderer())->render($report);

        $this->assertStringContainsString(
            "<title>Phalcon\\Support\\Exception:exception message</title>",
            $actual
        );
        $this->assertStringContainsString(
            "<a class='version-badge' href='https://docs.phalcon.io/{$link}/' target='_new'>",
            $actual
        );
        $this->assertStringContainsString(
            "<span class='error-type'>Phalcon\\Support\\Exception</span>",
            $actual
        );
        $this->assertStringContainsString(
            "<h1 class='error-message'>exception message</h1>",
            $actual
        );
        $this->assertStringNotContainsString('data-tab=', $actual);
        $this->assertStringEndsWith('</html>', $actual);
    }

    public function testRenderSignatureLinksAndArgs(): void
    {
        $arg    = uniqid('var-');
        $report = new ExceptionReport('My\\Ex', 'boom', '/app/x.php', 5, true, self::URI);
        $report->setBacktrace(
            [
                new BacktraceItem(
                    'methodA',
                    '->',
                    'My\\Service',
                    'https://docs.example/My_Service',
                    null,
                    true,
                    [$arg],
                    '/app/x.php',
                    5,
                    null
                ),
                new BacktraceItem(
                    'array_map',
                    null,
                    null,
                    null,
                    'https://secure.php.net/manual/en/function.array-map.php',
                    false,
                    [],
                    null,
                    null,
                    null
                ),
            ]
        );

        $actual = (new HtmlRenderer())->render($report);

        $this->assertStringContainsString("href='https://docs.example/My_Service'", $actual);
        $this->assertStringContainsString(
            "href='https://secure.php.net/manual/en/function.array-map.php'",
            $actual
        );
        $this->assertStringContainsString($arg, $actual);
    }

    public function testRenderWithBacktraceContainsTabs(): void
    {
        $exception = new Exception('exception message', 1234);
        $report    = (new ReportBuilder())->build(
            $exception,
            ['request' => [], 'server' => []],
            true,
            false,
            false,
            self::URI,
            []
        );

        $actual = (new HtmlRenderer())->render($report);

        $this->assertStringContainsString("<div class='tabs' role='tablist'>", $actual);
        $this->assertStringContainsString("data-tab='backtrace'", $actual);
        $this->assertStringContainsString("data-tab='memory'", $actual);
        $this->assertStringContainsString("id='backtrace'", $actual);
        $this->assertStringContainsString("<details class='frame", $actual);
    }

    public function testSetTemplateOverridesDefault(): void
    {
        $renderer = new HtmlRenderer();
        $renderer->setTemplate('version', 'OVERRIDDEN');

        $this->assertSame('OVERRIDDEN', $renderer->getTemplate('version'));
    }

    public function testTemplatesFallThrough(): void
    {
        $this->assertSame('', (new HtmlRenderer())->getTemplate('does-not-exist'));
    }

    public function testVarDumpAndArrayDumpBranches(): void
    {
        $renderer = new class () extends HtmlRenderer {
            public function dumpVar(mixed $value): string
            {
                return $this->getVarDump($value);
            }

            /**
             * @param array<array-key, mixed> $arguments
             * @param int                     $number
             *
             * @return string|null
             */
            public function dumpArr(array $arguments, int $number = 0): string | null
            {
                return $this->getArrayDump($arguments, $number);
            }
        };

        $object = new class () {
            /**
             * @return array<array-key, mixed>
             */
            public function dump(): array
            {
                return ['k' => 'v'];
            }
        };

        $resource = fopen('php://memory', 'r');
        if (false === $resource) {
            self::fail('Unable to open the in-memory resource.');
        }

        $this->assertSame('true', $renderer->dumpVar(true));
        $this->assertSame('false', $renderer->dumpVar(false));
        $this->assertSame('42', $renderer->dumpVar(42));
        $this->assertSame('null', $renderer->dumpVar(null));
        $this->assertSame('resource', $renderer->dumpVar($resource));
        $this->assertSame('Object(stdClass)', $renderer->dumpVar(new \stdClass()));
        $this->assertStringContainsString('a&lt;b', $renderer->dumpVar('a<b'));
        $this->assertSame("a\\nb", $renderer->dumpVar("a\nb"));
        $this->assertStringContainsString('Array(', $renderer->dumpVar([1, 2]));
        $this->assertStringContainsString('=&gt;', $renderer->dumpVar($object));

        $this->assertSame('Array([0] =&gt; 1, [1] =&gt; 2)', $renderer->dumpVar([1, 2]));
        $this->assertSame(
            'Array([0] =&gt; Array([0] =&gt; Array([0] =&gt; Array())))',
            $renderer->dumpVar([[[['deep']]]])
        );
        $this->assertSame(
            'Object(' . DumpableFixture::class . ': [alpha] =&gt; beta)',
            $renderer->dumpVar(new DumpableFixture())
        );

        $this->assertNull($renderer->dumpArr([]));
        $this->assertSame('10', $renderer->dumpArr(range(1, 10)));
        $this->assertSame('12', $renderer->dumpArr(range(1, 12)));

        $dump = $renderer->dumpArr(['', 5, [1], new \stdClass(), null, $resource]);

        /** @var string $dump */
        $this->assertStringContainsString('[0] =&gt; (empty string)', $dump);
        $this->assertStringContainsString('[1] =&gt; 5', $dump);
        $this->assertStringContainsString('[2] =&gt; Array([0] =&gt; 1)', $dump);
        $this->assertStringContainsString('[3] =&gt; Object(stdClass)', $dump);
        $this->assertStringContainsString('[4] =&gt; null', $dump);

        fclose($resource);

        $this->assertStringContainsString('resource (closed)', (string)$renderer->dumpArr([$resource]));
    }

    private function buildReport(): ExceptionReport
    {
        $fragment = [
            'mode'      => 'fragment',
            'firstLine' => 3,
            'line'      => 4,
            'lastLine'  => 5,
            'lines'     => [
                "line one\r\n",
                "line two\r\n",
                "AAA\ttabbed\r\n",
                "line<four>\r\n",
                "line five\r\n",
            ],
        ];

        $frame = new BacktraceItem(
            'methodA',
            '->',
            'My\\Service',
            'https://docs.example/My_Service',
            null,
            true,
            ['argOne', 42],
            '/app/x.php',
            5,
            $fragment
        );

        $function = new BacktraceItem(
            'array_map',
            null,
            null,
            null,
            'https://secure.php.net/manual/en/function.array-map.php',
            false,
            [],
            null,
            null,
            null
        );

        $report = new ExceptionReport(
            'My\\Ex',
            "boom\nsecond",
            '/app/x.php',
            5,
            true,
            self::URI
        );

        $report->setBacktrace([$frame, $function]);
        $report->setRequest(['reqKey' => 'reqVal', 7 => 'intKeyed']);
        $report->setServer(['SRV' => 'srvVal']);
        $report->setIncludedFiles(['/app/a.php', '/vendor/b.php']);
        $report->setMemoryUsage(1572864);
        $report->setPeakMemoryUsage(2621440);
        $report->setVariables(['myVar' => 'hello']);

        return $report;
    }
}
