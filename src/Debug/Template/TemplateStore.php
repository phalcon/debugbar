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

namespace Phalcon\Debug\Template;

use Phalcon\Debug\Contracts\TemplateCatalog;

/**
 * Holds per-name template overrides on top of a TemplateCatalog. Replaces the
 * former TemplateAwareTrait with composition, which is portable to the
 * Zephir/cphalcon mirror (that cannot use traits).
 */
final class TemplateStore
{
    /**
     * @var array<string, string>
     */
    private array $overrides = [];

    /**
     * @param TemplateCatalog $catalog
     */
    public function __construct(private readonly TemplateCatalog $catalog)
    {
    }

    /**
     * Returns the override for the name if set, otherwise the catalog default.
     *
     * @param string $name
     *
     * @return string
     */
    public function get(string $name): string
    {
        return $this->overrides[$name] ?? $this->catalog->defaultFor($name);
    }

    /**
     * Overrides the template for the given name.
     *
     * @param string $name
     * @param string $template
     *
     * @return void
     */
    public function set(string $name, string $template): void
    {
        $this->overrides[$name] = $template;
    }
}
