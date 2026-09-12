<?php

namespace Redaxo\Core\MetaInfo\Handler;

use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\AsExtension;
use Redaxo\Core\ExtensionPoint\ExtensionLevel;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\ExtensionPoint\LanguageAdded;
use Redaxo\Core\Language\ExtensionPoint\LanguageFormAdd;
use Redaxo\Core\Language\ExtensionPoint\LanguageFormButtons;
use Redaxo\Core\Language\ExtensionPoint\LanguageFormEdit;
use Redaxo\Core\Language\ExtensionPoint\LanguageUpdated;
use Redaxo\Core\MetaInfo\MetaContext;
use Redaxo\Core\MetaInfo\MetaEntity;

/**
 * @internal
 */
final class LanguageHandler extends AbstractHandler
{
    public const CONTAINER = 'rex-language-metainfo';

    #[AsExtension]
    public function renderToggleButton(LanguageFormButtons $ep): string
    {
        if ($this->hasFields(new MetaContext(MetaEntity::Language))) {
            return $ep->subject . '<a class="btn btn-default collapsed" data-toggle="collapse" href="#' . self::CONTAINER . '"><i class="rex-icon rex-icon-structure-category-metainfo"></i></a>';
        }

        return $ep->subject;
    }

    #[AsExtension]
    public function extendForm(LanguageFormAdd|LanguageFormEdit $ep): string
    {
        $context = new MetaContext(MetaEntity::Language, $ep instanceof LanguageFormEdit ? $ep->language : null);

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

    /** The fields are saved along with the language itself, as only that request is protected by a csrf token. */
    #[AsExtension(level: ExtensionLevel::Early)]
    public function saveFields(LanguageAdded|LanguageUpdated $ep): void
    {
        if ('post' !== Request::requestMethod()) {
            return;
        }

        $sql = Sql::factory();
        $sql->setTable(Core::getTablePrefix() . 'language');
        $sql->setWhere('id=:id', ['id' => $ep->language->id]);

        $this->saveRequestValues($sql, new MetaContext(MetaEntity::Language));

        if ($sql->hasValues()) {
            $sql->update();
        }

        \Redaxo\Core\Language\LanguageHandler::generateCache();
    }
}
