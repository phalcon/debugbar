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

namespace Phalcon\Debug\Contracts;

/**
 * Supplies the embedded default template string for a given name. Implemented
 * by the per-renderer catalogs that hold the built-in template strings.
 */
interface TemplateCatalog
{
    /**
     * Returns the embedded default template for the given name.
     *
     * @param string $name
     *
     * @return string
     */
    public function defaultFor(string $name): string;
}
