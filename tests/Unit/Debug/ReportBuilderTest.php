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

namespace Phalcon\Tests\Unit\Debug;

use Phalcon\Debug\Report\BacktraceItem;
use Phalcon\Debug\ReportBuilder;
use Phalcon\Support\Exception;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Talon\Talon;
use PHPUnit\Framework\Attributes\BackupGlobals;

#[BackupGlobals(true)]
final class ReportBuilderTest extends AbstractUnitTestCase
{
    public function testBuildCastsRequestAndServerBlacklistToArray(): void
    {
        $_REQUEST['REQ_KEEP'] = 'reqvalue';
        $_SERVER['SRV_KEEP']  = 'srvvalue';

        $report = (new ReportBuilder())->build(
            new Exception('boom', 7),
            ['request' => 'not-an-array', 'server' => 'not-an-array'],
            true,
            false,
            false,
            'https://cdn/',
            []
        );

        $this->assertArrayHasKey('REQ_KEEP', $report->getRequest());
        $this->assertArrayHasKey('SRV_KEEP', $report->getServer());
    }

    public function testBuildFragmentForUnreadableFile(): void
    {
        $builder = new ReportBuilder();

        $method = new \ReflectionMethod(ReportBuilder::class, 'buildFragment');

        /** @var array{lines: array<int, string>} $fragment */
        $fragment = @$method->invoke($builder, '/phalcon/no/such/file.php', 5, false);

        $this->assertSame([], $fragment['lines']);
    }

    public function testBuildItemPreservesArgs(): void
    {
        $builder = new ReportBuilder();

        $method = new \ReflectionMethod(ReportBuilder::class, 'buildItem');

        /** @var BacktraceItem $item */
        $item = $method->invoke(
            $builder,
            ['function' => 'foo', 'args' => ['alpha', 'beta']],
            false,
            false
        );

        $this->assertSame('foo', $item->getFunctionName());
        $this->assertTrue($item->hasArgs());
        $this->assertSame(['alpha', 'beta'], $item->getArgs());
    }

    public function testFilterCastsIntegerKeysToString(): void
    {
        $builder = new ReportBuilder();

        $method = new \ReflectionMethod(ReportBuilder::class, 'filter');

        /** @var array<array-key, mixed> $result */
        $result = $method->invoke($builder, [5 => 'keep', 'name' => 'value'], []);

        $this->assertSame([5 => 'keep', 'name' => 'value'], $result);
    }

    public function testFilterUsesMultibyteLowercase(): void
    {
        $builder = new ReportBuilder();

        $method = new \ReflectionMethod(ReportBuilder::class, 'filter');

        /** @var array<array-key, mixed> $result */
        $result = $method->invoke(
            $builder,
            ["\u{00C4}" => 'secret', 'keep' => 'value'],
            ["\u{00E4}" => 1]
        );

        $this->assertArrayNotHasKey("\u{00C4}", $result);
        $this->assertArrayHasKey('keep', $result);
    }

    public function testFragmentFirstLineFloor(): void
    {
        $builder = new ReportBuilder();
        $file    = Talon::settings()->supportPath('assets/Debug/report-fragment.txt');

        $method = new \ReflectionMethod(ReportBuilder::class, 'buildFragment');

        /** @var array{firstLine: int, line: int} $fragment */
        $fragment = $method->invoke($builder, $file, 3, true);

        $this->assertSame(1, $fragment['firstLine']);
        $this->assertSame(3, $fragment['line']);
    }

    public function testFragmentModeFragment(): void
    {
        $builder = new ReportBuilder();
        $file    = Talon::settings()->supportPath('assets/Debug/report-fragment.txt');

        $method = new \ReflectionMethod(ReportBuilder::class, 'buildFragment');

        /**
         * @var array{
         *     mode: string,
         *     firstLine: int,
         *     line: int,
         *     lastLine: int,
         *     lines: array<int, string>
         * } $fragment
         */
        $fragment = $method->invoke($builder, $file, 20, true);

        $this->assertArrayHasKey('mode', $fragment);
        $this->assertSame('fragment', $fragment['mode']);
        $this->assertSame(13, $fragment['firstLine']);
        $this->assertSame(20, $fragment['line']);
        $this->assertSame(25, $fragment['lastLine']);
        $this->assertCount(30, $fragment['lines']);
    }

    public function testFragmentModeFull(): void
    {
        $builder = new ReportBuilder();
        $file    = Talon::settings()->supportPath('assets/Debug/report-fragment.txt');

        $method = new \ReflectionMethod(ReportBuilder::class, 'buildFragment');

        /**
         * @var array{
         *     mode: string,
         *     firstLine: int,
         *     line: int,
         *     lastLine: int
         * } $fragment
         */
        $fragment = $method->invoke($builder, $file, 20, false);

        $this->assertSame('full', $fragment['mode']);
        $this->assertSame(1, $fragment['firstLine']);
        $this->assertSame(20, $fragment['line']);
        $this->assertSame(30, $fragment['lastLine']);
    }

    public function testInternalFunctionLinkIsResolved(): void
    {
        $exception = new Exception('placeholder');

        try {
            array_map(
                static function () {
                    throw new Exception('boom', 7);
                },
                [1]
            );
        } catch (Exception $e) {
            $exception = $e;
        }

        $report = (new ReportBuilder())->build(
            $exception,
            ['request' => [], 'server' => []],
            true,
            false,
            false,
            'https://cdn/',
            []
        );

        $found = false;
        foreach ($report->getBacktrace() as $item) {
            $link = $item->getFunctionLink();
            if (null !== $link && str_contains($link, '/function.')) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found);
    }

    public function testLinkResolution(): void
    {
        $builder = new ReportBuilder();

        $classLink = new \ReflectionMethod(ReportBuilder::class, 'resolveClassLink');

        /** @var string $phalconLink */
        $phalconLink = $classLink->invoke($builder, 'Phalcon\\Mvc\\Model');
        $this->assertSame(
            'https://docs.phalcon.io/6.0/en/api/Phalcon_Mvc',
            $phalconLink
        );

        /** @var string $internalLink */
        $internalLink = $classLink->invoke($builder, '__PHP_Incomplete_Class');
        $this->assertSame(
            'https://secure.php.net/manual/en/class.--php-incomplete-class.php',
            $internalLink
        );

        $this->assertNull($classLink->invoke($builder, 'PHPUnit\\Framework\\TestCase'));

        $functionLink = new \ReflectionMethod(ReportBuilder::class, 'resolveFunctionLink');

        /** @var string $arrayMapLink */
        $arrayMapLink = $functionLink->invoke($builder, 'array_map');
        $this->assertSame(
            'https://secure.php.net/manual/en/function.array-map.php',
            $arrayMapLink
        );

        $this->assertNull($functionLink->invoke($builder, 'phalcon_undefined_function_xyz'));
        $this->assertNull($functionLink->invoke($builder, 'supportDir'));
        $this->assertNull($functionLink->invoke($builder, 'trigger_deprecation'));
    }

    public function testMetaWithoutBacktrace(): void
    {
        $exception = new Exception('boom', 7);
        $report    = (new ReportBuilder())->build(
            $exception,
            ['request' => [], 'server' => []],
            false,
            true,
            false,
            'https://cdn/',
            []
        );

        $this->assertSame(Exception::class, $report->getClassName());
        $this->assertSame('boom', $report->getMessage());
        $this->assertSame('https://cdn/', $report->getUri());
        $this->assertSame([], $report->getBacktrace());
    }

    public function testRequestBlacklistIsApplied(): void
    {
        $_REQUEST['DATA_REQUEST_TEST'] = 'secret';
        $_SERVER['DATA_SERVER_TEST']   = 'keepme';

        $report = (new ReportBuilder())->build(
            new Exception('boom', 7),
            ['request' => ['data_request_test' => 1], 'server' => []],
            true,
            true,
            false,
            'https://cdn/',
            []
        );

        $this->assertArrayNotHasKey('DATA_REQUEST_TEST', $report->getRequest());
        $this->assertArrayHasKey('DATA_SERVER_TEST', $report->getServer());
        $this->assertGreaterThan(0, $report->getMemoryUsage());
        $this->assertGreaterThan(0, $report->getPeakMemoryUsage());
    }
}
