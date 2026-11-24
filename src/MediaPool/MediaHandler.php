<?php

namespace Redaxo\Core\MediaPool;

use enshrined\svgSanitize\Sanitizer;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Exception\UserMessageException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\Language;
use Redaxo\Core\MediaPool\ExtensionPoint\MediaAdded;
use Redaxo\Core\MediaPool\ExtensionPoint\MediaAdding;
use Redaxo\Core\MediaPool\ExtensionPoint\MediaDeleted;
use Redaxo\Core\MediaPool\ExtensionPoint\MediaListQuery;
use Redaxo\Core\MediaPool\ExtensionPoint\MediaUpdated;
use Redaxo\Core\MetaInfo\MetaEntity;
use Redaxo\Core\MetaInfo\MetaValues;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\Util\Pager;
use Redaxo\Core\Util\Type;
use Symfony\Component\HttpFoundation\File\File as SymfonyFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use ValueError;

use function array_key_exists;
use function count;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function Redaxo\Core\View\escape;
use function strlen;

use const PATHINFO_EXTENSION;
use const UPLOAD_ERR_INI_SIZE;
use const UPLOAD_ERR_NO_FILE;

final class MediaHandler
{
    private const ORDER_BY = [
        'filename',
        'updatedate',
        'title',
    ];

    private function __construct() {}

    /**
     * Adds a file to the media pool, the file is moved into the media folder.
     *
     * @param array{file: SymfonyFile, category_id: int|null, title?: string} $data Media data, `file` is an
     *     {@see UploadedFile} for uploads, `category_id` is `null` for the root level, `title` is used for all languages
     * @param array<string, mixed> $meta meta values, keyed by the storage key of the field (see {@see MetaValues}),
     *     used for all languages
     * @param bool $subindexing Appends a counter to the filename if a media with that name already exists
     * @param list<string> $types Restrict the allowed file extensions to these types
     *
     * @throws UserMessageException
     */
    public static function add(array $data, array $meta = [], bool $subindexing = true, array $types = []): Media
    {
        $metaValues = new MetaValues(MetaEntity::Media, $meta);

        Type::requireKeys($data, 'file', 'category_id');

        $file = $data['file'];
        self::checkUpload($file);

        $originalName = self::originalName($file);
        $srcFile = $file->getPathname();

        if (!MediaPool::isAllowedExtension($originalName, $types)) {
            $warning = I18n::msg('pool_file_mediatype_not_allowed') . ' <code>' . escape(File::extension($originalName)) . '</code>';
            $allowedExtensions = MediaPool::getAllowedExtensions($types);
            $warning .= count($allowedExtensions) > 0
                    ? '<br />' . I18n::msg('pool_file_allowed_mediatypes') . ' <code>' . rtrim(implode('</code>, <code>', escape($allowedExtensions)), ', ') . '</code>'
                    : '<br />' . I18n::msg('pool_file_banned_mediatypes') . ' <code>' . rtrim(implode('</code>, <code>', escape(MediaPool::$blockedExtensions)), ', ') . '</code>';

            throw new UserMessageException($warning);
        }

        if (!MediaPool::isAllowedMimeType($srcFile, $originalName)) {
            $warning = I18n::msg('pool_file_mediatype_not_allowed') . ' <code>' . escape(File::extension($originalName)) . '</code> (<code>' . escape(File::mimeType($srcFile, $originalName) ?? 'unknown mime type') . '</code>)';
            throw new UserMessageException($warning);
        }

        $categoryId = $data['category_id'];
        $title = $data['title'] ?? '';

        $fileName = MediaPool::filename($originalName, $subindexing);
        $dstFile = Path::media($fileName);
        $type = File::mimeType($srcFile, $originalName);

        $errorMessage = Extension::dispatch(new MediaAdding(
            fileName: $fileName,
            originalFileName: $originalName,
            path: $srcFile,
            type: $type,
            title: $title,
            categoryId: $categoryId,
            isUpload: $file instanceof UploadedFile,
        ));
        if (null !== $errorMessage && '' !== $errorMessage) {
            throw new UserMessageException($errorMessage);
        }

        // preserve the perms of an existing file, otherwise use the umask-derived default
        $perm = is_file($dstFile) ? fileperms($dstFile) : false;

        self::moveMedia($srcFile, $dstFile, $type);

        // the uploaded temp file may carry restrictive perms (e.g. 0600), restore the preserved or umask-derived perms
        @chmod($dstFile, $perm ?: 0o666 & ~umask());

        $size = @getimagesize($dstFile);
        if ('' == $type && isset($size['mime'])) {
            $type = $size['mime'];
        }

        $saveObject = Sql::factory();
        $saveObject->setTable('rex_media');
        $saveObject->setValue('filetype', $type);
        $saveObject->setValue('filename', $fileName);
        $saveObject->setValue('originalname', $originalName);
        $saveObject->setValue('filesize', filesize($dstFile));

        if ($size) {
            $saveObject->setValue('width', $size[0]);
            $saveObject->setValue('height', $size[1]);
        }

        $saveObject->setValue('category_id', $categoryId);
        $saveObject->setValues($metaValues->forTable('rex_media'));
        $saveObject->addGlobalCreateFields();
        $saveObject->addGlobalUpdateFields();
        $saveObject->insert();
        $mediaId = $saveObject->getLastId();

        foreach (Language::getAllIds() as $languageId) {
            $translation = Sql::factory()
                ->setTable('rex_media_translation')
                ->setValues(['media_id' => $mediaId, 'language_id' => $languageId, 'title' => $title]);
            $translation->setValues($metaValues->forTable('rex_media_translation'));
            $translation->insert();
        }

        Media::clearInstances($fileName);
        MediaPoolCache::deleteList($categoryId);

        $media = Type::notNull(Media::get($fileName));

        Extension::dispatch(new MediaAdded($media));

        return $media;
    }

    /**
     * Edits a media: category and file for all languages, the title in the given language.
     *
     * @param array{category_id?: int|null, title?: string, file?: SymfonyFile} $data Media data, missing keys keep
     *     their value, `category_id` is `null` for the root level, `file` replaces the file (it is moved into the media
     *     folder and must have the same extension)
     * @param array<string, mixed> $meta meta values, keyed by the storage key of the field (see {@see MetaValues}),
     *     the translated ones are saved for the given language
     *
     * @throws UserMessageException
     */
    public static function edit(string $filename, int $languageId, array $data, array $meta = []): Media
    {
        $metaValues = new MetaValues(MetaEntity::Media, $meta);

        if ('' === $filename) {
            throw new InvalidArgumentException('Expecting Filename.');
        }

        $previous = Media::get($filename, $languageId);
        if (!$previous) {
            throw new UserMessageException(I18n::msg('pool_file_not_found'));
        }

        $saveObject = Sql::factory();
        $saveObject->setTable('rex_media');
        $saveObject->setWhere(['filename' => $filename]);
        if (array_key_exists('category_id', $data)) {
            $saveObject->setValue('category_id', $data['category_id']);
        }
        $saveObject->setValues($metaValues->forTable('rex_media'));

        if (isset($data['file'])) {
            $file = $data['file'];
            self::checkUpload($file);

            $originalName = self::originalName($file);
            $srcFile = $file->getPathname();
            $dstFile = Path::media($filename);
            $filetype = File::mimeType($srcFile, $originalName);

            $extensionNew = mb_strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $extensionOld = mb_strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (
                $extensionNew != $extensionOld
                && !(in_array($extensionNew, ['jpg', 'jpeg']) && in_array($extensionOld, ['jpg', 'jpeg']))
            ) {
                throw new UserMessageException(I18n::msg('pool_file_upload_errortype'));
            }

            if (!MediaPool::isAllowedMimeType($srcFile, $dstFile)) {
                $warning = I18n::msg('pool_file_mediatype_not_allowed') . ' <code>' . escape($extensionNew) . '</code> (<code>' . escape($filetype ?? 'unknown mime type') . '</code>)';
                throw new UserMessageException($warning);
            }

            // preserve the perms of an existing file, otherwise use the umask-derived default
            $perm = is_file($dstFile) ? fileperms($dstFile) : false;

            self::moveMedia($srcFile, $dstFile, $filetype);

            // the uploaded temp file may carry restrictive perms (e.g. 0600), restore the preserved or umask-derived perms
            @chmod($dstFile, $perm ?: 0o666 & ~umask());

            $saveObject->setValue('filetype', $filetype);
            $saveObject->setValue('filesize', filesize($dstFile));
            $saveObject->setValue('originalname', $originalName);

            if ($size = @getimagesize($dstFile)) {
                $saveObject->setValue('width', $size[0]);
                $saveObject->setValue('height', $size[1]);
            }
        }

        $translation = Sql::factory()
            ->setTable('rex_media_translation')
            ->setWhere(['media_id' => $previous->id, 'language_id' => $languageId]);
        if (array_key_exists('title', $data)) {
            $translation->setValue('title', $data['title']);
        }
        $translation->setValues($metaValues->forTable('rex_media_translation'));
        if ($translation->hasValues()) {
            $translation->update();
        }

        $saveObject->addGlobalUpdateFields();
        $saveObject->update();

        MediaPoolCache::delete($filename);

        $media = Type::notNull(Media::get($filename, $languageId));

        Extension::dispatch(new MediaUpdated($media, $previous));

        return $media;
    }

    /** @throws UserMessageException */
    public static function delete(string $filename): void
    {
        $media = Media::get($filename);
        if (!$media) {
            throw new UserMessageException(I18n::msg('pool_file_not_found', $filename));
        }

        $usages = MediaPool::findUsages($media);
        if (!$usages->isEmpty()) {
            throw new UserMessageException(I18n::msg('pool_file_delete_error', $filename) . ' ' . I18n::msg('pool_object_in_use_by') . $usages->render());
        }

        $sql = Sql::factory();
        $sql->setQuery('DELETE FROM rex_media WHERE filename = ? LIMIT 1', [$filename]);

        File::delete(Path::media($filename));
        MediaPoolCache::delete($filename);

        Extension::dispatch(new MediaDeleted($media));
    }

    /**
     * @param array{category_id?: int|null, category_id_path?: int, types?: list<string>, term?: string} $filter `category_id` is `null` for the root level
     * @param list<array{string, 'ASC'|'DESC'}> $orderBy
     * @return list<Media>
     */
    public static function getList(array $filter = [], array $orderBy = [], ?Pager $pager = null): array
    {
        $sql = Sql::factory();
        $where = [];
        $queryParams = ['language_id' => Language::getCurrentId()];
        $tables = [];
        $tables[] = 'rex_media AS m LEFT JOIN rex_media_translation AS l ON l.media_id = m.id AND l.language_id = :language_id';

        $counter = 0;
        foreach ($filter as $type => $value) {
            ++$counter;

            switch ($type) {
                case 'category_id':
                    if (null === $value || is_int($value)) {
                        $where[] = '(m.category_id <=> :search_' . $counter . ')';
                        $queryParams['search_' . $counter] = $value;
                    }
                    break;
                case 'category_id_path':
                    if (is_int($value)) {
                        $tables[] = 'rex_media_category AS c';
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
                        $where[] = '(m.filename LIKE :' . $param . ' || l.title LIKE :' . $param . ')';
                        $queryParams[$param] = '%' . $sql->escapeLikeWildcards($part) . '%';
                    }
                    break;
            }
        }

        $where = count($where) ? ' WHERE ' . implode(' AND ', $where) : '';
        $query = 'SELECT m.filename FROM ' . implode(',', $tables) . ' ' . $where;

        $orderbys = [];
        foreach ($orderBy as $orderByItem) {
            if (!is_array($orderByItem)) {
                continue;
            }
            if (!in_array($orderByItem[0], self::ORDER_BY, true)) {
                continue;
            }
            // column names can't be bound as parameters; they come from the allow list self::ORDER_BY
            $orderbys[] = ('title' === $orderByItem[0] ? 'l.' : 'm.') . $orderByItem[0] . ' ' . ('ASC' == $orderByItem[1] ? 'ASC' : 'DESC');
        }

        if (0 == count($orderbys)) {
            $orderbys[] = 'm.updatedate DESC';
        }

        if ($pager) {
            $countQuery = str_replace('SELECT m.filename', 'SELECT count(*)', $query);
            $ep = new MediaListQuery($countQuery, $queryParams, count: true);
            $countQuery = Extension::dispatch($ep);

            $sql->setQuery($countQuery, $ep->params);
            $pager->setRowCount($sql->getIntValue('count(*)'));
        }

        $query .= ' ORDER BY ' . implode(', ', $orderbys);

        if ($pager) {
            $query .= ' LIMIT ' . $pager->getCursor() . ',' . $pager->getRowsPerPage();
        }

        $ep = new MediaListQuery($query, $queryParams);
        $query = Extension::dispatch($ep);
        $queryParams = $ep->params;

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

    /** @throws UserMessageException */
    private static function checkUpload(SymfonyFile $file): void
    {
        if (!$file instanceof UploadedFile) {
            return;
        }

        $error = $file->getError();
        if (UPLOAD_ERR_INI_SIZE === $error) {
            throw new UserMessageException(I18n::msg('pool_file_upload_error_size', Formatter::bytes(Request::getMaxUploadSize())));
        }
        if (UPLOAD_ERR_NO_FILE === $error) {
            throw new UserMessageException(I18n::msg('pool_file_not_found'));
        }
        if (!$file->isValid()) {
            throw new UserMessageException(I18n::msg('pool_file_upload_error'));
        }
    }

    /** @throws UserMessageException */
    private static function originalName(SymfonyFile $file): string
    {
        $name = $file instanceof UploadedFile ? $file->getClientOriginalName() : $file->getFilename();
        if ('' === $name) {
            throw new UserMessageException(I18n::msg('pool_file_not_found'));
        }

        return $name;
    }

    /**
     * Sanitizes the file if necessary and moves it into the media folder.
     *
     * The file must never be moved before it has been sanitized, otherwise the unsanitized content would be publicly
     * available for a short time (or permanently, if sanitizing fails).
     *
     * @throws UserMessageException
     */
    private static function moveMedia(string $srcFile, string $dstFile, ?string $type): void
    {
        $sanitizedFile = self::sanitizeMedia($srcFile, $type, Path::basename($dstFile));

        if (!File::move($sanitizedFile ?? $srcFile, $dstFile)) {
            if (null !== $sanitizedFile) {
                File::delete($sanitizedFile);
            }

            throw new UserMessageException(I18n::msg('pool_file_movefailed'));
        }

        // the source file is left behind when the sanitized copy has been moved instead
        if (null !== $sanitizedFile && $srcFile !== $dstFile) {
            File::delete($srcFile);
        }
    }

    /**
     * Sanitizes the file into a temporary file, the source file is left untouched.
     *
     * @param string $filename Name of the target file, used to detect the file extension
     *
     * @throws UserMessageException
     *
     * @return string|null Path of the sanitized temp file or `null` if the file does not need sanitization
     */
    private static function sanitizeMedia(string $path, ?string $type, string $filename): ?string
    {
        if (!MediaPool::$sanitizeSvgs) {
            return null;
        }

        if ('image/svg+xml' !== $type && 'svg' !== strtolower(File::extension($filename))) {
            return null;
        }

        try {
            $content = new Sanitizer()->sanitize(Type::notNull(File::get($path)));
        } catch (ValueError) {
            // svg-sanitize 1.1.0 throws instead of returning `false` for some malformed input
            $content = false;
        }

        if (false === $content) {
            throw new UserMessageException(I18n::msg('pool_file_upload_error'));
        }

        // the temp file is placed inside the project to keep the final move to the media folder atomic
        $sanitizedFile = Path::coreCache('mediapool/sanitize/' . bin2hex(random_bytes(16)) . '.svg');

        if (!File::put($sanitizedFile, $content)) {
            throw new UserMessageException(I18n::msg('pool_file_movefailed'));
        }

        return $sanitizedFile;
    }
}
