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

namespace Phalcon\Tests\Unit\DebugBar;

use Phalcon\DebugBar\Renderer;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;

final class RendererTest extends AbstractUnitTestCase
{
    public function testAssetsPathIsProtected(): void
    {
        // assetsPath() must remain protected so subclasses can override the
        // asset location; a private mutation breaks this subclass call.
        $renderer = new class () extends Renderer {
            public function exposeAssetsPath(): string
            {
                return $this->assetsPath();
            }
        };

        $this->assertStringEndsWith('/resources/assets/', $renderer->exposeAssetsPath());
    }

    public function testCapturedScriptTagCannotBreakTheDataBlock(): void
    {
        $html = (new Renderer())->render(['data' => ['x' => '</script><script>alert(1)</script>']]);

        // The captured tag is hex-escaped in the JSON, so it cannot close the data block.
        $this->assertStringContainsString('\\u003C', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function testDefaultTemplateIsProtected(): void
    {
        // defaultTemplate() must remain protected so subclasses can reuse it;
        // a private mutation breaks this subclass call.
        $renderer = new class () extends Renderer {
            public function exposeDefaultTemplate(string $name): string
            {
                return $this->defaultTemplate($name);
            }
        };

        $this->assertStringContainsString('phalcon-debugbar-data', $renderer->exposeDefaultTemplate('bar'));
    }

    public function testGetTemplateReturnsEmptyForUnknownName(): void
    {
        // The match's default arm yields an empty string for unknown names;
        // removing it would raise an UnhandledMatchError.
        $this->assertSame('', (new Renderer())->getTemplate('does-not-exist'));
    }

    public function testGetTemplateReturnsExactBarTemplate(): void
    {
        $expected = '<script type="application/json" id="phalcon-debugbar-data"%nonce%>'
            . '%data%</script>' . "\n"
            . '<div id="phalcon-debugbar"></div>';

        $this->assertSame($expected, (new Renderer())->getTemplate('bar'));
    }

    public function testGetTemplateReturnsExactHeadTemplate(): void
    {
        $expected = '<style%nonce%>%css%</style>' . "\n"
            . '<script%nonce% defer>%js%</script>';

        $this->assertSame($expected, (new Renderer())->getTemplate('head'));
    }

    public function testNonceIsAppliedToScriptTags(): void
    {
        $renderer = new Renderer();

        $this->assertStringContainsString('nonce="abc123"', $renderer->render([], 'abc123'));
        $this->assertStringContainsString('nonce="abc123"', $renderer->renderHead('abc123'));
    }

    public function testRenderEmitsTheJsonDataBlock(): void
    {
        $html = (new Renderer())->render(['meta' => ['collectors' => 2]]);

        $this->assertStringContainsString('id="phalcon-debugbar-data"', $html);
        $this->assertStringContainsString('"collectors":2', $html);
    }

    public function testRenderFallsBackToEmptyObjectWhenJsonFails(): void
    {
        // Invalid UTF-8 makes json_encode() return false; the renderer must
        // fall back to an empty JSON object rather than emitting the raw false.
        $html = (new Renderer())->render(['bad' => "\xFF"]);

        $this->assertStringContainsString('>{}</script>', $html);
    }

    public function testRenderHeadIncludesCollectorSummaryAssets(): void
    {
        $html = (new Renderer())->renderHead();
        $this->assertStringContainsString('phalcon-debugbar-summary', $html);
        $this->assertStringContainsString('entry.summary', $html);
        $this->assertStringContainsString('is-duplicate', $html);
        $this->assertStringContainsString('Executed ', $html);
    }

    public function testRenderHeadInlinesMinifiedAssets(): void
    {
        $html = (new Renderer())->renderHead();

        $this->assertStringContainsString('<style>', $html);
        $this->assertStringContainsString('#phalcon-debugbar', $html);
        $this->assertStringContainsString('<script', $html);
        $this->assertStringContainsString('phalcon-debugbar-data', $html);
    }
    public function testRenderHeadUsesCssMinifierForStyles(): void
    {
        // The CSS minifier shortens #ffffff to #fff; running the JS minifier on
        // the stylesheet (the swapped-branch mutation) would leave #ffffff intact.
        $html = (new Renderer())->renderHead();

        $this->assertStringNotContainsString('#ffffff', $html);
    }

    public function testTemplatesAreOverridable(): void
    {
        $renderer = new Renderer();
        $renderer->setTemplate('bar', 'CUSTOM %data%');

        $html = $renderer->render(['k' => 1]);

        $this->assertStringContainsString('CUSTOM', $html);
        $this->assertStringNotContainsString('phalcon-debugbar-data', $html);
    }
}
