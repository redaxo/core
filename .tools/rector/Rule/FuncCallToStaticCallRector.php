<?php

declare(strict_types=1);

namespace Redaxo\Rector\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Redaxo\Rector\ValueObject\FuncCallToStaticCall;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Webmozart\Assert\Assert;

/** Replaces the rule of the same name, which rector has dropped. */
final class FuncCallToStaticCallRector extends AbstractRector implements ConfigurableRectorInterface
{
    /** @var list<FuncCallToStaticCall> */
    private array $configuration = [];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Turn function calls into static method calls', [
            new ConfiguredCodeSample(
                <<<'CODE_SAMPLE'
                    rex_get('foo', 'int');
                    CODE_SAMPLE,
                <<<'CODE_SAMPLE'
                    Request::get('foo', 'int');
                    CODE_SAMPLE,
                [new FuncCallToStaticCall('rex_get', 'Redaxo\Core\Http\Request', 'get')],
            ),
        ]);
    }

    /** @return array<class-string<Node>> */
    public function getNodeTypes(): array
    {
        return [FuncCall::class];
    }

    /** @param FuncCall $node */
    public function refactor(Node $node): ?Node
    {
        if (!$node->name instanceof Name) {
            return null;
        }

        foreach ($this->configuration as $config) {
            if ($this->isName($node, $config->function)) {
                return new StaticCall(new FullyQualified($config->class), $config->method, $node->args);
            }
        }

        return null;
    }

    /** @param array<mixed> $configuration */
    public function configure(array $configuration): void
    {
        Assert::allIsInstanceOf($configuration, FuncCallToStaticCall::class);
        $this->configuration = $configuration;
    }
}
