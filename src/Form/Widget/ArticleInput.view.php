<?php

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Core;
use Redaxo\Core\Form\Widget\ArticleInput;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\HtmlAttributes;

use function Redaxo\Core\View\escape;

return static function (ArticleInput $input): void {
    $article = null === $input->articleId ? null : Article::get($input->articleId);
    $allowed = !$input->disabled && Core::requireUser()->getComplexPerm('structure')->hasStructurePerm();

    $button = static fn (string $action, string $title, string $icon): string => '<button ' . new HtmlAttributes([
        'type' => 'button',
        'class' => ['rex-button'],
        'data-variant' => 'field',
        'data-rex-widget-action' => $action,
        'title' => I18n::rawMsg($title),
        'aria-label' => I18n::rawMsg($title),
        'disabled' => !$allowed,
    ])->toString() . '><i class="rex-icon ' . $icon . '" aria-hidden="true"></i></button>' ?>
    <div <?= new HtmlAttributes([
        'class' => ['rex-input-group'],
        'data-rex-widget' => 'article',
        'data-rex-widget-options' => json_encode([
            'category' => $article->categoryId ?? $input->categoryId ?? Category::getCurrent()->id ?? 0,
            'language' => Language::getCurrentId(),
        ], JSON_THROW_ON_ERROR),
    ]) ?>>
        <input <?= $input->attributes->with([
            'class' => ['rex-input'],
            'type' => 'text',
            'id' => $input->id,
            'value' => null === $article ? '' : $article->name . ' [' . $article->id . ']',
            'readonly' => true,
            'disabled' => $input->disabled,
            'data-rex-widget-label' => true,
        ]) ?>>
        <input type="hidden" name="<?= escape($input->name) ?>" value="<?= escape((string) $input->articleId) ?>" data-rex-widget-value>
        <span class="rex-input-group-actions">
            <?= $button('open', 'form_article_open', 'rex-icon-open-linkmap') ?>
            <?= $button('remove', 'form_article_delete', 'rex-icon-delete-link') ?>
        </span>
    </div>
<?php };
