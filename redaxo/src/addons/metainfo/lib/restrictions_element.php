<?php

/**
 * @package redaxo\metainfo
 *
 * @internal
 */
class rex_form_restrictons_element extends rex_form_select_element
{
    /** @var string */
    private $allCheckboxLabel;

    // 1. Parameter nicht genutzt, muss aber hier stehen,
    // wg einheitlicher Konstrukturparameter
    /**
     * @param string $tag
     * @param array<string, int|string> $attributes
     */
    public function __construct($tag = '', ?rex_metainfo_table_expander $form = null, array $attributes = [])
    {
        parent::__construct('', $form, $attributes);

        $this->setNotice(rex_i18n::msg('ctrl'));
    }

    public function setAllCheckboxLabel(string $label): void
    {
        $this->allCheckboxLabel = $label;
    }

    /** @return string */
    public function get()
    {
        $id = $this->getAttribute('id');
        $checkboxId = $id . '-checkbox';
        $slctDivId = $id . '-div';

        $checkbox = new rex_form_checkbox_element('');
        $checkbox->setAttribute('name', 'enable_restrictions');
        $checkbox->setAttribute('id', $checkboxId);
        $checkbox->addOption($this->allCheckboxLabel, '');

        // Wert aus dem select in die checkbox übernehmen
        $checkbox->setValue($this->getValue());

        $this->select->setMultiple(true);

        $html = '';

        $html .= '
        <script type="text/javascript" nonce="' . rex_response::getNonce() . '">
        <!--

        jQuery(function($) {

            $("#' . $checkboxId . '").click(function() {
                $("#' . $slctDivId . '").slideToggle("slow");
                if($(this).is(":checked"))
                {
                    $("option:selected", "#' . $slctDivId . '").each(function () {
                        $(this).removeAttr("selected");
                    });
                }
            });

            if($("#' . $checkboxId . '").is(":checked")) {
                $("#' . $slctDivId . '").hide();
            }
        });

        //-->
        </script>';

        $html .= $checkbox->get();

        $html .= '<div id="' . $slctDivId . '">' . parent::get() . '</div>';

        return $html;
    }
}
