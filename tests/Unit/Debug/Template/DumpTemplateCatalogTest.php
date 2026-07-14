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

namespace Phalcon\Tests\Unit\Debug\Template;

use Phalcon\Debug\Template\DumpTemplateCatalog;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;

final class DumpTemplateCatalogTest extends AbstractUnitTestCase
{
    public function testDefaultForKnownName(): void
    {
        $catalog = new DumpTemplateCatalog();

        $this->assertSame(
            "-><span style=\"%style%\">%method%</span>(); "
            . "[<b style=\"%style%\">constructor</b>]\n",
            $catalog->defaultFor('objectMethodConstructor')
        );
    }

    public function testDefaultForUnknownName(): void
    {
        $this->assertSame('', (new DumpTemplateCatalog())->defaultFor('does-not-exist'));
    }
}
