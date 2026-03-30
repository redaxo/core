<?php

/**
 * @package redaxo\core\form
 */
class rex_form_control_element extends rex_form_element
{
    public function __construct(
        rex_form_base $form,
        private ?rex_form_element $saveElement = null,
        private ?rex_form_element $applyElement = null,
        private ?rex_form_element $deleteElement = null,
        private ?rex_form_element $resetElement = null,
        private ?rex_form_element $abortElement = null,
    ) {
        parent::__construct('', $form);
    }

    /** @return string */
    protected function _get()
    {
        $s = '';
        $elements = [];

        if ($this->saveElement) {
            if (!$this->saveElement->hasAttribute('class')) {
                $this->saveElement->setAttribute('class', 'btn btn-save rex-form-aligned');
            }

            $e = [];
            $e['field'] = $this->saveElement->formatElement();
            $elements[] = $e;
        }

        if ($this->applyElement) {
            if (!$this->applyElement->hasAttribute('class')) {
                $this->applyElement->setAttribute('class', 'btn btn-apply');
            }

            $e = [];
            $e['field'] = $this->applyElement->formatElement();
            $elements[] = $e;
        }

        if ($this->abortElement) {
            if (!$this->abortElement->hasAttribute('class')) {
                $this->abortElement->setAttribute('class', 'btn btn-abort');
            }

            $e = [];
            $e['field'] = $this->abortElement->formatElement();
            $elements[] = $e;
        }

        if ($this->deleteElement) {
            if (!$this->deleteElement->hasAttribute('class')) {
                $this->deleteElement->setAttribute('class', 'btn btn-delete');
            }

            if (!$this->deleteElement->hasAttribute('data-confirm') && !$this->deleteElement->hasAttribute('onclick')) {
                $this->deleteElement->setAttribute('data-confirm', rex_i18n::msg('form_delete') . '?');
            }

            $e = [];
            $e['field'] = $this->deleteElement->formatElement();
            $elements[] = $e;
        }

        if ($this->resetElement) {
            if (!$this->resetElement->hasAttribute('class')) {
                $this->resetElement->setAttribute('class', 'btn btn-reset');
            }

            if (!$this->resetElement->hasAttribute('data-confirm') && !$this->resetElement->hasAttribute('onclick')) {
                $this->resetElement->setAttribute('data-confirm', rex_i18n::msg('form_reset') . '?');
            }

            $e = [];
            $e['field'] = $this->resetElement->formatElement();
            $elements[] = $e;
        }

        if (count($elements) > 0) {
            $fragment = new rex_fragment();
            $fragment->setVar('elements', $elements, false);
            $s = $fragment->parse('core/form/submit.php');
        }

        return $s;
    }

    /** @return bool */
    public function submitted($element)
    {
        return is_object($element) && '' != rex_post($element->getAttribute('name'), 'string');
    }

    /** @return bool */
    public function saved()
    {
        return $this->submitted($this->saveElement);
    }

    /** @return bool */
    public function applied()
    {
        return $this->submitted($this->applyElement);
    }

    /** @return bool */
    public function deleted()
    {
        return $this->submitted($this->deleteElement);
    }

    /** @return bool */
    public function resetted()
    {
        return $this->submitted($this->resetElement);
    }

    /** @return bool */
    public function aborted()
    {
        return $this->submitted($this->abortElement);
    }
}
