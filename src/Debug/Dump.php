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

namespace Phalcon\Debug;

use InvalidArgumentException;
use JsonException;
use Phalcon\Container\Container;
use Phalcon\Debug\Contracts\TemplateAware;
use Phalcon\Debug\Template\DumpTemplateCatalog;
use Phalcon\Debug\Template\TemplateStore;
use Phalcon\Di\DiInterface;
use Phalcon\Support\Helper\Json\Encode;
use Phalcon\Traits\Support\Helper\Str\InterpolateTrait;
use Reflection;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use stdClass;

use function array_merge;
use function get_class;
use function get_class_methods;
use function get_object_vars;
use function get_parent_class;
use function htmlentities;
use function implode;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_numeric;
use function is_object;
use function is_string;
use function mb_strlen;
use function nl2br;
use function str_repeat;

use const ENT_IGNORE;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const PHP_EOL;

/**
 * Dumps information about a variable(s)
 *
 * ```php
 * $foo = 123;
 *
 * echo (new \Phalcon\Debug\Dump())->variable($foo, "foo");
 * ```
 *
 * ```php
 * $foo = "string";
 * $bar = ["key" => "value"];
 * $baz = new stdClass();
 *
 * echo (new \Phalcon\Debug\Dump())->variables($foo, $bar, $baz);
 * ```
 *
 * @property bool                  $detailed
 * @property array<string, string> $styles
 */
class Dump implements TemplateAware
{
    use InterpolateTrait;

    /**
     * @var bool
     */
    protected bool $detailed = false;

    /**
     * @var array<string, string>
     */
    protected array $styles = [];

    /**
     * @var Encode
     */
    private Encode $encode;

    /**
     * @var TemplateStore
     */
    private TemplateStore $templates;

    /**
     * Dump constructor.
     *
     * @param array<string, string> $styles
     * @param bool                  $detailed
     */
    public function __construct(array $styles = [], bool $detailed = false)
    {
        $this->encode    = new Encode();
        $this->templates = new TemplateStore(new DumpTemplateCatalog());

        $this->setStyles($styles);

        $this->detailed = $detailed;
    }

    /**
     * Alias of variables() method
     *
     * @param mixed ...$vars
     *
     * @return string
     * @throws ReflectionException
     */
    public function all(mixed ...$vars): string
    {
        return $this->variables(...$vars);
    }

    /**
     * @return bool
     */
    public function getDetailed(): bool
    {
        return $this->detailed;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    public function getTemplate(string $name): string
    {
        return $this->templates->get($name);
    }

    /**
     * Alias of variable() method
     *
     * @param mixed       $variable
     * @param string|null $name
     *
     * @return string
     * @throws ReflectionException
     */
    public function one(mixed $variable, string | null $name = null): string
    {
        return $this->variable($variable, $name);
    }

    /**
     * @param bool $flag
     */
    public function setDetailed(bool $flag): void
    {
        $this->detailed = $flag;
    }

    /**
     * Set styles for vars type
     *
     * @param array<string, string> $styles
     *
     * @return array<string, string>
     */
    public function setStyles(array $styles = []): array
    {
        $defaultStyles = [
            'pre'   => 'background-color:#f3f3f3; font-size:11px; ' .
                'padding:10px; border:1px solid #ccc; ' .
                'text-align:left; color:#333',
            'arr'   => 'color:red',
            'bool'  => 'color:green',
            'float' => 'color:fuchsia',
            'int'   => 'color:blue',
            'null'  => 'color:black',
            'num'   => 'color:navy',
            'obj'   => 'color:purple',
            'other' => 'color:maroon',
            'res'   => 'color:lime',
            'str'   => 'color:teal',
        ];

        $this->styles = array_merge($defaultStyles, $styles);

        return $this->styles;
    }

    /**
     * Overrides the template for the given name.
     *
     * @param string $name
     * @param string $template
     *
     * @return static
     */
    public function setTemplate(string $name, string $template): static
    {
        $this->templates->set($name, $template);

        return $this;
    }

    /**
     * Returns an JSON string of information about a single variable.
     *
     * ```php
     * $foo = [
     *     "key" => "value",
     * ];
     *
     * echo (new \Phalcon\Debug\Dump())->toJson($foo);
     *
     * $foo = new stdClass();
     * $foo->bar = "buz";
     *
     * echo (new \Phalcon\Debug\Dump())->toJson($foo);
     * ```
     *
     * @param mixed $variable
     *
     * @return string
     * @throws InvalidArgumentException if the JSON cannot be encoded.
     * @throws JsonException
     */
    public function toJson(mixed $variable): string
    {
        return $this->encode->__invoke(
            $variable,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }

    /**
     * Returns an HTML string of information about a single variable.
     *
     * ```php
     * echo (new \Phalcon\Debug\Dump())->variable($foo, "foo");
     * ```
     *
     * @param mixed       $variable
     * @param string|null $name
     *
     * @return string
     * @throws ReflectionException
     */
    public function variable(mixed $variable, string | null $name = null): string
    {
        $seen = [];

        return $this->renderPreBlock($variable, $name, $seen);
    }

    /**
     * Returns an HTML string of debugging information about any number of
     * variables, each wrapped in a "pre" tag.
     *
     * ```php
     * $foo = "string";
     * $bar = ["key" => "value"];
     * $baz = new stdClass();
     *
     * echo (new \Phalcon\Debug\Dump())->variables($foo, $bar, $baz);
     * ```
     *
     * @param mixed ...$vars
     *
     * @return string
     * @throws ReflectionException
     */
    public function variables(mixed ...$vars): string
    {
        $seen   = [];
        $output = "";

        foreach ($vars as $key => $value) {
            $output .= $this->renderPreBlock($value, 'var ' . $key, $seen);
        }

        return $output;
    }

    /**
     * Prepare an HTML string of information about a single variable.
     *
     * @param mixed                    $variable
     * @param string|null              $name
     * @param int                      $tab
     * @param array<class-string, bool> $seen
     *
     * @return string
     * @throws ReflectionException
     */
    protected function formatValue(
        mixed $variable,
        string | null $name = null,
        int $tab = 1,
        array &$seen = []
    ): string {
        $output = (!empty($name)) ? $name . ' ' : '';

        return $output . match (true) {
            is_array($variable)   => $this->formatArray($variable, $name, $tab, $seen),
            is_object($variable)  => $this->formatObject($variable, $tab, $seen),
            is_int($variable)     => $this->formatInteger($variable),
            is_float($variable)   => $this->formatFloat($variable),
            is_numeric($variable) => $this->formatNumericString($variable),
            is_string($variable)  => $this->formatString($variable),
            is_bool($variable)    => $this->formatBoolean($variable),
            null === $variable    => $this->formatNull(),
            default               => $this->formatResource($variable),
        };
    }

    /**
     * Get style for type
     *
     * @param string $type
     *
     * @return string
     */
    protected function getStyle(string $type): string
    {
        if (isset($this->styles[$type])) {
            return $this->styles[$type];
        }

        return 'color:gray';
    }

    /**
     * @param array<array-key, mixed>   $variable
     * @param string|null               $name
     * @param int                       $tab
     * @param array<class-string, bool> $seen
     *
     * @return string
     * @throws ReflectionException
     */
    private function formatArray(array $variable, string | null $name, int $tab, array &$seen): string
    {
        $space   = '  ';
        $message = $this->getTemplate('arrayHeader') . PHP_EOL;
        $context = [
            'style' => $this->getStyle('arr'),
            'count' => (string) count($variable),
        ];

        $output = $this->toInterpolate($message, $context);
        foreach ($variable as $key => $value) {
            $output .= str_repeat($space, $tab);

            $message = $this->getTemplate('arrayKey');
            $context = [
                'style' => $this->getStyle('arr'),
                'key'   => $key,
            ];
            $output  .= $this->toInterpolate($message, $context);

            if (
                1 === $tab &&
                !empty($name) &&
                true !== is_int($key) &&
                $name === $key
            ) {
                continue;
            }

            $output .= $this->formatValue($value, '', $tab + 1, $seen) . "\n";
        }

        return $output . str_repeat($space, $tab - 1) . ')';
    }

    /**
     * @param bool $variable
     *
     * @return string
     */
    private function formatBoolean(bool $variable): string
    {
        return $this->formatLabeledValue('Boolean', 'bool', ($variable) ? 'TRUE' : 'FALSE');
    }

    /**
     * @param float $variable
     *
     * @return string
     */
    private function formatFloat(float $variable): string
    {
        return $this->formatLabeledValue('Float', 'float', (string) $variable);
    }

    /**
     * @param int $variable
     *
     * @return string
     */
    private function formatInteger(int $variable): string
    {
        return $this->formatLabeledValue('Integer', 'int', (string) $variable);
    }

    /**
     * Formats the near-identical int/float/bool leaves that share the
     * bold-label plus parenthesized-value shape.
     *
     * @param string $label
     * @param string $styleType
     * @param string $value
     *
     * @return string
     */
    private function formatLabeledValue(string $label, string $styleType, string $value): string
    {
        $message = $this->renderBoldLabel($label) . ' ' . $this->getTemplate('varParens');
        $context = [
            'style' => $this->getStyle($styleType),
            'var'   => $value,
        ];

        return $this->toInterpolate($message, $context);
    }

    /**
     * @return string
     */
    private function formatNull(): string
    {
        return $this->toInterpolate(
            $this->renderBoldLabel('NULL'),
            ['style' => $this->getStyle('null')]
        );
    }

    /**
     * @param string $variable
     *
     * @return string
     */
    private function formatNumericString(string $variable): string
    {
        $message = $this->renderBoldLabel('Numeric String') . ' ' . $this->getTemplate('lengthValue');
        $context = [
            'style'  => $this->getStyle('num'),
            'length' => (string) mb_strlen($variable),
            'var'    => $variable,
        ];

        return $this->toInterpolate($message, $context);
    }

    /**
     * @param object                    $variable
     * @param int                       $tab
     * @param array<class-string, bool> $seen
     *
     * @return string
     * @throws ReflectionException
     */
    private function formatObject(object $variable, int $tab, array &$seen): string
    {
        $space   = '  ';
        $message = $this->getTemplate('objectHeader');
        $context = [
            'style' => $this->getStyle('obj'),
            'class' => get_class($variable),
        ];
        $output  = $this->toInterpolate($message, $context);

        if (false !== get_parent_class($variable)) {
            $message = $this->getTemplate('objectExtends');
            $context = [
                'style'  => $this->getStyle('obj'),
                'parent' => get_parent_class($variable),
            ];
            $output  .= $this->toInterpolate($message, $context);
        }

        $output .= " (\n";
        $output .= $this->formatObjectProperties($variable, $tab, $seen);
        $output .= $this->formatObjectMethods($variable, $tab, $seen);

        return $output . str_repeat($space, $tab - 1) . ")";
    }

    /**
     * @param object                    $variable
     * @param int                       $tab
     * @param array<class-string, bool> $seen
     *
     * @return string
     */
    private function formatObjectMethods(object $variable, int $tab, array &$seen): string
    {
        $space     = '  ';
        $className = get_class($variable);
        $attr      = get_class_methods($variable);

        $message = $this->getTemplate('objectMethods');
        $context = [
            'style' => $this->getStyle('obj'),
            'class' => $className,
            'count' => (string) count($attr),
        ];

        $output = str_repeat($space, $tab)
            . $this->toInterpolate($message, $context);

        if (isset($seen[$className])) {
            return $output . str_repeat($space, $tab) . "[already listed]\n";
        }

        $seen[$className] = true;
        foreach ($attr as $value) {
            $message = $this->getTemplate('objectMethod');
            if ('__construct' === $value) {
                $message = $this->getTemplate('objectMethodConstructor');
            }
            $context = [
                'style'  => $this->getStyle('obj'),
                'method' => $value,
            ];

            $output .= str_repeat($space, $tab + 1)
                . $this->toInterpolate($message, $context);
        }

        return $output . str_repeat($space, $tab) . ")\n";
    }

    /**
     * @param object                    $variable
     * @param int                       $tab
     * @param array<class-string, bool> $seen
     *
     * @return string
     * @throws ReflectionException
     */
    private function formatObjectProperties(object $variable, int $tab, array &$seen): string
    {
        $space = '  ';

        if ($variable instanceof DiInterface || $variable instanceof Container) {
            // Skip debugging di and container
            return str_repeat($space, $tab) . "[skipped]\n";
        }

        if (true !== $this->detailed || $variable instanceof stdClass) {
            // Debug only public properties
            return $this->formatPublicProperties($variable, $tab, $seen);
        }

        // Debug all properties
        return $this->formatReflectedProperties($variable, $tab, $seen);
    }

    /**
     * @param object                    $variable
     * @param int                       $tab
     * @param array<class-string, bool> $seen
     *
     * @return string
     * @throws ReflectionException
     */
    private function formatPublicProperties(object $variable, int $tab, array &$seen): string
    {
        $space  = '  ';
        $output = '';
        $vars   = get_object_vars($variable);

        foreach ($vars as $key => $value) {
            $message = $this->getTemplate('objectProperty');
            $context = [
                'style' => $this->getStyle('obj'),
                'key'   => $key,
                'type'  => 'public',
            ];

            $output .= str_repeat($space, $tab)
                . $this->toInterpolate($message, $context)
                . $this->formatValue($value, '', $tab + 1, $seen)
                . "\n";
        }

        return $output;
    }

    /**
     * @param object                    $variable
     * @param int                       $tab
     * @param array<class-string, bool> $seen
     *
     * @return string
     * @throws ReflectionException
     */
    private function formatReflectedProperties(object $variable, int $tab, array &$seen): string
    {
        $space   = '  ';
        $output  = '';
        $reflect = new ReflectionClass($variable);
        $props   = $reflect->getProperties(
            ReflectionProperty::IS_PUBLIC |
            ReflectionProperty::IS_PROTECTED |
            ReflectionProperty::IS_PRIVATE
        );

        foreach ($props as $property) {
            $key  = $property->getName();
            $type = implode(
                ' ',
                Reflection::getModifierNames($property->getModifiers())
            );

            $message = $this->getTemplate('objectProperty');
            $context = [
                'style' => $this->getStyle('obj'),
                'key'   => $key,
                'type'  => $type,
            ];

            $output .= str_repeat($space, $tab)
                . $this->toInterpolate($message, $context)
                . $this->formatValue($property->getValue($variable), '', $tab + 1, $seen)
                . "\n";
        }

        return $output;
    }

    /**
     * @param mixed $variable
     *
     * @return string
     */
    private function formatResource(mixed $variable): string
    {
        /** @var resource $variable */
        $message = $this->getTemplate('varParens');
        $context = [
            'style' => $this->getStyle('other'),
            'var'   => (string) $variable,
        ];

        return $this->toInterpolate($message, $context);
    }

    /**
     * @param string $variable
     *
     * @return string
     */
    private function formatString(string $variable): string
    {
        $message = $this->renderBoldLabel('String') . ' ' . $this->getTemplate('lengthValue');
        $context = [
            'style'  => $this->getStyle('str'),
            'length' => (string) mb_strlen($variable),
            'var'    => nl2br(htmlentities($variable, ENT_IGNORE, 'utf-8')),
        ];

        return $this->toInterpolate($message, $context);
    }

    /**
     * @param string $text
     *
     * @return string
     */
    private function renderBoldLabel(string $text): string
    {
        return $this->toInterpolate(
            $this->getTemplate('bold'),
            ['text' => $text]
        );
    }

    /**
     * Wraps a single variable's formatted output in a "pre" block, sharing the
     * $seen guard so classes already listed earlier in the same top-level dump
     * are collapsed to "[already listed]".
     *
     * @param mixed                     $variable
     * @param string|null               $name
     * @param array<class-string, bool> $seen
     *
     * @return string
     * @throws ReflectionException
     */
    private function renderPreBlock(mixed $variable, string | null $name, array &$seen): string
    {
        return $this->toInterpolate(
            $this->getTemplate('pre'),
            [
                'style'  => $this->getStyle('pre'),
                'output' => $this->formatValue($variable, $name, 1, $seen),
            ]
        );
    }
}
