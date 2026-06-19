<?php

namespace Redaxo\Core\MetaInfo\Handler;

use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\AsExtension;
use Redaxo\Core\ExtensionPoint\ExtensionLevel;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Http\Request;
use Redaxo\Core\MetaInfo\MetaContext;
use Redaxo\Core\MetaInfo\MetaEntity;

use function in_array;

/**
 * @internal
 */
final class LanguageHandler extends AbstractHandler
{
    public const CONTAINER = 'rex-clang-metainfo';

    #[AsExtension('CLANG_FORM_BUTTONS')]
    public function renderToggleButton(ExtensionPoint $ep): string
    {
        if ($this->hasFields(new MetaContext(MetaEntity::Clang))) {
            return $ep->subject . '<a class="btn btn-default collapsed" data-toggle="collapse" href="#' . self::CONTAINER . '"><i class="rex-icon rex-icon-structure-category-metainfo"></i></a>';
        }

        return $ep->subject;
    }

    #[AsExtension('CLANG_FORM_ADD')]
    #[AsExtension('CLANG_FORM_EDIT')]
    #[AsExtension('CLANG_ADDED', ExtensionLevel::Early)]
    #[AsExtension('CLANG_UPDATED', ExtensionLevel::Early)]
    public function extendForm(ExtensionPoint $ep): string
    {
        $params = $ep->getParams();

        /** @var object|null $subject */
        $subject = $params['sql'] ?? null;
        $context = new MetaContext(MetaEntity::Clang, $subject);

        if ('post' == Request::requestMethod() && isset($params['id'])) {
            $this->save((int) $params['id'], $context);
        }

        // On CLANG_ADDED and CLANG_UPDATED only save, render no form.
        if (in_array($ep->name, ['CLANG_UPDATED', 'CLANG_ADDED'], true)) {
            return $ep->subject;
        }

        return $ep->subject . '
            <tr id="' . self::CONTAINER . '" class="collapse mark">
                <td colspan="2"></td>
                <td colspan="6">
                    <div class="rex-collapse-content">
                        ' . $this->renderFields($context) . '
                    </div>
                </td>
            </tr>';
    }

    private function save(int $id, MetaContext $context): void
    {
        $sql = Sql::factory();
        $sql->setTable(Core::getTablePrefix() . 'clang');
        $sql->setWhere('id=:id', ['id' => $id]);

        $this->saveRequestValues($sql, $context);

        if ($sql->hasValues()) {
            $sql->update();
        }

        \Redaxo\Core\Language\LanguageHandler::generateCache();
    }
}
