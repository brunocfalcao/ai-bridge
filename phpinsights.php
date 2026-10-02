<?php

declare(strict_types=1);

return [
    'preset' => 'default',
    'exclude' => [],
    'add' => [],
    'remove' => [
        // Anonymous migration classes and empty promoted-constructor bodies follow
        // the Laravel/Pint layout, which these PHP-CS-Fixer and PHPCS sniffs reject.
        'PHP_CodeSniffer\\Standards\\PEAR\\Sniffs\\WhiteSpace\\ScopeClosingBraceSniff',
        'PHP_CodeSniffer\\Standards\\PSR12\\Sniffs\\Classes\\ClassInstantiationSniff',
        'PhpCsFixer\\Fixer\\Operator\\NewWithBracesFixer',
        'PhpCsFixer\\Fixer\\Basic\\BracesFixer',
        'PhpCsFixer\\Fixer\\ClassNotation\\ClassDefinitionFixer',
        // Pint's phpdoc_separation removes the blank line between annotation groups that this sniff requires.
        'SlevomatCodingStandard\\Sniffs\\Commenting\\DocCommentSpacingSniff',
        // Classes in this package are extension points for the host application.
        'NunoMaduro\\PhpInsights\\Domain\\Insights\\ForbiddenNormalClasses',
    ],
    'config' => [
        // The project wraps at 120 characters (PSR-12 soft limit), not the preset's 80.
        'PHP_CodeSniffer\\Standards\\Generic\\Sniffs\\Files\\LineLengthSniff' => [
            'lineLimit' => 120,
            'absoluteLineLimit' => 120,
        ],
        // Pest closures bind $this and Schema blueprint closures are framework callbacks.
        'SlevomatCodingStandard\\Sniffs\\Functions\\StaticClosureSniff' => [
            'exclude' => ['tests', 'database/migrations'],
        ],
        // Migration up()/down() methods are not global helper functions.
        'NunoMaduro\\PhpInsights\\Domain\\Insights\\ForbiddenDefineFunctions' => [
            'exclude' => ['tests', 'database/migrations'],
        ],
        // quality-gate.php is a tooling script that parses untyped JSON reports.
        'SlevomatCodingStandard\\Sniffs\\TypeHints\\DisallowMixedTypeHintSniff' => [
            'exclude' => ['quality-gate.php'],
        ],
        'SlevomatCodingStandard\\Sniffs\\Functions\\FunctionLengthSniff' => [
            'exclude' => ['quality-gate.php'],
        ],
        'NunoMaduro\\PhpInsights\\Domain\\Insights\\CyclomaticComplexityIsHigh' => [
            'exclude' => ['quality-gate.php'],
        ],
        'NunoMaduro\\PhpInsights\\Domain\\Insights\\MethodCyclomaticComplexityIsHigh' => [
            'exclude' => ['quality-gate.php'],
        ],
        'PHP_CodeSniffer\\Standards\\PSR1\\Sniffs\\Files\\SideEffectsSniff' => [
            'exclude' => ['tests', 'quality-gate.php'],
        ],
    ],
    'requirements' => [
        'min-quality' => 82.8,
        'min-complexity' => 84.9,
        'min-architecture' => 81.2,
        'min-style' => 97.4,
    ],
];
