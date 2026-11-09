<?php

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Core;
use Redaxo\Core\Form\Widget\ArticleListInput;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\HtmlAttributes;

use function Redaxo\Core\View\escape;

return static function (ArticleListInput $input): void {
    $allowed = !$input->disabled && Core::requireUser()->getComplexPerm('structure')->hasStructurePerm();

    $button = static fn (string $action, string $title, string $icon, bool $enabled = true): string => '<button ' . new HtmlAttributes([
        'type' => 'button',
        'class' => ['rex-button'],
        'data-variant' => 'field',
        'data-rex-widget-action' => $action,
        'title' => I18n::rawMsg($title),
        'aria-label' => I18n::rawMsg($title),
        'disabled' => !$enabled,
    ])->toString() . '><i class="rex-icon ' . $icon . '" aria-hidden="true"></i></button>' ?>
    <div <?= new HtmlAttributes([
        'class' => ['rex-input-group'],
        'data-rex-widget' => 'articlelist',
        'data-rex-widget-options' => json_encode([
            'category' => $input->categoryId ?? Category::getCurrent()->id ?? 0,
            'language' => Language::getCurrentId(),
        ], JSON_THROW_ON_ERROR),
    ]) ?>>
        <select <?= $input->attributes->with(['class' => ['rex-input'], 'id' => $input->labelTarget(), 'size' => 10, 'disabled' => $input->disabled, 'data-rex-widget-list' => true]) ?>>
            <?php foreach ($input->articleIds as $articleId): ?>
                <?php if ($article = Article::get($articleId)): ?>
                    <option value="<?= $article->id ?>"><?= escape($article->name . ' [' . $article->id . ']') ?></option>
                <?php endif ?>
            <?php endforeach ?>
        </select>
        <input type="hidden" name="<?= escape($input->name) ?>" value="<?= escape(implode(',', $input->articleIds)) ?>" data-rex-widget-value>
        <span class="rex-input-group-text">
            <div class="rex-button-group" data-orientation="vertical">
                <?= $button('move:top', 'form_articlelist_move_top', 'rex-icon-top', !$input->disabled) ?>
                <?= $button('move:up', 'form_articlelist_move_up', 'rex-icon-up', !$input->disabled) ?>
                <?= $button('move:down', 'form_articlelist_move_down', 'rex-icon-down', !$input->disabled) ?>
                <?= $button('move:bottom', 'form_articlelist_move_bottom', 'rex-icon-bottom', !$input->disabled) ?>
            </div>
            <div class="rex-button-group" data-orientation="vertical">
                <?= $button('open', 'form_article_open', 'rex-icon-open-linkmap', $allowed) ?>
                <?= $button('remove', 'form_article_delete', 'rex-icon-delete-link', $allowed) ?>
            </div>
        </span>
    </div>
<?php };
