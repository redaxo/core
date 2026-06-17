<?php

namespace Redaxo\Core\MetaInfo\Handler;

use Redaxo\Core\Content\ArticleCache;
use Redaxo\Core\Content\Category;
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
final class CategoryHandler extends AbstractHandler
{
    public const CONTAINER = 'rex-structure-category-metainfo';

    #[AsExtension('CAT_FORM_BUTTONS')]
    public function renderToggleButton(ExtensionPoint $ep): string
    {
        $params = $ep->getParams();

        /** @var object|null $subject */
        $subject = $params['category'] ?? null;
        $category = isset($params['id']) ? Category::get((int) $params['id'], (int) $params['clang']) : null;

        if ($this->hasFields(new MetaContext(MetaEntity::Category, $subject, $category))) {
            return $ep->subject . '<a class="btn btn-default collapsed" data-toggle="collapse" href="#' . self::CONTAINER . '"><i class="rex-icon rex-icon-structure-category-metainfo"></i></a>';
        }

        return $ep->subject;
    }

    #[AsExtension('CAT_FORM_ADD')]
    #[AsExtension('CAT_FORM_EDIT')]
    #[AsExtension('CAT_ADDED', ExtensionLevel::Early)]
    #[AsExtension('CAT_UPDATED', ExtensionLevel::Early)]
    public function extendForm(ExtensionPoint $ep): string
    {
        $params = $ep->getParams();

        /** @var object|null $subject */
        $subject = $params['category'] ?? null;
        // The surrounding category (the edited category, or the parent when adding); null = root.
        $category = isset($params['id']) ? Category::get((int) $params['id'], (int) $params['clang']) : null;

        $ctx = new MetaContext(MetaEntity::Category, $subject, $category);

        if ('post' == Request::requestMethod() && isset($params['id'])) {
            $this->save((int) $params['id'], (int) $params['clang'], $ctx);
        }

        // On CAT_ADDED and CAT_UPDATED only save, render no form.
        if (in_array($ep->name, ['CAT_UPDATED', 'CAT_ADDED'], true)) {
            return $ep->subject;
        }

        return $ep->subject . '
            <tr id="' . self::CONTAINER . '" class="collapse mark">
                <td colspan="2"></td>
                <td colspan="5">
                    <div class="rex-collapse-content">
                    ' . $this->renderFields($ctx) . '
                    </div>
                </td>
            </tr>';
    }

    private function save(int $id, int $clang, MetaContext $ctx): void
    {
        $sql = Sql::factory();
        $sql->setTable(Core::getTablePrefix() . 'article');
        $sql->setWhere('id=:id AND clang_id=:clang', ['id' => $id, 'clang' => $clang]);

        $this->saveRequestValues($sql, $ctx);

        if ($sql->hasValues()) {
            $sql->update();
        }

        // Regenerate the article with the additional values.
        ArticleCache::generateMeta($id, $clang);
    }
}
