<?php

use Redaxo\Core\Core;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;

use function Redaxo\Core\View\escape;

$success = '';

if ('' != Request::post('btn_save', 'string')) {
    // set config
    $settings = Request::post('settings', [
        ['be_style_labelcolor', 'string'],
        ['be_style_showlink', 'boolean'],
    ]);

    Core::setConfig($settings);
    $success = I18n::msg('customizer_config_updated');
}

if ('' != $success) {
    echo Message::success($success);
}

// form

$content = '<fieldset><legend>' . I18n::msg('customizer_labeling') . '</legend>';

$formElements = [];

$n = [];
$n['label'] = '<label for="customizer-labelcolor">' . I18n::msg('customizer_labelcolor') . '</label>';
$n['field'] = '
    <div class="input-group">
        <div class="input-group-addon">
            <input id="customizer-labelcolor-picker" type="color" value="' . escape(Core::getConfig('be_style_labelcolor', '')) . '" oninput="jQuery(\'#customizer-labelcolor\').val(this.value)" />
        </div>
        <input class="form-control" id="customizer-labelcolor" type="text" name="settings[be_style_labelcolor]" value="' . escape(Core::getConfig('be_style_labelcolor', '')) . '" oninput="jQuery(\'#customizer-labelcolor-picker\').val(this.value)" />
    </div>
';
$n['note'] = I18n::msg('customizer_labelcolor_notice');
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="customizer-showlink">' . I18n::msg('customizer_showlink') . '</label>';
$n['field'] = '<input type="checkbox" id="customizer-showlink" name="settings[be_style_showlink]" value="1" ' . (Core::getConfig('be_style_showlink') ? 'checked="checked" ' : '') . ' />';
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$content .= '</fieldset>';

// form - Button

$formElements = [];

$n = [];
$n['field'] = '<button class="btn btn-save rex-form-aligned" type="submit" name="btn_save" value="' . I18n::msg('customizer_update') . '">' . I18n::msg('customizer_update') . '</button>';
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('flush', true);
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

// section
$fragment = new Fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', I18n::msg('customizer'), false);
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');

echo '
    <form action="' . Url::currentBackendPage() . '" method="post">
        ' . $content . '
    </form>
';
