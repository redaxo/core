<?php

declare(strict_types=1);

namespace Redaxo\Rector\Rule;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Expression;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\PhpParser\Enum\NodeGroup;
use Rector\Rector\AbstractRector;
use Redaxo\Rector\ValueObject\SetterCallToConstructorArgument;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Webmozart\Assert\Assert;

use function count;

/**
 * Merges a setter call directly following a `new` assignment into a named
 * constructor argument. Only adjacent statements operating on the same local
 * variable are touched — anything more complex (conditionals, loops,
 * intermediate calls) is left for manual cleanup.
 */
final class SetterCallToConstructorArgumentRector extends AbstractRector implements ConfigurableRectorInterface
{
    /** @var list<SetterCallToConstructorArgument> */
    private array $configuration = [];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Merge setter calls following `new` into a named constructor argument', [
            new ConfiguredCodeSample(
                <<<'CODE_SAMPLE'
                    $result = new Result(true);
                    $result->setRequiresReboot(true);
                    CODE_SAMPLE,
                <<<'CODE_SAMPLE'
                    $result = new Result(true, requiresReboot: true);
                    CODE_SAMPLE,
                [new SetterCallToConstructorArgument('Some\Result', 'setRequiresReboot', 'requiresReboot')],
            ),
        ]);
    }

    /** @return array<class-string<Node>> */
    public function getNodeTypes(): array
    {
        return NodeGroup::STMTS_AWARE;
    }

    public function refactor(Node $node): ?Node
    {
        if (null === $node->stmts) {
            return null;
        }

        $stmts = array_values($node->stmts);
        $count = count($stmts);
        $skip = [];
        $changed = false;

        for ($i = 0; $i < $count; ++$i) {
            $assignNew = $this->matchAssignNew($stmts[$i]);
            if (null === $assignNew) {
                continue;
            }

            [$variableName, $new] = $assignNew;

            for ($j = $i + 1; $j < $count; ++$j) {
                $config = $this->matchSetterCall($stmts[$j], $variableName);
                if (null === $config) {
                    break;
                }

                /** @var MethodCall $methodCall */
                $methodCall = $stmts[$j]->expr;

                if ($this->hasNamedArgument($new, $config->argumentName)) {
                    break;
                }

                $new->args[] = new Arg(
                    $methodCall->getArgs()[0]->value,
                    name: new Identifier($config->argumentName),
                );

                $skip[$j] = true;
                $changed = true;
            }
        }

        if (!$changed) {
            return null;
        }

        $newStmts = [];
        foreach ($stmts as $key => $stmt) {
            if (!isset($skip[$key])) {
                $newStmts[] = $stmt;
            }
        }
        $node->stmts = $newStmts;
        return $node;
    }

    /** @param array<mixed> $configuration */
    public function configure(array $configuration): void
    {
        Assert::allIsInstanceOf($configuration, SetterCallToConstructorArgument::class);
        $this->configuration = $configuration;
    }

    /** @return array{string, New_}|null */
    private function matchAssignNew(Node $stmt): ?array
    {
        if (!$stmt instanceof Expression || !$stmt->expr instanceof Assign) {
            return null;
        }

        $assign = $stmt->expr;
        if (!$assign->var instanceof Variable || !$assign->expr instanceof New_) {
            return null;
        }

        $variableName = $this->getName($assign->var);
        if (null === $variableName) {
            return null;
        }

        return [$variableName, $assign->expr];
    }

    private function matchSetterCall(Node $stmt, string $variableName): ?SetterCallToConstructorArgument
    {
        if (!$stmt instanceof Expression || !$stmt->expr instanceof MethodCall) {
            return null;
        }

        $methodCall = $stmt->expr;
        if (!$methodCall->var instanceof Variable
            || $this->getName($methodCall->var) !== $variableName
            || $methodCall->isFirstClassCallable()
        ) {
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
            return $config;
        }

        return null;
    }

    private function hasNamedArgument(New_ $new, string $name): bool
    {
        foreach ($new->args as $arg) {
            if ($arg instanceof Arg && null !== $arg->name && $arg->name->toString() === $name) {
                return true;
            }
        }
        return false;
    }
}
