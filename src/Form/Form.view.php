<?php

use Redaxo\Core\Form\Form;
use Redaxo\Core\View\Component\Section;
use Redaxo\Core\View\Component\SectionVariant;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\Message;

return static function (Form $form): void {
    $body = Html::capture(static function () use ($form): void {
        foreach ($form->getFormErrors() as $error) {
            echo Message::error((string) Html::from($error));
        }
        echo $form->rest();
    }) ?>
    <?= $form->open() ?>
        <?= new Section(
            $form->title ?? '',
            $body,
            footer: Html::raw('<div class="rex-form-panel-footer">' . (string) $form->buttons() . '</div>'),
            variant: SectionVariant::Edit,
        )->render() ?>
    <?= $form->close() ?>
<?php };
