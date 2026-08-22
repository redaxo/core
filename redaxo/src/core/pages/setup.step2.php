<?php

assert(isset($context) && $context instanceof rex_context);
assert(isset($successArray) && is_array($successArray));
assert(isset($errorArray) && is_array($errorArray));
assert(isset($cancelSetupBtn));

$content = '';

if (count($successArray) > 0) {
    $content .= '<ul><li>' . implode('</li><li>', $successArray) . '</li></ul>';
}

$buttons = '';
$class = '';
if (count($errorArray) > 0) {
    $class = 'error';
    $content .= implode('', $errorArray);

    $buttons = '<a class="btn btn-setup" href="' . $context->getUrl(['step' => 3]) . '">' . rex_i18n::msg('setup_212') . '</a>';
} else {
    $class = 'success';
    $buttons = '<a class="btn btn-setup" href="' . $context->getUrl(['step' => 3]) . '">' . rex_i18n::msg('setup_210') . '</a>';
}

$security = '<div class="rex-js-setup-security-message" style="display:none">' . rex_view::error(rex_i18n::msg('setup_security_msg') . '<br />' . rex_i18n::msg('setup_no_js_security_msg')) . '</div>';
$security .= '<noscript>' . rex_view::error(rex_i18n::msg('setup_no_js_security_msg')) . '</noscript>';

$security .= '<script nonce="' . rex_response::getNonce() . '">

    jQuery(function($){
        // urls which are not expected to be accessible
        var urls = [
            "' . rex_url::backend('bin/console') . '",
            "' . rex_url::backend('data/.redaxo') . '",
            "' . rex_url::backend('src/core/boot.php') . '",
            "' . rex_url::backend('cache/.redaxo') . '"
        ];

        // NOTE: the backend runs a similar check, see standard.js
        $.each(urls, function (i, url) {
            $.ajax({
                url: url,
                cache: false,
                success: function(data) {
                    $(".rex-js-setup-security-message").show();
                    $(".rex-js-setup-section").hide();
                }
            });
        });

    })

</script>';

foreach (rex_setup::checkPhpSecurity() as $warning) {
    $security .= rex_view::warning($warning);
}

echo rex_view::title(rex_i18n::msg('setup_200') . $cancelSetupBtn);

$fragment = new rex_fragment();
$fragment->setVar('class', $class, false);
$fragment->setVar('title', rex_i18n::msg('setup_207'), false);
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
echo '<div class="rex-js-setup-section">' . $fragment->parse('core/page/section.php') . '</div>';
echo $security;
