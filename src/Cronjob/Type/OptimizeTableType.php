<?php

namespace Redaxo\Core\Cronjob\Type;

use Override;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Exception\SqlException;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Translation\I18n;

/** @internal */
final class OptimizeTableType extends AbstractType
{
    #[Override]
    public function execute(): bool
    {
        $tables = Sql::factory()->getTables(Core::getTablePrefix());
        if (!empty($tables)) {
            $sql = Sql::factory();
            // $sql->setDebug();
            try {
                $sql->setQuery('OPTIMIZE TABLE ' . implode(', ', array_map($sql->escapeIdentifier(...), $tables)));
                return true;
            } catch (SqlException $e) {
                $this->message = $e->getMessage();
                return false;
            }
        }
        return false;
    }

    #[Override]
    public function getTypeName(): string
    {
        return I18n::msg('cronjob_optimize_tables');
    }
}
