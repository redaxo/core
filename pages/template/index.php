<?php

use Redaxo\Core\Content\Template;
use Redaxo\Core\Content\TemplateCache;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Exception\SqlException;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\Select\CategorySelect;
use Redaxo\Core\Form\Select\Select;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\View\DataList;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;
use Redaxo\Core\View\View;

use function Redaxo\Core\View\escape;

echo View::title(I18n::msg('title_templates'));

$OUT = true;

$function = Request::request('function', 'string');
$templateId = Request::request('template_id', 'int');
$save = Request::request('save', 'string');
$goon = Request::request('goon', 'string');

$success = '';
$error = '';

$content = '';
$message = '';

$templatekey = null;
$templatename = '';
$template = '';
$active = '';
$attributes = [];

$csrfToken = CsrfToken::factory('structure_content_template');

if ('delete' == $function) {
    if (!$csrfToken->isValid()) {
        $error = I18n::msg('csrf_token_invalid');
    } else {
        $del = Sql::factory();
        $templateIsInUseError = Template::templateIsInUse($templateId, 'cant_delete_template_because_its_in_use');
        if (false !== $templateIsInUseError) {
            $error .= $templateIsInUseError;
        }

        if (Template::getDefaultId() == $templateId) {
            $del = Sql::factory();
            $del->setQuery('SELECT name FROM ' . Core::getTable('template') . ' WHERE id = ' . $templateId);
            $templatename = $del->getValue('name');

            $error .= I18n::msg('cant_delete_template_because_its_default_template', $templatename);
        }
        if ('' == $error) {
            $del->setQuery('DELETE FROM ' . Core::getTablePrefix() . 'template WHERE id = "' . $templateId . '" LIMIT 1'); // max. ein Datensatz darf loeschbar sein
            TemplateCache::delete($templateId);
            $success = I18n::msg('template_deleted');
            $success = Extension::registerPoint(new ExtensionPoint('TEMPLATE_DELETED', $success, [
                'id' => $templateId,
            ]));
        }
    }
} elseif ('edit' == $function) {
    $hole = Sql::factory();
    $hole->setQuery('SELECT * FROM ' . Core::getTablePrefix() . 'template WHERE id = "' . $templateId . '"');
    if (1 == $hole->getRows()) {
        $templatekey = $hole->getValue('key');
        $templatename = $hole->getValue('name');
        $template = $hole->getValue('content');
        $active = $hole->getValue('active');
        $attributes = $hole->getArrayValue('attributes');
    } else {
        $function = '';
    }
} else {
    $templateId = 0;
}

if ('add' == $function || 'edit' == $function) {
    if ('ja' == $save && !$csrfToken->isValid()) {
        echo Message::error(I18n::msg('csrf_token_invalid'));
        $save = 'nein';
    }

    if ('ja' == $save) {
        $previousActive = $active;
        $active = Request::post('active', 'int');
        $templatename = Request::post('templatename', 'string');
        $template = Request::post('content', 'string');

        $templatekey = trim(Request::post('templatekey', 'string'));
        $templatekey = '' === $templatekey ? null : $templatekey;

        $ctypes = Request::post('ctype', 'array');

        $numCtypes = count($ctypes);
        if ('' == $ctypes[$numCtypes]) {
            unset($ctypes[$numCtypes]);
            if (isset($ctypes[$numCtypes - 1]) && '' == $ctypes[$numCtypes - 1]) {
                unset($ctypes[$numCtypes - 1]);
            }
        }

        $categories = Request::post('categories', 'array');
        // leerer eintrag = 0
        if (0 == count($categories) || !isset($categories['all']) || 1 != $categories['all']) {
            $categories['all'] = 0;
        }

        $modules = Request::post('modules', 'array');
        // leerer eintrag = 0
        if (0 == count($modules)) {
            $modules[1]['all'] = 0;
        }

        for ($k = 1; $k <= $numCtypes; ++$k) {
            if (!isset($modules[$k]['all']) || 1 != $modules[$k]['all']) {
                $modules[$k]['all'] = 0;
            }
        }

        $attributes['ctype'] = $ctypes;
        $attributes['modules'] = $modules;
        $attributes['categories'] = $categories;
        $TPL = Sql::factory();
        $TPL->setTable(Core::getTablePrefix() . 'template');
        $TPL->setValue('key', $templatekey);
        $TPL->setValue('name', $templatename);
        $TPL->setValue('active', $active);
        $TPL->setValue('content', $template);
        $TPL->addGlobalUpdateFields();

        $TPL->setArrayValue('attributes', $attributes);

        if ('add' == $function) {
            $TPL->addGlobalCreateFields();

            try {
                $TPL->insert();
                $templateId = $TPL->getLastId();
                TemplateCache::delete($templateId);
                $success = I18n::msg('template_added');
                $success = Extension::registerPoint(new ExtensionPoint('TEMPLATE_ADDED', $success, [
                    'id' => $templateId,
                    'key' => $templatekey,
                    'name' => $templatename,
                    'content' => $template,
                    'active' => $active,
                    'ctype' => $ctypes,
                    'modules' => $modules,
                    'categories' => $categories,
                ]));
            } catch (SqlException $e) {
                if (Sql::ERROR_VIOLATE_UNIQUE_KEY == $e->getErrorCode()) {
                    $error = I18n::msg('template_key_exists');
                    $save = 'nein';
                } else {
                    $error = $e->getMessage();
                }
            }
        } else {
            if ($previousActive && !$active) {
                if (Template::getDefaultId() == $templateId) {
                    $error .= I18n::msg('cant_inactivate_template_because_its_default_template', $templatename);
                }

                $templateIsInUseError = Template::templateIsInUse($templateId, 'cant_inactivate_template_because_its_in_use');
                if (false !== $templateIsInUseError) {
                    $error .= ($error ? '<br><br>' : '') . $templateIsInUseError;
                }
            }

            if ('' == $error) {
                $TPL->setWhere(['id' => $templateId]);

                try {
                    $TPL->update();
                    TemplateCache::delete($templateId);
                    $success = I18n::msg('template_updated');
                    $success = Extension::registerPoint(new ExtensionPoint('TEMPLATE_UPDATED', $success, [
                        'id' => $templateId,
                        'key' => $templatekey,
                        'name' => $templatename,
                        'content' => $template,
                        'active' => $active,
                        'ctype' => $ctypes,
                        'modules' => $modules,
                        'categories' => $categories,
                    ]));
                } catch (SqlException $e) {
                    if (Sql::ERROR_VIOLATE_UNIQUE_KEY == $e->getErrorCode()) {
                        $error = I18n::msg('template_key_exists');
                        $save = 'nein';
                    } else {
                        $error = $e->getMessage();
                    }
                }
            }
        }

        if ('' != $goon) {
            $function = 'edit';
            $save = 'nein';
        } else {
            $function = '';
        }
    }

    if ('ja' != $save) {
        // Ctype Handling
        $ctypes = $attributes['ctype'] ?? [];
        $modules = $attributes['modules'] ?? [];
        $categories = $attributes['categories'] ?? [];

        if (!is_array($modules)) {
            $modules = [];
        }

        if (!is_array($categories)) {
            $categories = [];
        }

        // modules[ctype_id][module_id];
        // modules[ctype_id]['all'];

        // Module ...
        $modulSelect = new Select();
        $modulSelect->setMultiple(true);
        $modulSelect->setSize(10);
        $modulSelect->setAttribute('class', 'form-control');
        $mSql = Sql::factory();
        foreach ($mSql->getArray('SELECT id, name FROM ' . Core::getTablePrefix() . 'module ORDER BY name') as $m) {
            $modulSelect->addOption(I18n::translate((string) $m['name']), (int) $m['id']);
        }

        // Kategorien
        $catSelect = new CategorySelect(false, false, false, false);
        $catSelect->setMultiple(true);
        $catSelect->setSize(10);
        $catSelect->setName('categories[]');
        $catSelect->setId('rex-id-categories-select');
        $catSelect->setAttribute('class', 'form-control');

        foreach ($categories as $c => $cc) {
            // typsicherer vergleich, weil (0 != "all") => false
            if ('all' !== $c) {
                $catSelect->setSelected($cc);
            }
        }

        $ctypesOut = '';
        $i = 1;
        $ctypes[] = ''; // Extra, fuer Neue Spalte

        if (is_array($ctypes)) {
            foreach ($ctypes as $name) {
                $modulSelect->setName('modules[' . $i . '][]');
                $modulSelect->setId('rex-id-modules-' . $i . '-select');
                $modulSelect->resetSelected();
                if (isset($modules[$i]) && count($modules[$i]) > 0) {
                    foreach ($modules[$i] as $j => $jj) {
                        // typsicherer vergleich, weil (0 != "all") => false
                        if ('all' !== $j) {
                            $modulSelect->setSelected($jj);
                        }
                    }
                }

                $ctypesOut .= '<fieldset><legend><small>' . I18n::msg('content_type') . '</small> ' . I18n::msg('id') . '=' . $i . '</legend>';

                $formElements = [];
                $n = [];
                $n['label'] = '<label for="rex-id-ctype' . $i . '">' . I18n::msg('name') . '</label>';
                $n['field'] = '<input class="form-control" id="rex-id-ctype' . $i . '" type="text" name="ctype[' . $i . ']" value="' . escape($name) . '" />';
                $formElements[] = $n;

                $fragment = new Fragment();
                $fragment->setVar('flush', true);
                $fragment->setVar('elements', $formElements, false);
                $ctypesOut .= $fragment->parse('core/form/form.php');

                $field = '';
                $field .= '<input id="rex-js-allmodules' . $i . '" type="checkbox" name="modules[' . $i . '][all]" ';
                if (!isset($modules[$i]['all']) || 1 == $modules[$i]['all']) {
                    $field .= ' checked="checked" ';
                }
                $field .= ' value="1" />';

                $formElements = [];
                $n = [];
                $n['label'] = '<label>' . I18n::msg('modules_available_all') . '</label>';
                $n['field'] = $field;
                $formElements[] = $n;

                $fragment = new Fragment();
                $fragment->setVar('elements', $formElements, false);
                $ctypesOut .= $fragment->parse('core/form/checkbox.php');

                $formElements = [];
                $n = [];
                $n['id'] = 'rex-js-modules' . $i;
                $n['label'] = '<label for="rex-id-modules-' . $i . '-select">' . Formatter::widont(I18n::msg('modules_available')) . '</label>';
                $n['field'] = $modulSelect->get();
                $n['note'] = I18n::msg('ctrl');
                $formElements[] = $n;

                $fragment = new Fragment();
                $fragment->setVar('flush', true);
                $fragment->setVar('elements', $formElements, false);
                $ctypesOut .= $fragment->parse('core/form/form.php');

                $ctypesOut .= '</fieldset>';

                ++$i;
            }
        }

        $ctypesOut .= '
            <script type="text/javascript" nonce="' . Response::getNonce() . '">
            <!--
            jQuery(function($) {
        ';

        for ($j = 1; $j <= $i; ++$j) {
            $ctypesOut .= '

                $("#rex-js-allmodules' . $j . '").click(function() {
                    $("#rex-js-modules' . $j . '").slideToggle("slow");
                });

                if($("#rex-js-allmodules' . $j . '").is(":checked")) {
                    $("#rex-js-modules' . $j . '").hide();
                }
            ';
        }

        $ctypesOut .= '
            });
            //--></script>';

        $tmplActiveChecked = 1 == $active ? ' checked="checked"' : '';

        if ('' != $success) {
            $message .= Message::success($success);
        }

        if ('' != $error) {
            $message .= Message::error($error);
        }

        $panel = '';

        $panel .= '
        <div class="tab-content">
            <div class="tab-pane fade" id="rex-form-template-default">
                    <fieldset>
                        <input type="hidden" name="function" value="' . $function . '" />
                        <input type="hidden" name="save" value="ja" />
                        <input type="hidden" name="template_id" value="' . $templateId . '" />
                        <input id="rex-js-form-template-tab" type="hidden" name="template_tab" value="" />';

        $formElements = [];
        $n = [];
        $n['label'] = '<label for="rex-id-templatename">' . I18n::msg('template_name') . '</label>';
        $n['field'] = '<input class="form-control" id="rex-id-templatename" type="text" name="templatename" value="' . escape($templatename) . '" maxlength="255" />';
        $n['note'] = I18n::msg('translatable');
        $formElements[] = $n;

        $n = [];
        $n['label'] = '<label for="rex-id-templatekey">' . I18n::msg('template_key') . '</label>';
        $n['field'] = '<input class="form-control" id="rex-id-templatekey" type="text" name="templatekey" value="' . escape($templatekey) . '" maxlength="191" autocorrect="off" autocapitalize="off" spellcheck="false" />';
        $n['note'] = I18n::msg('template_key_notice');
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('flush', true);
        $fragment->setVar('elements', $formElements, false);
        $panel .= $fragment->parse('core/form/form.php');

        $formElements = [];
        $n = [];
        $n['label'] = '<label>' . I18n::msg('checkbox_template_active') . '</label>';
        $n['field'] = '<input type="checkbox" id="rex-js-active" name="active" value="1"' . $tmplActiveChecked . '/>';
        $n['note'] = I18n::msg('checkbox_template_active_info');
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);
        $panel .= $fragment->parse('core/form/checkbox.php');

        $formElements = [];
        $n = [];
        $n['label'] = '<label for="rex-id-content">' . I18n::msg('header_template') . '</label>';
        $n['field'] = '<textarea class="form-control rex-code rex-js-code" id="rex-id-content" name="content" autocapitalize="off" autocorrect="off" spellcheck="false">' . escape($template) . '</textarea>';
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('flush', true);
        $fragment->setVar('elements', $formElements, false);
        $panel .= $fragment->parse('core/form/form.php');

        $panel .= '
                </fieldset>
            </div>
            <div class="tab-pane fade" id="rex-form-template-ctype">
                ' . $ctypesOut . '
            </div>

            <div class="tab-pane fade" id="rex-form-template-categories">
                <fieldset>
                     <legend>' . I18n::msg('template_categories') . '</legend>';

        $field = '';
        $field .= '<input id="rex-js-allcategories" type="checkbox" name="categories[all]" ';
        if (!isset($categories['all']) || 1 == $categories['all']) {
            $field .= ' checked="checked" ';
        }
        $field .= ' value="1" />';

        $formElements = [];
        $n = [];
        $n['reverse'] = true;
        $n['label'] = '<label>' . I18n::msg('template_categories_all') . '</label>';
        $n['field'] = $field;
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);
        $panel .= $fragment->parse('core/form/checkbox.php');

        $formElements = [];
        $n = [];
        $n['id'] = 'rex-id-categories';
        $n['label'] = '<label for="rex-id-categories-select">' . Formatter::widont(I18n::msg('template_categories_custom')) . '</label>';
        $n['field'] = $catSelect->get();
        $n['note'] = I18n::msg('ctrl');
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('flush', true);
        $fragment->setVar('elements', $formElements, false);
        $panel .= $fragment->parse('core/form/form.php');

        $panel .= '
                    </fieldset>
                </div>
            </div>';

        $formElements = [];

        $n = [];
        $n['field'] = '<a class="btn btn-abort" href="' . Url::currentBackendPage() . '">' . I18n::msg('form_abort') . '</a>';
        $formElements[] = $n;

        $n = [];
        $n['field'] = '<button class="btn btn-save rex-form-aligned" type="submit"' . Core::getAccesskey(I18n::msg('save_and_close_tooltip'), 'save') . '>' . I18n::msg('save_template_and_quit') . '</button>';
        $formElements[] = $n;

        $n = [];
        $n['field'] = '<button class="btn btn-apply" type="submit" name="goon" value="1"' . Core::getAccesskey(I18n::msg('save_and_goon_tooltip'), 'apply') . '>' . I18n::msg('save_template_and_continue') . '</button>';
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);
        $buttons = $fragment->parse('core/form/submit.php');

        $activeTab = Request::request('template_tab', 'string', 'rex-form-template-default');
        $optionTabs = [
            'rex-form-template-default' => I18n::msg('header_template'),
            'rex-form-template-ctype' => I18n::msg('content_types'),
            'rex-form-template-categories' => I18n::msg('template_categories'),
        ];
        $options = '<ul class="nav nav-tabs" id="rex-js-form-template-tabs">';
        foreach ($optionTabs as $optionTabId => $optionTabTitle) {
            $options .= '<li><a href="#' . $optionTabId . '" data-toggle="tab">' . $optionTabTitle . '</a></li>';
        }
        $options .= '</ul>';

        if ('edit' === $function) {
            $legend = I18n::msg('edit_template') . ' <small class="rex-primary-id">' . I18n::msg('id') . ' = ' . $templateId . '</small>';
        } else {
            $legend = I18n::msg('create_template');
        }

        $fragment = new Fragment();
        $fragment->setVar('class', 'edit', false);
        $fragment->setVar('title', $legend, false);
        $fragment->setVar('options', $options, false);
        $fragment->setVar('body', $panel, false);
        $fragment->setVar('buttons', $buttons, false);
        $content = $fragment->parse('core/page/section.php');

        $content = '
            <form id="rex-form-template" action="' . Url::currentBackendPage(['start' => Request::request('start', 'int')]) . '" method="post">
                ' . $csrfToken->getHiddenField() . '
                ' . $content . '
            </form>

            <script type="text/javascript" nonce="' . Response::getNonce() . '">
            <!--
            jQuery(function($) {
                // store the currently selected tab in the hidden input#rex-js-form-template-tab
                $("#rex-js-form-template-tabs > li > a").on("shown.bs.tab", function(e) {
                    var id = $(e.target).attr("href").substr(1);
                    $("#rex-js-form-template-tab").val(id);
                });
                $("#rex-js-form-template-tabs a[href=\"#' . escape($activeTab, 'js') . '\"]").tab("show");

                $("#rex-js-active").click(function() {
                    $("#rex-js-form-template-tabs a[href=\"#rex-form-template-ctype\"]").toggle("slow");
                    $("#rex-js-form-template-tabs a[href=\"#rex-form-template-categories\"]").toggle("slow");
                });

                if($("#rex-js-active").is(":not(:checked)")) {
                    $("#rex-js-form-template-tabs a[href=\"#rex-form-template-ctype\"]").hide();
                    $("#rex-js-form-template-tabs a[href=\"#rex-form-template-categories\"]").hide();
                }

                $("#rex-js-allcategories").click(function() {
                    $("#rex-id-categories").slideToggle("slow");
                });

                if($("#rex-js-allcategories").is(":checked")) {
                    $("#rex-id-categories").hide();
                }

            });

            //--></script>';

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

    $list = DataList::factory('SELECT id, `key`, name, active FROM ' . Core::getTablePrefix() . 'template ORDER BY name', 100);
    $list->addParam('start', Request::request('start', 'int'));
    $list->addTableAttribute('class', 'table-striped table-hover');

    $tdIcon = '<i class="rex-icon rex-icon-template"></i>';
    $thIcon = '<a class="rex-link-expanded" href="' . $list->getUrl(['function' => 'add']) . '"' . Core::getAccesskey(I18n::msg('create_template'), 'add') . ' title="' . I18n::msg('create_template') . '"><i class="rex-icon rex-icon-add-template"></i></a>';
    $list->addColumn($thIcon, $tdIcon, 0, ['<th class="rex-table-icon">###VALUE###</th>', '<td class="rex-table-icon">###VALUE###</td>']);
    $list->setColumnParams($thIcon, ['function' => 'edit', 'template_id' => '###id###']);

    $list->setColumnLabel('id', I18n::msg('id'));
    $list->setColumnLayout('id', ['<th class="rex-table-id">###VALUE###</th>', '<td class="rex-table-id" data-title="' . I18n::msg('id') . '">###VALUE###</td>']);

    $list->setColumnLabel('key', I18n::msg('header_template_key'));

    $list->setColumnLabel('name', I18n::msg('header_template_description'));
    $list->setColumnParams('name', ['function' => 'edit', 'template_id' => '###id###']);
    $list->setColumnFormat('name', 'custom', static function () use ($list) {
        return $list->getColumnLink('name', I18n::translate((string) $list->getValue('name')));
    });

    $list->setColumnLabel('active', I18n::msg('header_template_active'));
    $list->setColumnFormat('active', 'custom', static function () use ($list) {
        return 1 == $list->getValue('active') ? '<i class="rex-icon rex-icon-active-true"></i> ' . I18n::msg('yes') : '<i class="rex-icon rex-icon-active-false"></i> ' . I18n::msg('no');
    });

    $list->addColumn(I18n::msg('header_template_functions'), '<i class="rex-icon rex-icon-edit"></i> ' . I18n::msg('edit'));
    $list->setColumnLayout(I18n::msg('header_template_functions'), ['<th class="rex-table-action" colspan="2">###VALUE###</th>', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams(I18n::msg('header_template_functions'), ['function' => 'edit', 'template_id' => '###id###']);

    $list->addColumn(I18n::msg('delete_template'), '<i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('delete'));
    $list->setColumnLayout(I18n::msg('delete_template'), ['', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams(I18n::msg('delete_template'), ['function' => 'delete', 'template_id' => '###id###'] + $csrfToken->getUrlParams());
    $list->addLinkAttribute(I18n::msg('delete_template'), 'data-confirm', I18n::msg('confirm_delete_template'));

    $list->setNoRowsMessage(I18n::msg('templates_not_found'));

    $content .= $list->get();

    echo $message;

    $fragment = new Fragment();
    $fragment->setVar('title', I18n::msg('header_template_caption'), false);
    $fragment->setVar('content', $content, false);
    echo $fragment->parse('core/page/section.php');
}
