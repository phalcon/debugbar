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

namespace Phalcon\Tests\Support\DebugBar\Fixtures;

use stdClass;

/**
 * Provides a deterministic `dump()` implementation returning a non-array value
 * so `HtmlRenderer::getVarDump()` exercises its object-with-dump branch and the
 * `(array)` cast applied to the dump result.
 */
final class DumpableFixture
{
    /**
     * @return stdClass
     */
    public function dump(): stdClass
    {
        $result        = new stdClass();
        $result->alpha = 'beta';

        return $result;
    }
}
