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

use function array_keys;
use function array_values;
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
 * json">` block (never executable JS) and the asset `<link>`/`<script>` tags.
 * Templates are overridable and both script tags accept a CSP nonce.
 */
class Renderer
{
    /**
     * @var array<string, string>
     */
    protected array $templates = [];

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
     * Renders the asset tags pointing at the configured base URI.
     *
     * @param string      $uri
     * @param string|null $nonce
     *
     * @return string
     */
    public function renderHead(string $uri, ?string $nonce = null): string
    {
        return $this->interpolate(
            $this->getTemplate('head'),
            [
                '%uri%'   => $uri,
                '%nonce%' => $this->nonceAttribute($nonce),
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
     * @param string $name
     *
     * @return string
     */
    protected function defaultTemplate(string $name): string
    {
        return match ($name) {
            'head'  => '<link rel="stylesheet" href="%uri%debugbar.css">' . "\n"
                . '<script src="%uri%debugbar.js"%nonce% defer></script>',
            'bar'   => '<script type="application/json" id="phalcon-debugbar-data"%nonce%>'
                . '%data%</script>' . "\n"
                . '<div id="phalcon-debugbar"></div>',
            default => '',
        };
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
