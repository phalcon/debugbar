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

namespace Phalcon\Tests\Unit\DebugBar;

use Phalcon\DebugBar\Debug;
use Phalcon\DebugBar\DebugBar;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\Fixtures\RecordingMessagesCollector;
use Phalcon\Tests\Support\DebugBar\Fixtures\RecordingTimeCollector;
use ReflectionClass;
use RuntimeException;

final class DebugTest extends AbstractUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Debug::setBar(null);
    }

    protected function tearDown(): void
    {
        Debug::setBar(null);

        parent::tearDown();
    }

    public function testConstructorIsPrivate(): void
    {
        $reflection  = new ReflectionClass(Debug::class);
        $constructor = $reflection->getConstructor();

        if (null === $constructor) {
            self::fail('The Debug facade must declare a constructor.');
        }

        $this->assertTrue($constructor->isPrivate());

        $constructor->setAccessible(true);
        $constructor->invoke($reflection->newInstanceWithoutConstructor());
    }

    public function testDelegatesEveryConvenienceMethod(): void
    {
        $messages = new RecordingMessagesCollector();
        $time     = new RecordingTimeCollector();
        $bar      = new DebugBar();
        $bar->addCollector($messages);
        $bar->addCollector($time);

        Debug::setBar($bar);
        Debug::debug('d');
        Debug::notice('n');
        Debug::warning('w');
        Debug::error('e');
        Debug::startMeasure('m', 'M');
        Debug::stopMeasure('m');

        $this->assertCount(4, $messages->getMessages());
        $this->assertCount(2, $time->getMeasures());
    }

    public function testDelegatesToTheBarWhenSet(): void
    {
        $messages = new RecordingMessagesCollector();
        $bar      = new DebugBar();
        $bar->addCollector($messages);

        Debug::setBar($bar);
        Debug::message('hello', 'notice');
        Debug::info('a');

        $this->assertSame($bar, Debug::getBar());
        $this->assertCount(2, $messages->getMessages());
    }

    public function testNoOpsWhenUnset(): void
    {
        Debug::message('x');
        Debug::info('y');
        Debug::startMeasure('z');
        Debug::addException(new RuntimeException('boom'));

        $this->assertNull(Debug::getBar());
    }
}
