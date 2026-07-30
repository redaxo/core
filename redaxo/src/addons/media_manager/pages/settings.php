<?php

/**
 * media_manager Addon.
 *
 * @author markus.staab[at]redaxo[dot]de Markus Staab
 * @author jan.kristinus[at]yakmara[dot]de Jan Kristinus
 */

$addon = rex_addon::get('media_manager');

$func = rex_request('func', 'string');

$csrfToken = rex_csrf_token::factory('media_manager_settings');

if ('update' == $func && !$csrfToken->isValid()) {
    echo rex_view::error(rex_i18n::msg('csrf_token_invalid'));
} elseif ('update' == $func) {
    $config = rex_post('settings', [
        ['jpg_quality', 'int'],
        ['png_compression', 'int'],
        ['webp_quality', 'int'],
        ['avif_quality', 'int'],
        ['avif_speed', 'int'],
        ['interlace', 'array[string]'],
    ]);

    $config['jpg_quality'] = max(0, min(100, $config['jpg_quality']));
    $config['png_compression'] = max(-1, min(9, $config['png_compression']));
    $config['webp_quality'] = max(0, min(101, $config['webp_quality']));
    $config['avif_quality'] = max(0, min(100, $config['avif_quality']));
    $config['avif_speed'] = max(0, min(10, $config['avif_speed']));

    $addon->setConfig($config);
    rex_media_manager::deleteCache();
    echo rex_view::info($addon->i18n('config_saved'));
}

$formElements = [];

$inputGroups = [];
$n = [];
$n['class'] = 'rex-range-input-group';
$n['left'] = '<input id="rex-js-rating-source-jpg-quality" type="range" min="0" max="100" step="1" value="' . rex_escape($addon->getConfig('jpg_quality')) . '" />';
$n['field'] = '<input class="form-control" id="rex-js-rating-text-jpg-quality" type="text" name="settings[jpg_quality]" value="' . rex_escape($addon->getConfig('jpg_quality')) . '" />';
$inputGroups[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $inputGroups, false);
$inputGroup = $fragment->parse('core/form/input_group.php');

$n = [];
$n['label'] = '<label for="rex-js-rating-text-jpg-quality">' . $addon->i18n('jpg_quality') . '</label>';
$n['field'] = $inputGroup;
$formElements[] = $n;

$inputGroups = [];
$n = [];
$n['class'] = 'rex-range-input-group';
$n['left'] = '<input id="rex-js-rating-source-png-compression" type="range" min="0" max="9" step="1" value="' . rex_escape($addon->getConfig('png_compression')) . '" />';
$n['field'] = '<input class="form-control" id="rex-js-rating-text-png-compression" type="text" name="settings[png_compression]" value="' . rex_escape($addon->getConfig('png_compression')) . '" />';
$inputGroups[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $inputGroups, false);
$inputGroup = $fragment->parse('core/form/input_group.php');

$n = [];
$n['label'] = '<label for="rex-js-rating-text-png-compression">' . $addon->i18n('png_compression') . '</label>';
$n['field'] = $inputGroup;
$n['note'] = $addon->i18n('png_compression_note');
$formElements[] = $n;

$inputGroups = [];
$n = [];
$n['class'] = 'rex-range-input-group';
$n['left'] = '<input id="rex-js-rating-source-webp-quality" type="range" min="0" max="101" step="1" value="' . rex_escape($addon->getConfig('webp_quality')) . '" />';
$n['field'] = '<input class="form-control" id="rex-js-rating-text-webp-quality" type="text" name="settings[webp_quality]" value="' . rex_escape($addon->getConfig('webp_quality')) . '" />';
$inputGroups[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $inputGroups, false);
$inputGroup = $fragment->parse('core/form/input_group.php');

$n = [];
$n['label'] = '<label for="rex-js-rating-text-webp-quality">' . $addon->i18n('webp_quality') . '</label>';
$n['field'] = $inputGroup;
$formElements[] = $n;

$inputGroups = [];
$n = [];
$n['class'] = 'rex-range-input-group';
$n['left'] = '<input id="rex-js-rating-source-avif-quality" type="range" min="0" max="100" step="1" value="' . rex_escape((int) $addon->getConfig('avif_quality')) . '" />';
$n['field'] = '<input class="form-control" id="rex-js-rating-text-avif-quality" type="text" name="settings[avif_quality]" value="' . rex_escape((int) $addon->getConfig('avif_quality')) . '" />';
$inputGroups[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $inputGroups, false);
$inputGroup = $fragment->parse('core/form/input_group.php');

$n = [];
$n['label'] = '<label for="rex-js-rating-text-avif-quality">' . $addon->i18n('avif_quality') . '</label>';
$n['field'] = $inputGroup;
$formElements[] = $n;

$inputGroups = [];
$n = [];
$n['class'] = 'rex-range-input-group';
$n['left'] = '<input id="rex-js-rating-source-avif-speed" type="range" min="0" max="10" step="1" value="' . rex_escape((int) $addon->getConfig('avif_speed')) . '" />';
$n['field'] = '<input class="form-control" id="rex-js-rating-text-avif-speed" type="text" name="settings[avif_speed]" value="' . rex_escape((int) $addon->getConfig('avif_speed')) . '" />';
$inputGroups[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $inputGroups, false);
$inputGroup = $fragment->parse('core/form/input_group.php');

$n = [];
$n['label'] = '<label for="rex-js-rating-text-avif-quality">' . $addon->i18n('avif_speed') . '</label>';
$n['field'] = $inputGroup;
$formElements[] = $n;

$select = new rex_select();
$select->setName('settings[interlace][]');
$select->setId('rex-media-manager-interlace');
$select->setAttribute('class', 'form-control selectpicker');
$select->setMultiple(true);
$select->addOptions(['jpg', 'png', 'gif'], true);
$select->setSelected($addon->getConfig('interlace'));

$n = [];
$n['label'] = '<label for="rex-media-manager-interlace">' . $addon->i18n('interlace') . '</label>';
$n['field'] = $select->get();
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content = $fragment->parse('core/form/form.php');

$formElements = [];
$n = [];
$n['field'] = '<a class="btn btn-abort" href="' . rex_url::currentBackendPage() . '">' . rex_i18n::msg('form_abort') . '</a>';
$formElements[] = $n;

$n = [];
$n['field'] = '<button class="btn btn-apply rex-form-aligned" type="submit" name="sendit" value="1"' . rex::getAccesskey(rex_i18n::msg('save_and_goon_tooltip'), 'apply') . '>' . rex_i18n::msg('update') . '</button>';
$formElements[] = $n;

$fragment = new rex_fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', $addon->i18n('subpage_config'), false);
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');

$content = '
    <form action="' . rex_url::currentBackendPage() . '" method="post">
        ' . $csrfToken->getHiddenField() . '
        <fieldset>
            <input type="hidden" name="func" value="update" />
            ' . $content . '
        </fieldset>
    </form>

    <script type="text/javascript" nonce="' . rex_response::getNonce() . '">
    <!--

    (function($) {

        $("#rex-js-rating-text-jpg-quality").on("input change", function(){
            $("#rex-js-rating-source-jpg-quality").val(this.value);
        });
        $("#rex-js-rating-source-jpg-quality").on("input change", function(){
            $("#rex-js-rating-text-jpg-quality").val(this.value);
            $("#rex-js-rating-text-jpg-quality").trigger("change");
        });
        $("#rex-js-rating-text-png-compression").on("input change", function(){
            $("#rex-js-rating-source-png-compression").val(this.value);
        });
        $("#rex-js-rating-source-png-compression").on("input change", function(){
            $("#rex-js-rating-text-png-compression").val(this.value);
            $("#rex-js-rating-text-png-compression").trigger("change");
        });
        $("#rex-js-rating-text-webp-quality").on("input change", function(){
            $("#rex-js-rating-source-webp-quality").val(this.value);
        });
        $("#rex-js-rating-source-webp-quality").on("input change", function(){
            $("#rex-js-rating-text-webp-quality").val(this.value);
            $("#rex-js-rating-text-webp-quality").trigger("change");
        });
        $("#rex-js-rating-text-avif-quality").on("input change", function(){
            $("#rex-js-rating-source-avif-quality").val(this.value);
        });
        $("#rex-js-rating-source-avif-quality").on("input change", function(){
            $("#rex-js-rating-text-avif-quality").val(this.value);
            $("#rex-js-rating-text-avif-quality").trigger("change");
        });
        $("#rex-js-rating-text-avif-speed").on("input change", function(){
            $("#rex-js-rating-source-avif-speed").val(this.value);
        });
        $("#rex-js-rating-source-avif-speed").on("input change", function(){
            $("#rex-js-rating-text-avif-speed").val(this.value);
            $("#rex-js-rating-text-avif-speed").trigger("change");
        });

    })(jQuery);

    //-->
    </script>

    ';

echo $content;
