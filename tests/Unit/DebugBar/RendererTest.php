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

use function dirname;

final class RendererTest extends AbstractUnitTestCase
{
    public function testCapturedScriptTagCannotBreakTheDataBlock(): void
    {
        $html = (new Renderer())->render(['data' => ['x' => '</script><script>alert(1)</script>']]);

        // The captured tag is hex-escaped in the JSON, so it cannot close the data block.
        $this->assertStringContainsString('\\u003C', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
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

    public function testRenderHeadInlinesMinifiedAssets(): void
    {
        $html = (new Renderer())->renderHead();

        $this->assertStringContainsString('<style>', $html);
        $this->assertStringContainsString('#phalcon-debugbar', $html);
        $this->assertStringContainsString('<script', $html);
        $this->assertStringContainsString('phalcon-debugbar-data', $html);
    }

    public function testRenderHeadWritesCompiledFilesWhenOutputPathIsGiven(): void
    {
        $dir = dirname(__DIR__, 2) . '/_output';

        $html = (new Renderer($dir))->renderHead();

        $this->assertFileExists($dir . '/debugbar.min.css');
        $this->assertFileExists($dir . '/debugbar.min.js');
        $this->assertStringContainsString('#phalcon-debugbar', $html);

        $this->safeDeleteFile($dir . '/debugbar.min.css');
        $this->safeDeleteFile($dir . '/debugbar.min.js');
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
