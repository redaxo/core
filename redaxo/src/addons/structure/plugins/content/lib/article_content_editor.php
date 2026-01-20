<?php

/**
 * Erweiterung eines Artikels um slicemanagement.
 *
 * @package redaxo\structure\content
 */
class rex_article_content_editor extends rex_article_content
{
    /** @var array<int, list<array{name: string, id: int, key: string}>> */
    private $MODULESELECT;

    /** @var int */
    private $sliceAddPosition = 0;

    /**
     * @param int|null $articleId
     * @param int|null $clang
     */
    public function __construct($articleId = null, $clang = null)
    {
        parent::__construct($articleId, $clang);
    }

    protected function outputSlice(rex_sql $artDataSql, $moduleIdToAdd)
    {
        if ('edit' != $this->mode) {
            // ----- wenn mode nicht edit
            $sliceContent = parent::outputSlice(
                $artDataSql,
                $moduleIdToAdd,
            );
        } else {
            $sliceId = (int) $artDataSql->getValue(rex::getTablePrefix() . 'article_slice.id');
            $sliceCtype = (int) $artDataSql->getValue(rex::getTablePrefix() . 'article_slice.ctype_id');
            $sliceStatus = (int) $artDataSql->getValue(rex::getTablePrefix() . 'article_slice.status');
            $sliceRevision = (int) $artDataSql->getValue(rex::getTablePrefix() . 'article_slice.revision');

            $moduleInput = (string) $artDataSql->getValue(rex::getTablePrefix() . 'module.input');
            $moduleOutput = (string) $artDataSql->getValue(rex::getTablePrefix() . 'module.output');
            $moduleId = (int) $artDataSql->getValue(rex::getTablePrefix() . 'module.id');

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
                    $msg .= rex_view::error($this->warning);
                }
                if ('' != $this->info) {
                    $msg .= rex_view::success($this->info);
                }
                $panel .= $msg;
            }
            // }

            // ----- EDIT/DELETE BLOCK - Wenn Rechte vorhanden
            if (rex::requireUser()->getComplexPerm('modules')->hasPerm($moduleId)) {
                if ('edit' == $this->function && $this->slice_id == $sliceId) {
                    // **************** Aktueller Slice

                    // ----- PRE VIEW ACTION [EDIT]
                    $action = new rex_article_action($moduleId, 'edit', $artDataSql);
                    if ('post' == rex_request_method() && 'edit' == rex_request('function', 'string')) {
                        $action->setRequestValues();
                    }
                    $action->exec(rex_article_action::PREVIEW);
                    // ----- / PRE VIEW ACTION

                    $moduleInput = $this->replaceVars($artDataSql, $moduleInput);
                    return $sliceContent . $this->editSlice($sliceId, $moduleInput, $sliceCtype, $moduleId, $artDataSql);
                }
            }
            // Modulinhalt ausgeben
            $moduleOutput = $this->replaceVars($artDataSql, $moduleOutput);
            $content = $this->getWrappedModuleOutput($moduleId, $moduleOutput);

            // EP for changing the module preview
            $panel .= rex_extension::registerPoint(new rex_extension_point('SLICE_BE_PREVIEW', $content, [
                'article_id' => $this->article_id,
                'clang' => $this->clang,
                'ctype' => $this->ctype,
                'module_id' => $moduleId,
                'slice_id' => $sliceId,
                'revision' => $sliceRevision,
            ]));

            $fragment = new rex_fragment();
            $fragment->setVar('title', $this->getSliceHeading($artDataSql), false);
            $fragment->setVar('options', $this->getSliceMenu($artDataSql), false);
            $fragment->setVar('body', $panel, false);
            $statusName = $sliceStatus ? 'online' : 'offline';
            $sliceContent .= '<li class="rex-slice rex-slice-output rex-slice-' . $statusName . '" id="slice' . $sliceId . '">' . $fragment->parse('core/page/section.php') . '</li>';
        }

        return $sliceContent;
    }

    /**
     * Returns the slice heading.
     *
     * @param rex_sql $artDataSql rex_sql istance containing all the slice and module information
     *
     * @return string
     */
    private function getSliceHeading(rex_sql $artDataSql)
    {
        return rex_i18n::translate((string) $artDataSql->getValue(rex::getTablePrefix() . 'module.name'));
    }

    /**
     * Returns the slice menu.
     *
     * @param rex_sql $artDataSql rex_sql istance containing all the slice and module information
     *
     * @return string
     */
    private function getSliceMenu(rex_sql $artDataSql)
    {
        $sliceId = (int) $artDataSql->getValue(rex::getTablePrefix() . 'article_slice.id');
        $sliceCtype = (int) $artDataSql->getValue(rex::getTablePrefix() . 'article_slice.ctype_id');
        $sliceStatus = (int) $artDataSql->getValue(rex::getTablePrefix() . 'article_slice.status');

        $moduleId = (int) $artDataSql->getValue(rex::getTablePrefix() . 'module.id');
        $moduleName = rex_i18n::translate((string) $artDataSql->getValue(rex::getTablePrefix() . 'module.name'));

        $context = new rex_context([
            'page' => rex_be_controller::getCurrentPage(),
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
        if (rex::requireUser()->getComplexPerm('modules')->hasPerm($moduleId)) {
            $templateHasModule = rex_template::hasModule($this->template_attributes, $this->ctype, $moduleId);
            if ($templateHasModule) {
                // edit
                $item = [];
                $item['label'] = rex_i18n::msg('edit');
                $item['url'] = $context->getUrl(['function' => 'edit']) . $fragment;
                $item['attributes']['class'][] = 'btn-edit';
                $item['attributes']['title'] = rex_i18n::msg('edit');
                $menuEditAction = $item;
            }

            // delete
            $item = [];
            $item['label'] = rex_i18n::msg('delete');
            $item['url'] = $context->getUrl(['function' => 'delete', 'save' => 1]) . $fragment;
            $item['attributes']['class'][] = 'btn-delete';
            $item['attributes']['title'] = rex_i18n::msg('delete');
            $item['attributes']['data-confirm'] = rex_i18n::msg('confirm_delete_block');
            $menuDeleteAction = $item;

            if ($templateHasModule && rex::requireUser()->hasPerm('publishSlice[]')) {
                // status
                $item = [];
                $statusName = $sliceStatus ? 'online' : 'offline';
                $item['label'] = rex_i18n::msg('status_' . $statusName);
                $item['url'] = $context->getUrl(['status' => $sliceStatus ? 0 : 1] + rex_api_content_slice_status::getUrlParams());
                $item['attributes']['class'][] = 'btn-default';
                $item['attributes']['class'][] = 'rex-' . $statusName;
                $menuStatusAction = $item;
            }

            if ($templateHasModule && rex::requireUser()->hasPerm('moveSlice[]')) {
                // moveup
                $item = [];
                $item['hidden_label'] = rex_i18n::msg('module') . ' ' . $moduleName . ' ' . rex_i18n::msg('move_slice_up');
                $item['url'] = $context->getUrl(['upd' => time(), 'direction' => 'moveup'] + rex_api_content_move_slice::getUrlParams()) . $fragment;
                $item['attributes']['class'][] = 'btn-move';
                $item['attributes']['title'] = rex_i18n::msg('move_slice_up');
                $item['icon'] = 'up';
                $menuMoveupAction = $item;

                // movedown
                $item = [];
                $item['hidden_label'] = rex_i18n::msg('module') . ' ' . $moduleName . ' ' . rex_i18n::msg('move_slice_down');
                $item['url'] = $context->getUrl(['upd' => time(), 'direction' => 'movedown'] + rex_api_content_move_slice::getUrlParams()) . $fragment;
                $item['attributes']['class'][] = 'btn-move';
                $item['attributes']['title'] = rex_i18n::msg('move_slice_down');
                $item['icon'] = 'down';
                $menuMovedownAction = $item;
            }
        } else {
            $headerRight .= sprintf('<div class="alert">%s %s</div>', rex_i18n::msg('no_editing_rights'), $moduleName);
        }

        // ----- EXTENSION POINT
        rex_extension::registerPoint($ep = new rex_extension_point_slice_menu(
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
            rex::requireUser()->getComplexPerm('modules')->hasPerm($moduleId),
        ));

        $actionItems = [];
        if ($ep->getMenuEditAction()) {
            $actionItems[] = $ep->getMenuEditAction();
        }
        if ($ep->getMenuDeleteAction()) {
            $actionItems[] = $ep->getMenuDeleteAction();
        }
        if (count($actionItems) > 0) {
            $fragment = new rex_fragment();
            $fragment->setVar('items', $actionItems, false);
            $headerRight .= $fragment->parse('slice_menu_action.php');
        }

        if ($ep->getMenuStatusAction()) {
            $fragment = new rex_fragment();
            $fragment->setVar('items', [$ep->getMenuStatusAction()], false);
            $headerRight .= $fragment->parse('slice_menu_action.php');
        }

        if (count($ep->getAdditionalActions()) > 0) {
            $fragment = new rex_fragment();
            $fragment->setVar('items', $ep->getAdditionalActions(), false);
            $headerRight .= $fragment->parse('slice_menu_ep.php');
        }

        $moveItems = [];
        if ($ep->getMenuMoveupAction()) {
            $moveItems[] = $ep->getMenuMoveupAction();
        }
        if ($ep->getMenuMovedownAction()) {
            $moveItems[] = $ep->getMenuMovedownAction();
        }
        if (count($moveItems) > 0) {
            $fragment = new rex_fragment();
            $fragment->setVar('items', $moveItems, false);
            $headerRight .= $fragment->parse('slice_menu_move.php');
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
        $context = new rex_context([
            'page' => rex_be_controller::getCurrentPage(),
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
                $item['title'] = rex_escape($module['name']);
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

        $fragment = new rex_fragment();
        $fragment->setVar('block', true);
        $fragment->setVar('button_label', rex_i18n::msg('add_block'));
        $fragment->setVar('items', $items, false);
        $select = $fragment->parse('module_select.php');
        $select = rex_extension::registerPoint(new rex_extension_point(
            'STRUCTURE_CONTENT_MODULE_SELECT',
            $select,
            [
                'page' => rex_be_controller::getCurrentPage(),
                'article_id' => $this->article_id,
                'clang' => $this->clang,
                'ctype' => $this->ctype,
                'slice_id' => $sliceId,
            ],
        ));
        return '<li class="rex-slice rex-slice-select" id="slice-add-pos-' . $position . '">' . $select . '</li>';
    }

    protected function preArticle($articleContent, $moduleId)
    {
        // ---------- moduleselect: nur module nehmen auf die der user rechte hat
        if ('edit' == $this->mode) {
            $MODULE = rex_sql::factory();
            $modules = $MODULE->getArray('select * from ' . rex::getTablePrefix() . 'module order by name');

            $templateCtypes = $this->template_attributes['ctype'] ?? [];
            // wenn keine ctyes definiert sind, gibt es immer den CTYPE=1
            if (0 == count($templateCtypes)) {
                $templateCtypes = [1 => 'default'];
            }

            $this->MODULESELECT = [];
            foreach ($templateCtypes as $ctId => $ctName) {
                foreach ($modules as $m) {
                    $id = (int) $m['id'];
                    if (rex::requireUser()->getComplexPerm('modules')->hasPerm($id)) {
                        if (rex_template::hasModule($this->template_attributes, $ctId, $id)) {
                            $this->MODULESELECT[$ctId][] = ['name' => rex_i18n::translate((string) $m['name'], false), 'id' => $id, 'key' => (string) $m['key']];
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

        $MOD = rex_sql::factory();
        $MOD->setQuery('SELECT * FROM ' . rex::getTablePrefix() . 'module WHERE id="' . $moduleId . '"');

        if (1 != $MOD->getRows()) {
            return rex_view::error(rex_i18n::msg('module_doesnt_exist'));
        }

        $initDataSql = rex_sql::factory();
        $initDataSql
            ->setValue('module_id', $moduleId)
            ->setValue('ctype_id', $this->ctype);

        // ----- PRE VIEW ACTION [ADD]
        $action = new rex_article_action($moduleId, 'add', $initDataSql);
        $action->setRequestValues();
        $action->exec(rex_article_action::PREVIEW);
        // ----- / PRE VIEW ACTION

        $moduleInput = $this->replaceVars($initDataSql, (string) $MOD->getValue('input'));
        $moduleInput = $this->getStreamOutput('module/' . $moduleId . '/input', $moduleInput);

        $msg = '';
        if ('' != $this->warning) {
            $msg .= rex_view::warning($this->warning);
        }
        if ('' != $this->info) {
            $msg .= rex_view::success($this->info);
        }

        $formElements = [];

        $n = [];
        $n['field'] = '<a class="btn btn-abort" href="' . rex_url::currentBackendPage(['article_id' => $this->article_id, 'slice_id' => $sliceId, 'clang' => $this->clang, 'ctype' => $this->ctype]) . '#slice-add-pos-' . $this->sliceAddPosition . '">' . rex_i18n::msg('form_abort') . '</a>';
        $formElements[] = $n;

        $n = [];
        $n['field'] = '<button class="btn btn-save" type="submit" name="btn_save" value="1"' . rex::getAccesskey(rex_i18n::msg('add_block'), 'save') . '>' . rex_i18n::msg('add_block') . '</button>';
        $formElements[] = $n;

        $fragment = new rex_fragment();
        $fragment->setVar('elements', $formElements, false);
        $sliceFooter = $fragment->parse('core/form/submit.php');

        $panel = '
                <fieldset>
                    <legend>' . rex_i18n::msg('add_block') . '</legend>
                    <input type="hidden" name="function" value="add" />
                    <input type="hidden" name="module_id" value="' . $moduleId . '" />
                    <input type="hidden" name="save" value="1" />

                    <div class="rex-slice-input">
                        ' . $moduleInput . '
                    </div>
                </fieldset>
                        ';

        $fragment = new rex_fragment();
        $fragment->setVar('before', $msg, false);
        $fragment->setVar('class', 'add', false);
        $fragment->setVar('title', rex_i18n::msg('module') . ': ' . rex_i18n::translate((string) $MOD->getValue('name')), false);
        $fragment->setVar('body', $panel, false);
        $fragment->setVar('footer', $sliceFooter, false);
        $sliceContent = $fragment->parse('core/page/section.php');

        return '
                <li class="rex-slice rex-slice-add">
                    <form action="' . rex_url::currentBackendPage(['article_id' => $this->article_id, 'slice_id' => $sliceId, 'clang' => $this->clang, 'ctype' => $this->ctype]) . '#slice-add-pos-' . $this->sliceAddPosition . '" method="post" id="REX_FORM" enctype="multipart/form-data">
                        ' . $sliceContent . '
                    </form>
                    <script type="text/javascript" nonce="' . rex_response::getNonce() . '">
                         <!--
                        jQuery(function($) {
                            $(":input:visible:enabled:not([readonly]):first", $("#REX_FORM")).focus();
                        });
                         //-->
                    </script>
                </li>
                ';
    }

    // ----- EDIT Slice

    /**
     * @param int $sliceId
     * @param string $moduleInput
     * @param int $ctypeId
     * @param int $moduleId
     * @param rex_sql $artDataSql
     * @return string
     */
    protected function editSlice($sliceId, $moduleInput, $ctypeId, $moduleId, $artDataSql)
    {
        $msg = '';
        if ($this->slice_id == $sliceId) {
            if ('' != $this->warning) {
                $msg .= rex_view::warning($this->warning);
            }
            if ('' != $this->info) {
                $msg .= rex_view::success($this->info);
            }
        }

        $formElements = [];

        $n = [];
        $n['field'] = '<a class="btn btn-abort" href="' . rex_url::currentBackendPage(['article_id' => $this->article_id, 'slice_id' => $sliceId, 'ctype' => $ctypeId, 'clang' => $this->clang]) . '#slice' . $sliceId . '">' . rex_i18n::msg('form_abort') . '</a>';
        $formElements[] = $n;

        $n = [];
        $n['field'] = '<button class="btn btn-save" type="submit" name="btn_save" value="1"' . rex::getAccesskey(rex_i18n::msg('save_and_close_tooltip'), 'save') . '>' . rex_i18n::msg('save_block') . '</button>';
        $formElements[] = $n;

        $n = [];
        $n['field'] = '<button class="btn btn-apply" type="submit" name="btn_update" value="1"' . rex::getAccesskey(rex_i18n::msg('save_and_goon_tooltip'), 'apply') . '>' . rex_i18n::msg('update_block') . '</button>';
        $formElements[] = $n;

        $fragment = new rex_fragment();
        $fragment->setVar('elements', $formElements, false);
        $sliceFooter = $fragment->parse('core/form/submit.php');

        $panel = '
                <fieldset>
                    <legend>' . rex_i18n::msg('edit_block') . '</legend>
                    <input type="hidden" name="module_id" value="' . $moduleId . '" />
                    <input type="hidden" name="save" value="1" />
                    <input type="hidden" name="update" value="0" />

                    <div class="rex-slice-input">
                        ' . $msg . $this->getStreamOutput('module/' . $moduleId . '/input', $moduleInput) . '
                    </div>
                </fieldset>

            </form>';

        $fragment = new rex_fragment();
        $fragment->setVar('class', 'edit', false);
        $fragment->setVar('title', $this->getSliceHeading($artDataSql), false);
        $fragment->setVar('options', $this->getSliceMenu($artDataSql), false);
        $fragment->setVar('body', $panel, false);
        $fragment->setVar('footer', $sliceFooter, false);
        $sliceContent = $fragment->parse('core/page/section.php');

        return '
            <li class="rex-slice rex-slice-edit" id="slice' . $sliceId . '">
                <form enctype="multipart/form-data" action="' . rex_url::currentBackendPage(['article_id' => $this->article_id, 'slice_id' => $sliceId, 'ctype' => $ctypeId, 'clang' => $this->clang, 'function' => 'edit']) . '#slice' . $sliceId . '" method="post" id="REX_FORM">
                    ' . $sliceContent . '
                </form>
                <script type="text/javascript" nonce="' . rex_response::getNonce() . '">
                     <!--
                    jQuery(function($) {
                        $(":input:visible:enabled:not([readonly]):first", $("#REX_FORM")).focus();
                    });
                     //-->
                </script>
            </li>
            ';
    }
}
