<?php

namespace Redaxo\Core\Form\Field;

use Redaxo\Core\Form\AbstractForm;
use Redaxo\Core\RexVar\LinkListVar;
use Redaxo\Core\RexVar\LinkVar;

class ArticleField extends BaseField
{
    /** @var int */
    private $categoryId = 0;

    private bool $multiple = false;

    // 1. Parameter nicht genutzt, muss aber hier stehen,
    // wg einheitlicher Konstruktorparameter
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
     * @return void
     */
    public function setCategoryId($categoryId)
    {
        $this->categoryId = $categoryId;
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
            $html = LinkListVar::getWidget($widgetCounter, $this->getAttribute('name'), $this->getValue(), $this->categoryId);
        } else {
            $html = LinkVar::getWidget($widgetCounter, $this->getAttribute('name'), $this->getValue(), $this->categoryId);
        }

        ++$widgetCounter;
        return $html;
    }
}
