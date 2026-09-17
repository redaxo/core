<?php

namespace Redaxo\Core\Cronjob\Type;

use Override;
use Redaxo\Core\Content\ArticleHandler;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Table;
use Redaxo\Core\Translation\I18n;

use function implode;

/** @internal */
final class ArticleStatusType extends AbstractType
{
    #[Override]
    public function execute(): bool
    {
        $from = [
            'field' => 'art_online_from',
            'before' => 0,
            'after' => 1,
        ];
        $to = [
            'field' => 'art_online_to',
            'before' => 1,
            'after' => 0,
        ];

        // the date fields may be shared or translatable, so they live in either of the two tables
        $fromTable = self::tableOfColumn($from['field']);
        $toTable = self::tableOfColumn($to['field']);
        if (null === $fromTable || null === $toTable) {
            $missing = array_keys(array_filter([$from['field'] => $fromTable, $to['field'] => $toTable], is_null(...)));
            $this->message = 'Metainfo field(s) `' . implode('`, `', $missing) . '` not found. Please define them in your meta schema.';
            return false;
        }

        $sql = Sql::factory();
        $time = time();
        $sql->setQuery(
            '
            SELECT  a.id, t.language_id, t.status
            FROM    rex_article a
            JOIN    rex_article_translation t ON t.article_id = a.id
            WHERE
                (     ' . $sql->escapeIdentifier($from['field']) . ' > 0
                AND   ' . $sql->escapeIdentifier($from['field']) . ' < :time
                AND   t.status IN (' . $sql->in([$from['before']]) . ')
                AND   (' . $sql->escapeIdentifier($to['field']) . ' > :time OR ' . $sql->escapeIdentifier($to['field']) . ' = 0 OR ' . $sql->escapeIdentifier($to['field']) . ' = "")
                )
            OR
                (     ' . $sql->escapeIdentifier($to['field']) . ' > 0
                AND   ' . $sql->escapeIdentifier($to['field']) . ' < :time
                AND   t.status IN (' . $sql->in([$to['before']]) . ')
                )',
            ['time' => $time],
        );
        $rows = $sql->getRows();

        for ($i = 0; $i < $rows; ++$i) {
            if ($sql->getValue('status') == $from['before']) {
                $status = $from['after'];
            } else {
                $status = $to['after'];
            }

            ArticleHandler::articleStatus((int) $sql->getValue('id'), (int) $sql->getValue('language_id'), $status);
            $sql->next();
        }
        $this->message = 'Updated articles: ' . $rows;

        if ($this->getParam('reset_date')) {
            $sql->setQuery(
                '
                UPDATE ' . $fromTable . '
                SET ' . $sql->escapeIdentifier($from['field']) . ' = ""
                WHERE     ' . $sql->escapeIdentifier($from['field']) . ' > 0
                    AND   ' . $sql->escapeIdentifier($from['field']) . ' < :time',
                ['time' => $time],
            );
            $sql->setQuery(
                '
                UPDATE ' . $toTable . '
                SET ' . $sql->escapeIdentifier($to['field']) . ' = ""
                WHERE ' . $sql->escapeIdentifier($to['field']) . ' > 0
                AND   ' . $sql->escapeIdentifier($to['field']) . ' < :time',
                ['time' => $time],
            );
        }
        return true;
    }

    #[Override]
    public function getTypeName(): string
    {
        return I18n::msg('cronjob_article_status');
    }

    #[Override]
    public function getParamFields(): array
    {
        return [
            [
                'name' => 'reset_date',
                'type' => 'checkbox',
                'options' => [1 => I18n::rawMsg('cronjob_article_reset_date')],
                'notice' => I18n::msg('cronjob_article_reset_date_info'),
            ],
        ];
    }

    /** @return non-empty-string|null */
    private static function tableOfColumn(string $column): ?string
    {
        foreach (['rex_article', 'rex_article_translation'] as $table) {
            if (Table::get($table)->hasColumn($column)) {
                return $table;
            }
        }

        return null;
    }
}
