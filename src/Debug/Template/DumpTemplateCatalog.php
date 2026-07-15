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
 * Holds the embedded default template strings for the variable Dump component.
 */
final class DumpTemplateCatalog implements TemplateCatalog
{
    /**
     * Returns the embedded default template for the given name.
     *
     * @param string $name
     *
     * @return string
     */
    public function defaultFor(string $name): string
    {
        return match ($name) {
            'pre'                     => '<pre style="%style%">%output%</pre>',
            'bold'                    => '<b style="%style%">%text%</b>',
            'varParens'               => '(<span style="%style%">%var%</span>)',
            'lengthValue'             => '(<span style="%style%">%length%</span>) '
                . '"<span style="%style%">%var%</span>"',
            'arrayHeader'             => '<b style="%style%">Array</b> '
                . '(<span style="%style%">%count%</span>) (',
            'arrayKey'                => '[<span style="%style%">%key%</span>] => ',
            'objectHeader'            => '<b style="%style%">Object</b> %class%',
            'objectExtends'           => ' <b style="%style%">extends</b> %parent%',
            'objectProperty'          => '-><span style="%style%">%key%</span> '
                . '(<span style="%style%">%type%</span>) = ',
            'objectMethods'           => "%class% <b style=\"%style%\">methods</b>: "
                . "(<span style=\"%style%\">%count%</span>) (\n",
            'objectMethod'            => "-><span style=\"%style%\">%method%</span>();\n",
            'objectMethodConstructor' => "-><span style=\"%style%\">%method%</span>(); "
                . "[<b style=\"%style%\">constructor</b>]\n",
            default                   => '',
        };
    }
}
