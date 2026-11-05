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

namespace Phalcon\Tests\Unit\DebugBar\Security;

use Phalcon\DebugBar\Security\Redactor;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class RedactorTest extends AbstractUnitTestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function uris(): array
    {
        return [
            'no query'             => ['/orders/42', '/orders/42'],
            'empty query'          => ['/orders?', '/orders?'],
            'no sensitive key'     => ['/orders?a[]=1&b=x%20y', '/orders?a[]=1&b=x%20y'],
            'sensitive key'        => ['/reset?token=abc&page=2', '/reset?token=***&page=2'],
            'key in another case'  => ['/reset?TOKEN=abc', '/reset?TOKEN=***'],
            'absolute URL'         => ['https://example.test/a?password=abc', 'https://example.test/a?password=***'],
            'nested sensitive key' => ['/a?filter[token]=abc', '/a?filter%5Btoken%5D=***'],
            'hidden key'           => ['/a?internal=abc&page=2', '/a?page=2'],
            'only a hidden key'    => ['/a?internal=abc', '/a'],
        ];
    }

    public function testConfiguredMultibyteKeysAreLowered(): void
    {
        $redacted = (new Redactor(['ПАРОЛЬ']))->redact([
            'пароль' => 'hunter2',
        ]);

        $this->assertSame('***', $redacted['пароль']);
    }

    public function testHiddenKeysAreOmittedEntirely(): void
    {
        $redacted = (new Redactor(['password'], ['internal']))->redact([
            'internal' => 'gone',
            'user'     => 'sarah-connor',
            'password' => 'hunter2',
        ]);

        $this->assertArrayNotHasKey('internal', $redacted);
        $this->assertArrayHasKey('user', $redacted);
        $this->assertSame('sarah-connor', $redacted['user']);
        $this->assertSame('***', $redacted['password']);
    }

    public function testRedactsConfiguredKeysCaseInsensitively(): void
    {
        $redacted = (new Redactor())->redact([
            'user'     => 'sarah-connor',
            'password' => 'hunter2',
            'Token'    => 'abc',
        ]);

        $this->assertSame('sarah-connor', $redacted['user']);
        $this->assertSame('***', $redacted['password']);
        $this->assertSame('***', $redacted['Token']);
    }

    public function testRedactsMultibyteKeyCaseInsensitively(): void
    {
        $redacted = (new Redactor(['пароль']))->redact([
            'ПАРОЛЬ' => 'hunter2',
        ]);

        $this->assertSame('***', $redacted['ПАРОЛЬ']);
    }

    public function testRedactsNestedKeys(): void
    {
        $redacted = (new Redactor())->redact([
            'db' => ['host' => 'localhost', 'secret' => 'x'],
        ]);

        $this->assertSame(['host' => 'localhost', 'secret' => '***'], $redacted['db']);
    }

    #[DataProvider('uris')]
    public function testRedactUri(string $uri, string $expected): void
    {
        $this->assertSame($expected, (new Redactor(Redactor::DEFAULT_KEYS, ['internal']))->redactUri($uri));
    }

    public function testUsesCustomKeys(): void
    {
        $redacted = (new Redactor(['pin']))->redact([
            'pin'      => '1234',
            'password' => 'kept',
        ]);

        $this->assertSame('***', $redacted['pin']);
        $this->assertSame('kept', $redacted['password']);
    }
}
