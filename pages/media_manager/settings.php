<?php

use Redaxo\Core\Core;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\Select\Select;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\MediaManager\MediaManager;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;

use function Redaxo\Core\View\escape;

$func = Request::request('func', 'string');

if ('update' == $func) {
    $config = Request::post('settings', [
        ['media_manager_jpg_quality', 'int'],
        ['media_manager_png_compression', 'int'],
        ['media_manager_webp_quality', 'int'],
        ['media_manager_avif_quality', 'int'],
        ['media_manager_avif_speed', 'int'],
        ['media_manager_interlace', 'array[string]'],
    ]);

    $config['media_manager_jpg_quality'] = max(0, min(100, $config['media_manager_jpg_quality']));
    $config['media_manager_png_compression'] = max(-1, min(9, $config['media_manager_png_compression']));
    $config['media_manager_webp_quality'] = max(0, min(101, $config['media_manager_webp_quality']));
    $config['media_manager_avif_quality'] = max(0, min(100, $config['media_manager_avif_quality']));
    $config['media_manager_avif_speed'] = max(0, min(10, $config['media_manager_avif_speed']));

    Core::setConfig($config);
    MediaManager::deleteCache();
    echo Message::info(I18n::msg('media_manager_config_saved'));
}

$formElements = [];

$inputGroups = [];
$n = [];
$n['class'] = 'rex-range-input-group';
$n['left'] = '<input id="rex-js-rating-source-jpg-quality" type="range" min="0" max="100" step="1" value="' . escape(Core::getConfig('media_manager_jpg_quality')) . '" />';
$n['field'] = '<input class="form-control" id="rex-js-rating-text-jpg-quality" type="text" name="settings[media_manager_jpg_quality]" value="' . escape(Core::getConfig('media_manager_jpg_quality')) . '" />';
$inputGroups[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $inputGroups, false);
$inputGroup = $fragment->parse('core/form/input_group.php');

$n = [];
$n['label'] = '<label for="rex-js-rating-text-jpg-quality">' . I18n::msg('media_manager_jpg_quality') . '</label>';
$n['field'] = $inputGroup;
$formElements[] = $n;

$inputGroups = [];
$n = [];
$n['class'] = 'rex-range-input-group';
$n['left'] = '<input id="rex-js-rating-source-png-compression" type="range" min="0" max="9" step="1" value="' . escape(Core::getConfig('media_manager_png_compression')) . '" />';
$n['field'] = '<input class="form-control" id="rex-js-rating-text-png-compression" type="text" name="settings[media_manager_png_compression]" value="' . escape(Core::getConfig('media_manager_png_compression')) . '" />';
$inputGroups[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $inputGroups, false);
$inputGroup = $fragment->parse('core/form/input_group.php');

$n = [];
$n['label'] = '<label for="rex-js-rating-text-png-compression">' . I18n::msg('media_manager_png_compression') . '</label>';
$n['field'] = $inputGroup;
$n['note'] = I18n::msg('media_manager_png_compression_note');
$formElements[] = $n;

$inputGroups = [];
$n = [];
$n['class'] = 'rex-range-input-group';
$n['left'] = '<input id="rex-js-rating-source-webp-quality" type="range" min="0" max="101" step="1" value="' . escape(Core::getConfig('media_manager_webp_quality')) . '" />';
$n['field'] = '<input class="form-control" id="rex-js-rating-text-webp-quality" type="text" name="settings[media_manager_webp_quality]" value="' . escape(Core::getConfig('media_manager_webp_quality')) . '" />';
$inputGroups[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $inputGroups, false);
$inputGroup = $fragment->parse('core/form/input_group.php');

$n = [];
$n['label'] = '<label for="rex-js-rating-text-webp-quality">' . I18n::msg('media_manager_webp_quality') . '</label>';
$n['field'] = $inputGroup;
$formElements[] = $n;

$inputGroups = [];
$n = [];
$n['class'] = 'rex-range-input-group';
$n['left'] = '<input id="rex-js-rating-source-avif-quality" type="range" min="0" max="100" step="1" value="' . escape((int) Core::getConfig('media_manager_avif_quality')) . '" />';
$n['field'] = '<input class="form-control" id="rex-js-rating-text-avif-quality" type="text" name="settings[media_manager_avif_quality]" value="' . escape((int) Core::getConfig('media_manager_avif_quality')) . '" />';
$inputGroups[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $inputGroups, false);
$inputGroup = $fragment->parse('core/form/input_group.php');

$n = [];
$n['label'] = '<label for="rex-js-rating-text-avif-quality">' . I18n::msg('media_manager_avif_quality') . '</label>';
$n['field'] = $inputGroup;
$formElements[] = $n;

$inputGroups = [];
$n = [];
$n['class'] = 'rex-range-input-group';
$n['left'] = '<input id="rex-js-rating-source-avif-speed" type="range" min="0" max="10" step="1" value="' . escape((int) Core::getConfig('media_manager_avif_speed')) . '" />';
$n['field'] = '<input class="form-control" id="rex-js-rating-text-avif-speed" type="text" name="settings[media_manager_avif_speed]" value="' . escape((int) Core::getConfig('media_manager_avif_speed')) . '" />';
$inputGroups[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $inputGroups, false);
$inputGroup = $fragment->parse('core/form/input_group.php');

$n = [];
$n['label'] = '<label for="rex-js-rating-text-avif-quality">' . I18n::msg('media_manager_avif_speed') . '</label>';
$n['field'] = $inputGroup;
$formElements[] = $n;

$select = new Select();
$select->setName('settings[media_manager_interlace][]');
$select->setId('rex-media-manager-interlace');
$select->setAttribute('class', 'form-control selectpicker');
$select->setMultiple(true);
$select->addOptions(['jpg', 'png', 'gif'], true);
$select->setSelected(Core::getConfig('media_manager_interlace'));

$n = [];
$n['label'] = '<label for="rex-media-manager-interlace">' . I18n::msg('media_manager_interlace') . '</label>';
$n['field'] = $select->get();
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$content = $fragment->parse('core/form/form.php');

$formElements = [];
$n = [];
$n['field'] = '<a class="btn btn-abort" href="' . Url::currentBackendPage() . '">' . I18n::msg('form_abort') . '</a>';
$formElements[] = $n;

$n = [];
$n['field'] = '<button class="btn btn-apply rex-form-aligned" type="submit" name="sendit" value="1"' . Core::getAccesskey(I18n::msg('save_and_goon_tooltip'), 'apply') . '>' . I18n::msg('update') . '</button>';
$formElements[] = $n;

$fragment = new Fragment();
$fragment->setVar('elements', $formElements, false);
$buttons = $fragment->parse('core/form/submit.php');

$fragment = new Fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', I18n::msg('media_manager_subpage_config'), false);
$fragment->setVar('body', $content, false);
$fragment->setVar('buttons', $buttons, false);
$content = $fragment->parse('core/page/section.php');

$content = '
    <form action="' . Url::currentBackendPage() . '" method="post">
        <fieldset>
            <input type="hidden" name="func" value="update" />
            ' . $content . '
        </fieldset>
    </form>

    <script type="text/javascript" nonce="' . Response::getNonce() . '">
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
