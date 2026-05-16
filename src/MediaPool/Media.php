<?php

namespace Redaxo\Core\MediaPool;

use Redaxo\Core\Base\InstanceListPoolTrait;
use Redaxo\Core\Base\InstancePoolTrait;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Util\Formatter;

use function in_array;

/**
 * Bildet ein Medium des Medienpools ab.
 */
final class Media
{
    use InstanceListPoolTrait;
    use InstancePoolTrait;

    private function __construct(
        public readonly int $id,
        public readonly int $categoryId,
        public readonly string $fileName,
        public readonly string $originalFileName,
        public readonly string $type,
        public readonly int $size,
        public readonly ?int $width,
        public readonly ?int $height,
        public readonly string $title,
        public readonly int $createDate,
        public readonly int $updateDate,
        public readonly string $createUser,
        public readonly string $updateUser,
        /** @var array<string, string|int|null> */
        private readonly array $additionalData,
    ) {}

    public static function get(string $name): ?self
    {
        if (!$name) {
            return null;
        }

        return self::getInstance($name, static function () use ($name): ?self {
            $mediaPath = Path::coreCache('mediapool/' . $name . '.media');

            $cache = File::getCache($mediaPath, []);
            if (!$cache) {
                MediaPoolCache::generate($name);
                $cache = File::getCache($mediaPath, []);
            }

            /** @var array<string, string|int|null>|null $cache */
            if (!$cache) {
                return null;
            }

            $getAndUnset = static function (string $key) use (&$cache): mixed {
                $value = $cache[$key];
                unset($cache[$key]);
                return $value;
            };

            /** @psalm-suppress InvalidScalarArgument */
            return new self(
                $getAndUnset('id'),
                $getAndUnset('category_id'),
                $getAndUnset('filename'),
                $getAndUnset('originalname'),
                $getAndUnset('filetype'),
                $getAndUnset('filesize'),
                $getAndUnset('width'),
                $getAndUnset('height'),
                $getAndUnset('title'),
                $getAndUnset('createdate'),
                $getAndUnset('updatedate'),
                $getAndUnset('createuser'),
                $getAndUnset('updateuser'),
                $cache,
            );
        });
    }

    public static function forId(int $mediaId): ?self
    {
        $media = Sql::factory();
        $media->setQuery('select filename from ' . Core::getTable('media') . ' where id=?', [$mediaId]);

        if (1 != $media->getRows()) {
            return null;
        }
        return self::get((string) $media->getValue('filename'));
    }

    /** @return list<self> */
    public static function getRootMedia(): array
    {
        return self::getInstanceList(
            'root_media',
            static fn (string $name): ?self => self::get($name),
            static function (): array {
                $listPath = Path::coreCache('mediapool/0.mlist');

                $list = File::getCache($listPath, null);
                if (null === $list) {
                    MediaPoolCache::generateList(0);
                    $list = File::getCache($listPath);
                }

                /** @var list<string> */
                return $list;
            },
        );
    }

    public function getCategory(): ?MediaCategory
    {
        return MediaCategory::get($this->categoryId);
    }

    public function getUrl(): string
    {
        $url = Extension::registerPoint(new ExtensionPoint('MEDIA_URL_REWRITE', '', ['media' => $this]));
        return $url ?: Url::media($this->fileName);
    }

    public function getFormattedSize(): string
    {
        return Formatter::bytes($this->size);
    }

    public function isImage(): bool
    {
        return self::isImageType($this->getExtension());
    }

    public function getExtension(): string
    {
        return File::extension($this->fileName);
    }

    public function fileExists(): bool
    {
        return is_file(Path::media($this->fileName));
    }

    // allowed image upload types
    /** @return list<string> */
    public static function getImageTypes(): array
    {
        return Core::getProperty('image_extensions', []);
    }

    public static function isImageType(string $extension): bool
    {
        return in_array($extension, self::getImageTypes());
    }

    public function hasValue(string $value): bool
    {
        return null !== $this->getValue($value);
    }

    public function getValue(string $value): string|int|null
    {
        $value = strtolower($value);

        return match ($value) {
            'id' => $this->id,
            'category_id' => $this->categoryId,
            'name' => $this->fileName,
            'originalname' => $this->originalFileName,
            'type' => $this->type,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'title' => $this->title,
            'createdate' => $this->createDate,
            'updatedate' => $this->updateDate,
            'createuser' => $this->createUser,
            'updateuser' => $this->updateUser,
            default => $this->additionalData[$value] ?? $this->additionalData['med_' . $value] ?? null,
        };
    }

    /** Returns whether the element is permitted. */
    public function isPermitted(): bool
    {
        return (bool) Extension::registerPoint(new ExtensionPoint('MEDIA_IS_PERMITTED', true, ['element' => $this]));
    }
}
