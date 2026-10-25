<?php

namespace Redaxo\Core\Content;

use Redaxo\Core\Content\Exception\ArticleNotFoundException;
use Redaxo\Core\Content\ExtensionPoint\ArticleContentUpdated;
use Redaxo\Core\Content\ExtensionPoint\ArticleSlicesCopying;
use Redaxo\Core\Content\ExtensionPoint\SliceAdding;
use Redaxo\Core\Content\ExtensionPoint\SliceDeleting;
use Redaxo\Core\Content\ExtensionPoint\SliceMoving;
use Redaxo\Core\Core;
use Redaxo\Core\Database\DatabaseUtil;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Security\User;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Type;

use function function_exists;
use function sprintf;

final class ContentHandler
{
    private function __construct() {}

    /**
     * Checks whether the user may change the slices of the given revision.
     *
     * With the work version enabled, the live revision may only be changed by users with the `version[live_version]` perm.
     *
     * @internal
     */
    public static function hasRevisionPerm(User $user, int $revision): bool
    {
        if (ArticleRevision::LIVE !== $revision || !Core::getConfig('article_work_version', false)) {
            return true;
        }

        return $user->hasPerm('version[live_version]');
    }

    /**
     * @param array<string, mixed> $values the stored values of the module fields, keyed by storage key
     * @param int|null $priority `null` to append the slice to the content section
     */
    public static function addSlice(int $articleId, int $languageId, int $contentSectionId, string $moduleKey, array $values = [], ?int $priority = null, int $revision = 0): ArticleSlice
    {
        $where = 'article_id=' . $articleId . ' AND language_id=' . $languageId . ' AND content_section_id=' . $contentSectionId . ' AND revision=' . $revision;

        if (null === $priority) {
            $prevSlice = Sql::factory();
            $prevSlice->setQuery('SELECT MAX(priority) as priority FROM rex_article_slice WHERE ' . $where);

            $priority = ($prevSlice->getNullableIntValue('priority') ?? 0) + 1;
        }

        Extension::dispatch(new SliceAdding(Article::require($articleId, $languageId), $contentSectionId, $moduleKey, $revision));

        $sql = Sql::factory();
        $sql->setTable('rex_article_slice');
        $sql->setValue('article_id', $articleId);
        $sql->setValue('language_id', $languageId);
        $sql->setValue('content_section_id', $contentSectionId);
        $sql->setValue('module', $moduleKey);
        $sql->setValue('revision', $revision);
        $sql->setValue('priority', max(1, $priority));
        $sql->setValue('data', ArticleSlice::encodeData($values));

        $sql->addGlobalCreateFields();
        $sql->addGlobalUpdateFields();

        $sql->insert();
        $sliceId = $sql->getLastId();

        DatabaseUtil::organizePriorities(
            'rex_article_slice',
            'priority',
            $where,
            'priority, updatedate DESC',
        );

        ArticleCache::delete($articleId, $languageId);

        $article = Article::require($articleId, $languageId);
        $slice = Type::notNull(ArticleSlice::getArticleSliceById($sliceId, $languageId, $revision));
        ArticleSliceReference::update($slice);

        Extension::dispatch(new ArticleContentUpdated($article, 'slice_added', $slice));

        return $slice;
    }

    /**
     * Verschiebt einen Slice.
     *
     * @throws UserMessageException
     */
    public static function moveSlice(int $sliceId, int $languageId, SliceMoveDirection $direction): ArticleSlice
    {
        // ctype beachten
        // verschieben / vertauschen
        // article regenerieren.

        // check if slice id is valid
        $CM = Sql::factory();
        $CM->setQuery('select * from rex_article_slice where id=? and language_id=?', [$sliceId, $languageId]);
        if (1 != $CM->getRows()) {
            throw new UserMessageException(I18n::msg('slice_not_found'));
        }

        // origin value for later success-check
        $oldPriority = $CM->getIntValue('priority');

        // prepare sql for later saving
        $upd = Sql::factory();
        $upd->setTable('rex_article_slice');
        $upd->setWhere([
            'id' => $sliceId,
        ]);

        // some vars for later use
        $articleId = $CM->getIntValue('article_id');
        $ctype = $CM->getIntValue('content_section_id');
        $sliceRevision = $CM->getIntValue('revision');

        Extension::dispatch(new SliceMoving(ArticleSlice::fromSql($CM), $direction));

        if (SliceMoveDirection::Up === $direction) {
            $upd->setValue('priority', $oldPriority - 1);
            $updSort = 'DESC';
        } else {
            $upd->setValue('priority', $oldPriority + 1);
            $updSort = 'ASC';
        }
        $upd->addGlobalUpdateFields(self::getUser());
        $upd->update();

        DatabaseUtil::organizePriorities(
            'rex_article_slice',
            'priority',
            'article_id=' . $articleId . ' AND language_id=' . $languageId . ' AND content_section_id=' . $ctype . ' AND revision=' . $sliceRevision,
            'priority, updatedate ' . $updSort,
        );

        // check if the slice moved at all (first cannot be moved up, last not down)
        $CM->setQuery('select * from rex_article_slice where id=? and language_id=?', [$sliceId, $languageId]);
        $newPriority = $CM->getIntValue('priority');
        if ($oldPriority === $newPriority) {
            throw new UserMessageException(I18n::msg('slice_moved_error'));
        }

        ArticleCache::deleteContent($articleId, $languageId);

        $article = Article::require($articleId, $languageId);
        $slice = Type::notNull(ArticleSlice::getArticleSliceById($sliceId, $languageId, $sliceRevision));
        Extension::dispatch(new ArticleContentUpdated($article, 'slice_moved', $slice));

        return $slice;
    }

    /**
     * Deletes a slice.
     *
     * @throws UserMessageException
     */
    public static function deleteSlice(int $sliceId): void
    {
        $curr = Sql::factory();
        $curr->setQuery('SELECT * FROM rex_article_slice WHERE id=?', [$sliceId]);
        if (1 != $curr->getRows()) {
            throw new UserMessageException(I18n::msg('slice_not_found'));
        }

        $slice = ArticleSlice::fromSql($curr);

        Extension::dispatch(new SliceDeleting($slice));

        $del = Sql::factory();
        $del->setQuery('DELETE FROM rex_article_slice WHERE id=?', [$sliceId]);

        // reorg remaining slices
        DatabaseUtil::organizePriorities(
            'rex_article_slice',
            'priority',
            'article_id=' . $slice->articleId . ' AND language_id=' . $slice->languageId . ' AND content_section_id=' . $slice->contentSectionId . ' AND revision=' . $slice->revision,
            'priority',
        );

        ArticleCache::deleteContent($slice->articleId, $slice->languageId);

        $article = Article::require($slice->articleId, $slice->languageId);

        Extension::dispatch(new ArticleContentUpdated($article, 'slice_deleted', $slice));
    }

    /** @throws UserMessageException */
    public static function changeSliceStatus(int $sliceId, int $status): ArticleSlice
    {
        $sql = Sql::factory();
        $sql->setQuery('SELECT article_id, language_id, revision FROM rex_article_slice WHERE id = ?', [$sliceId]);

        if (!$sql->getRows()) {
            throw new UserMessageException(I18n::msg('slice_not_found'));
        }

        $article = Article::require($sql->getIntValue('article_id'), $sql->getIntValue('language_id'));
        $revision = $sql->getIntValue('revision');

        $sql->setTable('rex_article_slice');
        $sql->setWhere(['id' => $sliceId]);
        $sql->setValue('status', $status);
        $sql->update();

        ArticleCache::deleteContent($article->id, $article->languageId);

        $slice = Type::notNull(ArticleSlice::getArticleSliceById($sliceId, $article->languageId, $revision));

        Extension::dispatch(new ArticleContentUpdated($article, 'slice_status', $slice));

        return $slice;
    }

    /**
     * Kopiert die Inhalte eines Artikels in einen anderen Artikel.
     *
     * @param int|null $revision If null, slices of all revisions are copied
     * @param bool $overwrite If true, existing content in target language will be deleted before copying
     *
     * @throws UserMessageException
     */
    public static function copyContent(int $fromId, int $toId, int $fromLanguageId = 1, int $toLanguageId = 1, ?int $revision = null, bool $overwrite = false): void
    {
        if ($fromId == $toId && $fromLanguageId == $toLanguageId) {
            throw new UserMessageException(I18n::msg('content_errorcopy'));
        }

        $gc = Sql::factory();
        if (null === $revision) {
            $gc->setQuery('select * from rex_article_slice where article_id=? and language_id=?', [$fromId, $fromLanguageId]);
        } else {
            $gc->setQuery('select * from rex_article_slice where article_id=? and language_id=? and revision=?', [$fromId, $fromLanguageId, $revision]);
        }

        Extension::dispatch(new ArticleSlicesCopying(
            Article::require($toId, $toLanguageId),
            Article::require($fromId, $fromLanguageId),
            $revision,
            $overwrite,
        ));

        // Wenn Überschreiben aktiviert ist, lösche zuerst den bestehenden Inhalt
        if ($overwrite) {
            $sql = Sql::factory();
            if (null === $revision) {
                $sql->setQuery('DELETE FROM rex_article_slice WHERE article_id=? AND language_id=?', [$toId, $toLanguageId]);
            } else {
                $sql->setQuery('DELETE FROM rex_article_slice WHERE article_id=? AND language_id=? AND revision=?', [$toId, $toLanguageId, $revision]);
            }
        }

        $ins = Sql::factory();
        // $ins->setDebug();
        $ctypes = [];

        $cols = Sql::factory();
        // $cols->setDebug();
        $cols->setQuery('SHOW COLUMNS FROM rex_article_slice');

        $maxPriorityRaw = Sql::factory()->getArray(
            'SELECT `content_section_id`, `revision`, MAX(`priority`) as max FROM rex_article_slice WHERE `article_id` = :to_id AND `language_id` = :to_language GROUP BY `content_section_id`, `revision`',
            ['to_id' => $toId, 'to_language' => $toLanguageId],
        );
        $maxPriority = [];
        foreach ($maxPriorityRaw as $row) {
            $maxPriority[(int) $row['content_section_id']][(int) $row['revision']] = (int) $row['max'];
        }

        $user = self::getUser();

        foreach ($gc as $slice) {
            foreach ($cols as $col) {
                $colname = $col->getStringValue('Field');
                if ('language_id' == $colname) {
                    $value = $toLanguageId;
                } elseif ('article_id' == $colname) {
                    $value = $toId;
                } elseif ('priority' == $colname) {
                    $contentSectionId = $slice->getIntValue('content_section_id');
                    $value = $slice->getIntValue($colname) + ($maxPriority[$contentSectionId][$slice->getIntValue('revision')] ?? 0);
                } else {
                    $value = $slice->getValue($colname);
                }

                // collect all affected ctypes
                if ('content_section_id' == $colname) {
                    $ctypes[$value][$slice->getIntValue('revision')] = true;
                }

                if ('id' != $colname) {
                    $ins->setValue($colname, $value);
                }
            }

            $ins->addGlobalUpdateFields($user);
            $ins->addGlobalCreateFields($user);
            $ins->setTable('rex_article_slice');
            $ins->insert();

            ArticleSliceReference::copy($slice->getIntValue('id'), $ins->getLastId());
        }

        foreach ($ctypes as $ctype => $revisions) {
            foreach ($revisions as $revision => $_) {
                // reorg slices
                DatabaseUtil::organizePriorities(
                    'rex_article_slice',
                    'priority',
                    'article_id=' . $toId . ' AND language_id=' . $toLanguageId . ' AND content_section_id=' . (int) $ctype . ' AND revision=' . $revision,
                    'priority, updatedate',
                );
            }
        }

        ArticleCache::deleteContent($toId, $toLanguageId);

        $article = Article::require($toId, $toLanguageId);
        Extension::dispatch(new ArticleContentUpdated($article, 'content_copied'));
    }

    /** Generates the article content cache, for one language or for all of them. */
    public static function generateArticleContent(int $articleId, ?int $onlyLanguageId = null): void
    {
        foreach (Language::getAllIds() as $languageId) {
            if (null !== $onlyLanguageId && $languageId != $onlyLanguageId) {
                continue;
            }

            try {
                $CONT = new ArticleContentBase($articleId, $languageId);

                $CONT->eval = false; // Content nicht ausführen, damit in Cachedatei gespeichert werden kann
                $articleContent = $CONT->renderContent();
            } catch (ArticleNotFoundException $e) {
                // renderContent() cannot throw here (modules are not executed while eval is off),
                // so this only covers a non-existent article from the constructor
                throw new RuntimeException(sprintf('Article %d (language %d) does not exist.', $articleId, $languageId), previous: $e);
            }

            // --------------------------------------------------- Artikelcontent speichern
            $articleContentFile = Path::coreCache('structure/' . $articleId . '.' . $languageId . '.content');

            if (!File::put($articleContentFile, $articleContent)) {
                throw new RuntimeException(sprintf('Article %d could not be generated, check the directory permissions for "%s".', $articleId, Path::coreCache('structure/')));
            }

            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($articleContentFile);
            }
        }
    }

    private static function getUser(): string
    {
        return Core::getUser()->login ?? Core::getEnvironment()->value;
    }
}
