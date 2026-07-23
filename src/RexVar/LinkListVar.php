<?php

namespace Redaxo\Core\RexVar;

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Core;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;

use function Redaxo\Core\View\escape;
use function sprintf;

final readonly class LinkListVar
{
    /** @param int|null $category Open the linkmap in this category; defaults to the current category */
    public static function getWidget(int|string $id, string $name, ?string $value, ?int $category = null): string
    {
        $category ??= Category::getCurrent()->id ?? 0; // Aktuelle Kategorie vorauswählen

        $openParams = '&clang=' . Language::getCurrentId() . '&category_id=' . $category;

        $options = '';
        $linklistarray = null === $value ? [] : explode(',', $value);
        foreach ($linklistarray as $link) {
            if ('' == $link) {
                continue;
            }
            if ($article = Article::get((int) $link)) {
                $options .= '<option value="' . $link . '">' . escape(trim(sprintf('%s [%s]', $article->name, $article->id))) . '</option>';
            }
        }

        $disabled = ' disabled';
        $openFunc = '';
        $deleteFunc = '';
        $quotedId = "'" . escape($id, 'js') . "'";
        if (Core::requireUser()->getComplexPerm('structure')->hasStructurePerm()) {
            $disabled = '';
            $openFunc = 'openREXLinklist(' . $quotedId . ', \'' . $openParams . '\');';
            $deleteFunc = 'deleteREXLinklist(' . $quotedId . ');';
        }

        $e = [];
        $e['field'] = '
                <select class="form-control" name="REX_LINKLIST_SELECT[' . $id . ']" id="REX_LINKLIST_SELECT_' . $id . '" size="10">
                    ' . $options . '
                </select>
                <input type="hidden" name="' . $name . '" id="REX_LINKLIST_' . $id . '" value="' . ($value ?? '') . '" />';
        $e['moveButtons'] = '
                    <a href="#" class="btn btn-popup" onclick="moveREXLinklist(' . $quotedId . ',\'top\');return false;" title="' . I18n::msg('var_linklist_move_top') . '"><i class="rex-icon rex-icon-top"></i></a>
                    <a href="#" class="btn btn-popup" onclick="moveREXLinklist(' . $quotedId . ',\'up\');return false;" title="' . I18n::msg('var_linklist_move_up') . '"><i class="rex-icon rex-icon-up"></i></a>
                    <a href="#" class="btn btn-popup" onclick="moveREXLinklist(' . $quotedId . ',\'down\');return false;" title="' . I18n::msg('var_linklist_move_down') . '"><i class="rex-icon rex-icon-down"></i></a>
                    <a href="#" class="btn btn-popup" onclick="moveREXLinklist(' . $quotedId . ',\'bottom\');return false;" title="' . I18n::msg('var_linklist_move_bottom') . '"><i class="rex-icon rex-icon-bottom"></i></a>';
        $e['functionButtons'] = '
                    <a href="#" class="btn btn-popup" onclick="' . $openFunc . 'return false;" title="' . I18n::msg('var_link_open') . '"' . $disabled . '><i class="rex-icon rex-icon-open-linkmap"></i></a>
                    <a href="#" class="btn btn-popup" onclick="' . $deleteFunc . 'return false;" title="' . I18n::msg('var_link_delete') . '"' . $disabled . '><i class="rex-icon rex-icon-delete-link"></i></a>';

        $fragment = new Fragment();
        $fragment->setVar('elements', [$e], false);

        return $fragment->parse('core/form/widget_list.php');
    }
}
