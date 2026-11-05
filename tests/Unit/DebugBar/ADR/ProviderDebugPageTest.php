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

namespace Phalcon\Tests\Unit\DebugBar\ADR;

use Phalcon\Debug;
use Phalcon\Debug\Exceptions\RequestHalted;
use Phalcon\DebugBar\Debug as BarFacade;
use Phalcon\DebugBar\DebugBarTypes;
use Phalcon\Http\ResponseInterface;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\ADR\Action\Failure\GetFailure;
use Phalcon\Tests\Support\DebugBar\ADR\Front;
use Phalcon\Tests\Support\DebugBar\ADR\RecordingEmitter;
use PHPUnit\Framework\Attributes\BackupGlobals;

use function bin2hex;
use function random_bytes;

/**
 * Real requests through a real ADR front controller, with the debug page on.
 *
 * @phpstan-import-type provider_config from DebugBarTypes
 */
#[BackupGlobals(true)]
final class ProviderDebugPageTest extends AbstractUnitTestCase
{
    /**
     * The debug page lists the source of the files in the backtrace, this file
     * also. It escapes the source, so these strings of raw markup do not match it.
     */
    private const APP_PAGE = '<body>error page';

    private const BAR = '<div id="phalcon-debugbar">';

    private const DEBUG_PAGE = "data-tab='backtrace'";

    private const ENV_VAR = 'DEBUGBAR_TEST_ENV';

    protected function setUp(): void
    {
        parent::setUp();

        BarFacade::setBar(null);

        $_ENV[self::ENV_VAR] = 'dev';
    }

    protected function tearDown(): void
    {
        BarFacade::setBar(null);

        parent::tearDown();
    }

    public function testBlockedEnvironmentKeepsTheApplicationPage(): void
    {
        $_ENV[self::ENV_VAR] = 'production';

        $content = $this->request('/failure', $this->config())->getContent();

        $this->assertStringContainsString(self::APP_PAGE, $content);
        $this->assertStringNotContainsString(self::DEBUG_PAGE, $content);
    }

    public function testConfiguredDebugPageIsUsed(): void
    {
        $secret                       = bin2hex(random_bytes(16));
        $_SERVER['DEBUG_PAGE_SECRET'] = $secret;

        $default = $this->request('/failure', $this->config())->getContent();

        $this->assertStringContainsString($secret, $default);

        $debug      = (new Debug())->setBlacklist(['server' => ['DEBUG_PAGE_SECRET']]);
        $configured = $this->request('/failure', $this->config($debug))->getContent();

        $this->assertStringContainsString(GetFailure::MESSAGE, $configured);
        $this->assertStringNotContainsString($secret, $configured);
    }

    public function testDebugHaltInAnActionShowsTheDebugPage(): void
    {
        $response = $this->request('/halt', $this->config());

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString(self::DEBUG_PAGE, $response->getContent());
        $this->assertStringContainsString(RequestHalted::class, $response->getContent());
        $this->assertStringNotContainsString(self::APP_PAGE, $response->getContent());
    }

    public function testDebugPageIsOffByDefault(): void
    {
        $content = $this->request('/failure', ['env' => ['var' => self::ENV_VAR]])->getContent();

        $this->assertStringContainsString(self::APP_PAGE, $content);
        $this->assertStringContainsString(self::BAR, $content);
        $this->assertStringNotContainsString(self::DEBUG_PAGE, $content);
    }

    public function testDeniedClientKeepsTheApplicationPage(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';

        $content = $this->request(
            '/failure',
            $this->config() + ['access' => ['allow_ips' => ['127.0.0.1']]]
        )->getContent();

        $this->assertStringContainsString(self::APP_PAGE, $content);
        $this->assertStringNotContainsString(self::DEBUG_PAGE, $content);
    }

    public function testExceptionInAnActionShowsTheDebugPage(): void
    {
        $response = $this->request('/failure', $this->config());

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString(self::DEBUG_PAGE, $response->getContent());
        $this->assertStringContainsString(GetFailure::MESSAGE, $response->getContent());
        $this->assertStringNotContainsString(self::APP_PAGE, $response->getContent());
        $this->assertStringNotContainsString(self::BAR, $response->getContent());
    }

    public function testFalseKeepsTheApplicationPage(): void
    {
        $content = $this->request('/failure', $this->config(false))->getContent();

        $this->assertStringContainsString(self::APP_PAGE, $content);
        $this->assertStringNotContainsString(self::DEBUG_PAGE, $content);
    }

    public function testJsonErrorKeepsTheJsonBody(): void
    {
        $response = $this->request('/failure', $this->config(), false);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString('Internal Server Error', $response->getContent());
        $this->assertStringNotContainsString(GetFailure::MESSAGE, $response->getContent());
    }

    public function testNormalPageKeepsTheBar(): void
    {
        $content = $this->request('/page', $this->config())->getContent();

        $this->assertStringStartsWith('<html><body>page', $content);
        $this->assertStringContainsString(self::BAR, $content);
    }

    public function testRouteNotFoundKeepsTheApplicationPageAndTheBar(): void
    {
        $response = $this->request('/missing', $this->config());

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString(self::APP_PAGE, $response->getContent());
        $this->assertStringContainsString(self::BAR, $response->getContent());
    }

    /**
     * @return array{env: array{var: string}, debug_page: bool|Debug}
     */
    private function config(bool | Debug $debugPage = true): array
    {
        return [
            'env'        => ['var' => self::ENV_VAR],
            'debug_page' => $debugPage,
        ];
    }

    /**
     * @param provider_config $config
     */
    private function request(string $uri, array $config, bool $htmlErrors = true): ResponseInterface
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = $uri;

        $emitter = new RecordingEmitter();

        $this->assertSame(0, (new Front($emitter, $config, $htmlErrors))->run());
        if (null === $emitter->response) {
            $this->fail('Expected the front controller to emit a response.');
        }

        return $emitter->response;
    }
}
