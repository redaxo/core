<?php

declare(strict_types=1);

namespace Redaxo\Rector\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\VarLikeIdentifier;
use PHPStan\Reflection\ReflectionProvider;
use Rector\NodeAnalyzer\PropertyFetchAnalyzer;
use Rector\Rector\AbstractRector;
use Redaxo\Rector\Util\UnderscoreCamelCaseExpectedNameResolver;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

use function array_count_values;
use function in_array;

final class UnderscoreToCamelCasePropertyNameRector extends AbstractRector
{
    public function __construct(
        private readonly UnderscoreCamelCaseExpectedNameResolver $underscoreCamelCaseExpectedNameResolver,
        private readonly PropertyFetchAnalyzer $propertyFetchAnalyzer,
        private readonly ReflectionProvider $reflectionProvider,
    ) {}

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Change under_score names to camelCase', [
            new CodeSample(
                <<<'CODE_SAMPLE'
                    final class SomeClass
                    {
                        public $property_name;

                        public function run($a)
                        {
                            $this->property_name = 5;
                        }
                    }
                    CODE_SAMPLE,
                <<<'CODE_SAMPLE'
                    final class SomeClass
                    {
                        public $propertyName;

                        public function run($a)
                        {
                            $this->propertyName = 5;
                        }
                    }
                    CODE_SAMPLE,
            ),
        ]);
    }

    /** @return array<class-string<Node>> */
    public function getNodeTypes(): array
    {
        return [ClassLike::class];
    }

    /** @param ClassLike $node */
    public function refactor(Node $node): ?Node
    {
        if ($this->hasMagicGetOrSet($node)) {
            return null;
        }

        $hasChanged = false;
        foreach ($node->getProperties() as $property) {
            if ($this->refactorProperty($node, $property)) {
                $hasChanged = true;
            }
        }

        return $hasChanged ? $node : null;
    }

    private function refactorProperty(ClassLike $classLike, Property $property): bool
    {
        // renaming non-private properties would break BC for subclasses and external callers
        if (!$property->isPrivate()) {
            return false;
        }

        $currentName = $this->getName($property);
        if (!str_contains($currentName, '_')) {
            return false;
        }

        $expectedName = $this->underscoreCamelCaseExpectedNameResolver->resolve($property);
        if (null === $expectedName || $expectedName === $currentName) {
            return false;
        }

        if (in_array($expectedName, $this->resolveConflictingNames($classLike), true)) {
            return false;
        }

        $property->props[0]->name = new VarLikeIdentifier($expectedName);

        $this->traverseNodesWithCallable($classLike, function (Node $node) use ($currentName, $expectedName): ?Node {
            if (!$this->propertyFetchAnalyzer->isLocalPropertyFetchName($node, $currentName)) {
                return null;
            }

            /** @var Node\Expr\PropertyFetch|Node\Expr\NullsafePropertyFetch|StaticPropertyFetch $node */
            $node->name = $node instanceof StaticPropertyFetch ? new VarLikeIdentifier($expectedName) : new Identifier($expectedName);
            return $node;
        });

        return true;
    }

    /**
     * Names that several properties would end up with after renaming (e.g. `$foo_bar` and `$fooBar`).
     *
     * @return list<string>
     */
    private function resolveConflictingNames(ClassLike $classLike): array
    {
        $expectedNames = [];
        foreach ($classLike->getProperties() as $property) {
            $expectedNames[] = $this->underscoreCamelCaseExpectedNameResolver->resolve($property) ?? $this->getName($property);
        }

        $conflictingNames = [];
        foreach (array_count_values($expectedNames) as $name => $count) {
            if ($count > 1) {
                $conflictingNames[] = (string) $name;
            }
        }

        return $conflictingNames;
    }

    private function hasMagicGetOrSet(ClassLike $classLike): bool
    {
        $className = $this->getName($classLike);
        if (null === $className || !$this->reflectionProvider->hasClass($className)) {
            return false;
        }

        $classReflection = $this->reflectionProvider->getClass($className);
        return $classReflection->hasMethod('__get') || $classReflection->hasMethod('__set');
    }
}
