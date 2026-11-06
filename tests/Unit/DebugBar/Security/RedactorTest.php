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
     * @return array<string, array{0: string}>
     */
    public static function keptKeys(): array
    {
        return [
            'word that contains key'      => ['keyword'],
            'word that ends with key'     => ['monkey'],
            'plural of a sensitive word'  => ['tokens'],
            'no sensitive part'           => ['user_name'],
            'sensitive word with a tail'  => ['passwordless'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function maskedKeys(): array
    {
        return [
            'underscore'            => ['reset_token'],
            'camelCase'             => ['accessToken'],
            'hyphen'                => ['X-Api-Key'],
            'dot'                   => ['db.password'],
            'acronym before a word' => ['APIKey'],
            'digit before a word'   => ['oauth2Token'],
            'two parts'             => ['client_secret'],
            'header name'           => ['Set-Cookie'],
            'three parts'           => ['x-csrf-token'],
            'basic auth password'   => ['Php-Auth-Pw'],
            'basic auth user'       => ['Php-Auth-User'],
            'digest auth'           => ['Php-Auth-Digest'],
            'short password'        => ['pwd'],
            'unix password'         => ['passwd'],
        ];
    }

    /**
     * @return array<string, array{0: list<string>, 1: string, 2: string}>
     */
    public static function unmaskPatterns(): array
    {
        return [
            'name shows the same key'               => [['card'], 'card', 'value'],
            'name keeps a longer key masked'        => [['card'], 'card_number', '***'],
            'trailing wildcard shows the name'      => [['card*'], 'card', 'value'],
            'trailing wildcard shows a longer key'  => [['card*'], 'card_number', 'value'],
            'trailing wildcard keeps other prefix'  => [['card*'], 'customer_card', '***'],
            'wildcards on both sides'               => [['*card*'], 'customer_card_number', 'value'],
            'pattern in another case'               => [['Primary_Key'], 'PRIMARY_KEY', 'value'],
            'dot is not a wildcard'                 => [['primary.key'], 'primary_key', '***'],
            'dot matches a dot'                     => [['primary.key'], 'primary.key', 'value'],
            'wildcard alone shows each masked key'  => [['*'], 'card', 'value'],
        ];
    }

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
            'part of a key'        => ['/reset?reset_token=abc', '/reset?reset_token=***'],
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

    public function testEntryWithManyPartsMatchesAdjacentParts(): void
    {
        $redacted = (new Redactor(['card_number']))->redact([
            'cardNumber'           => 1,
            'customer_card_number' => 2,
            'CARD-NUMBER'          => 3,
            'card_id_number'       => 4,
            'number_card'          => 5,
        ]);

        $this->assertSame(
            [
                'cardNumber'           => '***',
                'customer_card_number' => '***',
                'CARD-NUMBER'          => '***',
                'card_id_number'       => 4,
                'number_card'          => 5,
            ],
            $redacted
        );
    }

    public function testEntryWithNoWordPartsMatchesOnlyTheWholeKey(): void
    {
        $redacted = (new Redactor(['', '_']))->redact([
            '_'   => 1,
            'a_b' => 2,
            'a'   => 3,
        ]);

        $this->assertSame(['_' => '***', 'a_b' => 2, 'a' => 3], $redacted);
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

    public function testHiddenMatchesWordParts(): void
    {
        $redacted = (new Redactor([], ['card']))->redact([
            'card_number' => 1,
            'cardExpiry'  => 2,
            'discard'     => 3,
        ]);

        $this->assertSame(['discard' => 3], $redacted);
    }

    #[DataProvider('keptKeys')]
    public function testKeepsAKeyWithNoSensitiveWordPart(string $key): void
    {
        $this->assertSame('value', (new Redactor())->redact([$key => 'value'])[$key]);
    }

    #[DataProvider('maskedKeys')]
    public function testMasksAKeyWithASensitiveWordPart(string $key): void
    {
        $this->assertSame('***', (new Redactor())->redact([$key => 'value'])[$key]);
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

    /**
     * @param list<string> $unmask
     */
    #[DataProvider('unmaskPatterns')]
    public function testUnmask(array $unmask, string $key, string $expected): void
    {
        $redacted = (new Redactor(['card', 'key'], [], $unmask))->redact([$key => 'value']);

        $this->assertSame($expected, $redacted[$key]);
    }

    public function testUnmaskDoesNotShowAHiddenKey(): void
    {
        $redacted = (new Redactor(['password'], ['internal'], ['*']))->redact([
            'internal' => 'gone',
            'password' => 'hunter2',
        ]);

        $this->assertSame(['password' => 'hunter2'], $redacted);
    }

    public function testUnmaskedKeyRedactsItsNestedValues(): void
    {
        $redacted = (new Redactor(Redactor::DEFAULT_KEYS, [], ['primary_key']))->redact([
            'primary_key' => ['password' => 'hunter2', 'id' => 1],
        ]);

        $this->assertSame(['primary_key' => ['password' => '***', 'id' => 1]], $redacted);
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

    public function testWholeKeyNameStillMatches(): void
    {
        $redacted = (new Redactor(['apiKey']))->redact([
            'APIKEY' => 'x',
            'apikey' => 'y',
        ]);

        $this->assertSame(['APIKEY' => '***', 'apikey' => '***'], $redacted);
    }
}
