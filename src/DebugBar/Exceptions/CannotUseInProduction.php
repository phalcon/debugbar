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

namespace Phalcon\DebugBar\Exceptions;

/**
 * Thrown by the registration gate when the bar is asked to run in a blocked
 * (production) environment. Nothing is bound when this is raised.
 */
class CannotUseInProduction extends Exception
{
}
