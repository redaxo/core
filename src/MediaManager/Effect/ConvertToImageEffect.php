<?php

namespace Redaxo\Core\MediaManager\Effect;

use Imagick;
use Redaxo\Core\Exception\RuntimeException;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Type;

use function function_exists;
use function in_array;
use function strlen;

use const PATHINFO_EXTENSION;

/**
 * Benutzt den Konsolen convert oder ffmpeg Befehl.
 */
class ConvertToImageEffect extends AbstractEffect
{
    private const CONVERT_TYPES = [
        'pdf',
        'ps',
        'psd',
        'tif',
        'tiff',
        'bmp',
        'eps',
        'ico',
        'svg',
    ];

    private const VIDEO_TO_IMAGE_TYPES = [
        'mp4',
        'm4v',
        'avi',
        'mov',
    ];

    private const CONVERT_TO = [
        'jpg' => [
            'ext' => 'jpg',
            'content-type' => 'image/jpeg',
        ],
        'png' => [
            'ext' => 'png',
            'content-type' => 'image/png',
        ],
    ];

    private const DENSITIES = [100, 150, 200, 300, 600];
    private const DENSITY_DEFAULT = 150;
    private const CONVERT_TOS = ['jpg', 'png'];
    private const CONVERT_TO_DEFAULT = 'jpg';

    public function execute()
    {
        if (!isset(self::CONVERT_TO[(string) $this->params['convert_to']])) {
            $convertTo = self::CONVERT_TO[self::CONVERT_TO_DEFAULT];
        } else {
            $convertTo = self::CONVERT_TO[(string) $this->params['convert_to']];
        }

        if ($this->isVideoToImageConversionSupported()) {
            $inputFile = Type::notNull($this->media->getMediaPath());

            // Try to get the duration of the video using ffprobe
            $ffprobeCmd = 'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($inputFile);
            $duration = exec($ffprobeCmd);

            if ($duration) {
                $timestamp = gmdate('H:i:s', (int) floor((float) $duration / 2));
            } else {
                $timestamp = '00:00:01';
            }

            $outputFile = Path::coreCache('media_manager/media_manager__convert2img_' . md5($inputFile) . '.' . $convertTo['ext']);

            $cmd = 'ffmpeg -y -i ' . escapeshellarg($inputFile) . ' -ss ' . escapeshellarg($timestamp) . ' -vframes 1 ' . escapeshellarg($outputFile);
            exec($cmd, $out, $ret);

            if (0 !== $ret) {
                throw new RuntimeException('Unable to exec command "' . $cmd . '".');
            }

            $this->media->setSourcePath($outputFile);
            $this->media->refreshImageDimensions();
            $this->media->setFormat($convertTo['ext']);
            $this->media->setHeader('Content-Type', $convertTo['content-type']);
            $filename = $this->media->getMediaFilename();
            $this->media->setMediaFilename($filename);
            register_shutdown_function(static function () use ($outputFile) {
                File::delete($outputFile);
            });
            return;
        }

        $density = (int) $this->params['density'];

        $color = $this->params['color'] ?? '';

        if (!in_array($density, self::DENSITIES)) {
            $density = self::DENSITY_DEFAULT;
        }

        $fromPath = realpath($this->media->getMediaPath());
        $ext = File::extension($fromPath);

        if (!$ext) {
            return;
        }

        if (!in_array(strtolower($ext), self::CONVERT_TYPES)) {
            return;
        }

        if (class_exists(Imagick::class)) {
            $imagick = new Imagick();
            $imagick->setResolution($density, $density);
            $imagick->readImage($fromPath . '[0]');

            if ('' != $color) {
                $imagick->setImageBackgroundColor($color);
                $imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
                $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            }

            $imagick->transformImageColorspace(Imagick::COLORSPACE_RGB);
            $imagick->setImageFormat($convertTo['ext']);

            $gd = imagecreatefromstring($imagick->getImageBlob());

            $this->media->setImage($gd);
            $this->media->setFormat($convertTo['ext']);
            $this->media->setHeader('Content-Type', $convertTo['content-type']);
            $this->media->refreshImageDimensions();

            return;
        }

        $convertPath = self::getConvertPath();

        if ('' == $convertPath) {
            return;
        }

        $filename = $this->media->getMediaFilename();
        $filenameWoExt = substr($filename, 0, strlen($filename) - strlen($ext));

        $toPath = Path::coreCache('media_manager/media_manager__convert2img_' . md5($this->media->getMediaPath()) . '_' . $filenameWoExt . $convertTo['ext']);

        $addColor = '' != $color ? ' -background ' . escapeshellarg($color) . ' -flatten' : '';

        $cmd = $convertPath . ' -density ' . $density . ' ' . escapeshellarg($fromPath . '[0]') . '  ' . $addColor . ' -colorspace RGB ' . escapeshellarg($toPath);
        exec($cmd, $out, $ret);

        if (0 != $ret) {
            if ($error = error_get_last()) {
                throw new RuntimeException('Unable to exec command "' . $cmd . '": ' . $error['message']);
            }
            throw new RuntimeException('Unable to exec command "' . $cmd . '".');
        }

        $this->media->setSourcePath($toPath);
        $this->media->refreshImageDimensions();
        $this->media->setFormat($convertTo['ext']);
        $this->media->setMediaFilename($filename);
        $this->media->setHeader('Content-Type', $convertTo['content-type']);

        register_shutdown_function(static function () use ($toPath) {
            File::delete($toPath);
        });
    }

    public function getName()
    {
        return I18n::msg('media_manager_effect_convert2img');
    }

    public function getParams()
    {
        $notSupported = [];
        if (!class_exists(Imagick::class) && '' == self::getConvertPath()) {
            $notSupported[] = '<strong>' . I18n::msg('media_manager_effect_convert2img_noimagemagick') . '</strong> ';
        }

        if (!$this->isFfmpegAvailable()) {
            $notSupported[] = '<strong>' . I18n::msg('media_manager_effect_convert2img_videoconverternotfound') . '</strong> ';
        }

        return [
            [
                'label' => I18n::msg('media_manager_effect_convert2img_convertto'),
                'name' => 'convert_to',
                'type' => 'select',
                'options' => self::CONVERT_TOS,
                'default' => self::CONVERT_TO_DEFAULT,
                'prefix' => implode('<br>', $notSupported),
                'notice' => I18n::msg('media_manager_effect_convert2img_convertto_notice'),
            ],
            [
                'label' => I18n::msg('media_manager_effect_convert2img_density'),
                'name' => 'density',
                'type' => 'select',
                'options' => self::DENSITIES,
                'default' => self::DENSITY_DEFAULT,
                'notice' => I18n::msg('media_manager_effect_convert2img_density_notice'),
            ],
            [
                'label' => I18n::msg('media_manager_effect_convert2img_color'),
                'name' => 'color',
                'type' => 'int',
                'notice' => I18n::msg('media_manager_effect_convert2img_color_notice'),
            ],
        ];
    }

    /** @return string */
    private function getConvertPath()
    {
        $path = '';

        if (function_exists('exec')) {
            $out = [];
            $cmd = 'command -v convert || which convert';
            exec($cmd, $out, $ret);

            if (0 === $ret) {
                $path = (string) $out[0];
            }
        }
        return $path;
    }

    private function isVideoToImageConversionSupported(): bool
    {
        $inputFile = $this->media->getMediaPath();

        if (null === $inputFile) {
            return false;
        }

        $inputExt = pathinfo($inputFile, PATHINFO_EXTENSION);

        if ($this->isFfmpegAvailable()) {
            return in_array($inputExt, self::VIDEO_TO_IMAGE_TYPES);
        }
        return false;
    }

    private function isFfmpegAvailable(): bool
    {
        if (!function_exists('exec')) {
            return false;
        }
        $ffmpegPath = 'ffmpeg'; // change to full path if necessary
        $output = [];
        $returnVar = -1;

        exec($ffmpegPath . ' -version', $output, $returnVar);
        return 0 === $returnVar;
    }
}
