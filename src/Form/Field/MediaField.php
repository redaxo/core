<?php

namespace Redaxo\Core\Form\Field;

use Redaxo\Core\Form\AbstractForm;
use Redaxo\Core\RexVar\MediaListVar;
use Redaxo\Core\RexVar\MediaVar;

class MediaField extends BaseField
{
    private ?int $categoryId = null;

    /** @var list<string> */
    private array $types = [];

    private bool $preview = false;

    private bool $multiple = false;

    // 1. Parameter nicht genutzt, muss aber hier stehen,
    // wg einheitlicher Konstrukturparameter
    /**
     * @param string $tag
     * @param array<string, int|string> $attributes
     */
    public function __construct($tag = '', ?AbstractForm $form = null, array $attributes = [])
    {
        parent::__construct('', $form, $attributes);

        if ($this->hasAttribute('multiple')) {
            $this->setMultiple();
        }
    }

    /**
     * @param int $categoryId
     *
     * @return void
     */
    public function setCategoryId($categoryId)
    {
        $this->categoryId = $categoryId;
    }

    /** @param list<string> $types file extensions */
    public function setTypes(array $types): void
    {
        $this->types = $types;
    }

    /**
     * @param bool $preview
     * @return void
     */
    public function setPreview($preview = true)
    {
        $this->preview = $preview;
    }

    public function setMultiple(bool $multiple = true): void
    {
        $this->multiple = $multiple;
    }

    public function formatElement()
    {
        /** @var int $widgetCounter */
        static $widgetCounter = 1;

        if ($this->multiple) {
            $html = MediaListVar::getWidget($widgetCounter, $this->getAttribute('name'), $this->getValue(), $this->categoryId, $this->types, $this->preview);
        } else {
            $html = MediaVar::getWidget($widgetCounter, $this->getAttribute('name'), $this->getValue(), $this->categoryId, $this->types, $this->preview);
        }

        ++$widgetCounter;
        return $html;
    }
}
