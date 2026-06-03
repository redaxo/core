<?php

namespace Redaxo\Core\MetaInfo\Form\Input;

use function Redaxo\Core\View\escape;

/**
 * @internal
 *
 * @extends AbstractInput<string>
 */
final class TextareaInput extends AbstractInput
{
    public function __construct()
    {
        parent::__construct();
        $this->setAttribute('class', 'form-control');
        $this->setAttribute('cols', '50');
        $this->setAttribute('rows', '6');
    }

    public function getHtml()
    {
        $value = escape($this->value);
        return '<textarea' . $this->getAttributeString() . '>' . $value . '</textarea>';
    }
}
