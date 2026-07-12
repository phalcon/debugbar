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

namespace Phalcon\DebugBar\Collector;

use Phalcon\DebugBar\Security\Redactor;

use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function substr;
use function ucwords;

/**
 * Snapshots the incoming request from the superglobals — query, post, cookies,
 * and headers — redacting sensitive keys and flattening the nested arrays into a
 * single grid. Reads raw PHP state, so nothing is resolved from the container.
 */
final class RequestCollector extends AbstractCollector
{
    public const NAME = 'request';

    /**
     * @var string
     */
    protected string $icon = 'icon-request';

    /**
     * @var string
     */
    protected string $label = 'Request';

    /**
     * @var string
     */
    protected string $panel = 'grid';

    /**
     * @param Redactor $redactor
     */
    public function __construct(private readonly Redactor $redactor)
    {
    }

    /**
     * @return array{panel: array<string, scalar>, badge: scalar|null}
     */
    public function collect(): array
    {
        $grid = [
            'Method' => $this->server('REQUEST_METHOD'),
            'URI'    => $this->server('REQUEST_URI'),
        ];

        $sections = [
            'Query'   => $_GET,
            'Post'    => $_POST,
            'Cookies' => $_COOKIE,
            'Headers' => $this->headers(),
        ];

        foreach ($sections as $label => $values) {
            $flat = $this->flatten($this->redactor->redact($values));
            foreach ($flat as $key => $value) {
                $grid[$label . '.' . $key] = $value;
            }
        }

        return [
            'panel' => $grid,
            'badge' => null,
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     * @param string                  $prefix
     *
     * @return array<string, scalar>
     */
    private function flatten(array $data, string $prefix = ''): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $name = ('' === $prefix) ? (string) $key : $prefix . '.' . $key;

            if (is_array($value)) {
                foreach ($this->flatten($value, $name) as $nestedKey => $nestedValue) {
                    $result[$nestedKey] = $nestedValue;
                }

                continue;
            }

            $result[$name] = $this->stringify($value);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function headers(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'HTTP_')) {
                $headers[$this->headerName($key)] = $value;
            }
        }

        return $headers;
    }

    /**
     * @param string $key
     *
     * @return string
     */
    private function headerName(string $key): string
    {
        $name = str_replace('_', ' ', strtolower(substr($key, 5)));

        return str_replace(' ', '-', ucwords($name));
    }

    /**
     * @param string $key
     *
     * @return string
     */
    private function server(string $key): string
    {
        $value = $_SERVER[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    private function stringify(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return '';
    }
}
