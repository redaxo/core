<?php

use enshrined\svgSanitize\Sanitizer;

/**
 * @package redaxo\mediapool
 */
final class rex_media_service
{
    private const ORDER_BY = [
        'filename',
        'updatedate',
        'title',
    ];

    /**
     * Holt ein upgeloadetes File und legt es in den Medienpool
     * Dabei wird kontrolliert ob das File schon vorhanden ist und es
     * wird eventuell angepasst, weiterhin werden die Fileinformationen übergeben.
     *
     * @param array{category_id: int, title: string, file: array{name: string, path?: string, tmp_name?: string, error?: int}} $data
     * @param bool $doSubindexing // echte Dateinamen anpassen, falls schon vorhanden
     */
    public static function addMedia(array $data, bool $doSubindexing = true, array $allowedExtensions = []): array
    {
        $error = $data['file']['error'] ?? null;

        if (UPLOAD_ERR_INI_SIZE === $error) {
            throw new rex_api_exception(rex_i18n::msg('pool_file_upload_error_size', rex_formatter::bytes(rex_ini_get('upload_max_filesize'))));
        }
        if ($error) {
            throw new rex_api_exception(rex_i18n::msg('pool_file_upload_error'));
        }

        $data['file']['path'] ??= $data['file']['tmp_name'] ?? null;

        if (empty($data['file']) || empty($data['file']['name']) || empty($data['file']['path'])) {
            throw new rex_api_exception(rex_i18n::msg('pool_file_not_found'));
        }

        if (!rex_mediapool::isAllowedExtension($data['file']['name'], $allowedExtensions)) {
            $warning = rex_i18n::msg('pool_file_mediatype_not_allowed') . ' <code>' . rex_escape(rex_file::extension($data['file']['name'])) . '</code>';
            $allowedExtensions = rex_mediapool::getAllowedExtensions($allowedExtensions);
            $warning .= count($allowedExtensions) > 0
                    ? '<br />' . rex_i18n::msg('pool_file_allowed_mediatypes') . ' <code>' . implode('</code>, <code>', rex_escape($allowedExtensions)) . '</code>'
                    : '<br />' . rex_i18n::msg('pool_file_banned_mediatypes') . ' <code>' . implode('</code>, <code>', rex_escape(rex_mediapool::getBlockedExtensions())) . '</code>';

            throw new rex_api_exception($warning);
        }

        if (!rex_mediapool::isAllowedMimeType($data['file']['path'], $data['file']['name'])) {
            $warning = rex_i18n::msg('pool_file_mediatype_not_allowed') . ' <code>' . rex_escape(rex_file::extension($data['file']['name'])) . '</code> (<code>' . rex_escape(rex_file::mimeType($data['file']['path'])) . '</code>)';
            throw new rex_api_exception($warning);
        }

        $categoryId = (int) $data['category_id'];
        $title = (string) $data['title'];

        $data['file']['name_new'] = rex_mediapool::filename($data['file']['name'], $doSubindexing);

        // ----- alter/neuer filename
        $srcFile = $data['file']['path'];
        $dstFile = rex_path::media($data['file']['name_new']);

        $data['file']['type'] = rex_file::mimeType($srcFile);

        // Bevor die Datei engueltig in den Medienpool uebernommen wird, koennen
        // Addons ueber einen Extension-Point ein Veto einlegen.
        // Sobald ein Addon eine negative Entscheidung getroffen hat, sollten
        // Addons, fuer die der Extension-Point spaeter ausgefuehrt wird, diese
        // Entscheidung respektieren
        /** @var string|null $errorMessage */
        $errorMessage = null;
        $errorMessage = rex_extension::registerPoint(new rex_extension_point('MEDIA_ADD', $errorMessage, [
            'file' => $data['file'],
            'title' => $title,
            'filename' => $data['file']['name_new'],
            'old_filename' => $data['file']['name'],
            'is_upload' => !empty($data['file']['tmp_name']), // wir gehen davon aus, dass aus BC Gründen das tmp_name vorhanden sein muss wenn es ein upload ist.
            'category_id' => $categoryId,
            'type' => $data['file']['type'],
        ]));
        if ($errorMessage) {
            // ein Addon hat die Fehlermeldung gesetzt, dem Upload also faktisch widersprochen
            throw new rex_api_exception($errorMessage);
        }

        if (!rex_file::move($srcFile, $dstFile)) {
            throw new rex_api_exception(rex_i18n::msg('pool_file_movefailed'));
        }

        @chmod($dstFile, rex::getFilePerm());

        $size = @getimagesize($dstFile);
        if ('' == $data['file']['type'] && isset($size['mime'])) {
            $data['file']['type'] = $size['mime'];
        }

        self::sanitizeMedia($dstFile, $data['file']['type']);

        $saveObject = rex_sql::factory();
        $saveObject->setTable(rex::getTablePrefix() . 'media');
        $saveObject->setValue('filetype', $data['file']['type']);
        $saveObject->setValue('title', $title);
        $saveObject->setValue('filename', $data['file']['name_new']);
        $saveObject->setValue('originalname', $data['file']['name']);
        $saveObject->setValue('filesize', filesize($dstFile));

        if ($size) {
            $data['width'] = $size[0];
            $data['height'] = $size[1];
            $saveObject->setValue('width', $data['width']);
            $saveObject->setValue('height', $data['height']);
        }

        $saveObject->setValue('category_id', $categoryId);
        $saveObject->addGlobalCreateFields();
        $saveObject->addGlobalUpdateFields();
        $saveObject->insert();

        $message = [];

        $message[] = rex_i18n::msg('pool_file_added');

        if ($data['file']['name_new'] != $data['file']['name']) {
            $message[] = rex_i18n::rawMsg('pool_file_renamed', $data['file']['name'], $data['file']['name_new']);
        }

        $data['message'] = implode('<br />', $message);

        rex_media_cache::deleteList($categoryId);

        /**
         * @deprecated $return
         * in future only $data -> MEDIA_ADDED and return $data;
         */
        $return = $data;
        $return['type'] = $data['file']['type'];
        $return['msg'] = implode('<br />', $message);

        $return['filename'] = $data['file']['name_new'];
        $return['old_filename'] = $data['file']['name'];
        $return['ok'] = 1;

        rex_extension::registerPoint(new rex_extension_point('MEDIA_ADDED', '', $return));

        return $return;
    }

    /**
     * Holt ein upgeloadetes File und legt es in den Medienpool
     * Dabei wird kontrolliert ob das File schon vorhanden ist und es
     * wird eventuell angepasst, weiterhin werden die Fileinformationen übergeben.
     *
     * @param array{category_id: int, title: string, file?: array{name: string, path?: string, tmp_name?: string, error?: int}} $data
     */
    public static function updateMedia(string $filename, array $data): array
    {
        if ('' === $filename) {
            throw new rex_api_exception('Expecting Filename.');
        }

        $media = rex_media::get($filename);
        if (!$media) {
            throw new rex_api_exception(rex_i18n::msg('pool_file_not_found'));
        }

        $saveObject = rex_sql::factory();
        $saveObject->setTable(rex::getTablePrefix() . 'media');
        $saveObject->setWhere(['filename' => $filename]);
        $saveObject->setValue('title', $data['title']);
        $saveObject->setValue('category_id', (int) $data['category_id']);

        $file = $data['file'] ?? null;
        $filetype = null;

        if ($file && !empty($file['name'])) {
            $error = $file['error'] ?? null;

            if (UPLOAD_ERR_INI_SIZE === $error) {
                throw new rex_api_exception(rex_i18n::msg('pool_file_upload_error_size', rex_formatter::bytes(rex_ini_get('upload_max_filesize'))));
            }
            if ($error) {
                throw new rex_api_exception(rex_i18n::msg('pool_file_upload_error'));
            }

            $file['path'] = $file['tmp_name'] ?? null;
            if (empty($file['path'])) {
                throw new rex_api_exception(rex_i18n::msg('pool_file_not_found'));
            }

            $filetype = rex_file::mimeType($file['path']);

            $srcFile = $file['path'];
            $dstFile = rex_path::media($filename);

            $extensionNew = mb_strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $extensionOld = mb_strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (
                $extensionNew == $extensionOld
                || in_array($extensionNew, ['jpg', 'jpeg']) && in_array($extensionOld, ['jpg', 'jpeg'])
            ) {
                if (!rex_mediapool::isAllowedMimeType($srcFile, $dstFile)) {
                    $warning = rex_i18n::msg('pool_file_mediatype_not_allowed') . ' <code>' . rex_escape($extensionNew) . '</code> (<code>' . rex_escape($filetype ?? 'unknown mime type') . '</code>)';
                    throw new rex_api_exception($warning);
                }
                if (!rex_file::move($srcFile, $dstFile)) {
                    throw new rex_api_exception(rex_i18n::msg('pool_file_movefailed'));
                }

                @chmod($dstFile, rex::getFilePerm());

                self::sanitizeMedia($dstFile, $filetype);

                $saveObject->setValue('filetype', $filetype);
                $saveObject->setValue('filesize', filesize($dstFile));
                $saveObject->setValue('originalname', $file['name']);

                if ($size = @getimagesize($dstFile)) {
                    $saveObject->setValue('width', $size[0]);
                    $saveObject->setValue('height', $size[1]);
                }
                @chmod($dstFile, rex::getFilePerm());
            } else {
                throw new rex_api_exception(rex_i18n::msg('pool_file_upload_errortype'));
            }
        }

        $saveObject->addGlobalUpdateFields();
        $saveObject->update();

        rex_media_cache::delete($filename);

        /**
         * @deprecated $return
         * in future only $data -> MEDIA_UPDATED and return $data;
         */
        $return = $data;

        $return['ok'] = 1;
        $return['msg'] = rex_i18n::msg('pool_file_infos_updated');

        $return['id'] = $media->getId();
        $return['filename'] = $filename;
        $return['type'] = $filetype;
        $return['filetype'] = $filetype;

        rex_extension::registerPoint(new rex_extension_point('MEDIA_UPDATED', '', $return));

        return $return;
    }

    public static function deleteMedia(string $filename): void
    {
        $media = rex_media::get($filename);
        if (!$media) {
            throw new rex_api_exception(rex_i18n::msg('pool_file_not_found', $filename));
        }

        if ($uses = rex_mediapool::mediaIsInUse($filename)) {
            throw new rex_api_exception(rex_i18n::msg('pool_file_delete_error', $filename) . ' ' . rex_i18n::msg('pool_object_in_use_by') . $uses);
        }

        $sql = rex_sql::factory();
        $sql->setQuery('DELETE FROM ' . rex::getTable('media') . ' WHERE filename = ? LIMIT 1', [$filename]);

        rex_file::delete(rex_path::media($filename));
        rex_media_cache::delete($filename);

        rex_extension::registerPoint(new rex_extension_point('MEDIA_DELETED', '', [
            'filename' => $filename,
        ]));
    }

    /**
     * @param array{category_id?: int, category_id_path?: int, types?: list<string>, term?: string} $filter
     * @param list<array{string, 'ASC'|'DESC'}> $orderBy
     * @throws rex_sql_exception
     * @return list<rex_media>
     */
    public static function getList(array $filter = [], array $orderBy = [], ?rex_pager $pager = null): array
    {
        $sql = rex_sql::factory();
        $where = [];
        $queryParams = [];
        $tables = [];
        $tables[] = rex::getTable('media') . ' AS m';

        $counter = 0;
        foreach ($filter as $type => $value) {
            ++$counter;

            switch ($type) {
                case 'category_id':
                    if (is_int($value)) {
                        $where[] = '(m.category_id = :search_' . $counter . ')';
                        $queryParams['search_' . $counter] = $value;
                    }
                    break;
                case 'category_id_path':
                    if (is_int($value)) {
                        $tables[] = rex::getTable('media_category') . ' AS c';
                        $where[] = '(m.category_id = c.id AND (c.path LIKE "%|' . $value . '|%" OR c.id=' . $value . ') )';
                        $queryParams['search_' . $counter] = $value;
                    }
                    break;
                case 'types':
                    if (is_array($value) && $value) {
                        $where[] = 'LOWER(RIGHT(m.filename, LOCATE(".", REVERSE(m.filename))-1)) IN (' . $sql->in($value) . ')';
                    }
                    break;
                case 'term':
                    if (!is_string($value) || '' == $value) {
                        break;
                    }
                    foreach (str_getcsv(trim($value), ' ') as $i => $part) {
                        if (!$part) {
                            continue;
                        }
                        if (str_starts_with($part, 'type:') && strlen($part) > 5) {
                            $types = explode(',', strtolower(substr($part, 5)));
                            $where[] = 'LOWER(RIGHT(m.filename, LOCATE(".", REVERSE(m.filename))-1)) IN (' . $sql->in($types) . ')';

                            continue;
                        }

                        $param = "search_{$counter}_{$i}";
                        $where[] = '(m.filename LIKE :' . $param . ' || m.title LIKE :' . $param . ')';
                        $queryParams[$param] = '%' . $sql->escapeLikeWildcards($part) . '%';
                    }
                    break;
            }
        }

        $where = count($where) ? ' WHERE ' . implode(' AND ', $where) : '';
        $query = 'SELECT m.filename FROM ' . implode(',', $tables) . ' ' . $where;

        $orderbys = [];
        foreach ($orderBy as $index => $orderByItem) {
            if (!is_array($orderByItem)) {
                continue;
            }
            if (!in_array($orderByItem[0], self::ORDER_BY, true)) {
                continue;
            }
            $orderbys[] = ':orderby_' . $index . ' ' . ('ASC' == $orderByItem[1] ? 'ASC' : 'DESC');
            $queryParams['orderby_' . $index] = 'm.' . $orderByItem[0];
        }

        if (0 == count($orderbys)) {
            $orderbys[] = 'm.updatedate DESC';
        }

        if ($pager) {
            $countQuery = str_replace('SELECT m.filename', 'SELECT count(*)', $query);
            $countQueryParams = $queryParams;

            // EP is called twice, once for count query and once for data query
            $countQuery = rex_extension::registerPoint(new rex_extension_point('MEDIA_LIST_QUERY', $countQuery, [
                'queryParams' => &$countQueryParams,
            ]));
            assert(is_array($countQueryParams)); // @phpstan-ignore function.alreadyNarrowedType

            $sql->setQuery($countQuery, $countQueryParams);
            $pager->setRowCount((int) $sql->getValue('count(*)'));
        }

        $query .= ' ORDER BY ' . implode(', ', $orderbys);

        if ($pager) {
            $query .= ' LIMIT ' . $pager->getCursor() . ',' . $pager->getRowsPerPage();
        }

        // EP to modify the media list query
        $query = rex_extension::registerPoint(new rex_extension_point('MEDIA_LIST_QUERY', $query, [
            'queryParams' => &$queryParams,
        ]));
        assert(is_array($queryParams)); // @phpstan-ignore function.alreadyNarrowedType

        $items = [];

        /** @var array{filename: string} $media */
        foreach ($sql->getArray($query, $queryParams) as $media) {
            $mediaObject = rex_media::get($media['filename']);
            if (!$mediaObject) {
                throw new LogicException('Media "' . $media['filename'] . '" does not exist');
            }
            $items[] = $mediaObject;
        }

        return $items;
    }

    private static function sanitizeMedia(string $path, ?string $type): void
    {
        if (!rex_addon::require('mediapool')->getProperty('sanitize_svgs', true)) {
            return;
        }

        if ('image/svg+xml' !== $type && 'svg' !== strtolower(rex_file::extension($path))) {
            return;
        }

        $content = rex_type::notNull(rex_file::get($path));

        $content = (new Sanitizer())->sanitize($content);

        rex_file::put($path, $content);
    }
}
