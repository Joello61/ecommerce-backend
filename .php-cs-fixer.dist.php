<?php

declare(strict_types=1);
use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = (new Finder())
    ->in(__DIR__)
    ->exclude([
        'var',
        'vendor',
        'public/bundles',
        'public/uploads',
        'node_modules',
        '.git',
    ])
    ->notPath([
        'config/bundles.php',
        'public/index.php',
    ])
    ->name('*.php')
    ->notName('*.blade.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PSR12' => true,
        '@PSR12:risky' => true,
        '@DoctrineAnnotation' => true,
        '@PhpCsFixer' => true,
        '@PhpCsFixer:risky' => true,

        // Arrays
        'array_syntax' => ['syntax' => 'short'],
        'array_indentation' => true,
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays', 'arguments', 'parameters'],
        ],

        // Classes
        'class_attributes_separation' => [
            'elements' => [
                'method' => 'one',
                'property' => 'one',
                'trait_import' => 'none',
            ],
        ],
        'ordered_class_elements' => [
            'order' => [
                'use_trait',
                'case',
                'constant_public',
                'constant_protected',
                'constant_private',
                'property_public',
                'property_protected',
                'property_private',
                'construct',
                'destruct',
                'magic',
                'phpunit',
                'method_public',
                'method_protected',
                'method_private',
            ],
        ],
        'final_class' => false,
        'final_public_method_for_abstract_class' => false,

        // Comments
        'comment_to_phpdoc' => [
            'ignored_tags' => [
                'todo',
                'codeCoverageIgnore',
                'codeCoverageIgnoreStart',
                'codeCoverageIgnoreEnd',
            ],
        ],
        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_line_span' => ['property' => 'single', 'method' => 'single'],
        'phpdoc_order' => true,
        'phpdoc_separation' => true,
        'phpdoc_summary' => false,
        'phpdoc_to_comment' => false,
        'phpdoc_var_annotation_correct_order' => true,

        // Control structures
        'yoda_style' => ['equal' => false, 'identical' => false, 'less_and_greater' => false],
        'concat_space' => ['spacing' => 'one'],

        // Functions
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
            'keep_multiple_spaces_after_comma' => false,
        ],
        'function_declaration' => ['closure_function_spacing' => 'one'],

        // Imports
        'ordered_imports' => [
            'imports_order' => ['class', 'function', 'const'],
            'sort_algorithm' => 'alpha',
        ],
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true,
        ],

        // Language constructs
        'declare_strict_types' => true,
        'strict_comparison' => true,
        'strict_param' => true,

        // Operators
        'binary_operator_spaces' => [
            'operators' => [
                '=' => 'single_space',
                '+=' => 'single_space',
                '-=' => 'single_space',
                '*=' => 'single_space',
                '/=' => 'single_space',
                '%=' => 'single_space',
                '^=' => 'single_space',
                '&=' => 'single_space',
                '|=' => 'single_space',
                '<<=' => 'single_space',
                '>>=' => 'single_space',
                '??=' => 'single_space',
                '.=' => 'single_space',
            ],
        ],

        // PHP unit
        'php_unit_test_class_requires_covers' => false,
        'php_unit_internal_class' => false,
        'php_unit_test_case_static_method_calls' => ['call_type' => 'self'],

        // Return
        'return_assignment' => false,
        'simplified_null_return' => false,

        // Whitespace
        'blank_line_before_statement' => [
            'statements' => [
                'break',
                'continue',
                'declare',
                'return',
                'throw',
                'try',
                'yield',
                'yield_from',
            ],
        ],
        'method_chaining_indentation' => true,
        'multiline_whitespace_before_semicolons' => ['strategy' => 'no_multi_line'],

        // Custom rules for Symfony/API
        'native_function_invocation' => [
            'include' => ['@compiler_optimized'],
            'scope' => 'namespaced',
            'strict' => true,
        ],
        'native_constant_invocation' => [
            'fix_built_in' => false,
            'include' => [],
            'scope' => 'namespaced',
            'strict' => true,
        ],
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/var/.php-cs-fixer.cache');
