<?php

/**
 * History.
 *
 * @author jan@kristinus.de
 */

$plugin = rex_plugin::get('structure', 'history');

$historyDate = rex_request('rex_history_date', 'string');

rex_perm::register('history[read]', null, rex_perm::OPTIONS);
rex_perm::register('history[article_rollback]', null, rex_perm::OPTIONS);
if (rex_plugin::get('structure', 'version')->isAvailable()) {
    rex_perm::register('history[article_draft_rollback]', null, rex_perm::OPTIONS);
}

if ('' != $historyDate) {
    $historySession = rex_request('rex_history_session', 'string');
    $historyLogin = rex_request('rex_history_login', 'string');
    $historyValidtime = rex_request('rex_history_validtime', 'string');

    $user = null;
    if ('' != $historySession && '' != $historyLogin && '' != $historyValidtime && !rex::isBackend()) {
        $validtill = DateTime::createFromFormat('YmdHis', $historyValidtime);
        $now = new DateTime();
        if ($now < $validtill) {
            $login = new rex_history_login();

            if ($login->checkTempSession($historyLogin, $historySession, $historyValidtime)) {
                $user = $login->getUser();
                rex::setProperty('user', $user);
                rex_extension::register('OUTPUT_FILTER', static function (rex_extension_point $ep) use ($login) {
                    $login->deleteSession();
                });
            }
        }
    } else {
        $user = rex_backend_login::createUser();
    }

    if (!$user) {
        throw new rex_http_exception(new rex_exception('no permission'), rex_response::HTTP_UNAUTHORIZED);
    }

    if (!$user->hasPerm('history[read]')) {
        throw new rex_http_exception(new rex_exception('no permission for the slice version'), rex_response::HTTP_FORBIDDEN);
    }

    rex_extension::register('ART_INIT', static function (rex_extension_point $ep) {
        $article = $ep->getParam('article');
        if ($article instanceof rex_article_content) {
            $article->getContentAsQuery();
        }
        $article->setEval(true);
    });

    rex_extension::register('ART_SLICES_QUERY', static function (rex_extension_point $ep) {
        $historyDate = rex_request('rex_history_date', 'string');
        $article = $ep->getParam('article');

        if ($article instanceof rex_article_content && $article->getArticleId() == rex_article::getCurrentId()) {
            $articleLimit = '';
            if (0 != $article->getArticleId()) {
                $articleLimit = ' AND ' . rex::getTablePrefix() . 'article_slice.article_id=' . $article->getArticleId();
            }

            rex_article_slice_history::checkTables();

            $escapeSql = rex_sql::factory();

            $sliceDate = ' AND ' . rex::getTablePrefix() . 'article_slice.history_date = ' . $escapeSql->escape($historyDate);

            return 'SELECT ' . rex::getTablePrefix() . 'module.id, ' . rex::getTablePrefix() . 'module.key,' . rex::getTablePrefix() . 'module.name, ' . rex::getTablePrefix() . 'module.output, ' . rex::getTablePrefix() . 'module.input, ' . rex::getTablePrefix() . 'article_slice.*, ' . rex::getTablePrefix() . 'article.parent_id
                FROM
                    ' . rex_article_slice_history::getTable() . ' as ' . rex::getTablePrefix() . 'article_slice
                LEFT JOIN ' . rex::getTablePrefix() . 'module ON ' . rex::getTablePrefix() . 'article_slice.module_id=' . rex::getTablePrefix() . 'module.id
                LEFT JOIN ' . rex::getTablePrefix() . 'article ON ' . rex::getTablePrefix() . 'article_slice.article_id=' . rex::getTablePrefix() . 'article.id
                WHERE
                    ' . rex::getTablePrefix() . "article_slice.clang_id='" . $article->getClangId() . "' AND
                    " . rex::getTablePrefix() . "article.clang_id='" . $article->getClangId() . "' AND
                    " . rex::getTablePrefix() . 'article_slice.revision=0
                    ' . $articleLimit . '
                    ' . $sliceDate . '
                    ORDER BY ' . rex::getTablePrefix() . 'article_slice.priority';
        }

        return null;
    });
}

rex_extension::register(
    ['ART_SLICES_COPY', 'SLICE_ADD', 'SLICE_UPDATE', 'SLICE_MOVE', 'SLICE_DELETE'],
    static function (rex_extension_point $ep) {
        $type = match ($ep->getName()) {
            'ART_SLICES_COPY' => 'slices_copy',
            'SLICE_MOVE' => 'slice_' . $ep->getParam('direction'),
            default => strtolower($ep->getName()),
        };

        $articleId = $ep->getParam('article_id');
        $clangId = $ep->getParam('clang_id');
        $sliceRevision = $ep->getParam('slice_revision');

        if (0 == $sliceRevision) {
            rex_article_slice_history::makeSnapshot($articleId, $clangId, $type);
        }
    },
);

if (rex::isBackend() && rex::getUser()?->hasPerm('history[read]')) {
    rex_view::addCssFile($plugin->getAssetsUrl('noUiSlider/nouislider.css'));
    rex_view::addJsFile($plugin->getAssetsUrl('noUiSlider/nouislider.js'), [rex_view::JS_IMMUTABLE => true]);
    rex_view::addCssFile($plugin->getAssetsUrl('history.css'));
    rex_view::addJsFile($plugin->getAssetsUrl('history.js'), [rex_view::JS_IMMUTABLE => true]);

    switch (rex_request('rex_history_function', 'string')) {
        case 'snap':
            if (!rex::requireUser()->hasPerm('history[article_rollback]')) {
                throw new rex_http_exception(new rex_exception('no permission for article rollback'), rex_response::HTTP_FORBIDDEN);
            }
            $articleId = rex_request('history_article_id', 'int');
            $clangId = rex_request('history_clang_id', 'int');
            $historyDate = rex_request('history_date', 'string');
            rex_article_slice_history::restoreSnapshot($historyDate, $articleId, $clangId);

            // no break
        case 'snap_draft':
            if ('snap_draft' === rex_request('rex_history_function', 'string')) {
                if (!rex::requireUser()->hasPerm('history[article_draft_rollback]')) {
                    throw new rex_http_exception(new rex_exception('no permission for draft rollback'), rex_response::HTTP_FORBIDDEN);
                }
                $articleId = rex_request('history_article_id', 'int');
                $clangId = rex_request('history_clang_id', 'int');
                $historyDate = rex_request('history_date', 'string');
                rex_article_slice_history::restoreDraftSnapshot($historyDate, $articleId, $clangId);
            }

            // no break
        case 'layer':
            $articleId = rex_request('history_article_id', 'int');
            $clangId = rex_request('history_clang_id', 'int');
            $versions = rex_article_slice_history::getSnapshots($articleId, $clangId);

            $select1 = [];
            $versionAvailable = rex_plugin::get('structure', 'version')->isAvailable();
            $currentVersionLabel = $versionAvailable ? $plugin->i18n('current_live_version') : $plugin->i18n('current_version');
            $select1[] = '<option value="0" selected="selected" data-revision="0">' . $currentVersionLabel . '</option>';
            if ($versionAvailable) {
                $select1[] = '<option value="1" data-revision="1">' . rex_i18n::msg('version_workingversion') . '</option>';
            }

            $select2 = [];
            $select2[] = '<option value="" selected="selected">' . $currentVersionLabel . '</option>';
            foreach ($versions as $version) {
                $historyInfo = $version['history_date'];
                if ('' != $version['history_user']) {
                    $historyInfo = $version['history_date'] . ' [' . $version['history_user'] . ']';
                }
                $select2[] = '<option value="' . strtotime($version['history_date']) . '" data-history-date="' . rex_escape($version['history_date']) . '">' . rex_escape($historyInfo) . '</option>';
            }

            $content1select = '<select id="content-history-select-date-1" class="content-history-select" data-iframe="content-history-iframe-1" style="">' . implode('', $select1) . '</select>';
            $content1iframe = '<iframe id="content-history-iframe-1" class="history-iframe"></iframe>';
            $content2select = '<select id="content-history-select-date-2" class="content-history-select" data-iframe="content-history-iframe-2">' . implode('', $select2) . '</select>';
            $content2iframe = '<iframe id="content-history-iframe-2" class="history-iframe"></iframe>';

            // fragment holen und ausgeben
            $fragment = new rex_fragment();
            $fragment->setVar('title', $plugin->i18n('overview_versions'));
            $fragment->setVar('content1select', $content1select, false);
            $fragment->setVar('content1iframe', $content1iframe, false);
            $fragment->setVar('content2select', $content2select, false);
            $fragment->setVar('content2iframe', $content2iframe, false);
            $fragment->setVar('allow_rollback', rex::requireUser()->hasPerm('history[article_rollback]'));
            $fragment->setVar('allow_draft_rollback', $versionAvailable && rex::requireUser()->hasPerm('history[article_draft_rollback]'));
            $fragment->setVar('version_available', $versionAvailable);

            echo $fragment->parse('history/layer.php');
            exit;
    }

    rex_extension::register('STRUCTURE_CONTENT_HEADER', static function (rex_extension_point $ep) {
        if ('content/edit' == $ep->getParam('page')) {
            $articleLink = rex_getUrl(rex_article::getCurrentId(), rex_clang::getCurrentId(), [], '&');
            if (str_starts_with($articleLink, 'http')) {
                $user = rex::requireUser();
                $userLogin = $user->getLogin();
                $historyValidTime = new DateTime();
                $historyValidTime = $historyValidTime->modify('+10 Minutes')->format('YmdHis'); // 10 minutes valid key
                $userHistorySession = rex_history_login::createSessionKey($userLogin, $user->getValue('session_id'), $historyValidTime);
                $articleLink = rex_getUrl(rex_article::getCurrentId(), rex_clang::getCurrentId(), ['rex_history_login' => $userLogin, 'rex_history_session' => $userHistorySession, 'rex_history_validtime' => $historyValidTime], '&');
            }

            echo '<script nonce="' . rex_response::getNonce() . '">
                    var history_article_id = ' . rex_article::getCurrentId() . ';
                    var history_clang_id = ' . rex_clang::getCurrentId() . ';
                    var history_ctype_id = ' . rex_request('ctype', 'int', 0) . ';
                    var history_article_link = "' . rex_escape($articleLink, 'js') . '";
                    </script>';
        }
    },
    );
}

if (rex_addon::get('cronjob')->isAvailable()) {
    rex_cronjob_manager::registerType(rex_cronjob_structure_history::class);
}
