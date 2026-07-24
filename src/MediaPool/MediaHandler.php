<?php

namespace Redaxo\Core\MediaPool;

use enshrined\svgSanitize\Sanitizer;
use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\Util\Pager;
use Redaxo\Core\Util\Type;

use function assert;
use function count;
use function in_array;
use function ini_get;
use function is_array;
use function is_int;
use function is_string;
use function Redaxo\Core\View\escape;
use function strlen;

use const PATHINFO_EXTENSION;
use const UPLOAD_ERR_INI_SIZE;

final class MediaHandler
{
    private const ORDER_BY = [
        'filename',
        'updatedate',
        'title',
    ];

    private function __construct() {}

    /**
     * Holt ein upgeloadetes File und legt es in den Medienpool
     * Dabei wird kontrolliert ob das File schon vorhanden ist und es
     * wird eventuell angepasst, weiterhin werden die Fileinformationen übergeben.
     *
     * @param array{category_id: int, title: string, file: array{name: string, path?: string, tmp_name?: string, error?: int}} $data
     * @param bool $doSubindexing // echte Dateinamen anpassen, falls schon vorhanden
     * @param list<string> $types Restrict the allowed file extensions to these types
     */
    public static function addMedia(array $data, bool $doSubindexing = true, array $types = []): array
    {
        $error = $data['file']['error'] ?? null;

        if (UPLOAD_ERR_INI_SIZE === $error) {
            throw new ApiFunctionException(I18n::msg('pool_file_upload_error_size', Formatter::bytes(ini_get('upload_max_filesize'))));
        }
        if ($error) {
            throw new ApiFunctionException(I18n::msg('pool_file_upload_error'));
        }

        $data['file']['path'] ??= $data['file']['tmp_name'] ?? null;

        if (empty($data['file']) || empty($data['file']['name']) || empty($data['file']['path'])) {
            throw new ApiFunctionException(I18n::msg('pool_file_not_found'));
        }

        if (!MediaPool::isAllowedExtension($data['file']['name'], $types)) {
            $warning = I18n::msg('pool_file_mediatype_not_allowed') . ' <code>' . escape(File::extension($data['file']['name'])) . '</code>';
            $allowedExtensions = MediaPool::getAllowedExtensions($types);
            $warning .= count($allowedExtensions) > 0
                    ? '<br />' . I18n::msg('pool_file_allowed_mediatypes') . ' <code>' . rtrim(implode('</code>, <code>', escape($allowedExtensions)), ', ') . '</code>'
                    : '<br />' . I18n::msg('pool_file_banned_mediatypes') . ' <code>' . rtrim(implode('</code>, <code>', escape(MediaPool::$blockedExtensions)), ', ') . '</code>';

            throw new ApiFunctionException($warning);
        }

        if (!MediaPool::isAllowedMimeType($data['file']['path'], $data['file']['name'])) {
            $warning = I18n::msg('pool_file_mediatype_not_allowed') . ' <code>' . escape(File::extension($data['file']['name'])) . '</code> (<code>' . escape(File::mimeType($data['file']['path'])) . '</code>)';
            throw new ApiFunctionException($warning);
        }

        $categoryId = (int) $data['category_id'];
        $title = (string) $data['title'];

        $data['file']['name_new'] = MediaPool::filename($data['file']['name'], $doSubindexing);

        // ----- alter/neuer filename
        $srcFile = $data['file']['path'];
        $dstFile = Path::media($data['file']['name_new']);

        $data['file']['type'] = File::mimeType($srcFile);

        // Bevor die Datei engueltig in den Medienpool uebernommen wird, koennen
        // Addons ueber einen Extension-Point ein Veto einlegen.
        // Sobald ein Addon eine negative Entscheidung getroffen hat, sollten
        // Addons, fuer die der Extension-Point spaeter ausgefuehrt wird, diese
        // Entscheidung respektieren
        /** @var string|null $errorMessage */
        $errorMessage = null;
        $errorMessage = Extension::dispatch(new ExtensionPoint('MEDIA_ADD', $errorMessage, [
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
            throw new ApiFunctionException($errorMessage);
        }

        // preserve the perms of an existing file, otherwise use the umask-derived default
        $perm = is_file($dstFile) ? fileperms($dstFile) : false;

        if (!File::move($srcFile, $dstFile)) {
            throw new ApiFunctionException(I18n::msg('pool_file_movefailed'));
        }

        // the uploaded temp file may carry restrictive perms (e.g. 0600), restore the preserved or umask-derived perms
        @chmod($dstFile, $perm ?: 0o666 & ~umask());

        $size = @getimagesize($dstFile);
        if ('' == $data['file']['type'] && isset($size['mime'])) {
            $data['file']['type'] = $size['mime'];
        }

        self::sanitizeMedia($dstFile, $data['file']['type']);

        $saveObject = Sql::factory();
        $saveObject->setTable(Core::getTablePrefix() . 'media');
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

        $message[] = I18n::msg('pool_file_added');

        if ($data['file']['name_new'] != $data['file']['name']) {
            $message[] = I18n::rawMsg('pool_file_renamed', $data['file']['name'], $data['file']['name_new']);
        }

        $data['message'] = implode('<br />', $message);

        MediaPoolCache::deleteList($categoryId);

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

        Extension::dispatch(new ExtensionPoint('MEDIA_ADDED', '', $return));

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
            throw new ApiFunctionException('Expecting Filename.');
        }

        $media = Media::get($filename);
        if (!$media) {
            throw new ApiFunctionException(I18n::msg('pool_file_not_found'));
        }

        $saveObject = Sql::factory();
        $saveObject->setTable(Core::getTablePrefix() . 'media');
        $saveObject->setWhere(['filename' => $filename]);
        $saveObject->setValue('title', $data['title']);
        $saveObject->setValue('category_id', (int) $data['category_id']);

        $file = $data['file'] ?? null;
        $filetype = null;

        if ($file && !empty($file['name'])) {
            $error = $file['error'] ?? null;

            if (UPLOAD_ERR_INI_SIZE === $error) {
                throw new ApiFunctionException(I18n::msg('pool_file_upload_error_size', Formatter::bytes(ini_get('upload_max_filesize'))));
            }
            if ($error) {
                throw new ApiFunctionException(I18n::msg('pool_file_upload_error'));
            }

            $file['path'] = $file['tmp_name'] ?? null;
            if (empty($file['path'])) {
                throw new ApiFunctionException(I18n::msg('pool_file_not_found'));
            }

            $filetype = File::mimeType($file['path']);

            $srcFile = $file['path'];
            $dstFile = Path::media($filename);

            $extensionNew = mb_strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $extensionOld = mb_strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (
                $extensionNew == $extensionOld
                || in_array($extensionNew, ['jpg', 'jpeg']) && in_array($extensionOld, ['jpg', 'jpeg'])
            ) {
                if (!MediaPool::isAllowedMimeType($srcFile, $dstFile)) {
                    $warning = I18n::msg('pool_file_mediatype_not_allowed') . ' <code>' . escape($extensionNew) . '</code> (<code>' . escape($filetype ?? 'unknown mime type') . '</code>)';
                    throw new ApiFunctionException($warning);
                }
                // preserve the perms of an existing file, otherwise use the umask-derived default
                $perm = is_file($dstFile) ? fileperms($dstFile) : false;

                if (!File::move($srcFile, $dstFile)) {
                    throw new ApiFunctionException(I18n::msg('pool_file_movefailed'));
                }

                // the uploaded temp file may carry restrictive perms (e.g. 0600), restore the preserved or umask-derived perms
                @chmod($dstFile, $perm ?: 0o666 & ~umask());

                self::sanitizeMedia($dstFile, $filetype);

                $saveObject->setValue('filetype', $filetype);
                $saveObject->setValue('filesize', filesize($dstFile));
                $saveObject->setValue('originalname', $file['name']);

                if ($size = @getimagesize($dstFile)) {
                    $saveObject->setValue('width', $size[0]);
                    $saveObject->setValue('height', $size[1]);
                }
            } else {
                throw new ApiFunctionException(I18n::msg('pool_file_upload_errortype'));
            }
        }

        $saveObject->addGlobalUpdateFields();
        $saveObject->update();

        MediaPoolCache::delete($filename);

        /**
         * @deprecated $return
         * in future only $data -> MEDIA_UPDATED and return $data;
         */
        $return = $data;

        $return['ok'] = 1;
        $return['msg'] = I18n::msg('pool_file_infos_updated');

        $return['id'] = $media->id;
        $return['filename'] = $filename;
        $return['type'] = $filetype;
        $return['filetype'] = $filetype;

        Extension::dispatch(new ExtensionPoint('MEDIA_UPDATED', '', $return));

        return $return;
    }

    public static function deleteMedia(string $filename): void
    {
        $media = Media::get($filename);
        if (!$media) {
            throw new ApiFunctionException(I18n::msg('pool_file_not_found', $filename));
        }

        if ($uses = MediaPool::mediaIsInUse($filename)) {
            throw new ApiFunctionException(I18n::msg('pool_file_delete_error', $filename) . ' ' . I18n::msg('pool_object_in_use_by') . $uses);
        }

        $sql = Sql::factory();
        $sql->setQuery('DELETE FROM ' . Core::getTable('media') . ' WHERE filename = ? LIMIT 1', [$filename]);

        File::delete(Path::media($filename));
        MediaPoolCache::delete($filename);

        Extension::dispatch(new ExtensionPoint('MEDIA_DELETED', '', [
            'filename' => $filename,
        ]));
    }

    /**
     * @param array{category_id?: int, category_id_path?: int, types?: list<string>, term?: string} $filter
     * @param list<array{string, 'ASC'|'DESC'}> $orderBy
     * @return list<Media>
     */
    public static function getList(array $filter = [], array $orderBy = [], ?Pager $pager = null): array
    {
        $sql = Sql::factory();
        $where = [];
        $queryParams = [];
        $tables = [];
        $tables[] = Core::getTable('media') . ' AS m';

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
                        $tables[] = Core::getTable('media_category') . ' AS c';
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
                    foreach (str_getcsv(trim($value), ' ', '"', '') as $i => $part) {
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
            $countQuery = Extension::dispatch(new ExtensionPoint('MEDIA_LIST_QUERY', $countQuery, [
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
        $query = Extension::dispatch(new ExtensionPoint('MEDIA_LIST_QUERY', $query, [
            'queryParams' => &$queryParams,
        ]));
        assert(is_array($queryParams)); // @phpstan-ignore function.alreadyNarrowedType

        $items = [];

        /** @var array{filename: string} $media */
        foreach ($sql->getArray($query, $queryParams) as $media) {
            $mediaObject = Media::get($media['filename']);
            if (!$mediaObject) {
                throw new LogicException('Media "' . $media['filename'] . '" does not exist');
            }
            $items[] = $mediaObject;
        }

        return $items;
    }

    private static function sanitizeMedia(string $path, ?string $type): void
    {
        if (!MediaPool::$sanitizeSvgs) {
            return;
        }

        if ('image/svg+xml' !== $type && 'svg' !== strtolower(File::extension($path))) {
            return;
        }

        $content = Type::notNull(File::get($path));

        $content = new Sanitizer()->sanitize($content);

        File::put($path, $content);
    }
}
