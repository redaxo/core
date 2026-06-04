<?php

namespace Redaxo\Core\Content;

use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Content\Exception\ArticleNotFoundException;
use Redaxo\Core\Content\ExtensionPoint\ArticleContentUpdated;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Util;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Translation\I18n;

use function function_exists;
use function sprintf;

final class ContentHandler
{
    private function __construct() {}

    /** @throws ApiFunctionException */
    public static function addSlice(int $articleId, int $clangId, int $ctypeId, string $moduleKey, array $data = []): string
    {
        $data['revision'] ??= 0;

        $where = 'article_id=' . $articleId . ' AND clang_id=' . $clangId . ' AND ctype_id=' . $ctypeId . ' AND revision=' . (int) $data['revision'];

        if (!isset($data['priority'])) {
            $prevSlice = Sql::factory();
            $prevSlice->setQuery('SELECT IFNULL(MAX(priority),0)+1 as priority FROM ' . Core::getTable('article_slice') . ' WHERE ' . $where);

            $data['priority'] = $prevSlice->getValue('priority');
        } elseif ($data['priority'] <= 0) {
            $data['priority'] = 1;
        }

        $sql = Sql::factory();
        $sql->setTable(Core::getTable('article_slice'));
        $sql->setValue('article_id', $articleId);
        $sql->setValue('clang_id', $clangId);
        $sql->setValue('ctype_id', $ctypeId);
        $sql->setValue('module', $moduleKey);

        foreach ($data as $key => $value) {
            $sql->setValue($key, $value);
        }

        $sql->addGlobalCreateFields();
        $sql->addGlobalUpdateFields();

        $sql->insert();
        $sliceId = $sql->getLastId();

        Util::organizePriorities(
            Core::getTable('article_slice'),
            'priority',
            $where,
            'priority, updatedate DESC',
        );

        ArticleCache::delete($articleId, $clangId);

        $message = I18n::msg('slice_added');

        $article = Article::require($articleId, $clangId);

        // ----- EXTENSION POINT
        $message = Extension::dispatch(new ExtensionPoint('SLICE_ADDED', $message, [
            'article_id' => $articleId,
            'clang' => $clangId,
            'function' => '',
            'slice_id' => $sliceId,
            'page' => Controller::getCurrentPage(),
            'ctype' => $ctypeId,
            'category_id' => $article->categoryId,
            'module_key' => $moduleKey,
            'article_revision' => 0,
            'slice_revision' => $data['revision'],
        ]));

        return Extension::dispatch(new ArticleContentUpdated($article, 'slice_added', $message));
    }

    /**
     * Verschiebt einen Slice.
     *
     * @param int $sliceId Id des Slices
     * @param int $clang Id der Sprache
     * @param string $direction Richtung in die verschoben werden soll
     *
     * @throws ApiFunctionException
     *
     * @return string Eine Statusmeldung
     */
    public static function moveSlice($sliceId, $clang, $direction)
    {
        // ctype beachten
        // verschieben / vertauschen
        // article regenerieren.

        // check if slice id is valid
        $CM = Sql::factory();
        $CM->setQuery('select * from ' . Core::getTablePrefix() . 'article_slice where id=? and clang_id=?', [$sliceId, $clang]);
        if (1 == $CM->getRows()) {
            // origin value for later success-check
            $oldPriority = $CM->getValue('priority');

            // prepare sql for later saving
            $upd = Sql::factory();
            $upd->setTable(Core::getTablePrefix() . 'article_slice');
            $upd->setWhere([
                'id' => $sliceId,
            ]);

            // some vars for later use
            $articleId = $CM->getValue('article_id');
            $ctype = $CM->getValue('ctype_id');
            $sliceRevision = $CM->getValue('revision');

            Extension::dispatch(new ExtensionPoint('SLICE_MOVE', '', [
                'direction' => $direction,
                'slice_id' => $sliceId,
                'article_id' => $articleId,
                'clang_id' => $clang,
                'slice_revision' => $sliceRevision,
            ]));

            if ('moveup' == $direction || 'movedown' == $direction) {
                if ('moveup' == $direction) {
                    $upd->setValue('priority', $CM->getValue('priority') - 1);
                    $updSort = 'DESC';
                } else {
                    $upd->setValue('priority', $CM->getValue('priority') + 1);
                    $updSort = 'ASC';
                }
                $upd->addGlobalUpdateFields(self::getUser());
                $upd->update();

                Util::organizePriorities(
                    Core::getTable('article_slice'),
                    'priority',
                    'article_id=' . (int) $articleId . ' AND clang_id=' . (int) $clang . ' AND ctype_id=' . (int) $ctype . ' AND revision=' . (int) $sliceRevision,
                    'priority, updatedate ' . $updSort,
                );

                // check if the slice moved at all (first cannot be moved up, last not down)
                $CM->setQuery('select * from ' . Core::getTablePrefix() . 'article_slice where id=? and clang_id=?', [$sliceId, $clang]);
                $newPriority = $CM->getValue('priority');
                if ($oldPriority == $newPriority) {
                    throw new ApiFunctionException(I18n::msg('slice_moved_error'));
                }

                ArticleCache::deleteContent($articleId, $clang);

                $info = I18n::msg('slice_moved');
                $article = Article::get($articleId, $clang);
                $info = Extension::dispatch(new ArticleContentUpdated($article, 'slice_moved', $info));
            } else {
                throw new InvalidArgumentException('Unsupported move direction "' . $direction . '".');
            }
        } else {
            throw new ApiFunctionException(I18n::msg('slice_moved_error'));
        }

        return $info;
    }

    /**
     * Löscht einen Slice.
     *
     * @param int $sliceId Id des Slices
     *
     * @return bool TRUE bei Erfolg, sonst FALSE
     */
    public static function deleteSlice($sliceId)
    {
        // check if slice id is valid
        $curr = Sql::factory();
        $curr->setQuery('SELECT * FROM ' . Core::getTablePrefix() . 'article_slice WHERE id=?', [$sliceId]);
        if (1 != $curr->getRows()) {
            return false;
        }

        Extension::dispatch(new ExtensionPoint('SLICE_DELETE', '', [
            'slice_id' => $sliceId,
            'article_id' => $curr->getValue('article_id'),
            'clang_id' => $curr->getValue('clang_id'),
            'slice_revision' => $curr->getValue('revision'),
        ]));

        // delete the slice
        $del = Sql::factory();
        $del->setQuery('DELETE FROM ' . Core::getTablePrefix() . 'article_slice WHERE id=?', [$sliceId]);

        // reorg remaining slices
        Util::organizePriorities(
            Core::getTable('article_slice'),
            'priority',
            'article_id=' . (int) $curr->getValue('article_id') . ' AND clang_id=' . (int) $curr->getValue('clang_id') . ' AND ctype_id=' . (int) $curr->getValue('ctype_id') . ' AND revision=' . (int) $curr->getValue('revision'),
            'priority',
        );

        // check if delete was successfull
        return 1 == $curr->getRows();
    }

    /** @return void */
    public static function sliceStatus(int $sliceId, int $status)
    {
        $sql = Sql::factory();
        $sql->setQuery('SELECT article_id, clang_id FROM ' . Core::getTable('article_slice') . ' WHERE id = ?', [$sliceId]);

        if (!$sql->getRows()) {
            throw new RuntimeException(sprintf('Slice with id=%d not found.', $sliceId));
        }

        $article = Article::require($sql->getValue('article_id'), $sql->getValue('clang_id'));

        $sql->setTable(Core::getTable('article_slice'));
        $sql->setWhere(['id' => $sliceId]);
        $sql->setValue('status', $status);
        $sql->update();

        ArticleCache::deleteContent($article->id, $article->clangId);

        Extension::dispatch(new ArticleContentUpdated($article, 'slice_status'));
    }

    /**
     * Kopiert die Inhalte eines Artikels in einen anderen Artikel.
     *
     * @param int $fromId ArtikelId des Artikels, aus dem kopiert werden (Quell ArtikelId)
     * @param int $toId ArtikelId des Artikel, in den kopiert werden sollen (Ziel ArtikelId)
     * @param int $fromClang ClangId des Artikels, aus dem kopiert werden soll (Quell ClangId)
     * @param int $toClang ClangId des Artikels, in den kopiert werden soll (Ziel ClangId)
     * @param int|null $revision If null, slices of all revisions are copied
     * @param bool $overwrite If true, existing content in target language will be deleted before copying
     *
     * @return bool TRUE bei Erfolg, sonst FALSE
     */
    public static function copyContent($fromId, $toId, $fromClang = 1, $toClang = 1, $revision = null, $overwrite = false)
    {
        if ($fromId == $toId && $fromClang == $toClang) {
            return false;
        }

        $gc = Sql::factory();
        if (null === $revision) {
            $gc->setQuery('select * from ' . Core::getTablePrefix() . 'article_slice where article_id=? and clang_id=?', [$fromId, $fromClang]);
        } else {
            $gc->setQuery('select * from ' . Core::getTablePrefix() . 'article_slice where article_id=? and clang_id=? and revision=?', [$fromId, $fromClang, $revision]);
        }

        Extension::dispatch(new ExtensionPoint('ART_SLICES_COPY', '', [
            'article_id' => $toId,
            'clang_id' => $toClang,
            'slice_revision' => $revision,
            'overwrite' => $overwrite,
        ]));

        // Wenn Überschreiben aktiviert ist, lösche zuerst den bestehenden Inhalt
        if ($overwrite) {
            $sql = Sql::factory();
            if (null === $revision) {
                $sql->setQuery('DELETE FROM ' . Core::getTablePrefix() . 'article_slice WHERE article_id=? AND clang_id=?', [$toId, $toClang]);
            } else {
                $sql->setQuery('DELETE FROM ' . Core::getTablePrefix() . 'article_slice WHERE article_id=? AND clang_id=? AND revision=?', [$toId, $toClang, $revision]);
            }
        }

        $ins = Sql::factory();
        // $ins->setDebug();
        $ctypes = [];

        $cols = Sql::factory();
        // $cols->setDebug();
        $cols->setQuery('SHOW COLUMNS FROM ' . Core::getTablePrefix() . 'article_slice');

        $maxPriorityRaw = Sql::factory()->getArray(
            'SELECT `ctype_id`, `revision`, MAX(`priority`) as max FROM ' . Core::getTable('article_slice') . ' WHERE `article_id` = :to_id AND `clang_id` = :to_clang GROUP BY `ctype_id`, `revision`',
            ['to_id' => $toId, 'to_clang' => $toClang],
        );
        $maxPriority = [];
        foreach ($maxPriorityRaw as $row) {
            $maxPriority[(int) $row['ctype_id']][(int) $row['revision']] = (int) $row['max'];
        }

        $user = self::getUser();

        foreach ($gc as $slice) {
            foreach ($cols as $col) {
                $colname = $col->getValue('Field');
                if ('clang_id' == $colname) {
                    $value = $toClang;
                } elseif ('article_id' == $colname) {
                    $value = $toId;
                } elseif ('priority' == $colname) {
                    $ctypeId = $slice->getValue('ctype_id');
                    $value = (int) $slice->getValue($colname) + ($maxPriority[$ctypeId][(int) $slice->getValue('revision')] ?? 0);
                } else {
                    $value = $slice->getValue($colname);
                }

                // collect all affected ctypes
                if ('ctype_id' == $colname) {
                    $ctypes[$value][(int) $slice->getValue('revision')] = true;
                }

                if ('id' != $colname) {
                    $ins->setValue($colname, $value);
                }
            }

            $ins->addGlobalUpdateFields($user);
            $ins->addGlobalCreateFields($user);
            $ins->setTable(Core::getTablePrefix() . 'article_slice');
            $ins->insert();
        }

        foreach ($ctypes as $ctype => $revisions) {
            foreach ($revisions as $revision => $_) {
                // reorg slices
                Util::organizePriorities(
                    Core::getTable('article_slice'),
                    'priority',
                    'article_id=' . (int) $toId . ' AND clang_id=' . (int) $toClang . ' AND ctype_id=' . (int) $ctype . ' AND revision=' . $revision,
                    'priority, updatedate',
                );
            }
        }

        ArticleCache::deleteContent($toId, $toClang);

        $article = Article::require($toId, $toClang);
        Extension::dispatch(new ArticleContentUpdated($article, 'content_copied'));

        return true;
    }

    /**
     * Generiert den Artikel-Cache des Artikelinhalts.
     *
     * @param int $articleId Id des zu generierenden Artikels
     * @param int $clang ClangId des Artikels
     * @return true
     */
    public static function generateArticleContent($articleId, $clang = null)
    {
        foreach (Language::getAllIds() as $clangId) {
            if (null !== $clang && $clangId != $clang) {
                continue;
            }

            try {
                $CONT = new ArticleContentBase($articleId, $clangId);

                $CONT->eval = false; // Content nicht ausführen, damit in Cachedatei gespeichert werden kann
                $articleContent = $CONT->renderContent();
            } catch (ArticleNotFoundException $e) {
                // renderContent() cannot throw here (modules are not executed while eval is off),
                // so this only covers a non-existent article from the constructor
                throw new RuntimeException(sprintf('Article %d does not exist.', $articleId), previous: $e);
            }

            // --------------------------------------------------- Artikelcontent speichern
            $articleContentFile = Path::coreCache('structure/' . $articleId . '.' . $clangId . '.content');

            // ----- EXTENSION POINT
            $articleContent = Extension::dispatch(new ExtensionPoint('GENERATE_FILTER', $articleContent, [
                'id' => $articleId,
                'clang' => $clangId,
                'article' => $CONT,
            ]));

            if (!File::put($articleContentFile, $articleContent)) {
                throw new RuntimeException(sprintf('Article %d could not be generated, check the directory permissions for "%s".', $articleId, Path::coreCache('structure/')));
            }

            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($articleContentFile);
            }
        }

        return true;
    }

    /** @return string */
    private static function getUser()
    {
        return Core::getUser()->login ?? Core::getEnvironment()->value;
    }
}
