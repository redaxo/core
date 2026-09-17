<?php

namespace Redaxo\Core\MetaInfo\Handler;

use Redaxo\Core\Content\ArticleCache;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\AsExtension;
use Redaxo\Core\ExtensionPoint\ExtensionLevel;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\Language;
use Redaxo\Core\MetaInfo\MetaContext;
use Redaxo\Core\MetaInfo\MetaEntity;

use function in_array;

/**
 * @internal
 */
final class CategoryHandler extends AbstractHandler
{
    public const CONTAINER = 'rex-structure-category-metainfo';

    /** @param ExtensionPoint<string> $ep */
    #[AsExtension('CAT_FORM_BUTTONS')]
    public function renderToggleButton(ExtensionPoint $ep): string
    {
        $params = $ep->getParams();

        /** @var object|null $subject */
        $subject = $params['category'] ?? null;
        $category = isset($params['id']) ? Category::get((int) $params['id'], (int) $params['language']) : null;

        if ($this->hasFields(new MetaContext(MetaEntity::Category, $subject, $category))) {
            return $ep->subject . '<a class="btn btn-default collapsed" data-toggle="collapse" href="#' . self::CONTAINER . '"><i class="rex-icon rex-icon-structure-category-metainfo"></i></a>';
        }

        return $ep->subject;
    }

    /** @param ExtensionPoint<string> $ep */
    #[AsExtension('CAT_FORM_ADD')]
    #[AsExtension('CAT_FORM_EDIT')]
    #[AsExtension('CAT_ADDED', ExtensionLevel::Early)]
    #[AsExtension('CAT_UPDATED', ExtensionLevel::Early)]
    public function extendForm(ExtensionPoint $ep): string
    {
        $params = $ep->getParams();

        // Only save when the category itself is saved, as only that request is protected by a csrf token.
        $save = in_array($ep->name, ['CAT_ADDED', 'CAT_UPDATED'], true);

        /** @var object|null $subject */
        $subject = $params['category'] ?? null;
        // The language being edited; a freshly added category has none, its values apply to all languages.
        $languageId = isset($params['language']) ? (int) $params['language'] : null;
        // The surrounding category (the edited category, or the parent when adding); null = root.
        $category = isset($params['id']) ? Category::get((int) $params['id'], $languageId) : null;

        $context = new MetaContext(MetaEntity::Category, $subject, $category);

        if ($save && 'post' == Request::requestMethod() && isset($params['id'])) {
            $this->save((int) $params['id'], null === $languageId ? Language::getAllIds() : [$languageId], $context);
        }

        // On CAT_ADDED and CAT_UPDATED only save, render no form.
        if ($save) {
            return $ep->subject;
        }

        return $ep->subject . '
            <tr id="' . self::CONTAINER . '" class="collapse mark">
                <td colspan="2"></td>
                <td colspan="5">
                    <div class="rex-collapse-content">
                    ' . $this->renderFields($context) . '
                    </div>
                </td>
            </tr>';
    }

    /** @param list<int> $languageIds */
    private function save(int $id, array $languageIds, MetaContext $context): void
    {
        foreach ($languageIds as $languageId) {
            $translation = Sql::factory();
            $translation->setTable('rex_article_translation');
            $translation->setWhere(['article_id' => $id, 'language_id' => $languageId]);
            $this->saveRequestValues($translation, $context, translatable: true);
            if ($translation->hasValues()) {
                $translation->update();
            }
        }

        $shared = Sql::factory();
        $shared->setTable('rex_article');
        $shared->setWhere(['id' => $id]);
        $this->saveRequestValues($shared, $context, translatable: false);
        $shared->addGlobalUpdateFields();
        $shared->update();

        // Regenerate the article with the additional values.
        ArticleCache::generateMeta($id);
    }
}
