<?php

declare(strict_types=1);

namespace Redaxo\Rector\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\VarLikeIdentifier;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Redaxo\Rector\ValueObject\StaticCallToStaticPropertyFetch;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Webmozart\Assert\Assert;

final class StaticCallToStaticPropertyFetchRector extends AbstractRector implements ConfigurableRectorInterface
{
    /** @var list<StaticCallToStaticPropertyFetch> */
    private array $configuration = [];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Turn static method calls into static property fetches', [
            new ConfiguredCodeSample(
                <<<'CODE_SAMPLE'
                    MediaPool::getBlockedExtensions();
                    CODE_SAMPLE,
                <<<'CODE_SAMPLE'
                    MediaPool::$blockedExtensions;
                    CODE_SAMPLE,
                [new StaticCallToStaticPropertyFetch('Redaxo\Core\MediaPool\MediaPool', 'getBlockedExtensions', 'blockedExtensions')],
            ),
        ]);
    }

    /** @return array<class-string<Node>> */
    public function getNodeTypes(): array
    {
        return [StaticCall::class];
    }

    /** @param StaticCall $node */
    public function refactor(Node $node): ?Node
    {
        if ($node->isFirstClassCallable() || [] !== $node->getArgs()) {
            return null;
        }

        if (!$node->class instanceof Name) {
            return null;
        }

        $methodName = $this->getName($node->name);
        if (null === $methodName) {
            return null;
        }

        foreach ($this->configuration as $config) {
            if ($methodName !== $config->method) {
                continue;
            }

            if (!$this->isObjectType($node->class, $config->getObjectType())) {
                continue;
            }

            // use the configured class instead of `$node->class` so old (renamed) class names can't survive
            return new StaticPropertyFetch(new FullyQualified($config->getObjectType()->getClassName()), new VarLikeIdentifier($config->property));
        }

        return null;
    }

    /** @param array<mixed> $configuration */
    public function configure(array $configuration): void
    {
        Assert::allIsInstanceOf($configuration, StaticCallToStaticPropertyFetch::class);
        $this->configuration = $configuration;
    }
}
