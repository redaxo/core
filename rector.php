<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector as CodeQuality;
use Rector\Config\RectorConfig;
use Rector\Php55\Rector as Php55;
use Rector\Php70\Rector as Php70;
use Rector\Php80\Rector as Php80;
use Rector\Php81\Rector as Php81;
use Rector\Privatization\Rector as Privatization;
use Rector\ValueObject\PhpVersion;
use Redaxo\Rector\Rule as RedaxoRule;

return RectorConfig::configure()
    ->withPaths([
        // restrict to core and core addons, ignore other locally installed addons
        'redaxo/src/core/',
        'redaxo/src/addons/backup/',
        'redaxo/src/addons/be_style/',
        'redaxo/src/addons/cronjob/',
        'redaxo/src/addons/debug/',
        'redaxo/src/addons/install/',
        'redaxo/src/addons/media_manager/',
        'redaxo/src/addons/mediapool/',
        'redaxo/src/addons/metainfo/',
        'redaxo/src/addons/phpmailer/',
        'redaxo/src/addons/project/',
        'redaxo/src/addons/structure/',
        'redaxo/src/addons/users/',
    ])
    ->withSkip([
        'redaxo/src/core/vendor',
        'redaxo/src/addons/backup/vendor',
        'redaxo/src/addons/be_style/vendor',
        'redaxo/src/addons/debug/vendor',
        'redaxo/src/addons/phpmailer/vendor',

        Php81\Array_\ArrayToFirstClassCallableRector::class => ['redaxo/src/core/boot.php'],
    ])
    ->withParallel()
    ->withPhpVersion(PhpVersion::PHP_83)
    ->withPreparedSets(privatization: true)
    ->withImportNames()
    ->withRules([
        CodeQuality\Assign\CombinedAssignRector::class,
        CodeQuality\BooleanNot\SimplifyDeMorganBinaryRector::class,
        CodeQuality\Class_\InlineConstructorDefaultToPropertyRector::class,
        CodeQuality\Class_\ConvertStaticToSelfRector::class,
        CodeQuality\Foreach_\SimplifyForeachToCoalescingRector::class,
        CodeQuality\FuncCall\SimplifyRegexPatternRector::class,
        CodeQuality\FuncCall\SingleInArrayToCompareRector::class,
        CodeQuality\Identical\SimplifyBoolIdenticalTrueRector::class,
        CodeQuality\Identical\SimplifyConditionsRector::class,
        CodeQuality\If_\SimplifyIfReturnBoolRector::class,
        CodeQuality\NullsafeMethodCall\CleanupUnneededNullsafeOperatorRector::class,
        CodeQuality\Ternary\UnnecessaryTernaryExpressionRector::class,
        Php55\ClassConstFetch\StaticToSelfOnFinalClassRector::class,
        Php70\StmtsAwareInterface\IfIssetToCoalescingRector::class,
        Php70\Ternary\TernaryToNullCoalescingRector::class,
        Php80\Catch_\RemoveUnusedVariableInCatchRector::class,
        Php80\Identical\StrEndsWithRector::class,
        Php80\Identical\StrStartsWithRector::class,
        Php80\NotIdentical\StrContainsRector::class,
        Php80\Switch_\ChangeSwitchToMatchRector::class,
        Php81\Array_\ArrayToFirstClassCallableRector::class,
        Privatization\Class_\FinalizeTestCaseClassRector::class,

        // Own rules
        RedaxoRule\UnderscoreToCamelCasePropertyNameRector::class,
        RedaxoRule\UnderscoreToCamelCaseVariableNameRector::class,
    ])
;
