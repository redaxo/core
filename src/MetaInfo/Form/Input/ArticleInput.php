<?php

namespace Redaxo\Core\MetaInfo\Form\Input;

use Redaxo\Core\RexVar\LinkListVar;
use Redaxo\Core\RexVar\LinkVar;

use function Redaxo\Core\View\escape;

/**
 * @internal
 *
 * @extends AbstractInput<int|string>
 */
final class ArticleInput extends AbstractInput
{
    private string $buttonId = '';
    private ?int $categoryId = null;

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
        $this->setAttribute('id', 'REX_LINK_' . $this->buttonId);
    }

    /**
     * @param int|null $categoryId
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

    public function getHtml()
    {
        $buttonId = $this->buttonId;
        $categoryId = $this->categoryId;
        $value = escape($this->value);
        $name = $this->attributes['name'];

        if ($this->multiple) {
            $name .= '[]';
            return LinkListVar::getWidget($buttonId, $name, $value, ['category' => $categoryId]);
        }
        return LinkVar::getWidget($buttonId, $name, $value, ['category' => $categoryId]);
    }
}
