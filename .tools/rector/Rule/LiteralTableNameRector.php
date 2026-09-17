<?php

declare(strict_types=1);

namespace Redaxo\Rector\Rule;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Redaxo\Core\Core;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

use function array_key_last;
use function count;

/**
 * Inlines the fixed table prefix, so that queries contain the literal table names.
 *
 * `Core::getTable('article')` and `Core::getTablePrefix() . 'article'` become `'rex_article'`, and adjacent string
 * literals in the surrounding concatenation are merged. Where no literal is available (`Core::getTable($name)`,
 * `getTables(Core::getTablePrefix())`), the constant `Core::TABLE_PREFIX` is used instead.
 */
final class LiteralTableNameRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Replace the table prefix helpers with literal table names', [
            new CodeSample(
                <<<'CODE_SAMPLE'
                    $sql->setTable(Core::getTable('article'));
                    $sql->setQuery('SELECT * FROM ' . Core::getTablePrefix() . 'article WHERE id = ?', [$id]);
                    $tables = $sql->getTables(Core::getTablePrefix());
                    CODE_SAMPLE,
                <<<'CODE_SAMPLE'
                    $sql->setTable('rex_article');
                    $sql->setQuery('SELECT * FROM rex_article WHERE id = ?', [$id]);
                    $tables = $sql->getTables(Core::TABLE_PREFIX);
                    CODE_SAMPLE,
            ),
        ]);
    }

    /** @return array<class-string<Node>> */
    public function getNodeTypes(): array
    {
        return [Concat::class, StaticCall::class];
    }

    /** @param Concat|StaticCall $node */
    public function refactor(Node $node): ?Node
    {
        if ($node instanceof StaticCall) {
            return $this->refactorStaticCall($node);
        }

        // Flatten the left-associative concat chain into its operands
        $operands = [];
        $current = $node;
        while ($current instanceof Concat) {
            array_unshift($operands, $current->right);
            $current = $current->left;
        }
        array_unshift($operands, $current);

        $changed = false;
        $expanded = [];
        foreach ($operands as $operand) {
            if (!$operand instanceof StaticCall || !$this->isPrefixCall($operand)) {
                $expanded[] = $operand;
                continue;
            }

            $changed = true;
            $expanded[] = $this->createString(Core::TABLE_PREFIX);

            if ($this->isName($operand->name, 'getTable')) {
                $expanded[] = $operand->getArgs()[0]->value;
            }
        }

        if (!$changed) {
            return null;
        }

        $merged = [];
        foreach ($expanded as $operand) {
            $last = [] === $merged ? null : $merged[array_key_last($merged)];

            if ($last instanceof String_ && $operand instanceof String_) {
                $merged[array_key_last($merged)] = $this->createString($last->value . $operand->value);
                continue;
            }

            if ($last instanceof String_ && $operand instanceof InterpolatedString) {
                $merged[array_key_last($merged)] = $this->prependToInterpolatedString($last->value, $operand);
                continue;
            }

            $merged[] = $operand;
        }

        $result = array_shift($merged);
        foreach ($merged as $operand) {
            $result = new Concat($result, $operand);
        }

        return $result;
    }

    private function refactorStaticCall(StaticCall $node): ?Node
    {
        if (!$this->isPrefixCall($node)) {
            return null;
        }

        $prefix = new ClassConstFetch(new FullyQualified(Core::class), new Identifier('TABLE_PREFIX'));

        if (!$this->isName($node->name, 'getTable')) {
            return $prefix;
        }

        $table = $node->getArgs()[0]->value;
        if ($table instanceof String_) {
            return $this->createString(Core::TABLE_PREFIX . $table->value);
        }

        return new Concat($prefix, $table);
    }

    private function isPrefixCall(StaticCall $node): bool
    {
        if ($node->isFirstClassCallable() || !$this->isNames($node->class, [Core::class, 'rex'])) {
            return false;
        }

        if ($this->isName($node->name, 'getTablePrefix')) {
            return [] === $node->getArgs();
        }

        return $this->isName($node->name, 'getTable') && 1 === count($node->getArgs());
    }

    /**
     * Uses double quotes only for strings containing single quotes, mirroring the `single_quote` fixer.
     * Multi-line strings stay single-quoted, because double quotes would escape the line breaks.
     */
    private function createString(string $value): String_
    {
        $kind = str_contains($value, "'") && !str_contains($value, "\n")
            ? String_::KIND_DOUBLE_QUOTED
            : String_::KIND_SINGLE_QUOTED;

        return new String_($value, ['kind' => $kind]);
    }

    private function prependToInterpolatedString(string $value, InterpolatedString $string): InterpolatedString
    {
        $parts = $string->parts;
        if ($parts[0] instanceof InterpolatedStringPart) {
            $parts[0] = new InterpolatedStringPart($value . $parts[0]->value);
        } else {
            array_unshift($parts, new InterpolatedStringPart($value));
        }

        return new InterpolatedString($parts, $string->getAttributes());
    }
}
