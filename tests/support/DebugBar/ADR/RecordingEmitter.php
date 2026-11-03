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

namespace Phalcon\Tests\Support\DebugBar\ADR;

use Phalcon\Contracts\ADR\Emitter\Emitter;
use Phalcon\Http\ResponseInterface;

/**
 * Keeps the response and sends no output.
 */
final class RecordingEmitter implements Emitter
{
    public ?ResponseInterface $response = null;

    public function emit(ResponseInterface $response): void
    {
        $this->response = $response;
    }
}
