<?php

namespace Redaxo\Core\Cronjob\Type;

use DateTimeImmutable;
use Override;
use Redaxo\Core\Content\ArticleSliceHistory;
use Redaxo\Core\Translation\I18n;

/** @internal */
final class ClearArticleHistoryType extends AbstractType
{
    #[Override]
    public function execute(): bool
    {
        $period = $this->getParam('period');

        if ('' == $period) {
            $this->message = 'Article-History Cleanup failed: `' . $period . '` is not a period';
            return false;
        }

        $deleteDate = new DateTimeImmutable('- ' . $period);

        ArticleSliceHistory::clearHistoryByDate($deleteDate);
        $this->message = 'Article-History Cleanup done with `' . $period . '` as period';

        return true;
    }

    #[Override]
    public function getTypeName(): string
    {
        return I18n::msg('structure_history_cleanup');
    }

    /** @return list<array{label: string, name: 'period', type: 'select', options: array{'7 days': string, '14 days': string, '1 month': string, '6 months': string, '1 year': string}}> */
    #[Override]
    public function getParamFields(): array
    {
        $fields = [];

        $fields[] = [
            'label' => I18n::msg('structure_history_cleanup_after'),
            'name' => 'period',
            'type' => 'select',
            'options' => [
                '7 days' => I18n::msg('structure_history_days', 7),
                '14 days' => I18n::msg('structure_history_days', 14),
                '1 month' => I18n::msg('structure_history_months', 1),
                '6 months' => I18n::msg('structure_history_months', 6),
                '1 year' => I18n::msg('structure_history_years', 1),
            ],
        ];

        return $fields;
    }
}
