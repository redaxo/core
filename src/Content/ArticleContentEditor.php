<?php

namespace Redaxo\Core\Content;

use Collator;
use Locale;
use Override;
use Redaxo\Core\Backend\Accesskey;
use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Content\ApiFunction\ArticleSliceMove;
use Redaxo\Core\Content\ApiFunction\ArticleSliceStatusChange;
use Redaxo\Core\Content\ExtensionPoint\ContentPageModuleSelect;
use Redaxo\Core\Content\ExtensionPoint\SliceMenu;
use Redaxo\Core\Content\ExtensionPoint\SlicePreview;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\Form;
use Redaxo\Core\Http\Context;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;

use function assert;
use function count;
use function Redaxo\Core\View\escape;
use function sprintf;
use function uasort;

/**
 * Erweiterung eines Artikels um slicemanagement.
 */
final class ArticleContentEditor extends ArticleContentBase
{
    /** @var array<int, list<array{name: string, key: string}>>|null */
    private ?array $moduleSelect = null;

    private int $sliceAddPosition = 0;

    /**
     * The slice form submitted with invalid values in the current request, to show it again with the errors.
     *
     * @internal
     */
    public ?Form $invalidSliceForm = null;

    #[Override]
    protected function outputSlice(Sql $artDataSql, string $moduleKeyToAdd): string
    {
        if ('edit' !== $this->mode) {
            // ----- wenn mode nicht edit
            $sliceContent = parent::outputSlice(
                $artDataSql,
                $moduleKeyToAdd,
            );
        } else {
            $sliceId = $artDataSql->getIntValue('rex_article_slice.id');
            $sliceCtype = $artDataSql->getIntValue('rex_article_slice.content_section_id');
            $sliceStatus = $artDataSql->getIntValue('rex_article_slice.status');

            $moduleKey = $artDataSql->getStringValue('rex_article_slice.module');

            // ----- add select box einbauen
            $sliceContent = $this->getModuleSelect($sliceId);

            if ('add' === $this->function && $this->sliceId === $sliceId) {
                $sliceContent .= $this->addSlice($sliceId, $moduleKeyToAdd);
            }

            $panel = '';
            // ----- Display message at current slice
            // if(rex::requireUser()->getComplexPerm('modules')->hasPerm($moduleKey)) {
            if ('add' !== $this->function && $this->sliceId === $sliceId) {
                $msg = '';
                if ('' !== $this->error) {
                    $msg .= Message::error($this->error);
                }
                if ('' !== $this->success) {
                    $msg .= Message::success($this->success);
                }
                $panel .= $msg;
            }
            // }

            // ----- EDIT/DELETE BLOCK - Wenn Rechte vorhanden
            if (Core::requireUser()->getComplexPerm('modules')->hasPerm($moduleKey)) {
                if ('edit' === $this->function && $this->sliceId === $sliceId) {
                    // **************** Aktueller Slice

                    $slice = ArticleSlice::fromSql($artDataSql);

                    return $sliceContent . $this->editSlice($sliceId, $slice, $sliceCtype, $moduleKey, $artDataSql);
                }
            }
            // Modulinhalt ausgeben
            if (Module::exists($moduleKey)) {
                $content = Module::of(ArticleSlice::fromSql($artDataSql))->output();
            } else {
                $content = '';
            }

            $panel .= Extension::dispatch(new SlicePreview(ArticleSlice::fromSql($artDataSql), $content));

            $fragment = new Fragment();
            $fragment->setVar('title', $this->getSliceHeading($moduleKey), false);
            $fragment->setVar('options', $this->getSliceMenu($artDataSql), false);
            $fragment->setVar('body', $panel, false);
            $section = $fragment->parse('core/page/section.php');

            $statusName = $sliceStatus ? 'online' : 'offline';

            $fragment = new Fragment();
            $fragment->setVar('attributes', ['class' => ['rex-slice', 'rex-slice-output', 'rex-slice-' . $statusName], 'id' => 'slice' . $sliceId], false);
            $fragment->setVar('content', $section, false);
            $sliceContent .= $fragment->parse('core/structure/content/slice_list_item.php');
        }

        return $sliceContent;
    }

    /** Returns the slice heading. */
    private function getSliceHeading(string $moduleKey): string
    {
        $module = Module::getClass($moduleKey);
        return escape(null !== $module ? $module::name() : $moduleKey);
    }

    /** Returns the slice menu. */
    private function getSliceMenu(Sql $artDataSql): string
    {
        // the editor always operates on a concrete content section
        assert($this->contentSectionId > 0);

        $sliceId = $artDataSql->getIntValue('rex_article_slice.id');
        $sliceCtype = $artDataSql->getIntValue('rex_article_slice.content_section_id');
        $sliceStatus = $artDataSql->getIntValue('rex_article_slice.status');

        $moduleKey = $artDataSql->getStringValue('rex_article_slice.module');
        $moduleName = $this->getSliceHeading($moduleKey);

        $context = new Context([
            'page' => Controller::getCurrentPage(),
            'article_id' => $this->articleId,
            'slice_id' => $sliceId,
            'language' => $this->languageId,
            'content_section_id' => $this->contentSectionId,
        ]);
        $fragment = '#slice' . $sliceId;

        $headerRight = '';

        $menuEditAction = [];
        $menuDeleteAction = [];
        $menuStatusAction = [];
        $menuMoveupAction = [];
        $menuMovedownAction = [];
        if (Core::requireUser()->getComplexPerm('modules')->hasPerm($moduleKey)) {
            $module = Module::getClass($moduleKey);
            $category = $this->article->categoryId > 0 ? Category::get($this->article->categoryId) : null;
            $moduleAllowed = (!$this->article->templateKey || Template::checkModuleAllowed($this->article->templateKey, $this->contentSectionId, $moduleKey))
                && (null === $module || $module::isAllowedInCategory($category));
            if ($moduleAllowed) {
                // edit
                $item = [];
                $item['label'] = I18n::msg('edit');
                $item['url'] = $context->getUrl(['function' => 'edit']) . $fragment;
                $item['variant'] = 'success';
                $menuEditAction = $item;
            }

            // delete
            $item = [];
            $item['label'] = I18n::msg('delete');
            $item['url'] = $context->getUrl(['function' => 'delete', 'save' => 1] + CsrfToken::factory('structure_content_slice')->getUrlParams()) . $fragment;
            $item['variant'] = 'danger';
            $item['attributes']['data-confirm'] = I18n::msg('confirm_delete_block');
            $menuDeleteAction = $item;

            if ($moduleAllowed && Core::requireUser()->hasPerm('publishSlice[]')) {
                // status
                $item = [];
                $statusName = $sliceStatus ? 'online' : 'offline';
                $item['label'] = I18n::msg('status_' . $statusName);
                $item['url'] = $context->getUrl(['status' => $sliceStatus ? 0 : 1] + ArticleSliceStatusChange::getUrlParams());
                $item['attributes']['class'][] = 'rex-u-' . $statusName;
                $menuStatusAction = $item;
            }

            if ($moduleAllowed && Core::requireUser()->hasPerm('moveSlice[]')) {
                $item = [];
                $item['hidden_label'] = I18n::msg('module') . ' ' . $moduleName . ' ' . I18n::msg('move_slice_up');
                $item['url'] = $context->getUrl(
                    ['upd' => time(), 'direction' => SliceMoveDirection::Up->value] + ArticleSliceMove::getUrlParams(),
                ) . $fragment;
                $item['attributes']['title'] = I18n::msg('move_slice_up');
                $item['icon'] = 'up';
                $menuMoveupAction = $item;

                $item = [];
                $item['hidden_label'] = I18n::msg('module') . ' ' . $moduleName . ' ' . I18n::msg('move_slice_down');
                $item['url'] = $context->getUrl(
                    ['upd' => time(), 'direction' => SliceMoveDirection::Down->value] + ArticleSliceMove::getUrlParams(),
                ) . $fragment;
                $item['attributes']['title'] = I18n::msg('move_slice_down');
                $item['icon'] = 'down';
                $menuMovedownAction = $item;
            }
        } else {
            $headerRight .= sprintf('<div class="rex-alert">%s %s</div>', I18n::msg('no_editing_rights'), $moduleName);
        }

        // ----- EXTENSION POINT
        Extension::dispatch($ep = new SliceMenu(
            $menuEditAction,
            $menuDeleteAction,
            $menuStatusAction,
            $menuMoveupAction,
            $menuMovedownAction,
            $context,
            $fragment,
            $this->articleId,
            $this->languageId,
            $sliceCtype,
            $moduleKey,
            $sliceId,
            Core::requireUser()->getComplexPerm('modules')->hasPerm($moduleKey),
        ));

        $actionItems = [];
        if ($ep->menuEditAction) {
            $actionItems[] = $ep->menuEditAction;
        }
        if ($ep->menuDeleteAction) {
            $actionItems[] = $ep->menuDeleteAction;
        }
        if (count($actionItems) > 0) {
            $fragment = new Fragment();
            $fragment->setVar('items', $actionItems, false);
            $headerRight .= $fragment->parse('core/structure/content/slice_menu_action.php');
        }

        if ($ep->menuStatusAction) {
            $fragment = new Fragment();
            $fragment->setVar('items', [$ep->menuStatusAction], false);
            $headerRight .= $fragment->parse('core/structure/content/slice_menu_action.php');
        }

        if (count($ep->additionalActions) > 0) {
            $fragment = new Fragment();
            $fragment->setVar('items', $ep->additionalActions, false);
            $headerRight .= $fragment->parse('core/structure/content/slice_menu_ep.php');
        }

        $moveItems = [];
        if ($ep->menuMoveupAction) {
            $moveItems[] = $ep->menuMoveupAction;
        }
        if ($ep->menuMovedownAction) {
            $moveItems[] = $ep->menuMovedownAction;
        }
        if (count($moveItems) > 0) {
            $fragment = new Fragment();
            $fragment->setVar('items', $moveItems, false);
            $headerRight .= $fragment->parse('core/structure/content/slice_menu_move.php');
        }

        return $headerRight;
    }

    private function getModuleSelect(int $sliceId): string
    {
        // the editor always operates on a concrete content section
        assert($this->contentSectionId > 0);

        // ----- BLOCKAUSWAHL - SELECT
        $context = new Context([
            'page' => Controller::getCurrentPage(),
            'article_id' => $this->articleId,
            'language' => $this->languageId,
            'content_section_id' => $this->contentSectionId,
            'slice_id' => $sliceId,
            'function' => 'add',
        ]);

        $position = ++$this->sliceAddPosition;

        $items = [];
        if (isset($this->moduleSelect[$this->contentSectionId])) {
            foreach ($this->moduleSelect[$this->contentSectionId] as $module) {
                $item = [];
                $item['key'] = $module['key'];
                $item['title'] = escape($module['name']);
                $item['href'] = $context->getUrl(['module' => $module['key']]) . '#slice-add-pos-' . $position;
                /**
                 * It is intended to pass raw values to fragment here.
                 * @psalm-taint-escape html
                 * @psalm-taint-escape has_quotes
                 */
                $item = $item;
                $items[] = $item;
            }
        }

        $fragment = new Fragment();
        $fragment->setVar('block', true);
        $fragment->setVar('button_label', I18n::msg('add_block'));
        $fragment->setVar('items', $items, false);
        $select = $fragment->parse('core/structure/content/module_select.php');
        $select = Extension::dispatch(new ContentPageModuleSelect($this->article, $this->contentSectionId, $sliceId, $select));
        $fragment = new Fragment();
        $fragment->setVar('attributes', ['class' => ['rex-slice', 'rex-slice-select'], 'id' => 'slice-add-pos-' . $position], false);
        $fragment->setVar('content', $select, false);
        return $fragment->parse('core/structure/content/slice_list_item.php');
    }

    #[Override]
    protected function preArticle(string $articleContent, string $moduleKey): string
    {
        // ---------- moduleselect: nur module nehmen auf die der user rechte hat
        if ('edit' === $this->mode) {
            $template = $this->article->templateKey ? Template::get($this->article->templateKey) : null;
            $contentSections = $template?->getContentSections() ?? [new ContentSection(1, 'Content')];

            $category = $this->article->categoryId > 0 ? Category::get($this->article->categoryId) : null;

            $modules = Module::getAll();
            $names = [];
            foreach ($modules as $key => $module) {
                $names[$key] = $module::name();
            }
            $collator = new Collator(Locale::getDefault());
            uasort($names, static fn (string $a, string $b): int => (int) $collator->compare($a, $b));

            $this->moduleSelect = [];
            foreach ($contentSections as $section) {
                foreach ($names as $key => $name) {
                    $module = $modules[$key];
                    if (Core::requireUser()->getComplexPerm('modules')->hasPerm($key)) {
                        if ((!$template || $template->isModuleAllowed($section, $module)) && $module::isAllowedInCategory($category)) {
                            $this->moduleSelect[$section->id][] = ['name' => $name, 'key' => $key];
                        }
                    }
                }
            }
        }

        return parent::preArticle($articleContent, $moduleKey);
    }

    #[Override]
    protected function postArticle(string $articleContent, string $moduleKey): string
    {
        // special identifier for the slot behind the last slice
        $behindlastSliceId = -1;

        // ----- add module im edit mode
        if ('edit' === $this->mode) {
            if ('add' === $this->function && $this->sliceId === $behindlastSliceId) {
                ++$this->sliceAddPosition;
                $sliceContent = $this->addSlice($behindlastSliceId, $moduleKey);
            } else {
                // ----- BLOCKAUSWAHL - SELECT
                $sliceContent = $this->getModuleSelect($behindlastSliceId);
            }
            $articleContent .= $sliceContent;
        }

        return $articleContent;
    }

    private function addSlice(int $sliceId, string $moduleKey): string
    {
        // the editor always operates on a concrete content section
        assert($this->contentSectionId > 0);

        $module = Module::getClass($moduleKey);

        if (null === $module) {
            return Message::error(I18n::msg('module_doesnt_exist'));
        }

        $slice = ArticleSlice::forNewSlice($this->articleId, $this->languageId, $this->contentSectionId, $moduleKey, $this->sliceAddPosition, $this->sliceRevision);

        $moduleInput = $this->renderModuleInput($slice);

        $msg = '';
        if ('' !== $this->error) {
            $msg .= Message::warning($this->error);
        }
        if ('' !== $this->success) {
            $msg .= Message::success($this->success);
        }

        $formElements = [];

        $n = [];
        $n['field'] = '<a class="rex-button" data-align="end" href="' . Url::currentBackendPage(['article_id' => $this->articleId, 'slice_id' => $sliceId, 'language' => $this->languageId, 'content_section_id' => $this->contentSectionId]) . '#slice-add-pos-' . $this->sliceAddPosition . '">' . I18n::msg('form_abort') . '</a>';
        $formElements[] = $n;

        $n = [];
        $n['field'] = '<button class="rex-button" data-variant="success" type="submit" name="btn_save" value="1"' . Accesskey::attributes(I18n::msg('add_block'), 'save') . '>' . I18n::msg('add_block') . '</button>';
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);
        $sliceFooter = $fragment->parse('core/form/submit.php');

        $panel = '
                <fieldset>
                    <legend>' . I18n::msg('add_block') . '</legend>
                    ' . CsrfToken::factory('structure_content_slice')->getHiddenField() . '
                    <input type="hidden" name="function" value="add" />
                    <input type="hidden" name="module" value="' . escape($moduleKey) . '" />
                    <input type="hidden" name="save" value="1" />

                    <div class="rex-slice-input">
                        ' . $moduleInput . '
                    </div>
                </fieldset>
                        ';

        $fragment = new Fragment();
        $fragment->setVar('before', $msg, false);
        $fragment->setVar('class', 'add', false);
        $fragment->setVar('title', I18n::msg('module') . ': ' . escape($module::name()), false);
        $fragment->setVar('body', $panel, false);
        $fragment->setVar('footer', $sliceFooter, false);
        $sliceContent = $fragment->parse('core/page/section.php');

        $fragment = new Fragment();
        $fragment->setVar('attributes', ['class' => ['rex-slice']], false);
        $fragment->setVar('formAction', Url::currentBackendPage(['article_id' => $this->articleId, 'slice_id' => $sliceId, 'language' => $this->languageId, 'content_section_id' => $this->contentSectionId]) . '#slice-add-pos-' . $this->sliceAddPosition);
        $fragment->setVar('content', $sliceContent, false);
        return $fragment->parse('core/structure/content/slice_list_item.php');
    }

    private function editSlice(int $sliceId, ArticleSlice $slice, int $contentSectionId, string $moduleKey, Sql $artDataSql): string
    {
        $msg = '';
        if ($this->sliceId === $sliceId) {
            if ('' !== $this->error) {
                $msg .= Message::warning($this->error);
            }
            if ('' !== $this->success) {
                $msg .= Message::success($this->success);
            }
        }

        $formElements = [];

        $n = [];
        $n['field'] = '<a class="rex-button" data-align="end" href="' . Url::currentBackendPage(['article_id' => $this->articleId, 'slice_id' => $sliceId, 'content_section_id' => $contentSectionId, 'language' => $this->languageId]) . '#slice' . $sliceId . '">' . I18n::msg('form_abort') . '</a>';
        $formElements[] = $n;

        $n = [];
        $n['field'] = '<button class="rex-button" data-variant="success" type="submit" name="btn_save" value="1"' . Accesskey::attributes(I18n::msg('save_and_close_tooltip'), 'save') . '>' . I18n::msg('save_block') . '</button>';
        $formElements[] = $n;

        $n = [];
        $n['field'] = '<button class="rex-button" data-variant="success" data-rex-submit-shortcut type="submit" name="btn_update" value="1"' . Accesskey::attributes(I18n::msg('save_and_goon_tooltip'), 'apply') . '>' . I18n::msg('update_block') . '</button>';
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);
        $sliceFooter = $fragment->parse('core/form/submit.php');

        $moduleInput = Module::exists($moduleKey) ? $this->renderModuleInput($slice) : '';

        $panel = '
                <fieldset>
                    <legend>' . I18n::msg('edit_block') . '</legend>
                    ' . CsrfToken::factory('structure_content_slice')->getHiddenField() . '
                    <input type="hidden" name="module" value="' . escape($moduleKey) . '" />
                    <input type="hidden" name="save" value="1" />
                    <input type="hidden" name="update" value="0" />

                    <div class="rex-slice-input">
                        ' . $msg . $moduleInput . '
                    </div>
                </fieldset>';

        $fragment = new Fragment();
        $fragment->setVar('class', 'edit', false);
        $fragment->setVar('title', $this->getSliceHeading($moduleKey), false);
        $fragment->setVar('options', $this->getSliceMenu($artDataSql), false);
        $fragment->setVar('body', $panel, false);
        $fragment->setVar('footer', $sliceFooter, false);
        $sliceContent = $fragment->parse('core/page/section.php');

        $fragment = new Fragment();
        $fragment->setVar('attributes', ['class' => ['rex-slice'], 'id' => 'slice' . $sliceId], false);
        $fragment->setVar('formAction', Url::currentBackendPage(['article_id' => $this->articleId, 'slice_id' => $sliceId, 'content_section_id' => $contentSectionId, 'language' => $this->languageId, 'function' => 'edit']) . '#slice' . $sliceId);
        $fragment->setVar('content', $sliceContent, false);
        return $fragment->parse('core/structure/content/slice_list_item.php');
    }

    /** Renders the form of the module, or the form submitted with invalid values in the current request. */
    private function renderModuleInput(ArticleSlice $slice): string
    {
        $form = $this->invalidSliceForm ?? Module::of($slice)->createForm();
        $module = $form->data;
        assert($module instanceof Module);

        return $module->input($form);
    }
}
