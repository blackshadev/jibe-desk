<?php

declare(strict_types=1);

use PHP_CodeSniffer\Standards\Generic\Sniffs\CodeAnalysis\AssignmentInConditionSniff;
use PhpCsFixer\Fixer\Alias\MbStrFunctionsFixer;
use PhpCsFixer\Fixer\ClassNotation\ClassAttributesSeparationFixer;
use PhpCsFixer\Fixer\ClassNotation\FinalClassFixer;
use PhpCsFixer\Fixer\ClassNotation\OrderedClassElementsFixer;
use PhpCsFixer\Fixer\ControlStructure\TrailingCommaInMultilineFixer;
use PhpCsFixer\Fixer\FunctionNotation\ReturnTypeDeclarationFixer;
use PhpCsFixer\Fixer\Import\NoUnusedImportsFixer;
use PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer;
use PhpCsFixer\Fixer\Strict\StrictComparisonFixer;
use PhpCsFixer\Fixer\Strict\StrictParamFixer;
use PhpCsFixer\Fixer\Whitespace\TypeDeclarationSpacesFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return static function (ECSConfig $config): void {
    $config->paths(
        [
            __DIR__ . '/app',
            __DIR__ . '/bootstrap',
            __DIR__ . '/config',
            __DIR__ . '/database',
            __DIR__ . '/dev',
            __DIR__ . '/lang',
            __DIR__ . '/resources',
            __DIR__ . '/routes',
            __DIR__ . '/tests',
            __DIR__ . '/ecs.php',
        ],
    );

    $config->sets([SetList::PSR_12, SetList::CLEAN_CODE]);

    $config->rule(DeclareStrictTypesFixer::class);
    $config->rule(StrictComparisonFixer::class);
    $config->rule(StrictParamFixer::class);
    $config->rule(ReturnTypeDeclarationFixer::class);
    $config->rule(AssignmentInConditionSniff::class);
    $config->rule(MbStrFunctionsFixer::class);
    $config->rule(OrderedClassElementsFixer::class);
    $config->rule(ClassAttributesSeparationFixer::class);
    $config->rule(NoUnusedImportsFixer::class);
    $config->rule(TrailingCommaInMultilineFixer::class);
    $config->rule(FinalClassFixer::class);
    $config->rule(TypeDeclarationSpacesFixer::class);

    $config->skip([
        __DIR__ . '/bootstrap/cache',

        FinalClassFixer::class => [
            'app/Domain/*/Service/*',
        ],
    ]);
};
