<?php

use Redaxo\Core\Form\Widget\CollectionInput;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Html;

use function Redaxo\Core\View\escape;

return static function (CollectionInput $input): void {
    $item = static function (Html $fields) use ($input): void { ?>
        <div class="rex-form-collection-item rex-section rex-section--default">
            <?php if (!$input->disabled): ?>
            <div class="rex-section__header rex-u-align-end">
                <div class="rex-button-group" data-size="xs">
                    <button type="button" class="rex-button" data-rex-collection-move="up" title="<?= escape(I18n::rawMsg('form_collection_move_up')) ?>" aria-label="<?= escape(I18n::rawMsg('form_collection_move_up')) ?>"><i class="rex-icon rex-icon-up" aria-hidden="true"></i></button>
                    <button type="button" class="rex-button" data-rex-collection-move="down" title="<?= escape(I18n::rawMsg('form_collection_move_down')) ?>" aria-label="<?= escape(I18n::rawMsg('form_collection_move_down')) ?>"><i class="rex-icon rex-icon-down" aria-hidden="true"></i></button>
                    <button type="button" class="rex-button" data-variant="danger" data-rex-collection-remove title="<?= escape(I18n::rawMsg('form_collection_remove')) ?>" aria-label="<?= escape(I18n::rawMsg('form_collection_remove')) ?>"><i class="rex-icon rex-icon-delete" aria-hidden="true"></i></button>
                </div>
            </div>
            <?php endif ?>
            <div class="rex-section__body"><?= $fields ?></div>
        </div>
    <?php } ?>
    <div <?= $input->attributes->with([
        'class' => 'rex-form-collection',
        'id' => $input->id,
        'data-rex-collection' => true,
        'data-rex-collection-placeholder' => $input->placeholder,
        'data-rex-collection-next' => (string) count($input->items),
        'data-rex-collection-max' => null === $input->max ? null : (string) $input->max,
    ]) ?>>
        <div class="rex-form-collection-items">
            <?php foreach ($input->items as $fields): ?>
                <?php $item($fields) ?>
            <?php endforeach ?>
        </div>
        <?php if (!$input->disabled): ?>
            <template><?php $item($input->template) ?></template>
            <button type="button" class="rex-button" data-rex-collection-add><i class="rex-icon rex-icon-add" aria-hidden="true"></i> <?= escape(I18n::rawMsg('form_collection_add')) ?></button>
        <?php endif ?>
    </div>
<?php };
