<?php

/**
 * @package redaxo\structure\content
 */
class rex_content_service
{
    /** @throws rex_api_exception */
    public static function addSlice(int $articleId, int $clangId, int $ctypeId, int $moduleId, array $data = []): string
    {
        $data['revision'] ??= 0;

        $where = 'article_id=' . $articleId . ' AND clang_id=' . $clangId . ' AND ctype_id=' . $ctypeId . ' AND revision=' . (int) $data['revision'];

        if (!isset($data['priority'])) {
            $prevSlice = rex_sql::factory();
            $prevSlice->setQuery('SELECT IFNULL(MAX(priority),0)+1 as priority FROM ' . rex::getTable('article_slice') . ' WHERE ' . $where);

            $data['priority'] = $prevSlice->getValue('priority');
        } elseif ($data['priority'] <= 0) {
            $data['priority'] = 1;
        }

        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('article_slice'));
        $sql->setValue('article_id', $articleId);
        $sql->setValue('clang_id', $clangId);
        $sql->setValue('ctype_id', $ctypeId);
        $sql->setValue('module_id', $moduleId);

        foreach ($data as $key => $value) {
            $sql->setValue($key, $value);
        }

        $sql->addGlobalCreateFields();
        $sql->addGlobalUpdateFields();

        $sql->insert();
        $sliceId = $sql->getLastId();

        rex_sql_util::organizePriorities(
            rex::getTable('article_slice'),
            'priority',
            $where,
            'priority, updatedate DESC',
        );

        rex_article_cache::delete($articleId, $clangId);

        $message = rex_i18n::msg('slice_added');

        $article = rex_article::get($articleId, $clangId);

        // ----- EXTENSION POINT
        $message = rex_extension::registerPoint(new rex_extension_point('SLICE_ADDED', $message, [
            'article_id' => $articleId,
            'clang' => $clangId,
            'function' => '',
            'slice_id' => $sliceId,
            'page' => rex_be_controller::getCurrentPage(),
            'ctype' => $ctypeId,
            'category_id' => $article->getCategoryId(),
            'module_id' => $moduleId,
            'article_revision' => 0,
            'slice_revision' => $data['revision'],
        ]));

        return rex_extension::registerPoint(new rex_extension_point_art_content_updated($article, 'slice_added', $message));
    }

    /**
     * Verschiebt einen Slice.
     *
     * @param int $sliceId Id des Slices
     * @param int $clang Id der Sprache
     * @param string $direction Richtung in die verschoben werden soll
     *
     * @throws rex_exception
     * @throws rex_api_exception
     *
     * @return string Eine Statusmeldung
     */
    public static function moveSlice($sliceId, $clang, $direction)
    {
        // ctype beachten
        // verschieben / vertauschen
        // article regenerieren.

        // check if slice id is valid
        $CM = rex_sql::factory();
        $CM->setQuery('select * from ' . rex::getTablePrefix() . 'article_slice where id=? and clang_id=?', [$sliceId, $clang]);
        if (1 == $CM->getRows()) {
            // origin value for later success-check
            $oldPriority = $CM->getValue('priority');

            // prepare sql for later saving
            $upd = rex_sql::factory();
            $upd->setTable(rex::getTablePrefix() . 'article_slice');
            $upd->setWhere([
                'id' => $sliceId,
            ]);

            // some vars for later use
            $articleId = $CM->getValue('article_id');
            $ctype = $CM->getValue('ctype_id');
            $sliceRevision = $CM->getValue('revision');

            rex_extension::registerPoint(new rex_extension_point('SLICE_MOVE', '', [
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

                rex_sql_util::organizePriorities(
                    rex::getTable('article_slice'),
                    'priority',
                    'article_id=' . (int) $articleId . ' AND clang_id=' . (int) $clang . ' AND ctype_id=' . (int) $ctype . ' AND revision=' . (int) $sliceRevision,
                    'priority, updatedate ' . $updSort,
                );

                // check if the slice moved at all (first cannot be moved up, last not down)
                $CM->setQuery('select * from ' . rex::getTablePrefix() . 'article_slice where id=? and clang_id=?', [$sliceId, $clang]);
                $newPriority = $CM->getValue('priority');
                if ($oldPriority == $newPriority) {
                    throw new rex_api_exception(rex_i18n::msg('slice_moved_error'));
                }

                rex_article_cache::deleteContent($articleId, $clang);

                $info = rex_i18n::msg('slice_moved');
                $article = rex_article::get($articleId, $clang);
                $info = rex_extension::registerPoint(new rex_extension_point_art_content_updated($article, 'slice_moved', $info));
            } else {
                throw new rex_exception('rex_moveSlice: Unsupported direction "' . $direction . '"!');
            }
        } else {
            throw new rex_api_exception(rex_i18n::msg('slice_moved_error'));
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
        $curr = rex_sql::factory();
        $curr->setQuery('SELECT * FROM ' . rex::getTablePrefix() . 'article_slice WHERE id=?', [$sliceId]);
        if (1 != $curr->getRows()) {
            return false;
        }

        rex_extension::registerPoint(new rex_extension_point('SLICE_DELETE', '', [
            'slice_id' => $sliceId,
            'article_id' => $curr->getValue('article_id'),
            'clang_id' => $curr->getValue('clang_id'),
            'slice_revision' => $curr->getValue('revision'),
        ]));

        // delete the slice
        $del = rex_sql::factory();
        $del->setQuery('DELETE FROM ' . rex::getTablePrefix() . 'article_slice WHERE id=?', [$sliceId]);

        // reorg remaining slices
        rex_sql_util::organizePriorities(
            rex::getTable('article_slice'),
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
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT article_id, clang_id FROM ' . rex::getTable('article_slice') . ' WHERE id = ?', [$sliceId]);

        if (!$sql->getRows()) {
            throw new rex_exception(sprintf('Slice with id=%d not found.', $sliceId));
        }

        $article = rex_article::get($sql->getValue('article_id'), $sql->getValue('clang_id'));

        $sql->setTable(rex::getTable('article_slice'));
        $sql->setWhere(['id' => $sliceId]);
        $sql->setValue('status', $status);
        $sql->update();

        rex_article_cache::deleteContent($article->getId(), $article->getClangId());

        rex_extension::registerPoint(new rex_extension_point_art_content_updated($article, 'slice_status'));
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

        $gc = rex_sql::factory();
        if (null === $revision) {
            $gc->setQuery('select * from ' . rex::getTablePrefix() . 'article_slice where article_id=? and clang_id=?', [$fromId, $fromClang]);
        } else {
            $gc->setQuery('select * from ' . rex::getTablePrefix() . 'article_slice where article_id=? and clang_id=? and revision=?', [$fromId, $fromClang, $revision]);
        }

        rex_extension::registerPoint(new rex_extension_point('ART_SLICES_COPY', '', [
            'article_id' => $toId,
            'clang_id' => $toClang,
            'slice_revision' => $revision,
            'overwrite' => $overwrite,
        ]));

        // Wenn Überschreiben aktiviert ist, lösche zuerst den bestehenden Inhalt
        if ($overwrite) {
            $sql = rex_sql::factory();
            if (null === $revision) {
                $sql->setQuery('DELETE FROM ' . rex::getTablePrefix() . 'article_slice WHERE article_id=? AND clang_id=?', [$toId, $toClang]);
            } else {
                $sql->setQuery('DELETE FROM ' . rex::getTablePrefix() . 'article_slice WHERE article_id=? AND clang_id=? AND revision=?', [$toId, $toClang, $revision]);
            }
        }

        $ins = rex_sql::factory();
        // $ins->setDebug();
        $ctypes = [];

        $cols = rex_sql::factory();
        // $cols->setDebug();
        $cols->setQuery('SHOW COLUMNS FROM ' . rex::getTablePrefix() . 'article_slice');

        $maxPriorityRaw = rex_sql::factory()->getArray(
            'SELECT `ctype_id`, `revision`, MAX(`priority`) as max FROM ' . rex::getTable('article_slice') . ' WHERE `article_id` = :to_id AND `clang_id` = :to_clang GROUP BY `ctype_id`, `revision`',
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
            $ins->setTable(rex::getTablePrefix() . 'article_slice');
            $ins->insert();
        }

        foreach ($ctypes as $ctype => $revisions) {
            foreach ($revisions as $revision => $_) {
                // reorg slices
                rex_sql_util::organizePriorities(
                    rex::getTable('article_slice'),
                    'priority',
                    'article_id=' . (int) $toId . ' AND clang_id=' . (int) $toClang . ' AND ctype_id=' . (int) $ctype . ' AND revision=' . $revision,
                    'priority, updatedate',
                );
            }
        }

        rex_article_cache::deleteContent($toId, $toClang);

        $article = rex_article::get($toId, $toClang);
        rex_extension::registerPoint(new rex_extension_point_art_content_updated($article, 'content_copied'));

        return true;
    }

    /**
     * Generiert den Artikel-Cache des Artikelinhalts.
     *
     * @param int $articleId Id des zu generierenden Artikels
     * @param int $clang ClangId des Artikels
     *
     * @throws rex_exception
     *
     * @return true
     */
    public static function generateArticleContent($articleId, $clang = null)
    {
        foreach (rex_clang::getAllIds() as $clangId) {
            if (null !== $clang && $clangId != $clang) {
                continue;
            }

            $CONT = new rex_article_content_base();
            $CONT->setClang($clangId);
            $CONT->setEval(false); // Content nicht ausführen, damit in Cachedatei gespeichert werden kann
            if (!$CONT->setArticleId($articleId)) {
                throw new rex_exception(sprintf('Article %d does not exist.', $articleId));
            }

            // --------------------------------------------------- Artikelcontent speichern
            $articleContentFile = rex_path::addonCache('structure', "$articleId.$clangId.content");
            $articleContent = $CONT->getArticle();

            // ----- EXTENSION POINT
            $articleContent = rex_extension::registerPoint(new rex_extension_point('GENERATE_FILTER', $articleContent, [
                'id' => $articleId,
                'clang' => $clangId,
                'article' => $CONT,
            ]));

            if (!rex_file::put($articleContentFile, $articleContent)) {
                throw new rex_exception(sprintf('Article %d could not be generated, check the directory permissions for "%s".', $articleId, rex_path::addonCache('structure')));
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
        return rex::getUser()?->getLogin() ?? rex::getEnvironment();
    }
}
