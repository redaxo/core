<?php

use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Http\Context;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\View;

use function Redaxo\Core\View\escape;

assert(isset($context) && $context instanceof Context);
assert(isset($errorArray) && is_array($errorArray));
assert(isset($config) && is_array($config));
assert(isset($cancelSetupBtn));

$configFile = Path::coreData('config.yml');
$headline = View::title(I18n::msg('setup_300', Path::relative($configFile)) . $cancelSetupBtn);

$content = '';

$submitMessage = I18n::msg('setup_310');
if (count($errorArray) > 0) {
    $submitMessage = I18n::msg('setup_314');
}

$content .= '
            <fieldset>';

$dbCreateChecked = Request::post('redaxo_db_create', 'boolean') ? ' checked="checked"' : '';

$content .= '<legend>' . I18n::msg('setup_302') . '</legend>';

$formElements = [];

$n = [];
$n['label'] = '<label for="rex-form-serveraddress" class="required">' . I18n::msg('server') . '</label>';
$n['field'] = '<input class="form-control" type="url" id="rex-form-serveraddress" name="serveraddress" value="' . escape($config['server']) . '" required autofocus />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-form-servername" class="required">' . I18n::msg('servername') . '</label>';
$n['field'] = '<input class="form-control" type="text" id="rex-form-servername" name="servername" value="' . escape($config['servername']) . '" required />';
$formElements[] = $n;

$n = [];
$n['label'] = '<label for="rex-form-error-email" class="required">' . I18n::msg('error_email') . '</label>';
$n['field'] = '<input class="form-control" type="email" id="rex-form-error-email" name="error_email" value="' . escape($config['error_email']) . '" required />';
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

$content .= '</fieldset><fieldset><legend>' . I18n::msg('setup_303') . '</legend>';

// Database Create Checkbox
$formElements = [];
$n = [];
$n['label'] = '<label>' . I18n::msg('setup_311') . '</label>';
$n['field'] = '<input type="checkbox" name="redaxo_db_create" value="1"' . $dbCreateChecked . ' />';
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/checkbox.php');

$content .= '</fieldset>';

$formElements = [];

$n = [];
$n['field'] = '<button class="btn btn-setup" type="submit" value="' . I18n::msg('system_update') . '">' . $submitMessage . '</button>';
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

echo $headline;
echo implode('', $errorArray);

$fragment = new Fragment();
$fragment->setVar('title', I18n::msg('setup_316'), false);
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');

echo '<form action="' . $context->getUrl(['step' => 4]) . '" method="post">' . $content . '</form>';
