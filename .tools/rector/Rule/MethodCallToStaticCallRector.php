<?php

declare(strict_types=1);

namespace Redaxo\Rector\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name\FullyQualified;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Redaxo\Rector\ValueObject\MethodCallToStaticCall;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Webmozart\Assert\Assert;

/**
 * Replaces the rule of the same name, which rector has dropped.
 *
 * The object the method was called on is dropped, so only use it for methods that didn't depend on the object.
 */
final class MethodCallToStaticCallRector extends AbstractRector implements ConfigurableRectorInterface
{
    /** @var list<MethodCallToStaticCall> */
    private array $configuration = [];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Turn method calls into static method calls', [
            new ConfiguredCodeSample(
                <<<'CODE_SAMPLE'
                    $login->getLoginPolicy();
                    CODE_SAMPLE,
                <<<'CODE_SAMPLE'
                    BackendLogin::getLoginPolicy();
                    CODE_SAMPLE,
                [new MethodCallToStaticCall('Redaxo\Core\Security\BackendLogin', 'getLoginPolicy', 'Redaxo\Core\Security\BackendLogin', 'getLoginPolicy')],
            ),
        ]);
    }

    /** @return array<class-string<Node>> */
    public function getNodeTypes(): array
    {
        return [MethodCall::class];
    }

    /** @param MethodCall $node */
    public function refactor(Node $node): ?Node
    {
        $methodName = $this->getName($node->name);
        if (null === $methodName) {
            return null;
        }

        foreach ($this->configuration as $config) {
            if (strtolower($methodName) !== strtolower($config->method)) {
                continue;
            }

            if (!$this->isObjectType($node->var, $config->getObjectType())) {
                continue;
            }

            return new StaticCall(new FullyQualified($config->staticCallClass), $config->staticCallMethod, $node->args);
        }

        return null;
    }

    /** @param array<mixed> $configuration */
    public function configure(array $configuration): void
    {
        Assert::allIsInstanceOf($configuration, MethodCallToStaticCall::class);
        $this->configuration = $configuration;
    }
}
