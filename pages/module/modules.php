<?php

use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\ArticleCache;
use Redaxo\Core\Content\ModuleCache;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Exception\SqlException;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\Select\Select;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\DataList;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;

use function Redaxo\Core\View\escape;

$OUT = true;

$function = Request::request('function', 'string');
$functionAction = Request::request('function_action', 'string');
$save = Request::request('save', 'string');
$moduleId = Request::request('module_id', 'int');
$actionId = Request::request('action_id', 'int');
$iactionId = Request::request('iaction_id', 'int'); // id der module-action relation
$mname = trim(Request::request('mname', 'string'));
$mkey = trim(Request::request('mkey', 'string'));
$mkey = '' === $mkey ? null : $mkey;
$eingabe = Request::request('eingabe', 'string');
$ausgabe = Request::request('ausgabe', 'string');
$goon = Request::request('goon', 'string');
$addAction = Request::request('add_action', 'string');

$success = '';
$error = '';

$content = '';
$message = '';

$csrfToken = CsrfToken::factory('structure_content_module');

// ---------------------------- ACTIONSFUNKTIONEN FUER MODULE
if (('' != $addAction || 'delete' == $functionAction) && !$csrfToken->isValid()) {
    $error = I18n::msg('csrf_token_invalid');
} elseif ('' != $addAction) {
    $action = Sql::factory();
    $action->setTable(Core::getTablePrefix() . 'module_action');
    $action->setValue('module_id', $moduleId);
    $action->setValue('action_id', $actionId);

    $action->insert();
    $success = I18n::msg('action_taken');
    $goon = '1';
} elseif ('delete' == $functionAction) {
    $action = Sql::factory();
    $action->setTable(Core::getTablePrefix() . 'module_action');
    $action->setWhere(['id' => $iactionId]);
    $action->delete();

    if ($action->getRows() > 0) {
        $success = I18n::msg('action_deleted_from_modul');
    } else {
        $error = $action->getError();
    }
}

// ---------------------------- FUNKTIONEN FUER MODULE

if ('delete' == $function && !$csrfToken->isValid()) {
    $error = I18n::msg('csrf_token_invalid');
} elseif ('delete' == $function) {
    $del = Sql::factory();
    $del->setQuery('
        SELECT slice.article_id, slice.clang_id, slice.ctype_id, module.name
        FROM ' . Core::getTable('article_slice') . ' slice
        LEFT JOIN ' . Core::getTable('module') . ' module ON slice.module_id=module.id
        WHERE slice.module_id=?
        GROUP BY slice.article_id, slice.clang_id
        ORDER BY slice.article_id, slice.clang_id
        LIMIT 20
    ', [$moduleId]);

    if ($del->getRows() > 0) {
        $moduleInUseMessage = '';
        $modulname = $del->getValue('module.name');
        for ($i = 0; $i < $del->getRows(); ++$i) {
            $aid = $del->getValue('article_id');
            $clangId = $del->getValue('clang_id');
            $ctype = $del->getValue('ctype_id');
            $OOArt = Article::get($aid, $clangId);

            $label = $OOArt->getName() . ' [' . $aid . ']';
            if (Language::count() > 1) {
                $label .= ' [' . Language::get($clangId)->getCode() . ']';
            }

            $moduleInUseMessage .= '<li><a href="' . Url::backendPage('content', ['article_id' => $aid, 'clang' => $clangId, 'ctype' => $ctype]) . '">' . escape($label) . '</a></li>';
            $del->next();
        }

        $error = I18n::msg('module_cannot_be_deleted', $modulname);
        $error .= '<ul>' . $moduleInUseMessage . '</ul>';
    } else {
        $del = Sql::factory();
        $del->setQuery('DELETE FROM ' . Core::getTablePrefix() . 'module WHERE id=?', [$moduleId]);

        if ($del->getRows() > 0) {
            $del = Sql::factory();
            $del->setQuery('DELETE FROM ' . Core::getTablePrefix() . 'module_action WHERE module_id=?', [$moduleId]);
            ModuleCache::delete($moduleId);
            $success = I18n::msg('module_deleted');
            $success = Extension::registerPoint(new ExtensionPoint('MODULE_DELETED', $success, [
                'id' => $moduleId,
            ]));
        } else {
            $error = I18n::msg('module_not_found');
        }
    }
}

if ('add' == $function || 'edit' == $function) {
    if ('1' == $save && !$csrfToken->isValid()) {
        $error = I18n::msg('csrf_token_invalid');
        $save = '0';
    } elseif ('1' == $save) {
        $module = Sql::factory();

        try {
            if ('add' == $function) {
                $IMOD = Sql::factory();
                $IMOD->setTable(Core::getTablePrefix() . 'module');
                $IMOD->setValue('name', $mname);
                $IMOD->setValue('key', $mkey);
                $IMOD->setValue('input', $eingabe);
                $IMOD->setValue('output', $ausgabe);
                $IMOD->addGlobalCreateFields();
                $IMOD->addGlobalUpdateFields();

                $IMOD->insert();
                $moduleId = $IMOD->getLastId();
                ModuleCache::delete($moduleId);
                $success = I18n::msg('module_added');
                $success = Extension::registerPoint(new ExtensionPoint('MODULE_ADDED', $success, [
                    'id' => $moduleId,
                    'name' => $mname,
                    'key' => $mkey,
                    'input' => $eingabe,
                    'output' => $ausgabe,
                ]));
            } else {
                $module->setQuery('select * from ' . Core::getTablePrefix() . 'module where id=?', [$moduleId]);
                if (1 == $module->getRows()) {
                    $oldAusgabe = $module->getValue('output');

                    $UMOD = Sql::factory();
                    $UMOD->setTable(Core::getTablePrefix() . 'module');
                    $UMOD->setWhere(['id' => $moduleId]);
                    $UMOD->setValue('name', $mname);
                    $UMOD->setValue('key', $mkey);
                    $UMOD->setValue('input', $eingabe);
                    $UMOD->setValue('output', $ausgabe);
                    $UMOD->addGlobalUpdateFields();

                    $UMOD->update();
                    ModuleCache::delete($moduleId);
                    $success = I18n::msg('module_updated') . ' | ' . I18n::msg('articel_updated');
                    $success = Extension::registerPoint(new ExtensionPoint('MODULE_UPDATED', $success, [
                        'id' => $moduleId,
                        'name' => $mname,
                        'key' => $mkey,
                        'input' => $eingabe,
                        'output' => $ausgabe,
                    ]));

                    $newAusgabe = $ausgabe;

                    if ($oldAusgabe != $newAusgabe) {
                        // article updaten - nur wenn ausgabe sich veraendert hat
                        $gc = Sql::factory();
                        $gc->setQuery('SELECT DISTINCT(' . Core::getTablePrefix() . 'article.id) FROM ' . Core::getTablePrefix() . 'article
                                LEFT JOIN ' . Core::getTablePrefix() . 'article_slice ON ' . Core::getTablePrefix() . 'article.id=' . Core::getTablePrefix() . 'article_slice.article_id
                                WHERE ' . Core::getTablePrefix() . 'article_slice.module_id=?', [$moduleId]);
                        for ($i = 0; $i < $gc->getRows(); ++$i) {
                            ArticleCache::delete($gc->getValue(Core::getTablePrefix() . 'article.id'));
                            $gc->next();
                        }
                    }
                }
            }
        } catch (SqlException $e) {
            if (Sql::ERROR_VIOLATE_UNIQUE_KEY === $e->getErrorCode()) {
                $error = I18n::msg('module_key_exists');
                $save = '0';
            } else {
                $error = $e->getMessage();
            }
        }

        if ('' != $goon) {
            $save = '0';
        } else {
            $function = '';
        }
    }

    if ('1' != $save) {
        if ('edit' == $function) {
            $legend = I18n::msg('module_edit') . ' <small class="rex-primary-id">' . I18n::msg('id') . '=' . $moduleId . '</small>';

            $hole = Sql::factory();
            $hole->setQuery('SELECT * FROM ' . Core::getTablePrefix() . 'module WHERE id=?', [$moduleId]);
            $mname = $hole->getValue('name');
            $mkey = $hole->getValue('key');
            $ausgabe = $hole->getValue('output');
            $eingabe = $hole->getValue('input');
        } else {
            $legend = I18n::msg('create_module');
        }

        $btnUpdate = '';
        if ('add' != $function) {
            $btnUpdate = '<button class="btn btn-apply" type="submit" name="goon" value="1"' . Core::getAccesskey(I18n::msg('save_and_goon_tooltip'), 'apply') . '>' . I18n::msg('save_module_and_continue') . '</button>';
        }

        if ('' != $success) {
            $message .= Message::success($success);
        }

        if ('' != $error) {
            $message .= Message::error($error);
        }

        $content = '';
        $panel = '';
        $panel .= '
                <fieldset>
                        <input type="hidden" name="function" value="' . $function . '" />
                        <input type="hidden" name="save" value="1" />
                        <input type="hidden" name="category_id" value="0" />
                        <input type="hidden" name="module_id" value="' . $moduleId . '" />';

        $formElements = [];

        $n = [];
        $n['label'] = '<label for="mname">' . I18n::msg('module_name') . '</label>';
        $n['field'] = '<input class="form-control" id="mname" type="text" name="mname" value="' . escape($mname) . '" maxlength="255" />';
        $n['note'] = I18n::msg('translatable');
        $formElements[] = $n;

        $n = [];
        $n['label'] = '<label for="mkey">' . I18n::msg('module_key') . '</label>';
        $n['field'] = '<input class="form-control" id="mkey" type="text" name="mkey" value="' . escape($mkey) . '" maxlength="191" autocorrect="off" autocapitalize="off" spellcheck="false" />';
        $n['note'] = I18n::msg('module_key_notice');
        $formElements[] = $n;

        $n = [];
        $n['label'] = '<label for="minput">' . I18n::msg('input') . '</label>';
        $n['field'] = '<textarea class="form-control rex-code rex-js-code" id="minput" name="eingabe" autocapitalize="off" autocorrect="off" spellcheck="false">' . escape($eingabe) . '</textarea>';
        $formElements[] = $n;

        $n = [];
        $n['label'] = '<label for="moutput">' . I18n::msg('output') . '</label>';
        $n['field'] = '<textarea class="form-control rex-code rex-js-code" id="moutput" name="ausgabe" autocapitalize="off" autocorrect="off" spellcheck="false">' . escape($ausgabe) . '</textarea>';
        $n['note'] = I18n::msg('module_actions_notice');
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('flush', true);
        $fragment->setVar('elements', $formElements, false);
        $panel .= $fragment->parse('core/form/form.php');

        $panel .= '</fieldset>';

        $formElements = [];

        $n = [];
        $n['field'] = '<a class="btn btn-abort" href="' . Url::currentBackendPage() . '">' . I18n::msg('form_abort') . '</a>';
        $formElements[] = $n;

        $n = [];
        $n['field'] = '<button class="btn btn-save rex-form-aligned" type="submit"' . Core::getAccesskey(I18n::msg('save_and_close_tooltip'), 'save') . '>' . I18n::msg('save_module_and_quit') . '</button>';
        $formElements[] = $n;

        if ('' != $btnUpdate) {
            $n = [];
            $n['field'] = $btnUpdate;
            $formElements[] = $n;
        }

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);
        $buttons = $fragment->parse('core/form/submit.php');

        $fragment = new Fragment();
        $fragment->setVar('class', 'edit', false);
        $fragment->setVar('title', $legend, false);
        $fragment->setVar('body', $panel, false);
        $fragment->setVar('buttons', $buttons, false);
        $content .= $fragment->parse('core/page/section.php');

        if ('edit' == $function) {
            // Im Edit Mode Aktionen bearbeiten

            $gaa = Sql::factory();
            $gaa->setQuery('SELECT * FROM ' . Core::getTablePrefix() . 'action ORDER BY name');

            if ($gaa->getRows() > 0) {
                $gma = Sql::factory();
                $gma->setQuery('SELECT * FROM ' . Core::getTablePrefix() . 'module_action, ' . Core::getTablePrefix() . 'action WHERE ' . Core::getTablePrefix() . 'module_action.action_id=' . Core::getTablePrefix() . 'action.id and ' . Core::getTablePrefix() . 'module_action.module_id=?', [$moduleId]);

                $actions = '';
                for ($i = 0; $i < $gma->getRows(); ++$i) {
                    $iactionId = $gma->getValue(Core::getTablePrefix() . 'module_action.id');
                    $actionId = $gma->getValue(Core::getTablePrefix() . 'module_action.action_id');
                    $actionEditUrl = Url::backendPage('modules/actions', ['action_id' => $actionId, 'function' => 'edit']);
                    $actionName = I18n::translate($gma->getValue('name'));

                    $actions .= '<tr>
                        <td class="rex-table-icon"><a class="rex-link-expanded" href="' . $actionEditUrl . '" title="' . escape($actionName) . '"><i class="rex-icon rex-icon-action"></i></a></td>
                        <td class="rex-table-id" data-title="' . I18n::msg('id') . '">' . (int) $gma->getValue('id') . '</td>
                        <td data-title="' . I18n::msg('action_name') . '"><a class="rex-link-expanded" href="' . $actionEditUrl . '">' . $actionName . '</a></td>
                        <td class="rex-table-action"><a class="rex-link-expanded" href="' . $actionEditUrl . '"><i class="rex-icon rex-icon-edit"></i> ' . I18n::msg('edit') . '</a></td>
                        <td class="rex-table-action"><a class="rex-link-expanded" href="' . Url::currentBackendPage(['module_id' => $moduleId, 'function_action' => 'delete', 'function' => 'edit', 'iaction_id' => $iactionId] + $csrfToken->getUrlParams()) . '" data-confirm="' . I18n::msg('confirm_delete_action') . '"><i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('delete') . '</a></td>
                    </tr>';

                    $gma->next();
                }

                if ('' != $actions) {
                    $panel = '
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th class="rex-table-icon">&nbsp;</th>
                                    <th class="rex-table-id">' . I18n::msg('id') . '</th>
                                    <th>' . I18n::msg('action_name') . '</th>
                                    <th class="rex-table-action" colspan="2">' . I18n::msg('action_functions') . '</th>
                                </tr>
                            </thead>
                        <tbody>
                            ' . $actions . '
                        </tbody>
                        </table>
                    ';

                    $fragment = new Fragment();
                    $fragment->setVar('title', I18n::msg('actions_added_caption'), false);
                    $fragment->setVar('content', $panel, false);
                    $content .= $fragment->parse('core/page/section.php');
                }

                $gaaSel = new Select();
                $gaaSel->setName('action_id');
                $gaaSel->setId('action_id');
                $gaaSel->setSize(1);
                $gaaSel->setAttribute('class', 'form-control selectpicker');

                for ($i = 0; $i < $gaa->getRows(); ++$i) {
                    $gaaSel->addOption(I18n::translate($gaa->getValue('name'), false), $gaa->getValue('id'));
                    $gaa->next();
                }

                $panel = '';
                $panel .= '<fieldset>';

                $formElements = [];

                $n = [];
                $n['label'] = '<label for="action_id">' . I18n::msg('action') . '</label>';
                $n['field'] = $gaaSel->get();
                $formElements[] = $n;

                $fragment = new Fragment();
                $fragment->setVar('elements', $formElements, false);
                $panel .= $fragment->parse('core/form/form.php');

                $panel .= '</fieldset>';

                $formElements = [];

                $n = [];
                $n['field'] = '<button class="btn btn-save rex-form-aligned" type="submit" value="1" name="add_action">' . I18n::msg('action_add') . '</button>';
                $formElements[] = $n;

                $fragment = new Fragment();
                $fragment->setVar('elements', $formElements, false);
                $buttons = $fragment->parse('core/form/submit.php');

                $fragment = new Fragment();
                $fragment->setVar('title', I18n::msg('action_add'), false);
                $fragment->setVar('body', $panel, false);
                $fragment->setVar('buttons', $buttons, false);
                $content .= $fragment->parse('core/page/section.php');
            }
        }

        $content = '
            <form action="' . Url::currentBackendPage(['start' => Request::request('start', 'int')]) . '" method="post">
            ' . $csrfToken->getHiddenField() . '
            ' . $content . '
            </form>';

        echo $message;

        echo $content;

        $OUT = false;
    }
}

if ($OUT) {
    if ('' != $success) {
        $message .= Message::success($success);
    }

    if ('' != $error) {
        $message .= Message::error($error);
    }

    $list = DataList::factory('SELECT id, `key`, name FROM ' . Core::getTablePrefix() . 'module ORDER BY name', 100);
    $list->addParam('start', Request::request('start', 'int'));
    $list->addTableAttribute('class', 'table-striped table-hover');

    $tdIcon = '<i class="rex-icon rex-icon-module"></i>';
    $thIcon = '<a class="rex-link-expanded" href="' . $list->getUrl(['function' => 'add']) . '"' . Core::getAccesskey(I18n::msg('create_module'), 'add') . ' title="' . I18n::msg('create_module') . '"><i class="rex-icon rex-icon-add-module"></i></a>';
    $list->addColumn($thIcon, $tdIcon, 0, ['<th class="rex-table-icon">###VALUE###</th>', '<td class="rex-table-icon">###VALUE###</td>']);
    $list->setColumnParams($thIcon, ['function' => 'edit', 'module_id' => '###id###']);

    $list->setColumnLabel('id', I18n::msg('id'));
    $list->setColumnLayout('id', ['<th class="rex-table-id">###VALUE###</th>', '<td class="rex-table-id" data-title="' . I18n::msg('id') . '">###VALUE###</td>']);

    $list->setColumnLabel('key', I18n::msg('module_key'));

    $list->setColumnLabel('name', I18n::msg('module_description'));
    $list->setColumnParams('name', ['function' => 'edit', 'module_id' => '###id###']);
    $list->setColumnFormat('name', 'custom', static function () use ($list) {
        return $list->getColumnLink('name', I18n::translate($list->getValue('name')));
    });

    $slices = Sql::factory()->getArray('SELECT `module_id` FROM ' . Core::getTable('article_slice') . ' GROUP BY `module_id`');
    if (count($slices) > 0) {
        $usedIds = array_flip(array_map(static function ($slice) {
            return $slice['module_id'];
        }, $slices));

        $list->addColumn('use', '');
        $list->setColumnLabel('use', I18n::msg('module_in_use'));
        $list->setColumnFormat('use', 'custom', static function () use ($list, $usedIds) {
            return isset($usedIds[$list->getValue('id')]) ? '<i class="rex-icon rex-icon-active-true"></i> ' . I18n::msg('yes') : '<i class="rex-icon rex-icon-active-false"></i> ' . I18n::msg('no');
        });
    }

    $list->addColumn(I18n::msg('module_functions'), '<i class="rex-icon rex-icon-edit"></i> ' . I18n::msg('edit'));
    $list->setColumnLayout(I18n::msg('module_functions'), ['<th class="rex-table-action" colspan="2">###VALUE###</th>', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams(I18n::msg('module_functions'), ['function' => 'edit', 'module_id' => '###id###']);

    $list->addColumn(I18n::msg('delete_module'), '<i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('delete'));
    $list->setColumnLayout(I18n::msg('delete_module'), ['', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams(I18n::msg('delete_module'), ['function' => 'delete', 'module_id' => '###id###'] + $csrfToken->getUrlParams());
    $list->addLinkAttribute(I18n::msg('delete_module'), 'data-confirm', I18n::msg('confirm_delete_module'));

    $list->setNoRowsMessage(I18n::msg('modules_not_found'));

    $content .= $list->get();

    echo $message;

    $fragment = new Fragment();
    $fragment->setVar('title', I18n::msg('module_caption'), false);
    $fragment->setVar('content', $content, false);
    echo $fragment->parse('core/page/section.php');
}
