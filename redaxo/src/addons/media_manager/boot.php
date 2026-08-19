<?php

/**
 * media_manager Addon.
 *
 * @author markus.staab[at]redaxo[dot]de Markus Staab
 * @author jan.kristinus[at]redaxo[dot]de Jan Kristinus
 */

rex_extension::register('PACKAGES_INCLUDED', [rex_media_manager::class, 'init'], rex_extension::EARLY);

// delete thumbnails on mediapool changes
rex_extension::register('MEDIA_UPDATED', [rex_media_manager::class, 'mediaUpdated']);
rex_extension::register('MEDIA_DELETED', [rex_media_manager::class, 'mediaUpdated']);
rex_extension::register('MEDIA_IS_IN_USE', [rex_media_manager::class, 'mediaIsInUse']);

// the href of the "clear cache" page is defined in package.yml, so the csrf token can only be added at runtime
rex_extension::register('PAGES_PREPARED', static function (rex_extension_point $ep) {
    /** @var array<string, rex_be_page> $pages */
    $pages = $ep->getSubject();
    $subpage = ($pages['media_manager'] ?? null)?->getSubpage('clear_cache');
    $subpage?->setHref(['page' => 'media_manager/types', 'func' => 'clear_cache'] + rex_csrf_token::factory('media_manager')->getUrlParams());
});
