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

use Phalcon\DebugBar\Collector\AbstractCollector;

/**
 * A minimal code collector used to exercise the `code` panel contract.
 */
final class CodeCollector extends AbstractCollector
{
    public const NAME = 'code_fixture';

    /**
     * @var string
     */
    protected string $panel = 'code';

    /**
     * @return array{panel: array{language: string, source: string}, badge: scalar|null}
     */
    public function collect(): array
    {
        return [
            'panel' => [
                'language' => 'php',
                'source'   => 'echo 1;',
            ],
            'badge' => null,
        ];
    }
}
