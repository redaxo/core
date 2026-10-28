<?php

use Redaxo\Core\Core;
use Redaxo\Core\Form\Widget\MediaListInput;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\HtmlAttributes;

use function Redaxo\Core\View\escape;

return static function (MediaListInput $input): void {
    $allowed = !$input->disabled && Core::requireUser()->getComplexPerm('media')->hasMediaPerm();

    $button = static fn (string $action, string $title, string $icon, bool $enabled = true): string => '<button ' . new HtmlAttributes([
        'type' => 'button',
        'class' => ['btn', 'btn-popup'],
        'data-rex-widget-action' => $action,
        'title' => I18n::rawMsg($title),
        'disabled' => !$enabled,
    ])->toString() . '><i class="rex-icon ' . $icon . '"></i></button>' ?>
    <div <?= new HtmlAttributes([
        'class' => ['rex-js-widget', 'rex-js-widget-medialist', 'rex-js-widget-preview' => $input->preview, 'rex-js-widget-preview-media-manager' => $input->preview],
        'data-rex-widget' => 'medialist',
        'data-rex-widget-options' => json_encode(['types' => $input->types, 'category' => $input->categoryId], JSON_THROW_ON_ERROR),
    ]) ?>>
        <div class="input-group">
            <select <?= $input->attributes->with(['class' => ['form-control'], 'id' => $input->labelTarget(), 'size' => 10, 'disabled' => $input->disabled, 'data-rex-widget-list' => true]) ?>>
                <?php foreach ($input->fileNames as $fileName): ?>
                    <option value="<?= escape($fileName) ?>"><?= escape($fileName) ?></option>
                <?php endforeach ?>
            </select>
            <input type="hidden" name="<?= escape($input->name) ?>" value="<?= escape(implode(',', $input->fileNames)) ?>" data-rex-widget-value>
            <span class="input-group-addon">
                <div class="btn-group-vertical">
                    <?= $button('move:top', 'form_medialist_move_top', 'rex-icon-top', !$input->disabled) ?>
                    <?= $button('move:up', 'form_medialist_move_up', 'rex-icon-up', !$input->disabled) ?>
                    <?= $button('move:down', 'form_medialist_move_down', 'rex-icon-down', !$input->disabled) ?>
                    <?= $button('move:bottom', 'form_medialist_move_bottom', 'rex-icon-bottom', !$input->disabled) ?>
                </div>
                <div class="btn-group-vertical">
                    <?= $button('open', 'form_media_open', 'rex-icon-open-mediapool', $allowed) ?>
                    <?= $button('add', 'form_media_new', 'rex-icon-add-media', $allowed) ?>
                    <?= $button('remove', 'form_media_remove', 'rex-icon-delete-media', $allowed) ?>
                    <?= $button('view', 'form_media_view', 'rex-icon-view-media', $allowed) ?>
                </div>
            </span>
        </div>
        <div class="rex-js-media-preview"></div>
    </div>
<?php };
