<?php

declare(strict_types=1);

namespace Redaxo\Rector\Rule;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Property;
use Rector\Naming\ValueObject\PropertyRename;
use Rector\Naming\ValueObjectFactory\PropertyRenameFactory;
use Rector\Rector\AbstractRector;
use Redaxo\Rector\Util\UnderscoreCamelCaseExpectedNameResolver;
use Redaxo\Rector\Util\UnderscoreCamelCasePropertyRenamer;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UnderscoreToCamelCasePropertyNameRector extends AbstractRector
{
    public function __construct(
        private readonly UnderscoreCamelCasePropertyRenamer $underscoreCamelCasePropertyRenamer,
        private readonly PropertyRenameFactory $propertyRenameFactory,
        private readonly UnderscoreCamelCaseExpectedNameResolver $underscoreCamelCaseExpectedNameResolver,
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
        $hasChanged = false;

        foreach ($node->getProperties() as $property) {
            $property = $this->refactorProperty($node, $property);

            if ($property) {
                $hasChanged = true;
            }
        }

        return $hasChanged ? $node : null;
    }

    public function refactorProperty(ClassLike $classLike, Property $node): ?Node
    {
        $propertyName = $this->getName($node);
        if (!str_contains($propertyName, '_')) {
            return null;
        }

        $expectedPropertyName = $this->underscoreCamelCaseExpectedNameResolver->resolve($node);
        if (null === $expectedPropertyName) {
            return null;
        }

        $propertyRename = $this->propertyRenameFactory->createFromExpectedName($classLike, $node, $expectedPropertyName);
        if (!$propertyRename instanceof PropertyRename) {
            return null;
        }

        return $this->underscoreCamelCasePropertyRenamer->rename($propertyRename);
    }
}
