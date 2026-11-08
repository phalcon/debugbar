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
use function file_get_contents;
use function htmlspecialchars;
use function is_file;
use function str_replace;

use const ENT_QUOTES;
use const JSON_HEX_AMP;
use const JSON_HEX_APOS;
use const JSON_HEX_QUOT;
use const JSON_HEX_TAG;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Produces the bar's HTML: the collected data as a `<script type="application/
 * json">` block (never executable JS) and the CSS/JS injected inline (minified),
 * so the bar has no external asset dependency. Templates are overridable and
 * every tag accepts a CSP nonce.
 */
class Renderer
{
    /**
     * @var array<string, string>
     */
    protected array $templates = [];

    public function getTemplate(string $name): string
    {
        return $this->templates[$name] ?? $this->defaultTemplate($name);
    }

    /**
     * Renders the bar shell plus the collected payload as escaped JSON.
     *
     * @param array<array-key, mixed> $collected
     */
    public function render(array $collected, ?string $nonce = null): string
    {
        $json = Json::encode(
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

    public function setTemplate(string $name, string $template): static
    {
        $this->templates[$name] = $template;

        return $this;
    }

    /**
     * The directory with the minified `debugbar.min.css`/`debugbar.min.js`, or
     * with the source `debugbar.css`/`debugbar.js`.
     */
    protected function assetsPath(): string
    {
        return dirname(__DIR__, 2) . '/resources/assets/';
    }

    protected function defaultTemplate(string $name): string
    {
        return match ($name) {
            'head'  => '<style%nonce%>%css%</style>' . "\n"
                . '<script%nonce%>%js%</script>',
            'bar'   => '<script type="application/json" id="phalcon-debugbar-data"%nonce%>'
                . '%data%</script>' . "\n"
                . '<div id="phalcon-debugbar"></div>',
            default => '',
        };
    }

    /**
     * Returns the minified asset ('css' or 'js'). It reads `debugbar.min.<type>`
     * when the assets directory has it. If not, it minifies `debugbar.<type>`.
     */
    private function asset(string $type): string
    {
        $minified = $this->assetsPath() . 'debugbar.min.' . $type;
        if (is_file($minified)) {
            return (string) file_get_contents($minified);
        }

        $source   = $this->assetsPath() . 'debugbar.' . $type;
        $minifier = ('css' === $type) ? new CssMinifier($source) : new JsMinifier($source);

        return $minifier->minify();
    }

    /**
     * @param array<string, string> $replacements
     */
    private function interpolate(string $template, array $replacements): string
    {
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    private function nonceAttribute(?string $nonce): string
    {
        return (null !== $nonce && '' !== $nonce) ? ' nonce="' . htmlspecialchars($nonce, ENT_QUOTES) . '"' : '';
    }
}
