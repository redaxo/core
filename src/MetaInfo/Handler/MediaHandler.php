<?php

namespace Redaxo\Core\MetaInfo\Handler;

use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\ExtensionPoint\AsExtension;
use Redaxo\Core\ExtensionPoint\ExtensionLevel;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\MediaPool\MediaCategory;
use Redaxo\Core\MetaInfo\Field\MediaField;
use Redaxo\Core\MetaInfo\MetaContext;
use Redaxo\Core\MetaInfo\MetaEntity;
use Redaxo\Core\MetaInfo\MetaSchema;
use Redaxo\Core\Translation\I18n;

use function implode;
use function in_array;

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

        $where = ['articles' => [], 'categories' => [], 'media' => [], 'clangs' => []];
        $map = [
            [MetaEntity::Article, 'articles'],
            [MetaEntity::Category, 'categories'],
            [MetaEntity::Media, 'media'],
            [MetaEntity::Clang, 'clangs'],
        ];
        foreach ($map as [$entity, $key]) {
            foreach (MetaSchema::getFields($entity) as $field) {
                if ($field instanceof MediaField) {
                    $where[$key][] = 'FIND_IN_SET(' . $escapedFilename . ', ' . $sql->escapeIdentifier($field->columnName($entity)) . ')';
                }
            }
        }

        $articles = '';
        if (!empty($where['articles'])) {
            $items = $sql->getArray('SELECT id, clang_id, parent_id, name, catname, startarticle FROM ' . Core::getTablePrefix() . 'article WHERE ' . implode(' OR ', $where['articles']));
            foreach ($items as $artArr) {
                $aid = (int) $artArr['id'];
                $clang = (int) $artArr['clang_id'];
                $articles .= '<li><a href="javascript:openPage(\'' . Url::backendPage('content', ['article_id' => $aid, 'mode' => 'meta', 'clang' => $clang]) . '\')">' . (string) $artArr['name'] . '</a></li>';
            }
            if ('' != $articles) {
                $warning[] = I18n::msg('minfo_media_in_use_art') . '<br /><ul>' . $articles . '</ul>';
            }
        }

        $categories = '';
        if (!empty($where['categories'])) {
            $items = $sql->getArray('SELECT id, clang_id, parent_id, name, catname, startarticle FROM ' . Core::getTablePrefix() . 'article WHERE ' . implode(' OR ', $where['categories']));
            foreach ($items as $artArr) {
                $aid = (int) $artArr['id'];
                $clang = (int) $artArr['clang_id'];
                $parentId = (int) $artArr['parent_id'];
                $categories .= '<li><a href="javascript:openPage(\'' . Url::backendPage('structure', ['edit_id' => $aid, 'function' => 'edit_cat', 'category_id' => $parentId, 'clang' => $clang]) . '\')">' . (string) $artArr['catname'] . '</a></li>';
            }
            if ('' != $categories) {
                $warning[] = I18n::msg('minfo_media_in_use_cat') . '<br /><ul>' . $categories . '</ul>';
            }
        }

        $media = '';
        if (!empty($where['media'])) {
            $items = $sql->getArray('SELECT id, filename, category_id FROM ' . Core::getTablePrefix() . 'media WHERE ' . implode(' OR ', $where['media']));
            foreach ($items as $medArr) {
                $id = (int) $medArr['id'];
                $filename = (string) $medArr['filename'];
                $catId = (int) $medArr['category_id'];
                $media .= '<li><a href="' . Url::backendPage('mediapool/detail', ['file_id' => $id, 'rex_file_category' => $catId]) . '">' . $filename . '</a></li>';
            }
            if ('' != $media) {
                $warning[] = I18n::msg('minfo_media_in_use_med') . '<br /><ul>' . $media . '</ul>';
            }
        }

        $clangs = '';
        if (!empty($where['clangs'])) {
            $items = $sql->getArray('SELECT id, name FROM ' . Core::getTablePrefix() . 'clang WHERE ' . implode(' OR ', $where['clangs']));
            foreach ($items as $clangArr) {
                $name = (string) $clangArr['name'];
                if (Core::getUser()?->admin) {
                    $clangs .= '<li><a href="javascript:openPage(\'' . Url::backendPage('system/lang', ['clang_id' => $clangArr['id'], 'func' => 'editclang']) . '\')">' . $name . '</a></li>';
                } else {
                    $clangs .= '<li>' . $name . '</li>';
                }
            }
            if ('' != $clangs) {
                $warning[] = I18n::msg('minfo_media_in_use_clang') . '<br /><ul>' . $clangs . '</ul>';
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
            $sql->setQuery('SELECT id FROM ' . Core::getTablePrefix() . 'media WHERE filename=:filename', ['filename' => $params['filename']]);
            if (1 == $sql->getRows()) {
                $params['id'] = (int) $sql->getValue('id');
            } else {
                throw new RuntimeException('Error occured during file upload.');
            }
        }

        $catId = Request::session('media[rex_file_category]', 'int');
        $context = new MetaContext(MetaEntity::Media, $media, mediaCategory: $catId > 0 ? MediaCategory::get($catId) : null);

        if ($save && isset($params['id'])) {
            $this->save((int) $params['id'], $context);
        }

        return $ep->subject . $this->renderFields($context);
    }

    private function save(int $id, MetaContext $context): void
    {
        $sql = Sql::factory();
        $sql->setTable(Core::getTablePrefix() . 'media');
        $sql->setWhere('id=:mediaid', ['mediaid' => $id]);

        $this->saveRequestValues($sql, $context);

        if ($sql->hasValues()) {
            $sql->update();
        }
    }
}
