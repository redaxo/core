<?php

/**
 * Version.
 *
 * @author jan@kristinus.de
 */

rex_perm::register('version[live_version]', null, rex_perm::OPTIONS);

// ***** an EPs andocken
rex_extension::register('ART_INIT', static function (rex_extension_point $ep) {
    $version = rex_request('rex_version', 'int');
    if (rex_article_revision::WORK != $version) {
        return;
    }

    if (!rex_backend_login::hasSession()) {
        $fragment = new rex_fragment([
            'content' => '<p>No permission for the working version. You need to be logged into the REDAXO backend at the same time.</p>',
        ]);
        rex_response::setStatus(rex_response::HTTP_UNAUTHORIZED);
        rex_response::sendPage($fragment->parse('core/fe_ooops.php'));
        exit;
    }

    /** @var rex_article_content_base $article */
    $article = $ep->getParam('article');
    $article->setSliceRevision($version);
    if ($article instanceof rex_article_content) {
        $article->getContentAsQuery();
    }
    $article->setEval(true);
});

rex_extension::register('STRUCTURE_CONTENT_HEADER', static function (rex_extension_point $ep) {
    if ('content/edit' !== $ep->getParam('page')) {
        return null;
    }

    $params = $ep->getParams();
    $articleId = rex_type::int($params['article_id']);

    $version = rex_article_revision::getSessionArticleRevision($articleId);
    $newVersion = rex_request('rex_set_version', 'int', null);

    if (rex_article_revision::LIVE === $newVersion) {
        $version = rex_article_revision::LIVE;
    } elseif (rex_article_revision::WORK === $newVersion) {
        $version = rex_article_revision::WORK;
    }

    if (!rex::requireUser()->hasPerm('version[live_version]')) {
        $version = rex_article_revision::WORK;
    }

    rex_article_revision::setSessionArticleRevision($articleId, $version);

    $params['slice_revision'] = $version;
});

rex_extension::register('STRUCTURE_CONTENT_BEFORE_SLICES', static function (rex_extension_point $ep) {
    if ('content/edit' !== $ep->getParam('page')) {
        return null;
    }

    $user = rex::requireUser();
    $params = $ep->getParams();
    $articleId = rex_type::int($params['article_id']);
    $clangId = rex_type::int($params['clang']);
    $return = rex_type::string($ep->getSubject());

    $workingVersionEmpty = true;
    $gw = rex_sql::factory();
    $gw->setQuery('select * from ' . rex::getTablePrefix() . 'article_slice where article_id=? and clang_id=? and revision=1 LIMIT 1', [$articleId, $clangId]);
    if ($gw->getRows() > 0) {
        $workingVersionEmpty = false;
    }

    $csrfToken = rex_csrf_token::factory('structure_version');

    $func = rex_request('rex_version_func', 'string');
    if ('' !== $func && !$csrfToken->isValid()) {
        $return .= rex_view::error(rex_i18n::msg('csrf_token_invalid'));
        $func = '';
    }

    switch ($func) {
        case 'copy_work_to_live':
            if ($workingVersionEmpty) {
                $return .= rex_view::error(rex_i18n::msg('version_warning_working_version_to_live'));
            } elseif ($user->hasPerm('version[live_version]')) {
                if (rex_plugin::get('structure', 'history')->isAvailable()) {
                    rex_article_slice_history::makeSnapshot($articleId, $clangId, 'work_to_live');
                }

                rex_article_revision::copyContent($articleId, $clangId, rex_article_revision::WORK, rex_article_revision::LIVE);
                $return .= rex_view::success(rex_i18n::msg('version_info_working_version_to_live'));

                $article = rex_type::instanceOf(rex_article::get($articleId, $clangId), rex_article::class);
                rex_article_revision::setSessionArticleRevision($articleId, rex_article_revision::LIVE);
                $params['slice_revision'] = rex_article_revision::LIVE;
                $return = rex_extension::registerPoint(new rex_extension_point_art_content_updated($article, 'work_to_live', $return));
            }
            break;
        case 'copy_live_to_work':
            rex_article_revision::copyContent($articleId, $clangId, rex_article_revision::LIVE, rex_article_revision::WORK);
            $return .= rex_view::success(rex_i18n::msg('version_info_live_version_to_working'));
            rex_article_revision::setSessionArticleRevision($articleId, rex_article_revision::WORK);
            $params['slice_revision'] = rex_article_revision::WORK;
            break;
        case 'clear_work':
            rex_article_revision::clearContent($articleId, $clangId, rex_article_revision::WORK);
            $return .= rex_view::success(rex_i18n::msg('version_info_clear_workingversion'));
            break;
    }

    $revision = rex_article_revision::getSessionArticleRevision($articleId);

    $revisions = [];
    if ($user->hasPerm('version[live_version]')) {
        $revisions[rex_article_revision::LIVE] = rex_i18n::msg('version_liveversion');
    }
    $revisions[rex_article_revision::WORK] = rex_i18n::msg('version_workingversion');

    $context = new rex_context([
        'page' => $params['page'],
        'article_id' => $articleId,
        'clang' => $clangId,
        'ctype' => $params['ctype'],
    ]);

    $items = [];
    $currentRevision = '';
    foreach ($revisions as $version => $label) {
        $item = [];
        $item['title'] = $label;
        $item['href'] = $context->getUrl(['rex_set_version' => $version]);
        if ($revision == $version) {
            $item['active'] = true;
            $currentRevision = $label;
        }
        $items[] = $item;
    }

    $toolbar = '';

    $fragment = new rex_fragment();
    $fragment->setVar('button_prefix', '<b>' . $currentRevision . '</b>', false);
    $fragment->setVar('items', $items, false);
    $fragment->setVar('toolbar', true);

    if (!$user->hasPerm('version[live_version]')) {
        $fragment->setVar('disabled', true);
    }

    $toolbar .= '<li class="dropdown">' . $fragment->parse('core/dropdowns/dropdown.php') . '</li>';

    if (!$user->hasPerm('version[live_version]')) {
        if ($revision > 0) {
            $toolbar .= '<li><a href="' . $context->getUrl(['rex_version_func' => 'copy_live_to_work'] + $csrfToken->getUrlParams()) . '">' . rex_i18n::msg('version_copy_from_liveversion') . '</a></li>';
            $toolbar .= '<li><a href="' . rex_getUrl($articleId, $clangId, ['rex_version' => rex_article_revision::WORK]) . '" rel="noopener noreferrer" target="_blank">' . rex_i18n::msg('version_preview') . '</a></li>';
        }
    } else {
        if ($revision > 0) {
            if (!$workingVersionEmpty) {
                $toolbar .= '<li><a href="' . $context->getUrl(['rex_version_func' => 'clear_work'] + $csrfToken->getUrlParams()) . '" data-confirm="' . rex_i18n::msg('version_confirm_clear_workingversion') . '">' . rex_i18n::msg('version_clear_workingversion') . '</a></li>';
                $toolbar .= '<li><a href="' . $context->getUrl(['rex_version_func' => 'copy_work_to_live'] + $csrfToken->getUrlParams()) . '">' . rex_i18n::msg('version_working_to_live') . '</a></li>';
            }
            $toolbar .= '<li><a href="' . rex_getUrl($articleId, $clangId, ['rex_version' => rex_article_revision::WORK]) . '" rel="noopener noreferrer" target="_blank">' . rex_i18n::msg('version_preview') . '</a></li>';
        } else {
            $toolbar .= '<li><a href="' . $context->getUrl(['rex_version_func' => 'copy_live_to_work'] + $csrfToken->getUrlParams()) . '" data-confirm="' . rex_i18n::msg('version_confirm_copy_live_to_workingversion') . '">' . rex_i18n::msg('version_copy_live_to_workingversion') . '</a></li>';
        }
    }

    $inverse = rex_article_revision::WORK == $revision;
    $cssClass = rex_article_revision::WORK == $revision ? 'rex-state-inprogress' : 'rex-state-live';

    $return .= rex_view::toolbar('<ul class="nav navbar-nav">' . $toolbar . '</ul>', null, $cssClass, $inverse);

    return $return;
});
