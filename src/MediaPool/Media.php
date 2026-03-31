<?php

namespace Redaxo\Core\MediaPool;

use AllowDynamicProperties;
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
use function Redaxo\Core\View\escape;
use function sprintf;

/**
 * Object Oriented Framework: Bildet ein Medium des Medienpools ab.
 */
#[AllowDynamicProperties]
class Media
{
    use InstanceListPoolTrait;
    use InstancePoolTrait;

    /** @var int */
    protected $id;
    /** @var int */
    protected $category_id;

    /** @var string */
    protected $name = '';
    /** @var string */
    protected $originalname = '';
    /** @var string */
    protected $type = '';
    /** @var int */
    protected $size;

    /** @var int|null */
    protected $width;
    /** @var int|null */
    protected $height;

    /** @var string */
    protected $title = '';

    /** @var int */
    protected $updatedate;
    /** @var int */
    protected $createdate;

    /** @var string */
    protected $updateuser = '';
    /** @var string */
    protected $createuser = '';

    public static function get(string $name): ?static
    {
        if (!$name) {
            return null;
        }

        return static::getInstance($name, static function () use ($name) {
            $mediaPath = Path::coreCache('mediapool/' . $name . '.media');

            $cache = File::getCache($mediaPath, []);
            if (!$cache) {
                MediaPoolCache::generate($name);
                $cache = File::getCache($mediaPath, []);
            }

            if ($cache) {
                $aliasMap = [
                    'filename' => 'name',
                    'filetype' => 'type',
                    'filesize' => 'size',
                ];

                $media = new static();
                foreach ($cache as $key => $value) {
                    if (isset($aliasMap[$key])) {
                        $varName = $aliasMap[$key];
                    } else {
                        $varName = $key;
                    }

                    $media->$varName = match ($varName) {
                        'id', 'category_id', 'size', 'createdate', 'updatedate' => (int) $value,
                        'width', 'height' => null === $value ? $value : (int) $value,
                        default => $value,
                    };
                }

                return $media;
            }

            return null;
        });
    }

    /**
     * @return static|null
     */
    public static function forId(int $mediaId): ?self
    {
        $media = Sql::factory();
        $media->setQuery('select filename from ' . Core::getTable('media') . ' where id=?', [$mediaId]);

        if (1 != $media->getRows()) {
            return null;
        }
        return static::get((string) $media->getValue('filename'));
    }

    /** @return list<static> */
    public static function getRootMedia(): array
    {
        return static::getInstanceList(
            'root_media',
            static fn (string $name): ?static => static::get($name),
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

    /** @return int */
    public function getId()
    {
        return $this->id;
    }

    /** @return MediaCategory|null */
    public function getCategory()
    {
        return MediaCategory::get($this->getCategoryId());
    }

    /** @return int */
    public function getCategoryId()
    {
        return $this->category_id;
    }

    /** @return string */
    public function getTitle()
    {
        return $this->title;
    }

    /** @return string */
    public function getFileName()
    {
        return $this->name;
    }

    /** @return string */
    public function getOriginalFileName()
    {
        return $this->originalname;
    }

    /** @return string */
    public function getUrl()
    {
        $url = Extension::registerPoint(new ExtensionPoint('MEDIA_URL_REWRITE', '', ['media' => $this]));
        return $url ?: Url::media($this->getFileName());
    }

    /** @return int|null */
    public function getWidth()
    {
        return $this->width;
    }

    /** @return int|null */
    public function getHeight()
    {
        return $this->height;
    }

    /** @return string */
    public function getType()
    {
        return $this->type;
    }

    /** @return int */
    public function getSize()
    {
        return $this->size;
    }

    /** @return string */
    public function getFormattedSize()
    {
        return Formatter::bytes($this->getSize());
    }

    /** @return string */
    public function getUpdateUser()
    {
        return $this->updateuser;
    }

    /** @return int */
    public function getUpdateDate()
    {
        return $this->updatedate;
    }

    /** @return string */
    public function getCreateUser()
    {
        return $this->createuser;
    }

    /** @return int */
    public function getCreateDate()
    {
        return $this->createdate;
    }

    /** @return string */
    public function toImage(array $params = [])
    {
        if (!$this->isImage()) {
            return '';
        }

        $filename = Url::media($this->getFileName());
        $title = $this->getTitle();

        if (!isset($params['alt'])) {
            if ('' != $title) {
                $params['alt'] = escape($title);
            }
        }

        if (!isset($params['title'])) {
            if ('' != $title) {
                $params['title'] = escape($title);
            }
        }

        Extension::registerPoint(new ExtensionPoint('MEDIA_TOIMAGE', '', ['filename' => &$filename, 'params' => &$params]));

        $additional = '';
        foreach ($params as $name => $value) {
            $additional .= ' ' . $name . '="' . $value . '"';
        }

        return sprintf('<img src="%s"%s />', $filename, $additional);
    }

    /**
     * @param string $attributes
     *
     * @return string
     */
    public function toLink($attributes = '')
    {
        return sprintf('<a href="%s" title="%s"%s>%s</a>', $this->getUrl(), $this->getValue('med_description'), $attributes, $this->getFileName());
    }

    /** @return bool */
    public function isImage()
    {
        return self::isImageType($this->getExtension());
    }

    // new functions by vscope

    /** @return string */
    public function getExtension()
    {
        return File::extension($this->name);
    }

    /** @return bool */
    public function fileExists()
    {
        return is_file(Path::media($this->getFileName()));
    }

    // allowed filetypes
    /** @return list<string> */
    public static function getDocTypes()
    {
        return Core::getProperty('allowed_doctypes', []);
    }

    /** @return bool */
    public static function isDocType($type)
    {
        return in_array($type, self::getDocTypes());
    }

    // allowed image upload types
    /** @return list<string> */
    public static function getImageTypes()
    {
        return Core::getProperty('image_extensions', []);
    }

    /** @return bool */
    public static function isImageType($extension)
    {
        return in_array($extension, self::getImageTypes());
    }

    /** @return bool */
    public function hasValue($value)
    {
        return isset($this->$value) || isset($this->{'med_' . $value});
    }

    /** @return string|int|null */
    public function getValue($value)
    {
        // damit alte rex_media felder wie copyright, description
        // noch funktionieren
        if (isset($this->$value)) {
            return $this->$value;
        }
        if (isset($this->{'med_' . $value})) {
            return $this->getValue('med_' . $value);
        }

        return null;
    }

    /**
     * Returns whether the element is permitted.
     *
     * @return bool
     */
    public function isPermitted()
    {
        return (bool) Extension::registerPoint(new ExtensionPoint('MEDIA_IS_PERMITTED', true, ['element' => $this]));
    }
}
