<?php

declare(strict_types=1);

namespace Redaxo\Rector\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Identifier;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Redaxo\Rector\ValueObject\MethodCallToPropertyFetch;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Webmozart\Assert\Assert;

final class MethodCallToPropertyFetchRector extends AbstractRector implements ConfigurableRectorInterface
{
    /** @var list<MethodCallToPropertyFetch> */
    private array $methodCallsToPropertyFetches = [];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Turn method calls into property fetches', [
            new ConfiguredCodeSample(
                <<<'CODE_SAMPLE'
                    $addon->getName();
                    CODE_SAMPLE,
                <<<'CODE_SAMPLE'
                    $addon->name;
                    CODE_SAMPLE,
                [new MethodCallToPropertyFetch('Redaxo\Core\Addon\Addon', 'getName', 'name')],
            ),
        ]);
    }

    /** @return array<class-string<Node>> */
    public function getNodeTypes(): array
    {
        return [MethodCall::class, NullsafeMethodCall::class];
    }

    /** @param MethodCall|NullsafeMethodCall $node */
    public function refactor(Node $node): ?Node
    {
        if ($node->isFirstClassCallable() || [] !== $node->getArgs()) {
            return null;
        }

        $methodName = $this->getName($node->name);
        if (null === $methodName) {
            return null;
        }

        foreach ($this->methodCallsToPropertyFetches as $config) {
            if ($methodName !== $config->method) {
                continue;
            }

            if (!$this->isObjectType($node->var, $config->getObjectType())) {
                continue;
            }

            $propertyName = new Identifier($config->property);

            if ($node instanceof NullsafeMethodCall) {
                return new NullsafePropertyFetch($node->var, $propertyName);
            }

            return new PropertyFetch($node->var, $propertyName);
        }

        return null;
    }

    /** @param array<mixed> $configuration */
    public function configure(array $configuration): void
    {
        Assert::allIsInstanceOf($configuration, MethodCallToPropertyFetch::class);
        $this->methodCallsToPropertyFetches = $configuration;
    }
}
