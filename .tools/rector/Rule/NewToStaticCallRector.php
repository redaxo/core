<?php

declare(strict_types=1);

namespace Redaxo\Rector\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Redaxo\Rector\ValueObject\NewToStaticCall;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Webmozart\Assert\Assert;

/**
 * Replaces the rule of the same name, which rector has dropped.
 *
 * Only instantiations of exactly the configured class are changed, not those of subclasses.
 */
final class NewToStaticCallRector extends AbstractRector implements ConfigurableRectorInterface
{
    /** @var list<NewToStaticCall> */
    private array $configuration = [];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Turn object instantiations into static calls', [
            new ConfiguredCodeSample(
                <<<'CODE_SAMPLE'
                    new LogFile($path);
                    CODE_SAMPLE,
                <<<'CODE_SAMPLE'
                    LogFile::factory($path);
                    CODE_SAMPLE,
                [new NewToStaticCall('Redaxo\Core\Log\LogFile', 'Redaxo\Core\Log\LogFile', 'factory')],
            ),
        ]);
    }

    /** @return array<class-string<Node>> */
    public function getNodeTypes(): array
    {
        return [New_::class];
    }

    /** @param New_ $node */
    public function refactor(Node $node): ?Node
    {
        if (!$node->class instanceof Name) {
            return null;
        }

        foreach ($this->configuration as $config) {
            if ($this->isName($node->class, $config->class)) {
                return new StaticCall(new FullyQualified($config->staticCallClass), $config->staticCallMethod, $node->args);
            }
        }

        return null;
    }

    /** @param array<mixed> $configuration */
    public function configure(array $configuration): void
    {
        Assert::allIsInstanceOf($configuration, NewToStaticCall::class);
        $this->configuration = $configuration;
    }
}
