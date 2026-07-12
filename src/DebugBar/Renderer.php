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

use MatthiasMullie\Minify\CSS as CssMinifier;
use MatthiasMullie\Minify\JS as JsMinifier;

use function array_keys;
use function array_values;
use function dirname;
use function json_encode;
use function str_replace;

use const JSON_HEX_AMP;
use const JSON_HEX_APOS;
use const JSON_HEX_QUOT;
use const JSON_HEX_TAG;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Produces the bar's HTML: the collected data as a `<script type="application/
 * json">` block (never executable JS) and the CSS/JS injected inline (minified),
 * so the bar has no external asset dependency. When an output path is given the
 * compiled files are also written there. Templates are overridable and every
 * tag accepts a CSP nonce.
 */
class Renderer
{
    /**
     * @var array<string, string>
     */
    protected array $templates = [];

    /**
     * @param string|null $outputPath
     */
    public function __construct(private readonly ?string $outputPath = null)
    {
    }

    /**
     * @param string $name
     *
     * @return string
     */
    public function getTemplate(string $name): string
    {
        return $this->templates[$name] ?? $this->defaultTemplate($name);
    }

    /**
     * Renders the bar shell plus the collected payload as escaped JSON.
     *
     * @param array<array-key, mixed> $collected
     * @param string|null             $nonce
     *
     * @return string
     */
    public function render(array $collected, ?string $nonce = null): string
    {
        $json = json_encode(
            $collected,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
            | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return $this->interpolate(
            $this->getTemplate('bar'),
            [
                '%nonce%' => $this->nonceAttribute($nonce),
                '%data%'  => (false === $json) ? '{}' : $json,
            ]
        );
    }

    /**
     * Renders the head: the minified CSS and JS injected inline, so the bar
     * carries no external asset dependency.
     *
     * @param string|null $nonce
     *
     * @return string
     */
    public function renderHead(?string $nonce = null): string
    {
        return $this->interpolate(
            $this->getTemplate('head'),
            [
                '%nonce%' => $this->nonceAttribute($nonce),
                '%css%'   => $this->asset('css'),
                '%js%'    => $this->asset('js'),
            ]
        );
    }

    /**
     * @param string $name
     * @param string $template
     *
     * @return static
     */
    public function setTemplate(string $name, string $template): static
    {
        $this->templates[$name] = $template;

        return $this;
    }

    /**
     * The package directory holding the source `debugbar.css`/`debugbar.js`.
     *
     * @return string
     */
    protected function assetsPath(): string
    {
        return dirname(__DIR__, 2) . '/resources/assets/';
    }

    /**
     * @param string $name
     *
     * @return string
     */
    protected function defaultTemplate(string $name): string
    {
        return match ($name) {
            'head'  => '<style%nonce%>%css%</style>' . "\n"
                . '<script%nonce% defer>%js%</script>',
            'bar'   => '<script type="application/json" id="phalcon-debugbar-data"%nonce%>'
                . '%data%</script>' . "\n"
                . '<div id="phalcon-debugbar"></div>',
            default => '',
        };
    }

    /**
     * Returns the minified asset ('css' or 'js'). When an output path is set the
     * compiled file is written there as well.
     *
     * @param string $type
     *
     * @return string
     */
    private function asset(string $type): string
    {
        $source   = $this->assetsPath() . 'debugbar.' . $type;
        $minifier = ('css' === $type) ? new CssMinifier($source) : new JsMinifier($source);

        if (null === $this->outputPath) {
            return $minifier->minify();
        }

        return $minifier->minify($this->outputPath . '/debugbar.min.' . $type);
    }

    /**
     * @param string                $template
     * @param array<string, string> $replacements
     *
     * @return string
     */
    private function interpolate(string $template, array $replacements): string
    {
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * @param string|null $nonce
     *
     * @return string
     */
    private function nonceAttribute(?string $nonce): string
    {
        return (null !== $nonce && '' !== $nonce) ? ' nonce="' . $nonce . '"' : '';
    }
}
