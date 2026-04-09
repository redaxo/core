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
use Redaxo\Core\View\View;

use function count;
use function Redaxo\Core\View\escape;
use function sprintf;

/**
 * Erweiterung eines Artikels um slicemanagement.
 */
class ArticleContentEditor extends ArticleContent
{
    /** @var array<int, list<array{name: string, id: int, key: string}>> */
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

    protected function outputSlice(Sql $artDataSql, $moduleIdToAdd)
    {
        if ('edit' != $this->mode) {
            // ----- wenn mode nicht edit
            $sliceContent = parent::outputSlice(
                $artDataSql,
                $moduleIdToAdd,
            );
        } else {
            $sliceId = (int) $artDataSql->getValue(Core::getTablePrefix() . 'article_slice.id');
            $sliceCtype = (int) $artDataSql->getValue(Core::getTablePrefix() . 'article_slice.ctype_id');
            $sliceStatus = (int) $artDataSql->getValue(Core::getTablePrefix() . 'article_slice.status');
            $sliceRevision = (int) $artDataSql->getValue(Core::getTablePrefix() . 'article_slice.revision');

            $moduleInput = (string) $artDataSql->getValue(Core::getTablePrefix() . 'module.input');
            $moduleOutput = (string) $artDataSql->getValue(Core::getTablePrefix() . 'module.output');
            $moduleId = (int) $artDataSql->getValue(Core::getTablePrefix() . 'module.id');

            // ----- add select box einbauen
            $sliceContent = $this->getModuleSelect($sliceId);

            if ('add' == $this->function && $this->slice_id == $sliceId) {
                $sliceContent .= $this->addSlice($sliceId, $moduleIdToAdd);
            }

            $panel = '';
            // ----- Display message at current slice
            // if(rex::requireUser()->getComplexPerm('modules')->hasPerm($moduleId)) {
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
            if (Core::requireUser()->getComplexPerm('modules')->hasPerm($moduleId)) {
                if ('edit' == $this->function && $this->slice_id == $sliceId) {
                    // **************** Aktueller Slice

                    // ----- PRE VIEW ACTION [EDIT]
                    $action = new ArticleAction($moduleId, 'edit', $artDataSql);
                    if ('post' == Request::requestMethod() && 'edit' == Request::request('function', 'string')) {
                        $action->setRequestValues();
                    }
                    $action->exec(ArticleAction::PREVIEW);
                    // ----- / PRE VIEW ACTION

                    $moduleInput = $this->replaceVars($artDataSql, $moduleInput);
                    return $sliceContent . $this->editSlice($sliceId, $moduleInput, $sliceCtype, $moduleId, $artDataSql);
                }
            }
            // Modulinhalt ausgeben
            $moduleOutput = $this->replaceVars($artDataSql, $moduleOutput);
            $content = $this->getWrappedModuleOutput($moduleId, $moduleOutput);

            // EP for changing the module preview
            $panel .= Extension::registerPoint(new ExtensionPoint('SLICE_BE_PREVIEW', $content, [
                'article_id' => $this->article_id,
                'clang' => $this->clang,
                'ctype' => $this->ctype,
                'module_id' => $moduleId,
                'slice_id' => $sliceId,
                'revision' => $sliceRevision,
            ]));

            $fragment = new Fragment();
            $fragment->setVar('title', $this->getSliceHeading($artDataSql), false);
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

    /**
     * Returns the slice heading.
     *
     * @param Sql $artDataSql Sql instance containing all the slice and module information
     *
     * @return string
     */
    private function getSliceHeading(Sql $artDataSql)
    {
        return I18n::translate((string) $artDataSql->getValue(Core::getTablePrefix() . 'module.name'));
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

        $moduleId = (int) $artDataSql->getValue(Core::getTablePrefix() . 'module.id');
        $moduleName = I18n::translate((string) $artDataSql->getValue(Core::getTablePrefix() . 'module.name'));

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
        if (Core::requireUser()->getComplexPerm('modules')->hasPerm($moduleId)) {
            $templateHasModule = Template::hasModule($this->template_attributes, $this->ctype, $moduleId);
            if ($templateHasModule) {
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

            if ($templateHasModule && Core::requireUser()->hasPerm('publishSlice[]')) {
                // status
                $item = [];
                $statusName = $sliceStatus ? 'online' : 'offline';
                $item['label'] = I18n::msg('status_' . $statusName);
                $item['url'] = $context->getUrl(['status' => $sliceStatus ? 0 : 1] + ArticleSliceStatusChange::getUrlParams());
                $item['attributes']['class'][] = 'btn-default';
                $item['attributes']['class'][] = 'rex-' . $statusName;
                $menuStatusAction = $item;
            }

            if ($templateHasModule && Core::requireUser()->hasPerm('moveSlice[]')) {
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
        Extension::registerPoint($ep = new SliceMenu(
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
            $moduleId,
            $sliceId,
            Core::requireUser()->getComplexPerm('modules')->hasPerm($moduleId),
        ));

        $actionItems = [];
        if ($ep->getMenuEditAction()) {
            $actionItems[] = $ep->getMenuEditAction();
        }
        if ($ep->getMenuDeleteAction()) {
            $actionItems[] = $ep->getMenuDeleteAction();
        }
        if (count($actionItems) > 0) {
            $fragment = new Fragment();
            $fragment->setVar('items', $actionItems, false);
            $headerRight .= $fragment->parse('core/structure/content/slice_menu_action.php');
        }

        if ($ep->getMenuStatusAction()) {
            $fragment = new Fragment();
            $fragment->setVar('items', [$ep->getMenuStatusAction()], false);
            $headerRight .= $fragment->parse('core/structure/content/slice_menu_action.php');
        }

        if (count($ep->getAdditionalActions()) > 0) {
            $fragment = new Fragment();
            $fragment->setVar('items', $ep->getAdditionalActions(), false);
            $headerRight .= $fragment->parse('core/structure/content/slice_menu_ep.php');
        }

        $moveItems = [];
        if ($ep->getMenuMoveupAction()) {
            $moveItems[] = $ep->getMenuMoveupAction();
        }
        if ($ep->getMenuMovedownAction()) {
            $moveItems[] = $ep->getMenuMovedownAction();
        }
        if (count($moveItems) > 0) {
            $fragment = new Fragment();
            $fragment->setVar('items', $moveItems, false);
            $headerRight .= $fragment->parse('core/structure/content/slice_menu_move.php');
        }

        // $header_right = $header_right != '' ? '<div class="col-md-4 text-right">' . $header_right . '</div>' : '';

        return $headerRight;
    }

    /**
     * Wraps the output of a module.
     *
     * @param int $moduleId The id of the module
     * @param string $moduleOutput The output of the module
     *
     * @return string
     */
    private function getWrappedModuleOutput($moduleId, $moduleOutput)
    {
        return $this->getStreamOutput('module/' . (int) $moduleId . '/output', $moduleOutput);
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
                $item['id'] = (int) $module['id'];
                $item['key'] = $module['key'];
                $item['title'] = escape($module['name']);
                $item['href'] = $context->getUrl(['module_id' => $module['id']]) . '#slice-add-pos-' . $position;
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
        $select = Extension::registerPoint(new ExtensionPoint(
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

    protected function preArticle($articleContent, $moduleId)
    {
        // ---------- moduleselect: nur module nehmen auf die der user rechte hat
        if ('edit' == $this->mode) {
            $MODULE = Sql::factory();
            $modules = $MODULE->getArray('select * from ' . Core::getTablePrefix() . 'module order by name');

            $templateCtypes = $this->template_attributes['ctype'] ?? [];
            // wenn keine ctyes definiert sind, gibt es immer den CTYPE=1
            if (0 == count($templateCtypes)) {
                $templateCtypes = [1 => 'default'];
            }

            $this->MODULESELECT = [];
            foreach ($templateCtypes as $ctId => $ctName) {
                foreach ($modules as $m) {
                    $id = (int) $m['id'];
                    if (Core::requireUser()->getComplexPerm('modules')->hasPerm($id)) {
                        if (Template::hasModule($this->template_attributes, $ctId, $id)) {
                            $this->MODULESELECT[$ctId][] = ['name' => I18n::translate((string) $m['name'], false), 'id' => $id, 'key' => (string) $m['key']];
                        }
                    }
                }
            }
        }

        return parent::preArticle($articleContent, $moduleId);
    }

    protected function postArticle($articleContent, $moduleId)
    {
        // special identifier for the slot behind the last slice
        $behindlastSliceId = -1;

        // ----- add module im edit mode
        if ('edit' == $this->mode) {
            if ('add' == $this->function && $this->slice_id == $behindlastSliceId) {
                ++$this->sliceAddPosition;
                $sliceContent = $this->addSlice($behindlastSliceId, $moduleId);
            } else {
                // ----- BLOCKAUSWAHL - SELECT
                $sliceContent = $this->getModuleSelect($behindlastSliceId);
            }
            $articleContent .= $sliceContent;
        }

        return $articleContent;
    }

    // ----- ADD Slice

    /**
     * @param int $sliceId
     * @param int $moduleId
     * @return string
     */
    protected function addSlice($sliceId, $moduleId)
    {
        $sliceId = (int) $sliceId;
        $moduleId = (int) $moduleId;

        $MOD = Sql::factory();
        $MOD->setQuery('SELECT * FROM ' . Core::getTablePrefix() . 'module WHERE id="' . $moduleId . '"');

        if (1 != $MOD->getRows()) {
            return Message::error(I18n::msg('module_doesnt_exist'));
        }

        $initDataSql = Sql::factory();
        $initDataSql
            ->setValue('module_id', $moduleId)
            ->setValue('ctype_id', $this->ctype);

        // ----- PRE VIEW ACTION [ADD]
        $action = new ArticleAction($moduleId, 'add', $initDataSql);
        $action->setRequestValues();
        $action->exec(ArticleAction::PREVIEW);
        // ----- / PRE VIEW ACTION

        $this->currentSlice = ArticleSlice::forNewSlice($this->article_id, $this->clang, $this->ctype, $moduleId, $this->sliceAddPosition, $this->slice_revision);

        $moduleInput = $this->replaceVars($initDataSql, (string) $MOD->getValue('input'));
        $moduleInput = $this->getStreamOutput('module/' . $moduleId . '/input', $moduleInput);

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
                    <input type="hidden" name="module_id" value="' . $moduleId . '" />
                    <input type="hidden" name="save" value="1" />

                    <div class="rex-slice-input">
                        ' . $moduleInput . '
                    </div>
                </fieldset>
                        ';

        $fragment = new Fragment();
        $fragment->setVar('before', $msg, false);
        $fragment->setVar('class', 'add', false);
        $fragment->setVar('title', I18n::msg('module') . ': ' . I18n::translate((string) $MOD->getValue('name')), false);
        $fragment->setVar('body', $panel, false);
        $fragment->setVar('footer', $sliceFooter, false);
        $sliceContent = $fragment->parse('core/page/section.php');

        $fragment = new Fragment();
        $fragment->setVar('attributes', ['class' => ['rex-slice', 'rex-slice-add']], false);
        $fragment->setVar('formAction', Url::currentBackendPage(['article_id' => $this->article_id, 'slice_id' => $sliceId, 'clang' => $this->clang, 'ctype' => $this->ctype]) . '#slice-add-pos-' . $this->sliceAddPosition);
        $fragment->setVar('content', $sliceContent, false);
        return $fragment->parse('core/structure/content/slice_list_item.php');
    }

    // ----- EDIT Slice
    /**
     * @param int $sliceId
     * @param string $moduleInput
     * @param int $ctypeId
     * @param int $moduleId
     * @param Sql $artDataSql
     * @return string
     */
    protected function editSlice($sliceId, $moduleInput, $ctypeId, $moduleId, $artDataSql)
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

        $panel = '
                <fieldset>
                    <legend>' . I18n::msg('edit_block') . '</legend>
                    <input type="hidden" name="module_id" value="' . $moduleId . '" />
                    <input type="hidden" name="save" value="1" />
                    <input type="hidden" name="update" value="0" />

                    <div class="rex-slice-input">
                        ' . $msg . $this->getStreamOutput('module/' . $moduleId . '/input', $moduleInput) . '
                    </div>
                </fieldset>';

        $fragment = new Fragment();
        $fragment->setVar('class', 'edit', false);
        $fragment->setVar('title', $this->getSliceHeading($artDataSql), false);
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
