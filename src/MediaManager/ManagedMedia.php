<?php

namespace Redaxo\Core\MediaManager;

use GdImage;
use Redaxo\Core\Core;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Http\Response;
use Redaxo\Core\MediaManager\Exception\MediaNotFoundException;
use Redaxo\Core\Util\Str;

use function array_key_exists;
use function assert;
use function function_exists;
use function in_array;
use function sprintf;

class ManagedMedia
{
    public const PROP_JPG_QUALITY = 'jpg_quality';
    public const PROP_PNG_COMPRESSION = 'png_compression';
    public const PROP_WEBP_QUALITY = 'webp_quality';
    public const PROP_AVIF_QUALITY = 'avif_quality';
    public const PROP_AVIF_SPEED = 'avif_speed';
    public const PROP_INTERLACE = 'interlace';

    private const MIMETYPE_MAP = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/vnd.wap.wbmp' => 'wbmp',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
    ];

    /** @var string|null */
    private $mediaPath = '';

    /** @var string */
    private $media;

    /** @var bool */
    private $asImage = false;

    /** @var array{width: ?int, height: ?int, src?: GdImage, ...<string, mixed>} */
    private $image = [
        'width' => null,
        'height' => null,
    ];

    /** @var array<string, string> */
    private $header = [];

    /** @var string */
    private $sourcePath;

    /** @var string */
    private $format;

    /** @param string $mediaPath */
    public function __construct($mediaPath)
    {
        $this->setMediaPath($mediaPath);
        $this->format = strtolower(File::extension($mediaPath));
    }

    /**
     * Returns the original path of the media.
     *
     * To get the current source path (can be changed by effects) use `getSourcePath` instead.
     *
     * @return string|null
     */
    public function getMediaPath()
    {
        return $this->mediaPath;
    }

    /**
     * @param string|null $mediaPath
     * @return void
     */
    public function setMediaPath($mediaPath)
    {
        $this->mediaPath = $mediaPath;

        if (null === $mediaPath) {
            return;
        }

        $this->media = Path::basename($mediaPath);
        $this->asImage = false;

        $this->sourcePath = $mediaPath;
    }

    /** @return string */
    public function getMediaFilename()
    {
        return $this->media;
    }

    /**
     * @param string $filename
     * @return void
     */
    public function setMediaFilename($filename)
    {
        $this->media = $filename;
    }

    /**
     * @param string $name
     * @param string $value
     * @return void
     */
    public function setHeader($name, $value)
    {
        $this->header[$name] = $value;
    }

    /** @return array<string, string> */
    public function getHeader()
    {
        return $this->header;
    }

    /**
     * @throws MediaNotFoundException
     * @return void
     */
    public function asImage()
    {
        if ($this->asImage) {
            return;
        }

        if (!$this->sourcePath || !is_file($this->sourcePath)) {
            throw new MediaNotFoundException(sprintf('Source path "%s" does not exist.', $this->sourcePath));
        }

        $this->image = [
            'width' => null,
            'height' => null,
        ];

        $format = $this->format;

        // if mimetype detected and in imagemap -> change format
        if ($ftype = File::mimeType($this->getSourcePath())) {
            if (array_key_exists($ftype, self::MIMETYPE_MAP)) {
                $format = self::MIMETYPE_MAP[$ftype];
            }
        }

        if ('jpg' == $format || 'jpeg' == $format) {
            $format = 'jpeg';
            $image = @imagecreatefromjpeg($this->getSourcePath());
        } elseif ('gif' == $format) {
            $image = @imagecreatefromgif($this->getSourcePath());
        } elseif ('wbmp' == $format) {
            $image = @imagecreatefromwbmp($this->getSourcePath());
        } elseif ('webp' == $format) {
            $image = false;
            if (function_exists('imagecreatefromwebp')) {
                $image = @imagecreatefromwebp($this->getSourcePath());
                imagealphablending($image, false);
                imagesavealpha($image, true);
            }
        } elseif ('avif' == $format) {
            $image = false;
            if (function_exists('imagecreatefromavif')) {
                $image = @imagecreatefromavif($this->getSourcePath());
                imagealphablending($image, false);
                imagesavealpha($image, true);
            }
        } else {
            $image = @imagecreatefrompng($this->getSourcePath());
            if ($image) {
                imagealphablending($image, false);
                imagesavealpha($image, true);
                $format = 'png';
            }
        }

        if (!$image) {
            throw new MediaNotFoundException(sprintf('Source path "%s" could not be converted to gd resource.', $this->sourcePath));
        }

        $this->image['src'] = $image;
        $this->asImage = true;
        $this->format = $format;

        $this->fixOrientation();
        $this->refreshImageDimensions();
    }

    /** @return void */
    public function refreshImageDimensions()
    {
        if ($this->asImage) {
            assert(isset($this->image['src']));
            $this->image['width'] = imagesx($this->image['src']);
            $this->image['height'] = imagesy($this->image['src']);

            return;
        }

        if ('jpeg' !== $this->format && !in_array($this->format, self::MIMETYPE_MAP)) {
            return;
        }

        $size = @getimagesize($this->sourcePath);
        $this->image['width'] = isset($size[0]) ? (int) $size[0] : null;
        $this->image['height'] = isset($size[1]) ? (int) $size[1] : null;
    }

    /** @return string */
    public function getFormat()
    {
        return $this->format;
    }

    public function formatSupportsTransparency(): bool
    {
        return in_array($this->format, ['gif', 'png', 'webp', 'avif'], true);
    }

    /**
     * @param string $format
     * @return void
     */
    public function setFormat($format)
    {
        $this->format = $format;
    }

    /**
     * @param string $sourceCachePath
     * @param string $headerCachePath
     * @param bool $save
     * @return void
     */
    public function sendMedia($sourceCachePath, $headerCachePath, $save = false)
    {
        $this->prepareHeaders();

        if ($this->asImage) {
            $src = $this->getSource();
            $this->setHeader('Content-Length', (string) Str::size($src));

            Response::cleanOutputBuffers();
            foreach ($this->header as $t => $c) {
                header($t . ': ' . $c);
            }

            echo $src;

            if ($save) {
                File::putCache($headerCachePath, [
                    'media_path' => $this->getMediaPath(),
                    'media_filename' => $this->getMediaFilename(),
                    'format' => $this->format,
                    'headers' => $this->header,
                ]);

                File::put($sourceCachePath, $src);
            }
        } else {
            $this->setHeader('Content-Length', (string) filesize($this->getSourcePath()));

            Response::cleanOutputBuffers();
            foreach ($this->header as $t => $c) {
                Response::setHeader($t, $c);
            }

            Response::sendFile($this->getSourcePath(), $this->header['Content-Type']);

            if ($save) {
                File::putCache($headerCachePath, [
                    'media_path' => $this->getMediaPath(),
                    'media_filename' => $this->getMediaFilename(),
                    'format' => $this->format,
                    'headers' => $this->header,
                ]);

                File::copy($this->getSourcePath(), $sourceCachePath);
            }
        }
    }

    /**
     * @param string $sourceCachePath
     * @param string $headerCachePath
     * @return void
     */
    public function save($sourceCachePath, $headerCachePath)
    {
        $src = $this->getSource();

        $this->prepareHeaders($src);
        $this->saveFiles($src, $sourceCachePath, $headerCachePath);
    }

    public function exists(): bool
    {
        return $this->asImage || is_file($this->sourcePath);
    }

    /** @return string */
    protected function getImageSource()
    {
        if (!isset($this->image['src'])) {
            throw new LogicException(__METHOD__ . ' can not be called without calling asImage() before.');
        }

        $format = $this->format;
        $format = 'jpeg' === $format ? 'jpg' : $format;

        $interlace = (array) $this->getImageProperty(self::PROP_INTERLACE, Core::getConfig('media_manager_interlace'));
        imageinterlace($this->image['src'], in_array($format, $interlace));

        ob_start();
        if ('jpg' == $format) {
            $quality = (int) $this->getImageProperty(self::PROP_JPG_QUALITY, Core::getConfig('media_manager_jpg_quality'));
            imagejpeg($this->image['src'], null, $quality);
        } elseif ('png' == $format) {
            $compression = (int) $this->getImageProperty(self::PROP_PNG_COMPRESSION, Core::getConfig('media_manager_png_compression'));
            imagepng($this->image['src'], null, $compression);
        } elseif ('gif' == $format) {
            imagegif($this->image['src']);
        } elseif ('wbmp' == $format) {
            imagewbmp($this->image['src']);
        } elseif ('webp' == $format) {
            $quality = (int) $this->getImageProperty(self::PROP_WEBP_QUALITY, Core::getConfig('media_manager_webp_quality'));
            imagewebp($this->image['src'], null, $quality);
        } elseif ('avif' == $format) {
            $quality = (int) $this->getImageProperty(self::PROP_AVIF_QUALITY, Core::getConfig('media_manager_avif_quality'));
            $speed = (int) $this->getImageProperty(self::PROP_AVIF_SPEED, Core::getConfig('media_manager_avif_speed'));
            imageavif($this->image['src'], null, $quality, $speed);
        }
        return ob_get_clean();
    }

    /** @return GdImage */
    public function getImage()
    {
        if (!isset($this->image['src'])) {
            throw new LogicException(__METHOD__ . ' can not be called without calling asImage() before.');
        }

        return $this->image['src'];
    }

    /**
     * @param GdImage $src
     * @return void
     */
    public function setImage($src)
    {
        $this->image['src'] = $src;
        $this->asImage = true;
    }

    /**
     * @param string $path
     * @return void
     */
    public function setSourcePath($path)
    {
        $this->sourcePath = $path;

        $this->asImage = false;
    }

    /**
     * Returns the current source path.
     *
     * To get the original media path use `getMediaPath()` instead.
     *
     * @return string
     */
    public function getSourcePath()
    {
        return $this->sourcePath;
    }

    /** @return string */
    public function getSource()
    {
        if ($this->asImage) {
            return $this->getImageSource();
        }

        return File::require($this->sourcePath);
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function setImageProperty($name, $value)
    {
        $this->image[$name] = $value;
    }

    /**
     * @param string $name
     * @param mixed $default
     * @return mixed|null
     */
    public function getImageProperty($name, $default = null)
    {
        return $this->image[$name] ?? $default;
    }

    /** @return int|null */
    public function getWidth()
    {
        return $this->image['width'];
    }

    /** @return int|null */
    public function getHeight()
    {
        return $this->image['height'];
    }

    /** @return void */
    private function fixOrientation()
    {
        if (!isset($this->image['src'])) {
            throw new LogicException(__METHOD__ . ' can not be called without calling asImage() before.');
        }

        if (!function_exists('exif_read_data')) {
            return;
        }
        // exif_read_data() only works on jpg/jpeg/tiff
        if (!in_array($this->getFormat(), ['jpg', 'jpeg', 'tiff'])) {
            return;
        }
        // suppress warning in case of corrupt/ missing exif data
        $exif = @exif_read_data($this->getSourcePath());

        if (!isset($exif['Orientation']) || !in_array($exif['Orientation'], [3, 6, 8])) {
            return;
        }

        switch ($exif['Orientation']) {
            case 8:
                $this->image['src'] = imagerotate($this->image['src'], 90, 0);
                break;
            case 3:
                $this->image['src'] = imagerotate($this->image['src'], 180, 0);
                break;
            case 6:
                $this->image['src'] = imagerotate($this->image['src'], -90, 0);
                break;
        }
    }

    /**
     * @param string|null $src Source content
     * @return void
     */
    private function prepareHeaders($src = null)
    {
        if (null !== $src) {
            $this->setHeader('Content-Length', (string) Str::size($src));
        }

        $header = $this->getHeader();
        if (!isset($header['Content-Type']) && $this->sourcePath) {
            $contentType = File::mimeType($this->sourcePath);

            if ($contentType) {
                $this->setHeader('Content-Type', $contentType);
            }
        }
        if (!isset($header['Content-Disposition'])) {
            $this->setHeader('Content-Disposition', 'inline; filename="' . $this->getMediaFilename() . '";');
        }
        if (!isset($header['Last-Modified'])) {
            $this->setHeader('Last-Modified', gmdate('D, d M Y H:i:s T'));
        }
    }

    /**
     * @param string $src Source content
     * @param string $sourceCachePath
     * @param string $headerCachePath
     * @return void
     */
    private function saveFiles($src, $sourceCachePath, $headerCachePath)
    {
        File::putCache($headerCachePath, [
            'media_path' => $this->getMediaPath(),
            'media_filename' => $this->getMediaFilename(),
            'format' => $this->format,
            'headers' => $this->header,
        ]);

        File::put($sourceCachePath, $src);
    }
}
