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

namespace Phalcon\Tests\Support\DebugBar;

use Phalcon\DebugBar\Contracts\Renderable;
use PHPUnit\Framework\Assert;

/**
 * Asserts that a collector's `collect()['panel']` conforms to the schema of the
 * panel type it declares in `getWidget()['panel']`, so the PHP↔JS seam cannot
 * drift. Reused by every collector's test in later phases.
 */
trait PanelContractTrait
{
    protected function assertPanelContract(Renderable $collector): void
    {
        $panel     = $collector->getWidget()['panel'];
        $collected = $collector->collect();
        $data      = $collected['panel'];
        if (isset($collected['summary'])) {
            Assert::assertIsArray($collected['summary']);
            Assert::assertIsList($collected['summary']);
            foreach ($collected['summary'] as $metric) {
                Assert::assertIsArray($metric);
                Assert::assertArrayHasKey('label', $metric);
                Assert::assertArrayHasKey('value', $metric);
                Assert::assertIsString($metric['label']);
                Assert::assertIsScalar($metric['value']);
            }
        }
        switch ($panel) {
            case 'grid':
                Assert::assertIsArray($data);
                foreach ($data as $value) {
                    Assert::assertIsScalar($value);
                }

                break;
            case 'list':
                Assert::assertIsArray($data);
                foreach ($data as $row) {
                    Assert::assertIsArray($row);
                    Assert::assertArrayHasKey('message', $row);
                }

                break;
            case 'exceptions':
                Assert::assertIsArray($data);
                foreach ($data as $row) {
                    Assert::assertIsArray($row);
                    Assert::assertArrayHasKey('message', $row);
                    Assert::assertArrayHasKey('trace', $row);
                }

                break;
            case 'logs':
                Assert::assertIsArray($data);
                foreach ($data as $row) {
                    Assert::assertIsArray($row);
                    Assert::assertArrayHasKey('message', $row);
                    Assert::assertArrayHasKey('context', $row);
                }

                break;
            case 'code':
                Assert::assertIsArray($data);
                Assert::assertArrayHasKey('source', $data);

                break;
            case 'html':
                Assert::assertIsString($data);

                break;
            default:
                Assert::fail('Unknown panel type: ' . $panel);
        }
    }
}
