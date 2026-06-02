<?php

namespace Redaxo\Core\MetaInfo\Form\Input;

use function Redaxo\Core\View\escape;

/**
 * @internal
 *
 * @extends AbstractInput<string>
 */
final class TextInput extends AbstractInput
{
    public function __construct()
    {
        parent::__construct();
        $this->setAttribute('class', 'form-control');
        $this->setAttribute('type', 'text');
    }

    public function getHtml()
    {
        $value = escape($this->value);
        return '<input' . $this->getAttributeString() . ' value="' . $value . '" />';
    }
}
