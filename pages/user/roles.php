<?php

use Redaxo\Core\Backend\Accesskey;
use Redaxo\Core\Backend\Form\Role;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\Form;
use Redaxo\Core\Form\Storage\DatabaseStorage;
use Redaxo\Core\Form\SubmitButton;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Translation\Translatable;
use Redaxo\Core\View\Component\ColumnType;
use Redaxo\Core\View\DataList;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\HtmlAttributes;
use Redaxo\Core\View\Message;

use function Redaxo\Core\View\escape;

$func = Request::request('func', 'string');
$id = Request::request('id', 'int');

// the form submits to the current url, so the message of the previous save must not appear again
$message = 'get' !== Request::requestMethod() ? '' : match (Request::get('saved', 'string')) {
    'added' => Message::info(I18n::msg('user_role_added')),
    'updated' => Message::info(I18n::msg('user_role_data_updated')),
    default => '',
};
$content = '';

if ('delete' == $func) {
    if (!CsrfToken::factory('user_role_delete')->isValid()) {
        $message = Message::error(I18n::msg('csrf_token_invalid'));
    } else {
        $sql = Sql::factory();
        $sql->setQuery('DELETE FROM rex_user_role WHERE id = ? LIMIT 1', [$id]);
        $message = Message::info(I18n::msg('user_role_deleted'));
    }

    $func = '';
}

if ('' == $func) {
    $title = I18n::msg('user_role_caption');

    $list = new DataList('SELECT id, name FROM rex_user_role ORDER BY name');
    $list->attributes = new HtmlAttributes(['class' => ['table-striped', 'table-hover']]);
    $editUrl = static fn (array $row): string => $list->getUrl(['func' => 'edit', 'id' => (int) $row['id']]);

    $list->column(
        'icon',
        Html::raw('<a class="rex-link-expanded" href="' . escape($list->getUrl(['func' => 'add'])) . '"' . Accesskey::attributes(I18n::msg('create_user_role'), 'add') . '><i class="rex-icon rex-icon-add-userrole"></i></a>'),
        static fn (): Html => Html::raw('<i class="rex-icon rex-icon-userrole"></i>'),
        type: ColumnType::Icon,
        link: $editUrl,
    );
    $list->column('id', I18n::msg('id'), type: ColumnType::Id);
    $list->column('name', I18n::msg('name'), link: $editUrl);
    $list->column('edit', I18n::msg('user_functions'), static fn (): Html => Html::raw('<i class="rex-icon rex-icon-edit"></i> ' . I18n::msg('edit')), type: ColumnType::Action, link: $editUrl);
    $list->column(
        'duplicate',
        null,
        static fn (): Html => Html::raw('<i class="rex-icon rex-icon-duplicate"></i> ' . I18n::msg('user_role_duplicate')),
        type: ColumnType::Action,
        link: static fn (array $row): string => $list->getUrl(['func' => 'duplicate', 'id' => (int) $row['id']]),
    );
    $list->column(
        'delete',
        null,
        static fn (): Html => Html::raw('<i class="rex-icon rex-icon-delete"></i> ' . I18n::msg('user_role_delete')),
        type: ColumnType::Action,
        link: static fn (array $row): string => $list->getUrl(['func' => 'delete', 'id' => (int) $row['id']] + CsrfToken::factory('user_role_delete')->getUrlParams()),
        linkAttributes: ['data-confirm' => I18n::msg('delete') . ' ?'],
    );

    $content .= (string) $list->render();

    $fragment = new Fragment();
    $fragment->setVar('title', $title);
    $fragment->setVar('content', $content, false);
    $content = $fragment->parse('core/page/section.php');
} else {
    $role = new Role();
    $source = new DatabaseStorage('rex_user_role', $id, globalFields: true);

    if ('add' === $func) {
        $role->grantAllItems();
    } elseif (!$source->load($role)) {
        Response::sendRedirect(Url::currentBackendPage());
    }

    // a duplicate is saved as new role
    $storage = 'edit' === $func ? $source : new DatabaseStorage('rex_user_role', globalFields: true);

    $form = new Form(
        $role,
        title: new Translatable('edit' === $func ? 'edit_user_role' : 'add_user_role'),
        buttons: [SubmitButton::save(), SubmitButton::apply()],
        cancelUrl: Url::currentBackendPage(),
    );

    if ($form->handleRequest()) {
        $storage->save($role);

        $saved = 'edit' === $func ? 'updated' : 'added';
        Response::sendRedirect(Url::currentBackendPage($form->isSubmittedBy('apply') ? ['func' => 'edit', 'id' => $storage->id, 'saved' => $saved] : ['saved' => $saved]));
    }

    $content = $form->render();
}

echo $message;
echo $content;
