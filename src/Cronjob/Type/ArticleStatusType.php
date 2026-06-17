<?php

namespace Redaxo\Core\Cronjob\Type;

use Override;
use Redaxo\Core\Content\ArticleHandler;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Table;
use Redaxo\Core\Translation\I18n;

use function array_filter;
use function array_values;
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

        $table = Table::get(Core::getTable('article'));
        $missing = array_values(array_filter(
            [$from['field'], $to['field']],
            static fn (string $field): bool => !$table->hasColumn($field),
        ));
        if ([] !== $missing) {
            $this->message = 'Metainfo field(s) `' . implode('`, `', $missing) . '` not found. Please define them in your meta schema.';
            return false;
        }

        $sql = Sql::factory();
        $time = time();
        $sql->setQuery(
            '
            SELECT  id, clang_id, status
            FROM    ' . Core::getTablePrefix() . 'article
            WHERE
                (     ' . $sql->escapeIdentifier($from['field']) . ' > 0
                AND   ' . $sql->escapeIdentifier($from['field']) . ' < :time
                AND   status IN (' . $sql->in([$from['before']]) . ')
                AND   (' . $sql->escapeIdentifier($to['field']) . ' > :time OR ' . $sql->escapeIdentifier($to['field']) . ' = 0 OR ' . $sql->escapeIdentifier($to['field']) . ' = "")
                )
            OR
                (     ' . $sql->escapeIdentifier($to['field']) . ' > 0
                AND   ' . $sql->escapeIdentifier($to['field']) . ' < :time
                AND   status IN (' . $sql->in([$to['before']]) . ')
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

            ArticleHandler::articleStatus((int) $sql->getValue('id'), (int) $sql->getValue('clang_id'), $status);
            $sql->next();
        }
        $this->message = 'Updated articles: ' . $rows;

        if ($this->getParam('reset_date')) {
            $sql->setQuery(
                '
                UPDATE ' . Core::getTablePrefix() . 'article
                SET ' . $sql->escapeIdentifier($from['field']) . ' = ""
                WHERE     ' . $sql->escapeIdentifier($from['field']) . ' > 0
                    AND   ' . $sql->escapeIdentifier($from['field']) . ' < :time',
                ['time' => $time],
            );
            $sql->setQuery(
                '
                UPDATE ' . Core::getTablePrefix() . 'article
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
}
