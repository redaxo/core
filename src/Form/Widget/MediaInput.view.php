<?php

use Redaxo\Core\Core;
use Redaxo\Core\Form\Widget\MediaInput;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\HtmlAttributes;

return static function (MediaInput $input): void {
    $allowed = !$input->disabled && Core::requireUser()->getComplexPerm('media')->hasMediaPerm();

    $button = static fn (string $action, string $title, string $icon): string => '<button ' . new HtmlAttributes([
        'type' => 'button',
        'class' => ['btn', 'btn-popup'],
        'data-rex-widget-action' => $action,
        'title' => I18n::rawMsg($title),
        'disabled' => !$allowed,
    ])->toString() . '><i class="rex-icon ' . $icon . '"></i></button>' ?>
    <div <?= new HtmlAttributes([
        'class' => ['rex-js-widget', 'rex-js-widget-media', 'rex-js-widget-preview' => $input->preview, 'rex-js-widget-preview-media-manager' => $input->preview],
        'data-rex-widget' => 'media',
        'data-rex-widget-options' => json_encode(['types' => $input->types, 'category' => $input->categoryId], JSON_THROW_ON_ERROR),
    ]) ?>>
        <div class="input-group">
            <input <?= new HtmlAttributes(['class' => ['form-control'], 'type' => 'text', 'name' => $input->name, 'value' => (string) $input->fileName, 'id' => $input->labelTarget(), 'readonly' => true, 'disabled' => $input->disabled, 'data-rex-widget-value' => true]) ?>>
            <span class="input-group-btn">
                <?= $button('open', 'form_media_open', 'rex-icon-open-mediapool') ?>
                <?= $button('add', 'form_media_new', 'rex-icon-add-media') ?>
                <?= $button('remove', 'form_media_remove', 'rex-icon-delete-media') ?>
                <?= $button('view', 'form_media_view', 'rex-icon-view-media') ?>
            </span>
        </div>
        <div class="rex-js-media-preview"></div>
    </div>
<?php };
