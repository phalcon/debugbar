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

$finder = PhpCsFixer\Finder::create()
    ->in([dirname(__DIR__) . '/src', dirname(__DIR__) . '/tests']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12'                           => true,
        'declare_strict_types'             => true,
        'blank_line_between_import_groups' => true,
        'ordered_imports'                  => [
            'sort_algorithm' => 'alpha',
            'imports_order'  => ['class', 'function', 'const'],
        ],
        'no_unused_imports'                => true,
        'array_syntax'                     => ['syntax' => 'short'],
    ])
    ->setFinder($finder)
    ->setCacheFile(dirname(__DIR__) . '/tests/_output/.php-cs-fixer.cache');
