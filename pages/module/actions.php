<?php

use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\Select\ActionEventSelect;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Message;
use Redaxo\Core\View\View;

use function Redaxo\Core\View\escape;

$ASTATUS = ['ADD', 'EDIT', 'DELETE'];

$OUT = true;

$actionId = Request::request('action_id', 'int');
$function = Request::request('function', 'string');
$save = Request::request('save', 'bool');
$goon = Request::request('goon', 'string');

$success = '';
$error = '';

$content = '';
$message = '';

$csrfToken = CsrfToken::factory('structure_content_module_action');

if ('delete' == $function && !$csrfToken->isValid()) {
    $error = I18n::msg('csrf_token_invalid');
} elseif ('delete' == $function) {
    $del = Sql::factory();
    //  $del->setDebug();
    $qry = 'SELECT
                        *
                    FROM
                        ' . Core::getTablePrefix() . 'action a,
                        ' . Core::getTablePrefix() . 'module_action ma
                    LEFT JOIN
                     ' . Core::getTablePrefix() . 'module m
                    ON
                        ma.module_id = m.id
                    WHERE
                        ma.action_id = a.id AND
                        ma.action_id=?';
    $del->setQuery($qry, [$actionId]); // module mit dieser aktion vorhanden ?
    if ($del->getRows() > 0) {
        $actionInUseMsg = '';
        $actionName = $del->getValue('a.name');
        for ($i = 0; $i < $del->getRows(); ++$i) {
            $actionInUseMsg .= '<li><a href="' . Url::backendPage('modules', ['function' => 'edit', 'module_id' => $del->getValue('ma.module_id')]) . '">' . escape($del->getValue('m.name')) . ' [' . (int) $del->getValue('ma.module_id') . ']</a></li>';
            $del->next();
        }

        $actionInUseMsg = '<ul>' . $actionInUseMsg . '</ul>';
        $error = I18n::msg('action_cannot_be_deleted', $actionName) . $actionInUseMsg;
    } else {
        $del->setQuery('DELETE FROM ' . Core::getTablePrefix() . 'action WHERE id=? LIMIT 1', [$actionId]);
        $success = I18n::msg('action_deleted');
    }
}

if ('add' == $function || 'edit' == $function) {
    $name = Request::post('name', 'string');
    $previewaction = Request::post('previewaction', 'string');
    $presaveaction = Request::post('presaveaction', 'string');
    $postsaveaction = Request::post('postsaveaction', 'string');

    $previewstatus = 255;
    $presavestatus = 255;
    $postsavestatus = 255;

    if ($save && !$csrfToken->isValid()) {
        $error = I18n::msg('csrf_token_invalid');
        $save = false;
    } elseif ($save) {
        $faction = Sql::factory();

        $previewstatus = Request::post('preview_allevents', 'bool') ? [1, 2] : Request::post('previewstatus', 'array');
        $presavestatus = Request::post('presave_allevents', 'bool') ? [1, 2, 4] : Request::post('presavestatus', 'array');
        $postsavestatus = Request::post('postsave_allevents', 'bool') ? [1, 2, 4] : Request::post('postsavestatus', 'array');

        $previewmode = 0;
        foreach ($previewstatus as $status) {
            $previewmode |= $status;
        }

        $presavemode = 0;
        foreach ($presavestatus as $status) {
            $presavemode |= $status;
        }

        $postsavemode = 0;
        foreach ($postsavestatus as $status) {
            $postsavemode |= $status;
        }

        $faction->setTable(Core::getTablePrefix() . 'action');
        $faction->setValue('name', $name);
        $faction->setValue('preview', $previewaction);
        $faction->setValue('presave', $presaveaction);
        $faction->setValue('postsave', $postsaveaction);
        $faction->setValue('previewmode', $previewmode);
        $faction->setValue('presavemode', $presavemode);
        $faction->setValue('postsavemode', $postsavemode);
        $faction->addGlobalUpdateFields();

        if ('add' == $function) {
            $faction->addGlobalCreateFields();

            $faction->insert();
            $success = I18n::msg('action_added');
        } else {
            $faction->setWhere(['id' => $actionId]);

            $faction->update();
            $success = I18n::msg('action_updated');
        }

        if ('' != $goon) {
            $save = false;
        } else {
            $function = '';
        }
    }

    if (!$save) {
        if ('edit' == $function) {
            $legend = I18n::msg('action_edit') . ' <small class="rex-primary-id">' . I18n::msg('id') . '=' . $actionId . '</small>';

            $action = Sql::factory();
            $action->setQuery('SELECT * FROM ' . Core::getTablePrefix() . 'action WHERE id=?', [$actionId]);

            $name = $action->getValue('name');
            $previewaction = $action->getValue('preview');
            $presaveaction = $action->getValue('presave');
            $postsaveaction = $action->getValue('postsave');
            $previewstatus = $action->getValue('previewmode');
            $presavestatus = $action->getValue('presavemode');
            $postsavestatus = $action->getValue('postsavemode');
        } else {
            $legend = I18n::msg('action_create');
        }

        // PreView action macht nur bei add und edit Sinn da,
        // - beim Delete kommt keine View
        $options = [
            1 => $ASTATUS[0] . ' - ' . I18n::msg('action_event_add'),
            2 => $ASTATUS[1] . ' - ' . I18n::msg('action_event_edit'),
        ];

        $selPreviewStatus = new ActionEventSelect($options);
        $selPreviewStatus->setName('previewstatus[]');
        $selPreviewStatus->setId('previewstatus');
        $selPreviewStatus->setStyle('class="form-control"');

        $options = [
            1 => $ASTATUS[0] . ' - ' . I18n::msg('action_event_add'),
            2 => $ASTATUS[1] . ' - ' . I18n::msg('action_event_edit'),
            4 => $ASTATUS[2] . ' - ' . I18n::msg('action_event_delete'),
        ];

        $selPresaveStatus = new ActionEventSelect($options);
        $selPresaveStatus->setName('presavestatus[]');
        $selPresaveStatus->setId('presavestatus');
        $selPresaveStatus->setStyle('class="form-control"');

        $selPostsaveStatus = new ActionEventSelect($options);
        $selPostsaveStatus->setName('postsavestatus[]');
        $selPostsaveStatus->setId('postsavestatus');
        $selPostsaveStatus->setStyle('class="form-control"');

        $allPreviewChecked = 3 == $previewstatus ? ' checked="checked"' : '';
        foreach ([1, 2, 4] as $var) {
            if (($previewstatus & $var) == $var) {
                $selPreviewStatus->setSelected($var);
            }
        }

        $allPresaveChecked = 7 == $presavestatus ? ' checked="checked"' : '';
        foreach ([1, 2, 4] as $var) {
            if (($presavestatus & $var) == $var) {
                $selPresaveStatus->setSelected($var);
            }
        }

        $allPostsaveChecked = 7 == $postsavestatus ? ' checked="checked"' : '';
        foreach ([1, 2, 4] as $var) {
            if (($postsavestatus & $var) == $var) {
                $selPostsaveStatus->setSelected($var);
            }
        }

        $btnUpdate = '';
        if ('add' != $function) {
            $btnUpdate = '<button class="btn btn-apply" type="submit" name="goon" value="1"' . Core::getAccesskey(I18n::msg('save_and_goon_tooltip'), 'apply') . '>' . I18n::msg('save_action_and_continue') . '</button>';
        }

        if ('' != $success) {
            $message .= Message::success($success);
        }

        if ('' != $error) {
            $message .= Message::error($error);
        }

        $panel = '';
        $panel .= '<fieldset>
                        <input type="hidden" name="function" value="' . $function . '" />
                        <input type="hidden" name="save" value="1" />
                        <input type="hidden" name="action_id" value="' . $actionId . '" />';

        $formElements = [];

        $n = [];
        $n['label'] = '<label for="name">' . I18n::msg('action_name') . '</label>';
        $n['field'] = '<input class="form-control" type="text" id="name" name="name" value="' . escape($name) . '" maxlength="255" />';
        $n['note'] = I18n::msg('translatable');
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('flush', true);
        $fragment->setVar('elements', $formElements, false);
        $panel .= $fragment->parse('core/form/form.php');

        $panel .= '
                    </fieldset>

                    <fieldset>
                        <legend>' . I18n::msg('action_heading_preview') . ' <small>' . I18n::msg('action_mode_preview') . '</small></legend>';

        $formElements = [];
        $n = [];
        $n['label'] = '<label for="previewaction">' . I18n::msg('input') . '</label>';
        $n['field'] = '<textarea class="form-control rex-code rex-js-code" name="previewaction" id="previewaction" autocapitalize="off" autocorrect="off" spellcheck="false">' . escape($previewaction) . '</textarea>';
        $n['note'] = I18n::msg('action_hint', '<var>ArticleAction $this</var>');
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('flush', true);
        $fragment->setVar('elements', $formElements, false);
        $panel .= $fragment->parse('core/form/form.php');

        $formElements = [];
        $n = [];
        $n['reverse'] = true;
        $n['label'] = '<label>' . I18n::msg('action_event_all') . '</label>';
        $n['field'] = '<input id="rex-js-preview-allevents" type="checkbox" name="preview_allevents" ' . $allPreviewChecked . ' />';
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);
        $panel .= $fragment->parse('core/form/checkbox.php');

        $formElements = [];
        $n = [];
        $n['id'] = 'rex-js-preview-events';
        $n['label'] = '<label for="previestatus">' . I18n::msg('action_event') . '</label>';
        $n['field'] = $selPreviewStatus->get();
        $n['note'] = I18n::msg('ctrl');
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('flush', true);
        $fragment->setVar('elements', $formElements, false);
        $panel .= $fragment->parse('core/form/form.php');

        $panel .= '
                    </fieldset>

                    <fieldset>
                        <legend>' . I18n::msg('action_heading_presave') . ' <small>' . I18n::msg('action_mode_presave') . '</small></legend>';

        $formElements = [];
        $n = [];
        $n['label'] = '<label for="presaveaction">' . I18n::msg('input') . '</label>';
        $n['field'] = '<textarea class="form-control rex-code rex-js-code" name="presaveaction" id="presaveaction" autocapitalize="off" autocorrect="off" spellcheck="false">' . escape($presaveaction) . '</textarea>';
        $n['note'] = I18n::msg('action_hint', '<var>ArticleAction $this</var>');
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('flush', true);
        $fragment->setVar('elements', $formElements, false);
        $panel .= $fragment->parse('core/form/form.php');

        $formElements = [];
        $n = [];
        $n['label'] = '<label>' . I18n::msg('action_event_all') . '</label>';
        $n['field'] = '<input id="rex-js-presave-allevents" type="checkbox" name="presave_allevents" ' . $allPresaveChecked . ' />';
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);
        $panel .= $fragment->parse('core/form/checkbox.php');

        $formElements = [];
        $n = [];
        $n['id'] = 'rex-js-presave-events';
        $n['label'] = '<label for="presavestatus">' . I18n::msg('action_event') . '</label>';
        $n['field'] = $selPresaveStatus->get();
        $n['note'] = I18n::msg('ctrl');
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('flush', true);
        $fragment->setVar('elements', $formElements, false);
        $panel .= $fragment->parse('core/form/form.php');

        $panel .= '
                    </fieldset>


                    <fieldset>
                        <legend>' . I18n::msg('action_heading_postsave') . ' <small>' . I18n::msg('action_mode_postsave') . '</small></legend>';

        $formElements = [];
        $n = [];
        $n['label'] = '<label for="postsaveaction">' . I18n::msg('input') . '</label>';
        $n['field'] = '<textarea class="form-control rex-code rex-js-code" name="postsaveaction" id="postsaveaction" autocapitalize="off" autocorrect="off" spellcheck="false">' . escape($postsaveaction) . '</textarea>';
        $n['note'] = I18n::msg('action_hint', '<var>ArticleAction $this</var>');
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('flush', true);
        $fragment->setVar('elements', $formElements, false);
        $panel .= $fragment->parse('core/form/form.php');

        $formElements = [];
        $n = [];
        $n['label'] = '<label>' . I18n::msg('action_event_all') . '</label>';
        $n['field'] = '<input id="rex-js-postsave-allevents" type="checkbox" name="postsave_allevents" ' . $allPostsaveChecked . ' />';
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);
        $panel .= $fragment->parse('core/form/checkbox.php');

        $formElements = [];
        $n = [];
        $n['id'] = 'rex-js-postsave-events';
        $n['label'] = '<label for="postsavestatus">' . I18n::msg('action_event') . '</label>';
        $n['field'] = $selPostsaveStatus->get();
        $n['note'] = I18n::msg('ctrl');
        $formElements[] = $n;

        $fragment = new Fragment();
        $fragment->setVar('flush', true);
        $fragment->setVar('elements', $formElements, false);
        $panel .= $fragment->parse('core/form/form.php');

        $panel .= '</fieldset>';

        $formElements = [];

        $fragment = new Fragment();

        $n = [];
        $n['field'] = '<a class="btn btn-abort" href="' . Url::currentBackendPage() . '">' . I18n::msg('form_abort') . '</a>';
        $formElements[] = $n;

        $n = [];
        $n['field'] = '<button class="btn btn-save rex-form-aligned" type="submit"' . Core::getAccesskey(I18n::msg('save_and_close_tooltip'), 'save') . '>' . I18n::msg('save_action_and_quit') . '</button>';
        $formElements[] = $n;

        if ('' != $btnUpdate) {
            $n = [];
            $n['field'] = $btnUpdate;
            $formElements[] = $n;
        }

        $fragment->setVar('elements', $formElements, false);
        $buttons = $fragment->parse('core/form/submit.php');

        $fragment = new Fragment();
        $fragment->setVar('class', 'edit', false);
        $fragment->setVar('title', $legend, false);
        $fragment->setVar('body', $panel, false);
        $fragment->setVar('buttons', $buttons, false);
        $content = $fragment->parse('core/page/section.php');

        $content = '
        <form id="rex-form-action" action="' . Url::currentBackendPage() . '" method="post">
            ' . $csrfToken->getHiddenField() . '
            ' . $content . '
        </form>
        <script type="text/javascript" nonce="' . Response::getNonce() . '">
        <!--

        jQuery(function($) {
            var eventTypes = "#rex-js-preview #rex-js-presave #rex-js-postsave";

            $(eventTypes.split(" ")).each(function() {
                var eventType = this;
                $(eventType+ "-allevents").click(function() {
                    $(eventType+"-events").slideToggle("slow");
                });

                if($(eventType+"-allevents").is(":checked")) {
                    $(eventType+"-events").hide();
                }
            });
        });

        -->
        </script>
        ';

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

    // ausgabe actionsliste !
    $content .= '
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th class="rex-table-icon"><a class="rex-link-expanded" href="' . Url::currentBackendPage(['function' => 'add']) . '"' . Core::getAccesskey(I18n::msg('action_create'), 'add') . ' title="' . I18n::msg('action_create') . '"><i class="rex-icon rex-icon-add-action"></i></a></th>
                    <th class="rex-table-id">' . I18n::msg('id') . '</th>
                    <th>' . I18n::msg('action_name') . '</th>
                    <th>' . I18n::msg('action_header_preview') . '</th>
                    <th>' . I18n::msg('action_header_presave') . '</th>
                    <th>' . I18n::msg('action_header_postsave') . '</th>
                    <th class="rex-table-action" colspan="2">' . I18n::msg('action_functions') . '</th>
                </tr>
            </thead>
        ';

    $sql = Sql::factory();
    $sql->setQuery('SELECT * FROM ' . Core::getTablePrefix() . 'action ORDER BY name');
    $rows = $sql->getRows();

    if ($rows > 0) {
        $content .= '<tbody>' . "\n";

        for ($i = 0; $i < $rows; ++$i) {
            $previewmode = [];
            $presavemode = [];
            $postsavemode = [];

            foreach ([1 => 'ADD', 2 => 'EDIT', 4 => 'DELETE'] as $var => $value) {
                if (($sql->getValue('previewmode') & $var) == $var) {
                    $previewmode[] = $value;
                }
            }

            foreach ([1 => 'ADD', 2 => 'EDIT', 4 => 'DELETE'] as $var => $value) {
                if (($sql->getValue('presavemode') & $var) == $var) {
                    $presavemode[] = $value;
                }
            }

            foreach ([1 => 'ADD', 2 => 'EDIT', 4 => 'DELETE'] as $var => $value) {
                if (($sql->getValue('postsavemode') & $var) == $var) {
                    $postsavemode[] = $value;
                }
            }

            $content .= '
                        <tr>
                            <td class="rex-table-icon"><a class="rex-link-expanded" href="' . Url::currentBackendPage(['action_id' => $sql->getValue('id'), 'function' => 'edit']) . '" title="' . escape($sql->getValue('name')) . '"><i class="rex-icon rex-icon-action"></i></a></td>
                            <td class="rex-table-id" data-title="' . I18n::msg('id') . '">' . (int) $sql->getValue('id') . '</td>
                            <td data-title="' . I18n::msg('action_name') . '"><a class="rex-link-expanded" href="' . Url::currentBackendPage(['action_id' => $sql->getValue('id'), 'function' => 'edit']) . '">' . escape($sql->getValue('name')) . '</a></td>
                            <td data-title="' . I18n::msg('action_header_preview') . '">' . implode('/', $previewmode) . '</td>
                            <td data-title="' . I18n::msg('action_header_presave') . '">' . implode('/', $presavemode) . '</td>
                            <td data-title="' . I18n::msg('action_header_postsave') . '">' . implode('/', $postsavemode) . '</td>
                            <td class="rex-table-action"><a class="rex-link-expanded" href="' . Url::currentBackendPage(['action_id' => $sql->getValue('id'), 'function' => 'edit']) . '"><i class="rex-icon rex-icon-edit"></i> ' . I18n::msg('change') . '</a></td>
                            <td class="rex-table-action"><a class="rex-link-expanded" href="' . Url::currentBackendPage(['action_id' => $sql->getValue('id'), 'function' => 'delete'] + $csrfToken->getUrlParams()) . '" data-confirm="' . I18n::msg('action_delete') . ' ?"><i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('delete') . '</a></td>
                        </tr>
                    ';

            $sql->next();
        }

        $content .= '</tbody>' . "\n";
    }

    $content .= '
        </table>';

    if ($rows < 1) {
        $content .= Message::info(I18n::msg('actions_not_found'));
    }

    echo $message;

    $fragment = new Fragment();
    $fragment->setVar('title', I18n::msg('action_caption'), false);
    $fragment->setVar('content', $content, false);
    echo $fragment->parse('core/page/section.php');
}
