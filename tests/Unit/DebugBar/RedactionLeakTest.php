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

use Phalcon\DebugBar\Core;
use Phalcon\DebugBar\Debug;
use Phalcon\DebugBar\History\HistoryCookie;
use Phalcon\DebugBar\Logger\Adapter;
use Phalcon\Http\Request;
use Phalcon\Http\RequestInterface;
use Phalcon\Http\Response;
use Phalcon\Logger\Logger;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use PHPUnit\Framework\Attributes\BackupGlobals;

use function array_map;
use function bin2hex;
use function dirname;
use function file_get_contents;
use function glob;
use function implode;
use function is_array;
use function is_dir;
use function is_file;
use function random_bytes;
use function rmdir;
use function str_repeat;
use function unlink;

/**
 * Checks the two exits of a request: the bar in the response and the history files
 * on disk. A value under a masked key must not reach either one.
 */
#[BackupGlobals(true)]
final class RedactionLeakTest extends AbstractUnitTestCase
{
    private const ENV_VAR = 'DEBUGBAR_LEAK_TEST_ENV';

    private const SECRET_IN_LOG = 'secret-in-the-log-context';

    private const SECRET_IN_QUERY = 'secret-in-the-query';

    private string $storage = '';

    protected function setUp(): void
    {
        parent::setUp();

        Debug::setBar(null);
        $this->storage = dirname(__DIR__, 2) . '/_output/debugbar-leak-' . bin2hex(random_bytes(8));

        $_COOKIE[HistoryCookie::NAME] = str_repeat('a', 64);
        $_ENV[self::ENV_VAR]          = 'dev';
        $_GET                         = ['token' => self::SECRET_IN_QUERY, 'page' => '2'];
        $_SERVER['REMOTE_ADDR']       = '127.0.0.1';
        $_SERVER['REQUEST_METHOD']    = 'GET';
        $_SERVER['REQUEST_URI']       = '/reset?token=' . self::SECRET_IN_QUERY . '&page=2';
    }

    protected function tearDown(): void
    {
        Debug::setBar(null);
        foreach ($this->storedFiles() as $file) {
            unlink($file);
        }
        foreach ([$this->storage . '/.gc', $this->storage . '/.lock'] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        foreach ($this->matching($this->storage . '/*') as $directory) {
            rmdir($directory);
        }
        if (is_dir($this->storage)) {
            rmdir($this->storage);
        }

        parent::tearDown();
    }

    public function testSecretsDoNotReachTheBarOrTheStoredFiles(): void
    {
        $core     = new Core([
            'env'     => ['var' => self::ENV_VAR],
            'history' => ['enabled' => true, 'path' => $this->storage],
        ]);
        $request  = new Request();
        $response = new Response();
        $response->setContent('<html><body>page</body></html>');

        $core->boot(null, $request, null, $core->createHistory(fn (): RequestInterface => $request));
        (new Logger('app', ['debugbar' => new Adapter()]))->info('signed in', ['password' => self::SECRET_IN_LOG]);
        $core->handle($response);

        $files  = $this->storedFiles();
        $stored = implode("\n", array_map(fn (string $file): string => (string) file_get_contents($file), $files));

        $this->assertCount(2, $files);
        $this->assertStringContainsString('signed in', $response->getContent());
        $this->assertStringContainsString('signed in', $stored);
        $this->assertStringContainsString('/reset?token=***', $stored);
        foreach ([$response->getContent(), $stored] as $exit) {
            $this->assertStringNotContainsString(self::SECRET_IN_LOG, $exit);
            $this->assertStringNotContainsString(self::SECRET_IN_QUERY, $exit);
        }
    }

    /**
     * @return list<string>
     */
    private function matching(string $pattern): array
    {
        $matches = glob($pattern);

        return is_array($matches) ? $matches : [];
    }

    /**
     * @return list<string>
     */
    private function storedFiles(): array
    {
        return $this->matching($this->storage . '/*/*');
    }
}
