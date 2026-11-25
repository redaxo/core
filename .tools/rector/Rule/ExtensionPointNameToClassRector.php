<?php

declare(strict_types=1);

namespace Redaxo\Rector\Rule;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ObjectType;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Webmozart\Assert\Assert;

/**
 * Replaces the names of extension points in `Extension::register()` with their classes.
 *
 * Classes that keep a `NAME` constant are registered by that name, so they are referenced as `Foo::NAME`, all others
 * as `Foo::class`.
 */
final class ExtensionPointNameToClassRector extends AbstractRector implements ConfigurableRectorInterface
{
    private const string EXTENSION_CLASS = 'Redaxo\Core\ExtensionPoint\Extension';

    /** @var array<string, string> extension point name => class */
    private array $classesByName = [];

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {}

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Replace extension point names with their classes in Extension::register()', [
            new ConfiguredCodeSample(
                <<<'CODE_SAMPLE'
                    Extension::register('ART_ADDED', $callback);
                    Extension::register(['SLICE_ADDED', 'SLICE_UPDATED'], $callback);
                    CODE_SAMPLE,
                <<<'CODE_SAMPLE'
                    Extension::register(ArticleAdded::class, $callback);
                    Extension::register([ArticleContentUpdated::NAME, ArticleContentUpdated::NAME], $callback);
                    CODE_SAMPLE,
                [
                    'ART_ADDED' => 'Redaxo\Core\Content\ExtensionPoint\ArticleAdded',
                    'SLICE_ADDED' => 'Redaxo\Core\Content\ExtensionPoint\ArticleContentUpdated',
                    'SLICE_UPDATED' => 'Redaxo\Core\Content\ExtensionPoint\ArticleContentUpdated',
                ],
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
        if ($node->isFirstClassCallable() || !$this->isName($node->name, 'register')) {
            return null;
        }

        $arg = $node->getArgs()[0] ?? null;
        if (!$arg instanceof Arg || $arg->unpack || null !== $arg->name) {
            return null;
        }

        if (!$this->isObjectType($node->class, new ObjectType(self::EXTENSION_CLASS))) {
            return null;
        }

        if ($arg->value instanceof Array_) {
            $hasChanged = false;
            foreach ($arg->value->items as $item) {
                if ($replacement = $this->replace($item->value)) {
                    $item->value = $replacement;
                    $hasChanged = true;
                }
            }

            return $hasChanged ? $node : null;
        }

        $replacement = $this->replace($arg->value);
        if (!$replacement) {
            return null;
        }

        $arg->value = $replacement;

        return $node;
    }

    /** @param array<mixed> $configuration */
    public function configure(array $configuration): void
    {
        Assert::allString(array_keys($configuration));
        Assert::allString($configuration);
        /** @var array<string, string> $configuration */
        $this->classesByName = $configuration;
    }

    private function replace(Expr $expr): ?ClassConstFetch
    {
        if (!$expr instanceof String_) {
            return null;
        }

        $class = $this->classesByName[$expr->value] ?? null;
        if (null === $class) {
            return null;
        }

        $hasName = $this->reflectionProvider->hasClass($class) && $this->reflectionProvider->getClass($class)->hasConstant('NAME');

        return new ClassConstFetch(new FullyQualified($class), $hasName ? 'NAME' : 'class');
    }
}
