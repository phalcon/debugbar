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

use Phalcon\Http\RequestInterface;

use function is_string;

/**
 * The request values that the bar uses to make decisions. Create this object for
 * each decision: do not keep it at boot.
 */
final class RequestContext
{
    public function __construct(
        public readonly ?string $clientIp,
        public readonly bool $isAjax,
        public readonly bool $isSecure
    ) {
    }

    public static function fromRequest(?RequestInterface $request): self
    {
        if (null === $request) {
            return new self(null, false, false);
        }

        $clientIp = $request->getClientAddress();

        return new self(
            is_string($clientIp) ? $clientIp : null,
            $request->isAjax(),
            $request->isSecure()
        );
    }
}
