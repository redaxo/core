<?php

/**
 * @package redaxo\media-manager
 */
abstract class rex_effect_abstract
{
    /** @var rex_managed_media */
    public $media;

    /**
     * effekt parameter.
     *
     * @var array<string, mixed>
     */
    public $params = [];

    public function __construct() {}

    /** @return void */
    public function setMedia(rex_managed_media $media)
    {
        $this->media = $media;
    }

    /**
     * @param array<string, mixed> $params
     * @return void
     */
    public function setParams(array $params)
    {
        $this->params = $params;
    }

    /** @return void */
    abstract public function execute();

    /** @return string */
    public function getName()
    {
        return static::class;
    }

    /**
     * Returns a array of array items each describing a spec of a input field with which the enduser can configure parameters for this effect.
     *
     * Example:
     *     return [
     *       [
     *         'label' => rex_i18n::msg('...'),  -> a short user-friendly field label
     *         'notice' => rex_i18n::msg('...'), -> additional description.
     *         'name' => 'contrast',             -> name of your parameter. this will be the index within $this->params
     *         'type' => 'int',                  -> scalar storage type
     *         'default' => '',                  -> default value
     *       ],
     *       // ... the next input-field spec
     *     ];
     *
     * @return list<array{label: string, name: string, type: 'int'|'float'|'string'|'select'|'media', default?: mixed, notice?: string, prefix?: string, suffix?: string, attributes?: array, options?: array}>
     */
    public function getParams()
    {
        // implement me in your subclass.
        return [];
    }

    /**
     * @param GdImage $gdImage
     * @return void
     */
    protected function keepTransparent($gdImage)
    {
        $image = $this->media;

        if (!$image->formatSupportsTransparency()) {
            return;
        }

        if ('gif' !== $image->getFormat()) {
            imagealphablending($gdImage, false);
            imagesavealpha($gdImage, true);

            return;
        }

        $gdimage = $image->getImage();
        $colorTransparent = imagecolortransparent($gdimage);
        imagepalettecopy($gdimage, $gdImage);
        if ($colorTransparent > 0) {
            imagefill($gdImage, 0, 0, $colorTransparent);
            imagecolortransparent($gdImage, $colorTransparent);
        }
        imagetruecolortopalette($gdImage, true, 256);
    }
}
