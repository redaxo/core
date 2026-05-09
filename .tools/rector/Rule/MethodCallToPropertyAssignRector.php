<?php

declare(strict_types=1);

namespace Redaxo\Rector\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Expression;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Redaxo\Rector\ValueObject\MethodCallToPropertyAssign;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Webmozart\Assert\Assert;

use function count;

/**
 * Turns a setter call used as a stand-alone statement into a property
 * assignment. Only `$obj->setX($value);` statements are touched — calls
 * embedded in other expressions are skipped to avoid changing the result
 * type (e.g. fluent setters returning `$this`).
 */
final class MethodCallToPropertyAssignRector extends AbstractRector implements ConfigurableRectorInterface
{
    /** @var list<MethodCallToPropertyAssign> */
    private array $configuration = [];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Turn setter method calls into property assignments', [
            new ConfiguredCodeSample(
                <<<'CODE_SAMPLE'
                    $action->setSave(true);
                    CODE_SAMPLE,
                <<<'CODE_SAMPLE'
                    $action->save = true;
                    CODE_SAMPLE,
                [new MethodCallToPropertyAssign('Some\Action', 'setSave', 'save')],
            ),
        ]);
    }

    /** @return array<class-string<Node>> */
    public function getNodeTypes(): array
    {
        return [Expression::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node->expr instanceof MethodCall) {
            return null;
        }

        $methodCall = $node->expr;
        if ($methodCall->isFirstClassCallable()) {
            return null;
        }

        $args = $methodCall->getArgs();
        if (1 !== count($args)) {
            return null;
        }

        $arg = $args[0];
        if (null !== $arg->name || $arg->byRef || $arg->unpack) {
            return null;
        }

        $methodName = $this->getName($methodCall->name);
        if (null === $methodName) {
            return null;
        }

        foreach ($this->configuration as $config) {
            if ($methodName !== $config->method) {
                continue;
            }
            if (!$this->isObjectType($methodCall->var, $config->getObjectType())) {
                continue;
            }

            $node->expr = new Assign(
                new PropertyFetch($methodCall->var, new Identifier($config->property)),
                $arg->value,
            );
            return $node;
        }

        return null;
    }

    /** @param array<mixed> $configuration */
    public function configure(array $configuration): void
    {
        Assert::allIsInstanceOf($configuration, MethodCallToPropertyAssign::class);
        $this->configuration = $configuration;
    }
}
