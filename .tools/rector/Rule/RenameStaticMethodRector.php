<?php

declare(strict_types=1);

namespace Redaxo\Rector\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Redaxo\Rector\ValueObject\RenameStaticMethod;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Webmozart\Assert\Assert;

/** Replaces the rule of the same name, which rector has dropped. */
final class RenameStaticMethodRector extends AbstractRector implements ConfigurableRectorInterface
{
    /** @var list<RenameStaticMethod> */
    private array $configuration = [];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Rename static methods, optionally moving them to another class', [
            new ConfiguredCodeSample(
                <<<'CODE_SAMPLE'
                    Str::versionCompare($a, $b);
                    CODE_SAMPLE,
                <<<'CODE_SAMPLE'
                    Version::compare($a, $b);
                    CODE_SAMPLE,
                [new RenameStaticMethod('Redaxo\Core\Util\Str', 'versionCompare', 'Redaxo\Core\Util\Version', 'compare')],
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
        if (!$node->class instanceof Name) {
            return null;
        }

        $methodName = $this->getName($node->name);
        if (null === $methodName) {
            return null;
        }

        foreach ($this->configuration as $config) {
            if (strtolower($methodName) !== strtolower($config->oldMethod)) {
                continue;
            }

            if (!$this->isObjectType($node->class, $config->getOldObjectType())) {
                continue;
            }

            $node->class = new FullyQualified($config->newClass);
            $node->name = new Identifier($config->newMethod);

            return $node;
        }

        return null;
    }

    /** @param array<mixed> $configuration */
    public function configure(array $configuration): void
    {
        Assert::allIsInstanceOf($configuration, RenameStaticMethod::class);
        $this->configuration = $configuration;
    }
}
