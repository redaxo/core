<?php

/**
 * Class for the start_article_id and notfound_article_id settings.
 *
 * @author gharlan
 *
 * @package redaxo\structure
 *
 * @internal
 */
class rex_system_setting_article_id extends rex_system_setting
{
    public function __construct(
        private string $key,
    ) {}

    public function getKey()
    {
        return $this->key;
    }

    public function getField()
    {
        $field = new rex_form_widget_linkmap_element();
        $field->setAttribute('class', 'rex-form-widget');
        $field->setLabel(rex_i18n::msg('system_setting_' . $this->key));
        $field->setValue(rex_config::get('structure', $this->key, 1));
        return $field;
    }

    /** @return string|bool */
    public function setValue($value)
    {
        $value = (int) $value;
        $article = rex_article::get($value);
        if (!$article instanceof rex_article) {
            return rex_i18n::msg('system_setting_' . $this->key . '_invalid');
        }
        rex_config::set('structure', $this->key, $value);
        return true;
    }
}
