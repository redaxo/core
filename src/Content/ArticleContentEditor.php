<?php

namespace Redaxo\Core\Content;

use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Content\ApiFunction\ArticleSliceMove;
use Redaxo\Core\Content\ApiFunction\ArticleSliceStatusChange;
use Redaxo\Core\Content\ExtensionPoint\SliceMenu;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Context;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;

use function count;
use function Redaxo\Core\View\escape;
use function sprintf;

/**
 * Erweiterung eines Artikels um slicemanagement.
 */
final class ArticleContentEditor extends ArticleContent
{
    /** @var array<int, list<array{name: string, key: string}>> */
    private $MODULESELECT;

    /** @var int */
    private $sliceAddPosition = 0;

    private ?ArticleSlice $currentSlice = null;

    /**
     * @param int|null $articleId
     * @param int|null $clang
     */
    public function __construct($articleId = null, $clang = null)
    {
        parent::__construct($articleId, $clang);
    }

    protected function outputSlice(Sql $artDataSql, string $moduleKeyToAdd): string
    {
        if ('edit' != $this->mode) {
            // ----- wenn mode nicht edit
            $sliceContent = parent::outputSlice(
                $artDataSql,
                $moduleKeyToAdd,
            );
        } else {
            $sliceId = (int) $artDataSql->getValue(Core::getTablePrefix() . 'article_slice.id');
            $sliceCtype = (int) $artDataSql->getValue(Core::getTablePrefix() . 'article_slice.ctype_id');
            $sliceStatus = (int) $artDataSql->getValue(Core::getTablePrefix() . 'article_slice.status');
            $sliceRevision = (int) $artDataSql->getValue(Core::getTablePrefix() . 'article_slice.revision');

            $moduleKey = (string) $artDataSql->getValue(Core::getTablePrefix() . 'article_slice.module');
            $module = Module::get($moduleKey);

            // ----- add select box einbauen
            $sliceContent = $this->getModuleSelect($sliceId);

            if ('add' == $this->function && $this->slice_id == $sliceId) {
                $sliceContent .= $this->addSlice($sliceId, $moduleKeyToAdd);
            }

            $panel = '';
            // ----- Display message at current slice
            // if(rex::requireUser()->getComplexPerm('modules')->hasPerm($moduleKey)) {
            if ('add' != $this->function && $this->slice_id == $sliceId) {
                $msg = '';
                if ('' != $this->warning) {
                    $msg .= Message::error($this->warning);
                }
                if ('' != $this->info) {
                    $msg .= Message::success($this->info);
                }
                $panel .= $msg;
            }
            // }

            // ----- EDIT/DELETE BLOCK - Wenn Rechte vorhanden
            if (Core::requireUser()->getComplexPerm('modules')->hasPerm($moduleKey)) {
                if ('edit' == $this->function && $this->slice_id == $sliceId) {
                    // **************** Aktueller Slice

                    $slice = ArticleSlice::fromSql($artDataSql);
                    if ('post' == Request::requestMethod() && 'edit' == Request::request('function', 'string')) {
                        $slice = $slice->withRequestValues();
                    }

                    return $sliceContent . $this->editSlice($sliceId, $slice, $sliceCtype, $moduleKey, $artDataSql);
                }
            }
            // Modulinhalt ausgeben
            if (null !== $module) {
                $slice = ArticleSlice::fromSql($artDataSql);
                $content = $module->output($slice);
            } else {
                $content = '';
            }

            // EP for changing the module preview
            $panel .= Extension::dispatch(new ExtensionPoint('SLICE_BE_PREVIEW', $content, [
                'article_id' => $this->article_id,
                'clang' => $this->clang,
                'ctype' => $this->ctype,
                'module_key' => $moduleKey,
                'slice_id' => $sliceId,
                'revision' => $sliceRevision,
            ]));

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
        $module = Module::get($moduleKey);
        return null !== $module ? I18n::translate($module->name) : $moduleKey;
    }

    /**
     * Returns the slice menu.
     *
     * @param Sql $artDataSql Sql instance containing all the slice and module information
     *
     * @return string
     */
    private function getSliceMenu(Sql $artDataSql)
    {
        $sliceId = (int) $artDataSql->getValue(Core::getTablePrefix() . 'article_slice.id');
        $sliceCtype = (int) $artDataSql->getValue(Core::getTablePrefix() . 'article_slice.ctype_id');
        $sliceStatus = (int) $artDataSql->getValue(Core::getTablePrefix() . 'article_slice.status');

        $moduleKey = (string) $artDataSql->getValue(Core::getTablePrefix() . 'article_slice.module');
        $moduleName = $this->getSliceHeading($moduleKey);

        $context = new Context([
            'page' => Controller::getCurrentPage(),
            'article_id' => $this->article_id,
            'slice_id' => $sliceId,
            'clang' => $this->clang,
            'ctype' => $this->ctype,
        ]);
        $fragment = '#slice' . $sliceId;

        $headerRight = '';

        $menuEditAction = [];
        $menuDeleteAction = [];
        $menuStatusAction = [];
        $menuMoveupAction = [];
        $menuMovedownAction = [];
        if (Core::requireUser()->getComplexPerm('modules')->hasPerm($moduleKey)) {
            $module = Module::get($moduleKey);
            $category = $this->category_id > 0 ? Category::get($this->category_id) : null;
            $moduleAllowed = (!$this->template || Template::checkModuleAllowed($this->template, $this->ctype, $moduleKey))
                && (null === $module || $module->isAllowedInCategory($category));
            if ($moduleAllowed) {
                // edit
                $item = [];
                $item['label'] = I18n::msg('edit');
                $item['url'] = $context->getUrl(['function' => 'edit']) . $fragment;
                $item['attributes']['class'][] = 'btn-edit';
                $item['attributes']['title'] = I18n::msg('edit');
                $menuEditAction = $item;
            }

            // delete
            $item = [];
            $item['label'] = I18n::msg('delete');
            $item['url'] = $context->getUrl(['function' => 'delete', 'save' => 1]) . $fragment;
            $item['attributes']['class'][] = 'btn-delete';
            $item['attributes']['title'] = I18n::msg('delete');
            $item['attributes']['data-confirm'] = I18n::msg('confirm_delete_block');
            $menuDeleteAction = $item;

            if ($moduleAllowed && Core::requireUser()->hasPerm('publishSlice[]')) {
                // status
                $item = [];
                $statusName = $sliceStatus ? 'online' : 'offline';
                $item['label'] = I18n::msg('status_' . $statusName);
                $item['url'] = $context->getUrl(['status' => $sliceStatus ? 0 : 1] + ArticleSliceStatusChange::getUrlParams());
                $item['attributes']['class'][] = 'btn-default';
                $item['attributes']['class'][] = 'rex-' . $statusName;
                $menuStatusAction = $item;
            }

            if ($moduleAllowed && Core::requireUser()->hasPerm('moveSlice[]')) {
                // moveup
                $item = [];
                $item['hidden_label'] = I18n::msg('module') . ' article_content_editor.php' . $moduleName . ' ' . I18n::msg('move_slice_up');
                $item['url'] = $context->getUrl(
                    ['upd' => time(), 'direction' => 'moveup'] + ArticleSliceMove::getUrlParams(),
                ) . $fragment;
                $item['attributes']['class'][] = 'btn-move';
                $item['attributes']['title'] = I18n::msg('move_slice_up');
                $item['icon'] = 'up';
                $menuMoveupAction = $item;

                // movedown
                $item = [];
                $item['hidden_label'] = I18n::msg('module') . ' article_content_editor.php' . $moduleName . ' ' . I18n::msg('move_slice_down');
                $item['url'] = $context->getUrl(
                    ['upd' => time(), 'direction' => 'movedown'] + ArticleSliceMove::getUrlParams(),
                ) . $fragment;
                $item['attributes']['class'][] = 'btn-move';
                $item['attributes']['title'] = I18n::msg('move_slice_down');
                $item['icon'] = 'down';
                $menuMovedownAction = $item;
            }
        } else {
            $headerRight .= sprintf('<div class="alert">%s %s</div>', I18n::msg('no_editing_rights'), $moduleName);
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
            $this->article_id,
            $this->clang,
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

    /**
     * @param int $sliceId
     * @return string
     */
    private function getModuleSelect($sliceId)
    {
        // ----- BLOCKAUSWAHL - SELECT
        $context = new Context([
            'page' => Controller::getCurrentPage(),
            'article_id' => $this->article_id,
            'clang' => $this->clang,
            'ctype' => $this->ctype,
            'slice_id' => $sliceId,
            'function' => 'add',
        ]);

        $position = ++$this->sliceAddPosition;

        $items = [];
        if (isset($this->MODULESELECT[$this->ctype])) {
            foreach ($this->MODULESELECT[$this->ctype] as $module) {
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
        $select = Extension::dispatch(new ExtensionPoint(
            'STRUCTURE_CONTENT_MODULE_SELECT',
            $select,
            [
                'page' => Controller::getCurrentPage(),
                'article_id' => $this->article_id,
                'clang' => $this->clang,
                'ctype' => $this->ctype,
                'slice_id' => $sliceId,
            ],
        ));
        $fragment = new Fragment();
        $fragment->setVar('attributes', ['class' => ['rex-slice', 'rex-slice-select'], 'id' => 'slice-add-pos-' . $position], false);
        $fragment->setVar('content', $select, false);
        return $fragment->parse('core/structure/content/slice_list_item.php');
    }

    protected function preArticle(string $articleContent, string $moduleKey): string
    {
        // ---------- moduleselect: nur module nehmen auf die der user rechte hat
        if ('edit' == $this->mode) {
            $template = $this->template ? Template::get($this->template) : null;
            $contentSections = $template?->getContentSections() ?? [new ContentSection(1, 'Content')];

            $category = $this->category_id > 0 ? Category::get($this->category_id) : null;

            $this->MODULESELECT = [];
            foreach ($contentSections as $section) {
                foreach (Module::getAll() as $module) {
                    if (Core::requireUser()->getComplexPerm('modules')->hasPerm($module->key)) {
                        if ((!$template || $template->isModuleAllowed($section, $module)) && $module->isAllowedInCategory($category)) {
                            $this->MODULESELECT[$section->id][] = ['name' => I18n::translate($module->name), 'key' => $module->key];
                        }
                    }
                }
            }
        }

        return parent::preArticle($articleContent, $moduleKey);
    }

    protected function postArticle(string $articleContent, string $moduleKey): string
    {
        // special identifier for the slot behind the last slice
        $behindlastSliceId = -1;

        // ----- add module im edit mode
        if ('edit' == $this->mode) {
            if ('add' == $this->function && $this->slice_id == $behindlastSliceId) {
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
        $module = Module::get($moduleKey);

        if (null === $module) {
            return Message::error(I18n::msg('module_doesnt_exist'));
        }

        $this->currentSlice = ArticleSlice::forNewSlice($this->article_id, $this->clang, $this->ctype, $moduleKey, $this->sliceAddPosition, $this->slice_revision)
            ->withRequestValues();

        $moduleInput = $module->input($this->currentSlice);

        $this->currentSlice = null;

        $msg = '';
        if ('' != $this->warning) {
            $msg .= Message::warning($this->warning);
        }
        if ('' != $this->info) {
            $msg .= Message::success($this->info);
        }

        $formElements = [];

        $n = [];
        $n['field'] = '<a class="btn btn-abort" href="' . Url::currentBackendPage(['article_id' => $this->article_id, 'slice_id' => $sliceId, 'clang' => $this->clang, 'ctype' => $this->ctype]) . '#slice-add-pos-' . $this->sliceAddPosition . '">' . I18n::msg('form_abort') . '</a>';
        $formElements[] = $n;

        $n = [];
        $n['field'] = '<button class="btn btn-save" type="submit" name="btn_save" value="1"' . Core::getAccesskey(I18n::msg('add_block'), 'save') . '>' . I18n::msg('add_block') . '</button>';
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);
        $sliceFooter = $fragment->parse('core/form/submit.php');

        $panel = '
                <fieldset>
                    <legend>' . I18n::msg('add_block') . '</legend>
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
        $fragment->setVar('title', I18n::msg('module') . ': ' . I18n::translate($module->name), false);
        $fragment->setVar('body', $panel, false);
        $fragment->setVar('footer', $sliceFooter, false);
        $sliceContent = $fragment->parse('core/page/section.php');

        $fragment = new Fragment();
        $fragment->setVar('attributes', ['class' => ['rex-slice', 'rex-slice-add']], false);
        $fragment->setVar('formAction', Url::currentBackendPage(['article_id' => $this->article_id, 'slice_id' => $sliceId, 'clang' => $this->clang, 'ctype' => $this->ctype]) . '#slice-add-pos-' . $this->sliceAddPosition);
        $fragment->setVar('content', $sliceContent, false);
        return $fragment->parse('core/structure/content/slice_list_item.php');
    }

    private function editSlice(int $sliceId, ArticleSlice $slice, int $ctypeId, string $moduleKey, Sql $artDataSql): string
    {
        $msg = '';
        if ($this->slice_id == $sliceId) {
            if ('' != $this->warning) {
                $msg .= Message::warning($this->warning);
            }
            if ('' != $this->info) {
                $msg .= Message::success($this->info);
            }
        }

        $formElements = [];

        $n = [];
        $n['field'] = '<a class="btn btn-abort" href="' . Url::currentBackendPage(['article_id' => $this->article_id, 'slice_id' => $sliceId, 'ctype' => $ctypeId, 'clang' => $this->clang]) . '#slice' . $sliceId . '">' . I18n::msg('form_abort') . '</a>';
        $formElements[] = $n;

        $n = [];
        $n['field'] = '<button class="btn btn-save" type="submit" name="btn_save" value="1"' . Core::getAccesskey(I18n::msg('save_and_close_tooltip'), 'save') . '>' . I18n::msg('save_block') . '</button>';
        $formElements[] = $n;

        $n = [];
        $n['field'] = '<button class="btn btn-apply" type="submit" name="btn_update" value="1"' . Core::getAccesskey(I18n::msg('save_and_goon_tooltip'), 'apply') . '>' . I18n::msg('update_block') . '</button>';
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);
        $sliceFooter = $fragment->parse('core/form/submit.php');

        $module = Module::get($moduleKey);
        $moduleInput = $module ? $module->input($slice) : '';

        $panel = '
                <fieldset>
                    <legend>' . I18n::msg('edit_block') . '</legend>
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
        $fragment->setVar('attributes', ['class' => ['rex-slice', 'rex-slice-edit'], 'id' => 'slice' . $sliceId], false);
        $fragment->setVar('formAction', Url::currentBackendPage(['article_id' => $this->article_id, 'slice_id' => $sliceId, 'ctype' => $ctypeId, 'clang' => $this->clang, 'function' => 'edit']) . '#slice' . $sliceId);
        $fragment->setVar('content', $sliceContent, false);
        return $fragment->parse('core/structure/content/slice_list_item.php');
    }

    public function getCurrentSlice(): ArticleSlice
    {
        return $this->currentSlice ?? parent::getCurrentSlice();
    }
}
