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

final readonly class LinkVar
{
    /**
     * @param int|null $category Open the linkmap in this category; defaults to the category of the selected
     *                           article resp. the current category
     */
    public static function getWidget(int|string $id, string $name, ?int $value, ?int $category = null): string
    {
        $artName = '';
        $art = $value ? Article::get($value) : null;

        // Falls ein Artikel vorausgewählt ist, dessen Namen anzeigen und beim Öffnen der Linkmap dessen Kategorie anzeigen
        if ($art instanceof Article) {
            $artName = trim(sprintf('%s [%s]', $art->name, $art->id));
            $category ??= $art->categoryId ?? 0;
        }

        $category ??= Category::getCurrent()->id ?? 0; // Aktuelle Kategorie vorauswählen

        $openParams = '&clang=' . Language::getCurrentId() . '&category_id=' . $category;

        $class = ' rex-disabled';
        $openFunc = '';
        $deleteFunc = '';
        if (Core::requireUser()->getComplexPerm('structure')->hasStructurePerm()) {
            $class = '';
            $escapedId = escape($id, 'js');
            $openFunc = 'openLinkMap(\'REX_LINK_' . $escapedId . '\', \'' . $openParams . '\');';
            $deleteFunc = 'deleteREXLink(\'' . $escapedId . '\');';
        }

        $e = [];
        $e['field'] = '<input class="form-control" type="text" name="REX_LINK_NAME[' . $id . ']" value="' . escape($artName) . '" id="REX_LINK_' . $id . '_NAME" readonly="readonly" /><input type="hidden" name="' . $name . '" id="REX_LINK_' . $id . '" value="' . ($value ?? '') . '" />';
        $e['functionButtons'] = '
                        <a href="#" class="btn btn-popup' . $class . '" onclick="' . $openFunc . 'return false;" title="' . I18n::msg('var_link_open') . '"><i class="rex-icon rex-icon-open-linkmap"></i></a>
                        <a href="#" class="btn btn-popup' . $class . '" onclick="' . $deleteFunc . 'return false;" title="' . I18n::msg('var_link_delete') . '"><i class="rex-icon rex-icon-delete-link"></i></a>';

        $fragment = new Fragment();
        $fragment->setVar('elements', [$e], false);

        return $fragment->parse('core/form/widget.php');
    }
}
