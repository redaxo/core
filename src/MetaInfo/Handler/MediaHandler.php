<?php

namespace Redaxo\Core\MetaInfo\Handler;

use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\ExtensionPoint\AsExtension;
use Redaxo\Core\ExtensionPoint\ExtensionLevel;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Session;
use Redaxo\Core\Language\Language;
use Redaxo\Core\MediaPool\MediaCategory;
use Redaxo\Core\MetaInfo\Field\MediaField;
use Redaxo\Core\MetaInfo\MetaContext;
use Redaxo\Core\MetaInfo\MetaEntity;
use Redaxo\Core\MetaInfo\MetaSchema;
use Redaxo\Core\Translation\I18n;

use function implode;
use function in_array;
use function Redaxo\Core\View\escape;

/**
 * @internal
 */
final class MediaHandler extends AbstractHandler
{
    /**
     * Extension to check whether the given media is still in use.
     *
     * @param ExtensionPoint<list<string>> $ep
     *
     * @return list<string>
     */
    #[AsExtension('MEDIA_IS_IN_USE')]
    public static function isMediaInUse(ExtensionPoint $ep): array
    {
        $params = $ep->getParams();
        $warning = $ep->subject;

        $sql = Sql::factory();
        $escapedFilename = $sql->escape($params['filename']);

        $where = ['articles' => [], 'categories' => [], 'media' => [], 'languages' => []];
        $map = [
            [MetaEntity::Article, 'articles'],
            [MetaEntity::Category, 'categories'],
            [MetaEntity::Media, 'media'],
            [MetaEntity::Language, 'languages'],
        ];
        foreach ($map as [$entity, $key]) {
            foreach (MetaSchema::getFields($entity) as $field) {
                if ($field instanceof MediaField) {
                    $where[$key][] = 'FIND_IN_SET(' . $escapedFilename . ', ' . $sql->escapeIdentifier($field->columnName()) . ')';
                }
            }
        }

        $articles = '';
        if (!empty($where['articles'])) {
            $items = $sql->getArray('SELECT a.id, t.language_id, t.name FROM rex_article a JOIN rex_article_translation t ON t.article_id = a.id WHERE ' . implode(' OR ', $where['articles']));
            foreach ($items as $artArr) {
                $aid = (int) $artArr['id'];
                $languageId = (int) $artArr['language_id'];
                $articles .= '<li><a href="javascript:openPage(\'' . Url::backendPage('content', ['article_id' => $aid, 'mode' => 'meta', 'language' => $languageId]) . '\')">' . escape((string) $artArr['name']) . '</a></li>';
            }
            if ('' != $articles) {
                $warning[] = I18n::msg('minfo_media_in_use_art') . '<br /><ul>' . $articles . '</ul>';
            }
        }

        $categories = '';
        if (!empty($where['categories'])) {
            $items = $sql->getArray('SELECT c.id, ct.language_id, a.parent_id, ct.name FROM rex_category c JOIN rex_article a ON a.id = c.id JOIN rex_category_translation ct ON ct.category_id = c.id WHERE ' . implode(' OR ', $where['categories']));
            foreach ($items as $artArr) {
                $aid = (int) $artArr['id'];
                $languageId = (int) $artArr['language_id'];
                $parentId = (int) $artArr['parent_id'];
                $categories .= '<li><a href="javascript:openPage(\'' . Url::backendPage('structure', ['edit_id' => $aid, 'function' => 'edit_cat', 'category_id' => $parentId, 'language' => $languageId]) . '\')">' . escape((string) $artArr['name']) . '</a></li>';
            }
            if ('' != $categories) {
                $warning[] = I18n::msg('minfo_media_in_use_cat') . '<br /><ul>' . $categories . '</ul>';
            }
        }

        $media = '';
        if (!empty($where['media'])) {
            $items = $sql->getArray('SELECT DISTINCT m.id, m.filename, m.category_id FROM rex_media m LEFT JOIN rex_media_translation l ON l.media_id = m.id WHERE ' . implode(' OR ', $where['media']));
            foreach ($items as $medArr) {
                $id = (int) $medArr['id'];
                $filename = escape((string) $medArr['filename']);
                $catId = (int) $medArr['category_id'];
                $media .= '<li><a href="' . Url::backendPage('mediapool/detail', ['file_id' => $id, 'rex_file_category' => $catId]) . '">' . $filename . '</a></li>';
            }
            if ('' != $media) {
                $warning[] = I18n::msg('minfo_media_in_use_med') . '<br /><ul>' . $media . '</ul>';
            }
        }

        $languageList = '';
        if (!empty($where['languages'])) {
            $items = $sql->getArray('SELECT id, name FROM rex_language WHERE ' . implode(' OR ', $where['languages']));
            foreach ($items as $languageRow) {
                $name = escape((string) $languageRow['name']);
                if (Core::getUser()?->admin) {
                    $languageList .= '<li><a href="javascript:openPage(\'' . Url::backendPage('system/lang', ['language_id' => (int) $languageRow['id'], 'func' => 'edit']) . '\')">' . $name . '</a></li>';
                } else {
                    $languageList .= '<li>' . $name . '</li>';
                }
            }
            if ('' != $languageList) {
                $warning[] = I18n::msg('minfo_media_in_use_language') . '<br /><ul>' . $languageList . '</ul>';
            }
        }

        return $warning;
    }

    /** @param ExtensionPoint<string> $ep */
    #[AsExtension('MEDIA_FORM_EDIT')]
    #[AsExtension('MEDIA_FORM_ADD')]
    #[AsExtension('MEDIA_ADDED', ExtensionLevel::Early)]
    #[AsExtension('MEDIA_UPDATED', ExtensionLevel::Early)]
    public function extendForm(ExtensionPoint $ep): string
    {
        $params = $ep->getParams();
        $save = in_array($ep->name, ['MEDIA_ADDED', 'MEDIA_UPDATED'], true);

        $media = null;
        if ('MEDIA_FORM_EDIT' == $ep->name) {
            // Only on edit there is an existing medium to edit.
            /** @var object|null $media */
            $media = $params['media'] ?? null;
        } elseif ('MEDIA_ADDED' == $ep->name) {
            $sql = Sql::factory();
            $sql->setQuery('SELECT id FROM rex_media WHERE filename=:filename', ['filename' => $params['filename']]);
            if (1 == $sql->getRows()) {
                $params['id'] = (int) $sql->getValue('id');
            } else {
                throw new RuntimeException('Error occured during file upload.');
            }
        }

        $catId = (int) Session::start()->get('media[rex_file_category]', 0);
        $context = new MetaContext(MetaEntity::Media, $media, mediaCategory: $catId > 0 ? MediaCategory::get($catId) : null);

        if ($save && isset($params['id'])) {
            // a freshly added medium gets the submitted values in all languages, an edited one only in the current
            $languageIds = 'MEDIA_ADDED' == $ep->name ? Language::getAllIds() : [Language::getCurrentId()];
            $this->save((int) $params['id'], $context, $languageIds);
        }

        return $ep->subject . $this->renderFields($context);
    }

    /** @param list<int> $languageIds */
    private function save(int $id, MetaContext $context, array $languageIds): void
    {
        $sql = Sql::factory();
        $sql->setTable('rex_media');
        $sql->setWhere(['id' => $id]);

        $this->saveRequestValues($sql, $context, translatable: false);

        if ($sql->hasValues()) {
            $sql->update();
        }

        foreach ($languageIds as $languageId) {
            $sql = Sql::factory();
            $sql->setTable('rex_media_translation');
            $sql->setWhere(['media_id' => $id, 'language_id' => $languageId]);

            $this->saveRequestValues($sql, $context, translatable: true);

            if ($sql->hasValues()) {
                $sql->update();
            }
        }
    }
}
