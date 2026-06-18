<?php

namespace Redaxo\Core\MetaInfo\Handler;

use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\AsExtension;
use Redaxo\Core\ExtensionPoint\ExtensionLevel;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Http\Request;

/**
 * @internal
 */
final class LanguageHandler extends AbstractHandler
{
    public const PREFIX = 'clang_';
    public const CONTAINER = 'rex-clang-metainfo';

    #[AsExtension('CLANG_FORM_BUTTONS')]
    public function renderToggleButton(ExtensionPoint $ep): string
    {
        $fields = parent::getSqlFields(self::PREFIX);
        if ($fields->getRows() >= 1) {
            $return = '<a class="btn btn-default collapsed" data-toggle="collapse" href="#' . self::CONTAINER . '"><i class="rex-icon rex-icon-structure-category-metainfo"></i></a>';

            return $ep->subject . $return;
        }

        return $ep->subject;
    }

    public function handleSave(array $params, Sql $sqlFields): array
    {
        if ('post' != Request::requestMethod() || !isset($params['id'])) {
            return $params;
        }

        $sql = Sql::factory();
        // $sql->setDebug();
        $sql->setTable(Core::getTablePrefix() . 'clang');
        $sql->setWhere('id=:id', ['id' => $params['id']]);

        parent::fetchRequestValues($params, $sql, $sqlFields);

        // do the save only when metafields are defined
        if ($sql->hasValues()) {
            $sql->update();
        }

        \Redaxo\Core\Language\LanguageHandler::generateCache();

        return $params;
    }

    protected function buildFilterCondition(array $params): string
    {
        return '';
    }

    public function renderFormItem($field, $tag, $tagAttr, $id, $label, $labelIt, $inputType): string
    {
        if ('legend' == $inputType) {
            return '<h3 class="form-legend">' . $label . '</h3>';
        }

        return $field;
    }

    #[AsExtension('CLANG_FORM_ADD')]
    #[AsExtension('CLANG_FORM_EDIT')]
    #[AsExtension('CLANG_ADDED', ExtensionLevel::Early)]
    #[AsExtension('CLANG_UPDATED', ExtensionLevel::Early)]
    public function extendForm(ExtensionPoint $ep): string
    {
        $params = $ep->getParams();
        if (isset($params['sql'])) {
            $params['activeItem'] = $params['sql'];
        }

        $result = '
            <tr id="' . self::CONTAINER . '" class="collapse mark">
                <td colspan="2"></td>
                <td colspan="6">
                    <div class="rex-collapse-content">
                        ' . parent::renderFormAndSave(self::PREFIX, $params) . '
                    </div>
                </td>
            </tr>';

        // Bei CLANG_ADDED und CLANG_UPDATED nur speichern und kein Formular zurückgeben
        if ('CLANG_UPDATED' == $ep->name || 'CLANG_ADDED' == $ep->name) {
            return $ep->subject;
        }
        return $ep->subject . $result;
    }
}
