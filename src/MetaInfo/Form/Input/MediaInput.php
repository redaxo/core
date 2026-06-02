<?php

namespace Redaxo\Core\MetaInfo\Form\Input;

use Redaxo\Core\RexVar\MediaListVar;
use Redaxo\Core\RexVar\MediaVar;

use function Redaxo\Core\View\escape;

/**
 * @internal
 *
 * @extends AbstractInput<string>
 */
final class MediaInput extends AbstractInput
{
    private string $buttonId = '';
    private array $args = [];

    private bool $multiple = false;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param int $buttonId
     * @return void
     */
    public function setButtonId($buttonId)
    {
        $this->buttonId = 'METAINFO_' . $buttonId;
        $this->setAttribute('id', 'REX_MEDIA_' . $this->buttonId);
    }

    /**
     * @param int|null $categoryId
     * @return void
     */
    public function setCategoryId($categoryId)
    {
        $this->args['category'] = $categoryId;
    }

    /**
     * @param string $types
     * @return void
     */
    public function setTypes($types)
    {
        $this->args['types'] = $types;
    }

    /**
     * @param bool $preview
     * @return void
     */
    public function setPreview($preview = true)
    {
        $this->args['preview'] = $preview;
    }

    public function setMultiple(bool $multiple = true): void
    {
        $this->multiple = $multiple;
    }

    public function getHtml()
    {
        $buttonId = $this->buttonId;
        $value = escape($this->value);
        $name = $this->attributes['name'];
        $args = $this->args;

        if ($this->multiple) {
            $name .= '[]';
            return MediaListVar::getWidget($buttonId, $name, $value, $args);
        }
        return MediaVar::getWidget($buttonId, $name, $value, $args);
    }
}
