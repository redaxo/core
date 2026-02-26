<?php

declare(strict_types=1);

namespace Redaxo\Rector\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Param;
use Rector\Naming\ParamRenamer\ParamRenamer;
use Rector\Naming\ValueObject\ParamRename;
use Rector\Naming\ValueObjectFactory\ParamRenameFactory;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Php\ReservedKeywordAnalyzer;
use Rector\Rector\AbstractRector;
use Redaxo\Rector\Util\UnderscoreCamelCaseExpectedNameResolver;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class UnderscoreToCamelCaseVariableNameRector extends AbstractRector
{
    public function __construct(
        private readonly ReservedKeywordAnalyzer $reservedKeywordAnalyzer,
        private readonly ParamRenameFactory $paramRenameFactory,
        private readonly ParamRenamer $paramRenamer,
        private readonly UnderscoreCamelCaseExpectedNameResolver $underscoreCamelCaseExpectedNameResolver,
    ) {}

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Change under_score names to camelCase', [
            new CodeSample(
                <<<'CODE_SAMPLE'
                    final class SomeClass
                    {
                        public function run($a_b)
                        {
                            $some_value = $a_b;
                        }
                    }
                    CODE_SAMPLE,
                <<<'CODE_SAMPLE'
                    final class SomeClass
                    {
                        public function run($aB)
                        {
                            $someValue = $aB;
                        }
                    }
                    CODE_SAMPLE,
            ),
        ]);
    }

    /** @return array<class-string<Node>> */
    public function getNodeTypes(): array
    {
        return [Variable::class, FunctionLike::class];
    }

    /** @param Variable|FunctionLike $node */
    public function refactor(Node $node): ?Node
    {
        if ($node instanceof Variable) {
            return $this->renameVariable($node);
        }

        if (!$node instanceof FunctionLike) {
            return null;
        }

        $modified = false;
        foreach ($node->getParams() as $param) {
            $renamedParam = $this->renameParam($node, $param);
            $modified = $modified || null !== $renamedParam;
        }

        return $modified ? $node : null;
    }

    public function renameVariable(Variable $node): ?Node
    {
        $nodeName = $this->getName($node);
        if (null === $nodeName) {
            return null;
        }

        if (!str_contains(ltrim($nodeName, '_'), '_')) {
            return null;
        }

        if ($node->getAttribute(AttributeKey::IS_PARAM_VAR)) {
            return null;
        }

        if ($this->reservedKeywordAnalyzer->isNativeVariable($nodeName)) {
            return null;
        }

        $parts = explode('_', $nodeName);
        $uppercasedParts = array_map('ucfirst', $parts);
        $camelCaseName = lcfirst(implode('', $uppercasedParts));
        if ('this' === $camelCaseName) {
            return null;
        }

        if ('' === $camelCaseName) {
            return null;
        }

        if (is_numeric($camelCaseName[0])) {
            return null;
        }

        if ($this->isName($node, $camelCaseName)) {
            return null;
        }

        $node->name = $camelCaseName;

        return $node;
    }

    private function renameParam(FunctionLike $function, Param $param): ?Param
    {
        $resolvedExpectedName = $this->underscoreCamelCaseExpectedNameResolver->resolve($param);
        if (null === $resolvedExpectedName) {
            return null;
        }

        if ($this->isName($param, $resolvedExpectedName)) {
            return null;
        }

        $paramRename = $this->paramRenameFactory->createFromResolvedExpectedName($function, $param, $resolvedExpectedName);
        if (!$paramRename instanceof ParamRename) {
            return null;
        }

        $this->paramRenamer->rename($paramRename);

        return $param;
    }
}
