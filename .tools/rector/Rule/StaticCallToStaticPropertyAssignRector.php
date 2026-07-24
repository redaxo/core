<?php

declare(strict_types=1);

namespace Redaxo\Rector\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\VarLikeIdentifier;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Redaxo\Rector\ValueObject\StaticCallToStaticPropertyAssign;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Webmozart\Assert\Assert;

use function count;

/**
 * Turns a static setter call used as a stand-alone statement into a static
 * property assignment. Only `Foo::setX($value);` statements are touched —
 * calls embedded in other expressions are skipped to avoid changing the
 * result type.
 */
final class StaticCallToStaticPropertyAssignRector extends AbstractRector implements ConfigurableRectorInterface
{
    /** @var list<StaticCallToStaticPropertyAssign> */
    private array $configuration = [];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Turn static setter method calls into static property assignments', [
            new ConfiguredCodeSample(
                <<<'CODE_SAMPLE'
                    MediaPool::setAllowedMimeTypes($mimeTypes);
                    CODE_SAMPLE,
                <<<'CODE_SAMPLE'
                    MediaPool::$allowedMimeTypes = $mimeTypes;
                    CODE_SAMPLE,
                [new StaticCallToStaticPropertyAssign('Redaxo\Core\MediaPool\MediaPool', 'setAllowedMimeTypes', 'allowedMimeTypes')],
            ),
        ]);
    }

    /** @return array<class-string<Node>> */
    public function getNodeTypes(): array
    {
        return [Expression::class];
    }

    /** @param Expression $node */
    public function refactor(Node $node): ?Node
    {
        if (!$node->expr instanceof StaticCall) {
            return null;
        }

        $staticCall = $node->expr;
        if ($staticCall->isFirstClassCallable()) {
            return null;
        }

        if (!$staticCall->class instanceof Name) {
            return null;
        }

        $args = $staticCall->getArgs();
        if (1 !== count($args)) {
            return null;
        }

        $arg = $args[0];
        if (null !== $arg->name || $arg->byRef || $arg->unpack) {
            return null;
        }

        $methodName = $this->getName($staticCall->name);
        if (null === $methodName) {
            return null;
        }

        foreach ($this->configuration as $config) {
            if ($methodName !== $config->method) {
                continue;
            }
            if (!$this->isObjectType($staticCall->class, $config->getObjectType())) {
                continue;
            }

            // use the configured class instead of `$staticCall->class` so old (renamed) class names can't survive
            $node->expr = new Assign(
                new StaticPropertyFetch(new FullyQualified($config->getObjectType()->getClassName()), new VarLikeIdentifier($config->property)),
                $arg->value,
            );
            return $node;
        }

        return null;
    }

    /** @param array<mixed> $configuration */
    public function configure(array $configuration): void
    {
        Assert::allIsInstanceOf($configuration, StaticCallToStaticPropertyAssign::class);
        $this->configuration = $configuration;
    }
}
