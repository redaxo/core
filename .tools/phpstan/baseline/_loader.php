<?php

declare(strict_types=1);

// total 460 errors

return ['includes' => [
    __DIR__ . '/argument.templateType.php',
    __DIR__ . '/argument.type.php',
    __DIR__ . '/assign.propertyType.php',
    __DIR__ . '/booleanAnd.rightAlwaysFalse.php',
    __DIR__ . '/catch.neverThrown.php',
    __DIR__ . '/deadCode.unreachable.php',
    __DIR__ . '/function.alreadyNarrowedType.php',
    __DIR__ . '/if.alwaysTrue.php',
    __DIR__ . '/impure.propertyAssign.php',
    __DIR__ . '/method.childReturnType.php',
    __DIR__ . '/missingType.generics.php',
    __DIR__ . '/missingType.iterableValue.php',
    __DIR__ . '/missingType.parameter.php',
    __DIR__ . '/possiblyImpure.functionCall.php',
    __DIR__ . '/possiblyImpure.methodCall.php',
    __DIR__ . '/return.type.php',
    __DIR__ . '/varTag.nativeType.php',
]];
