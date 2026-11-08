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

use Closure;
use ErrorException;
use LogicException;
use Phalcon\Debug\Report\BacktraceItem;
use Phalcon\Debug\Report\CodeFragment;
use Phalcon\Debug\Report\ReportOptions;
use Phalcon\Debug\Report\Superglobals;
use Phalcon\Debug\ReportBuilder;
use Phalcon\Support\Exception;
use Phalcon\Support\Version;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Talon\Talon;
use RuntimeException;

use function bin2hex;
use function dirname;
use function file_put_contents;
use function random_bytes;
use function unlink;

final class ReportBuilderTest extends AbstractUnitTestCase
{
    public function testBuildCastsRequestAndServerBlacklistToArray(): void
    {
        $report = (new ReportBuilder())->build(
            new Exception('boom', 7),
            new ReportOptions(
                ['request' => 'not-an-array', 'server' => 'not-an-array'],
                true,
                false,
                false,
                'https://cdn/',
                []
            ),
            new Superglobals(
                ['REQ_KEEP' => 'reqvalue'],
                ['SRV_KEEP' => 'srvvalue']
            )
        );

        $this->assertArrayHasKey('REQ_KEEP', $report->getRequest());
        $this->assertArrayHasKey('SRV_KEEP', $report->getServer());
    }

    public function testBuildCollectsThePreviousExceptions(): void
    {
        $first  = new RuntimeException('first');
        $second = new LogicException('second', 0, $first);
        $main   = new Exception('main', 0, $second);

        $report = (new ReportBuilder())->build(
            $main,
            new ReportOptions(['request' => [], 'server' => []], false, false, false, 'https://cdn/', []),
            new Superglobals([], [])
        );

        $this->assertSame(
            [
                [
                    'className' => LogicException::class,
                    'message'   => 'second',
                    'file'      => __FILE__,
                    'line'      => $second->getLine(),
                ],
                [
                    'className' => RuntimeException::class,
                    'message'   => 'first',
                    'file'      => __FILE__,
                    'line'      => $first->getLine(),
                ],
            ],
            $report->getPreviousExceptions()
        );
    }

    public function testBuildFragmentForUnreadableFile(): void
    {
        $builder = new ReportBuilder();

        $method = new \ReflectionMethod(ReportBuilder::class, 'buildFragment');

        /** @var CodeFragment $fragment */
        $fragment = $this->withWarningsAsExceptions(
            fn (): mixed => $method->invoke($builder, "/app/x.php(3) : eval()'d code", 5, false)
        );

        $this->assertSame([], $fragment->getLines());
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

    public function testEachFileIsReadOnceForEachReport(): void
    {
        $file    = dirname(__DIR__, 2) . '/_output/report-builder-' . bin2hex(random_bytes(4)) . '.php';
        $builder = new ReportBuilder();
        $method  = new \ReflectionMethod(ReportBuilder::class, 'buildFragment');

        file_put_contents($file, "first\n");
        /** @var CodeFragment $before */
        $before = $method->invoke($builder, $file, 1, false);

        file_put_contents($file, "second\n");
        /** @var CodeFragment $cached */
        $cached = $method->invoke($builder, $file, 1, false);

        $builder->build(
            new Exception('next report'),
            new ReportOptions(['request' => [], 'server' => []], false, false, false, 'https://cdn/', []),
            new Superglobals([], [])
        );
        /** @var CodeFragment $after */
        $after = $method->invoke($builder, $file, 1, false);
        unlink($file);

        $this->assertSame(["first\n"], $before->getLines());
        $this->assertSame(["first\n"], $cached->getLines());
        $this->assertSame(["second\n"], $after->getLines());
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

        /** @var CodeFragment $fragment */
        $fragment = $method->invoke($builder, $file, 3, true);

        $this->assertSame(1, $fragment->getFirstLine());
        $this->assertSame(3, $fragment->getLine());
    }

    public function testFragmentModeFragment(): void
    {
        $builder = new ReportBuilder();
        $file    = Talon::settings()->supportPath('assets/Debug/report-fragment.txt');

        $method = new \ReflectionMethod(ReportBuilder::class, 'buildFragment');

        /** @var CodeFragment $fragment */
        $fragment = $method->invoke($builder, $file, 20, true);

        $this->assertSame(13, $fragment->getFirstLine());
        $this->assertSame(20, $fragment->getLine());
        $this->assertSame(25, $fragment->getLastLine());
        $this->assertCount(30, $fragment->getLines());
    }

    public function testFragmentModeFull(): void
    {
        $builder = new ReportBuilder();
        $file    = Talon::settings()->supportPath('assets/Debug/report-fragment.txt');

        $method = new \ReflectionMethod(ReportBuilder::class, 'buildFragment');

        /** @var CodeFragment $fragment */
        $fragment = $method->invoke($builder, $file, 20, false);

        $this->assertSame(1, $fragment->getFirstLine());
        $this->assertSame(20, $fragment->getLine());
        $this->assertSame(30, $fragment->getLastLine());
    }

    public function testGlobalClassThatStartsWithPhalconHasNoDocsLink(): void
    {
        if (!class_exists('PhalconHelper', false)) {
            class_alias(self::class, 'PhalconHelper');
        }

        $method = new \ReflectionMethod(ReportBuilder::class, 'resolveClassLink');

        $this->assertNull($this->withWarningsAsExceptions(
            fn (): mixed => $method->invoke(new ReportBuilder(), 'PhalconHelper')
        ));
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
            new ReportOptions(['request' => [], 'server' => []], true, false, false, 'https://cdn/', []),
            new Superglobals([], [])
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
        $version     = new Version();
        $this->assertSame(
            'https://docs.phalcon.io/'
            . $version->getPart(Version::VERSION_MAJOR) . '.'
            . $version->getPart(Version::VERSION_MEDIUM)
            . '/api/phalcon_mvc/',
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
            new ReportOptions(['request' => [], 'server' => []], false, true, false, 'https://cdn/', []),
            new Superglobals([], [])
        );

        $this->assertSame(Exception::class, $report->getClassName());
        $this->assertSame('boom', $report->getMessage());
        $this->assertSame('https://cdn/', $report->getUri());
        $this->assertSame([], $report->getBacktrace());
    }

    public function testPackageClassesHaveNoDocsLink(): void
    {
        $method  = new \ReflectionMethod(ReportBuilder::class, 'resolveClassLink');
        $builder = new ReportBuilder();

        $this->assertNull($method->invoke($builder, 'Phalcon\\Debug'));
        $this->assertNull($method->invoke($builder, 'Phalcon\\Debug\\Dump'));
        $this->assertNull($method->invoke($builder, 'Phalcon\\DebugBar\\Core'));
    }

    public function testRequestBlacklistFiltersNestedKeys(): void
    {
        $report = (new ReportBuilder())->build(
            new Exception('boom', 7),
            new ReportOptions(
                ['request' => ['password' => 1, 'token' => 1], 'server' => []],
                true,
                true,
                false,
                'https://cdn/',
                []
            ),
            new Superglobals(
                [
                    'user' => [
                        'name'     => 'alice',
                        'password' => 'nested-secret',
                        'profile'  => ['city' => 'Athens', 'TOKEN' => 'deep-secret'],
                    ],
                ],
                []
            )
        );

        $this->assertSame(
            ['user' => ['name' => 'alice', 'profile' => ['city' => 'Athens']]],
            $report->getRequest()
        );
    }

    public function testRequestBlacklistIsApplied(): void
    {
        $report = (new ReportBuilder())->build(
            new Exception('boom', 7),
            new ReportOptions(
                ['request' => ['data_request_test' => 1], 'server' => []],
                true,
                true,
                false,
                'https://cdn/',
                []
            ),
            new Superglobals(
                ['DATA_REQUEST_TEST' => 'secret'],
                ['DATA_SERVER_TEST' => 'keepme']
            )
        );

        $this->assertArrayNotHasKey('DATA_REQUEST_TEST', $report->getRequest());
        $this->assertArrayHasKey('DATA_SERVER_TEST', $report->getServer());
        $this->assertGreaterThan(0, $report->getMemoryUsage());
        $this->assertGreaterThan(0, $report->getPeakMemoryUsage());
    }

    /**
     * Runs the operation with an error handler that throws, as
     * `Debug::listenLowSeverity()` does.
     */
    private function withWarningsAsExceptions(Closure $operation): mixed
    {
        set_error_handler(static function (int $severity, string $message): never {
            throw new ErrorException($message, 0, $severity);
        });

        try {
            return $operation();
        } finally {
            restore_error_handler();
        }
    }
}
