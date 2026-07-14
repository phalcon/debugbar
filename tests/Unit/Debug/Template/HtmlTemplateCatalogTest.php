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

use Phalcon\Debug\Template\HtmlTemplateCatalog;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;

final class HtmlTemplateCatalogTest extends AbstractUnitTestCase
{
    public function testDefaultForKnownName(): void
    {
        $catalog = new HtmlTemplateCatalog();

        $this->assertSame(
            "<a class='version-badge' href='%link%' target='_new'><b>v%version%</b></a>",
            $catalog->defaultFor('version')
        );
    }

    public function testDefaultForUnknownName(): void
    {
        $this->assertSame('', (new HtmlTemplateCatalog())->defaultFor('does-not-exist'));
    }
}
