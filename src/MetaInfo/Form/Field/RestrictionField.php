<?php

namespace Redaxo\Core\MetaInfo\Form\Field;

use Redaxo\Core\Form\Field\CheckboxField;
use Redaxo\Core\Form\Field\SelectField;
use Redaxo\Core\Http\Response;
use Redaxo\Core\MetaInfo\Form\MetaInfoForm;
use Redaxo\Core\Translation\I18n;

use function is_array;

/**
 * @internal
 */
class RestrictionField extends SelectField
{
    /** @var string */
    private $allCheckboxLabel;

    // 1. Parameter nicht genutzt, muss aber hier stehen,
    // wg einheitlicher Konstrukturparameter
    /**
     * @param string $tag
     * @param array<string, int|string> $attributes
     */
    public function __construct($tag = '', ?MetaInfoForm $form = null, array $attributes = [])
    {
        parent::__construct('', $form, $attributes);

        $this->setSeparator(',');
        $this->setNotice(I18n::msg('ctrl'));
    }

    /** @return void */
    public function setValue($value)
    {
        if (is_array($value)) {
            $value = implode(',', $value);
        }
        $this->value = $value;
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

        $checkbox = new CheckboxField('');
        $checkbox->setAttribute('name', 'enable_restrictions');
        $checkbox->setAttribute('id', $checkboxId);
        $checkbox->addOption($this->allCheckboxLabel, '');

        // Wert aus dem select in die checkbox übernehmen
        $checkbox->setValue($this->getValue());

        $this->select->setMultiple(true);

        $html = '';

        $html .= '
        <script type="text/javascript" nonce="' . Response::getNonce() . '">
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
