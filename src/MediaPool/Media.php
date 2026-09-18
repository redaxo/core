<?php

namespace Redaxo\Core\MediaPool;

use Redaxo\Core\Base\InstanceListPoolTrait;
use Redaxo\Core\Base\InstancePoolTrait;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Language\Language;
use Redaxo\Core\MetaInfo\Field\MetaField;
use Redaxo\Core\Util\Formatter;

use function is_array;

/**
 * A medium of the media pool, seen through one language: the title and the translatable meta fields carry the values
 * of that language, everything else is shared between all languages.
 */
final class Media
{
    use InstanceListPoolTrait;
    use InstancePoolTrait;

    private function __construct(
        public readonly int $id,
        public readonly int $languageId,
        public readonly ?int $categoryId,
        public readonly string $fileName,
        public readonly string $originalFileName,
        public readonly ?string $type,
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

    /** @param int|null $languageId defaults to the current language */
    public static function get(string $name, ?int $languageId = null): ?self
    {
        if (!$name) {
            return null;
        }

        $languageId ??= Language::getCurrentId();

        return self::getInstance([$name, $languageId], static function () use ($name, $languageId): ?self {
            $mediaPath = Path::coreCache('mediapool/' . $name . '.media');

            $cache = File::getCache($mediaPath, []);
            if (!$cache) {
                MediaPoolCache::generate($name);
                $cache = File::getCache($mediaPath, []);
            }

            /** @var array<string, string|int|array<int, array<string, string|int|null>>|null>|null $cache */
            if (!$cache) {
                return null;
            }

            $translations = is_array($cache['translations'] ?? null) ? $cache['translations'] : [];
            unset($cache['translations']);
            /** @var array<string, string|int|null> $shared */
            $shared = $cache;
            /** @var array<string, string|int|null> $translation */
            $translation = $translations[$languageId] ?? [];
            $title = $translation['title'] ?? '';
            unset($translation['title']);

            $getAndUnset = static function (string $key) use (&$shared): mixed {
                $value = $shared[$key];
                unset($shared[$key]);
                return $value;
            };

            /** @psalm-suppress InvalidScalarArgument */
            return new self(
                $getAndUnset('id'),
                $languageId,
                $getAndUnset('category_id'),
                $getAndUnset('filename'),
                $getAndUnset('originalname'),
                $getAndUnset('filetype'),
                $getAndUnset('filesize'),
                $getAndUnset('width'),
                $getAndUnset('height'),
                (string) $title,
                $getAndUnset('createdate'),
                $getAndUnset('updatedate'),
                $getAndUnset('createuser'),
                $getAndUnset('updateuser'),
                [...$shared, ...$translation],
            );
        });
    }

    /** @param int|null $languageId defaults to the current language */
    public static function forId(int $mediaId, ?int $languageId = null): ?self
    {
        $media = Sql::factory();
        $media->setQuery('select filename from rex_media where id=?', [$mediaId]);

        if (1 != $media->getRows()) {
            return null;
        }
        return self::get((string) $media->getValue('filename'), $languageId);
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
                    MediaPoolCache::generateList(null);
                    $list = File::getCache($listPath);
                }

                /** @var list<string> */
                return $list;
            },
        );
    }

    /** Removes the cached instances of the medium in all languages. */
    public static function clearInstances(string $name): void
    {
        foreach (Language::getAllIds() as $languageId) {
            self::clearInstance([$name, $languageId]);
        }
    }

    public function getCategory(): ?MediaCategory
    {
        return null === $this->categoryId ? null : MediaCategory::get($this->categoryId);
    }

    public function getUrl(): string
    {
        $url = Extension::dispatch(new ExtensionPoint('MEDIA_URL_REWRITE', '', ['media' => $this]));
        return $url ?: Url::media($this->fileName);
    }

    public function getFormattedSize(): string
    {
        return Formatter::bytes($this->size);
    }

    public function getExtension(): string
    {
        return File::extension($this->fileName);
    }

    public function fileExists(): bool
    {
        return is_file(Path::media($this->fileName));
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
            'language_id' => $this->languageId,
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
            default => $this->additionalData[$value] ?? $this->additionalData[MetaField::COLUMN_PREFIX . $value] ?? null,
        };
    }

    /** Returns whether the element is permitted. */
    public function isPermitted(): bool
    {
        return (bool) Extension::dispatch(new ExtensionPoint('MEDIA_IS_PERMITTED', true, ['element' => $this]));
    }
}
