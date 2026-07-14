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
use Phalcon\Debug\Template\TemplateStore;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;

final class TemplateStoreTest extends AbstractUnitTestCase
{
    public function testGetReturnsCatalogDefaultWhenNoOverride(): void
    {
        $store = new TemplateStore(new DumpTemplateCatalog());

        $this->assertSame('<pre style="%style%">%output%</pre>', $store->get('pre'));
    }

    public function testGetReturnsEmptyForUnknownName(): void
    {
        $store = new TemplateStore(new DumpTemplateCatalog());

        $this->assertSame('', $store->get('does-not-exist'));
    }

    public function testSetOverridesCatalogDefault(): void
    {
        $store = new TemplateStore(new DumpTemplateCatalog());
        $store->set('pre', 'OVERRIDDEN');

        $this->assertSame('OVERRIDDEN', $store->get('pre'));
    }
}
