<?php

declare(strict_types=1);

namespace Redaxo\Rector\Rule;

use PhpParser\BuilderHelpers;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\PhpParser\Node\Value\ValueResolver;
use Rector\Rector\AbstractRector;
use Redaxo\Rector\ValueObject\ReplaceArgumentValue;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Webmozart\Assert\Assert;

/** Replaces scalar argument values, like the `ReplaceArgumentDefaultValueRector` rector has dropped. */
final class ReplaceArgumentValueRector extends AbstractRector implements ConfigurableRectorInterface
{
    /** @var list<ReplaceArgumentValue> */
    private array $configuration = [];

    public function __construct(
        private readonly ValueResolver $valueResolver,
    ) {}

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Replace scalar argument values of method calls', [
            new ConfiguredCodeSample(
                <<<'CODE_SAMPLE'
                    $user->getComplexPerm('clang');
                    CODE_SAMPLE,
                <<<'CODE_SAMPLE'
                    $user->getComplexPerm('language');
                    CODE_SAMPLE,
                [new ReplaceArgumentValue('Redaxo\Core\Security\User', 'getComplexPerm', 0, 'clang', 'language')],
            ),
        ]);
    }

    /** @return array<class-string<Node>> */
    public function getNodeTypes(): array
    {
        return [MethodCall::class, NullsafeMethodCall::class, StaticCall::class];
    }

    /** @param MethodCall|NullsafeMethodCall|StaticCall $node */
    public function refactor(Node $node): ?Node
    {
        if ($node->isFirstClassCallable()) {
            return null;
        }

        $methodName = $this->getName($node->name);
        if (null === $methodName) {
            return null;
        }

        $hasChanged = false;
        foreach ($this->configuration as $config) {
            if (strtolower($methodName) !== strtolower($config->method)) {
                continue;
            }

            $arg = $node->getArgs()[$config->position] ?? null;
            if (null === $arg || $arg->unpack || null !== $arg->name) {
                continue;
            }

            if (!$this->isObjectType($node instanceof StaticCall ? $node->class : $node->var, $config->getObjectType())) {
                continue;
            }

            if (!$this->valueResolver->isValue($arg->value, $config->value)) {
                continue;
            }

            $arg->value = BuilderHelpers::normalizeValue($config->replacement);
            $hasChanged = true;
        }

        return $hasChanged ? $node : null;
    }

    /** @param array<mixed> $configuration */
    public function configure(array $configuration): void
    {
        Assert::allIsInstanceOf($configuration, ReplaceArgumentValue::class);
        $this->configuration = $configuration;
    }
}
