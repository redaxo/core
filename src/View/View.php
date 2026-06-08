<?php

namespace Redaxo\Core\View;

use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Backend\Navigation;
use Redaxo\Core\Backend\Page;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Core;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\Select\MediaCategorySelect;
use Redaxo\Core\Http\Context;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Formatter;

use function count;
use function ini_get;
use function is_array;
use function is_string;

final class View
{
    private function __construct() {}

    /** Returns a toolbar. */
    public static function toolbar(string $content, ?string $brand = null, ?string $cssClass = null, bool $inverse = false): string
    {
        $fragment = new Fragment();
        $fragment->setVar('inverse', $inverse);
        $fragment->setVar('cssClass', $cssClass);
        $fragment->setVar('brand', $brand);
        $fragment->setVar('content', $content, false);

        return $fragment->parse('core/toolbar.php');
    }

    /** Returns a content block. */
    public static function content(string $content, string $title = ''): string
    {
        $fragment = new Fragment();
        $fragment->setVar('title', $title, false);
        $fragment->setVar('body', $content, false);
        return $fragment->parse('core/page/section.php');
    }

    /**
     * Returns the formatted title.
     *
     * @param string|array<Page>|null $subtitle
     *
     * @psalm-taint-sink html $head
     * @psalm-taint-sink html $subtitle
     */
    public static function title(string $head, string|array|null $subtitle = null): string
    {
        if (null === $subtitle) {
            $subtitle = Controller::getPageObject(Controller::getCurrentPagePart(1))->getSubpages();
        }

        if (is_array($subtitle) && count($subtitle) > 0) {
            $nav = Navigation::factory();
            $nav->setHeadline('default', I18n::msg('subnavigation', $head));
            foreach ($subtitle as $pageObj) {
                if (!$pageObj instanceof Page) {
                    throw new InvalidArgumentException('Expecting $subtitle to be a string or an array of ' . Page::class . ' objects.');
                }

                $nav->addPage($pageObj);
            }
            $blocks = $nav->getNavigation();
            $navigation = [];
            if (1 == count($blocks)) {
                $navigation = current($blocks);
                $navigation = $navigation['navigation'];
            }

            if (!empty($navigation)) {
                $fragment = new Fragment();
                $fragment->setVar('left', $navigation, false);
                $subtitle = $fragment->parse('core/navigations/content.php');
            } else {
                $subtitle = '';
            }
        } elseif (!is_string($subtitle)) {
            $subtitle = '';
        }

        $title = Extension::dispatch(new ExtensionPoint('PAGE_TITLE', $head));

        $fragment = new Fragment();
        $fragment->setVar('heading', $title, false);
        $fragment->setVar('subtitle', $subtitle, false);
        $return = $fragment->parse('core/page/header.php');

        return $return . Extension::dispatch(new ExtensionPoint('PAGE_TITLE_SHOWN', ''));
    }

    /** Returns a clang switch. */
    public static function clangSwitch(Context $context, bool $asDropDown = true): string
    {
        if (1 == Language::count()) {
            return '';
        }

        if ($asDropDown && Language::count() >= 4) {
            return self::clangSwitchAsDropdown($context);
        }

        $items = [];
        foreach (Language::getAll() as $id => $clang) {
            if (Core::requireUser()->getComplexPerm('clang')->hasPerm($id)) {
                $icon = $id === $context->getParam('clang') ? '<i class="rex-icon rex-icon-language-active"></i> ' : '<i class="rex-icon rex-icon-language"></i> ';
                $item = [];
                $item['href'] = $context->getUrl(['clang' => $id]);
                $item['title'] = $icon . I18n::translate($clang->name);
                if ($id === $context->getParam('clang')) {
                    $item['active'] = true;
                }
                $items[] = $item;
            }
        }
        $fragment = new Fragment();
        $fragment->setVar('left', $items, false);

        return $fragment->parse('core/navigations/content.php');
    }

    /** Returns a clang switch. */
    public static function clangSwitchAsButtons(Context $context, bool $asDropDown = true): string
    {
        if (1 == Language::count()) {
            return '';
        }

        if ($asDropDown && Language::count() >= 4) {
            return self::clangSwitchAsDropdown($context);
        }

        $items = [];
        foreach (Language::getAll() as $id => $clang) {
            if (Core::requireUser()->getComplexPerm('clang')->hasPerm($id)) {
                $icon = $clang->isOnline() ? '<i class="rex-icon rex-icon-online"></i> ' : '<i class="rex-icon rex-icon-offline"></i> ';
                $item = [];
                $item['label'] = $icon . I18n::translate($clang->name);
                $item['url'] = $context->getUrl(['clang' => $id]);
                $item['attributes']['class'][] = 'btn-clang';
                $item['attributes']['title'] = I18n::translate($clang->name);
                if ($id === $context->getParam('clang')) {
                    $item['attributes']['class'][] = 'active';
                }
                $items[] = $item;
            }
        }

        $fragment = new Fragment();
        $fragment->setVar('buttons', $items, false);
        return '<div class="rex-nav-btn rex-nav-language"><div class="btn-toolbar">' . $fragment->parse('core/buttons/button_group.php') . '</div></div>';
    }

    /** Returns a clang switch. */
    public static function clangSwitchAsDropdown(Context $context): string
    {
        if (1 == Language::count()) {
            return '';
        }

        $user = Core::requireUser();

        $buttonLabel = '';
        $items = [];
        foreach (Language::getAll() as $id => $clang) {
            if ($user->getComplexPerm('clang')->hasPerm($id)) {
                $item = [];
                $item['title'] = I18n::translate($clang->name);
                $item['href'] = $context->getUrl(['clang' => $id]);
                if ($id === $context->getParam('clang')) {
                    $item['active'] = true;
                    $buttonLabel = I18n::translate($clang->name);
                }
                $items[] = $item;
            }
        }

        $fragment = new Fragment();
        $fragment->setVar('class', 'rex-language');
        $fragment->setVar('button_prefix', I18n::msg('language'));
        $fragment->setVar('button_label', $buttonLabel);
        $fragment->setVar('header', I18n::msg('clang_select'));
        $fragment->setVar('items', $items, false);

        if ($user->admin) {
            $fragment->setVar('footer', '<a href="' . Url::backendPage('system/lang') . '"><i class="fa fa-flag"></i> ' . I18n::msg('languages_edit') . '</a>', false);
        }

        return $fragment->parse('core/dropdowns/dropdown.php');
    }

    /** @internal */
    public static function structureBreadcrumb(int $categoryId, int $articleId, int $clang): string
    {
        $navigation = [];

        $objectId = $articleId > 0 ? $articleId : $categoryId;
        $object = Article::get($objectId, $clang);
        if ($object) {
            $tree = $object->getParentTree();
            if (!$object->isStartArticle()) {
                $tree[] = $object;
            }
            foreach ($tree as $parent) {
                $id = $parent->id;
                if (Core::requireUser()->getComplexPerm('structure')->hasCategoryPerm($id)) {
                    $n = [];
                    $n['title'] = str_replace(' ', '&nbsp;', escape($parent->name));
                    if ($parent instanceof Category) {
                        $n['href'] = Url::backendPage('structure', ['category_id' => $id, 'clang' => $clang]);
                    }
                    $navigation[] = $n;
                }
            }
        }

        $title = '<a class="rex-link-expanded" href="' . Url::backendPage('structure', ['category_id' => 0, 'clang' => $clang]) . '"><i class="rex-icon rex-icon-structure-root-level"></i> ' . I18n::msg('root_level') . '</a>';

        $fragment = new Fragment();
        $fragment->setVar('id', 'rex-js-structure-breadcrumb', false);
        $fragment->setVar('title', $title, false);
        $fragment->setVar('items', $navigation, false);
        return $fragment->parse('core/navigations/breadcrumb.php');
    }

    /**
     * Ausgabe des Medienpool Formulars.
     *
     * @internal
     */
    public static function mediaPoolMediaForm(string $formTitle, string $buttonTitle, int $rexFileCategory, bool $fileChooser, bool $closeForm): string
    {
        $s = '';

        $catsSel = new MediaCategorySelect();
        $catsSel->setStyle('class="form-control"');
        $catsSel->setSize(1);
        $catsSel->setName('rex_file_category');
        $catsSel->setId('rex-mediapool-category');
        $catsSel->setAttribute('class', 'selectpicker form-control');
        $catsSel->setAttribute('data-live-search', 'true');
        $catsSel->setAttribute('onchange', 'this.form.submit()');
        $catsSel->setSelected($rexFileCategory);

        if (Core::requireUser()->getComplexPerm('media')->hasAll()) {
            $catsSel->addOption(I18n::msg('pool_kats_no'), '0');
        }

        $argFields = '';
        foreach (Request::request('args', 'array') as $argName => $argValue) {
            $argFields .= '<input type="hidden" name="args[' . escape($argName) . ']" value="' . escape($argValue) . '" />' . "\n";
        }

        $openerInputField = Request::request('opener_input_field', 'string');
        if ('' != $openerInputField) {
            $argFields .= '<input type="hidden" name="opener_input_field" value="' . escape($openerInputField) . '" />' . "\n";
        }

        $addSubmit = '';
        if ($closeForm && '' != $openerInputField) {
            $addSubmit = '<button class="btn btn-save" type="submit" name="saveandexit" value="' . I18n::msg('pool_file_upload_get') . '"' . Core::getAccesskey(I18n::msg('save_and_close_tooltip'), 'save') . '>' . I18n::msg('pool_file_upload_get') . '</button>';
        }

        $panel = '';
        $panel .= '<fieldset>';
        $formElements = [];

        $e = [];
        $e['label'] = '<label for="rex-mediapool-category">' . I18n::msg('pool_file_category') . '</label>';
        $e['field'] = $catsSel->get();
        $formElements[] = $e;

        $e = [];
        $e['label'] = '<label for="rex-mediapool-title">' . I18n::msg('pool_file_title') . '</label>';
        $e['field'] = '<input class="form-control" type="text" id="rex-mediapool-title" name="ftitle" value="' . escape(Request::post('ftitle', 'string')) . '" maxlength="255" />';
        $formElements[] = $e;

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);
        $panel .= $fragment->parse('core/form/form.php');

        $panel .= Extension::dispatch(new ExtensionPoint('MEDIA_FORM_ADD', ''));

        if ($fileChooser) {
            $e = [];
            $e['label'] = '<label for="rex-mediapool-choose-file">' . I18n::msg('pool_file_file') . '</label>';
            $e['field'] = '<input id="rex-mediapool-choose-file" type="file" name="file_new" />';
            $e['after'] = '<h3>' . I18n::msg('phpini_settings') . '</h3>
                        <dl class="dl-horizontal text-left">
                        ' . ((0 == ini_get('file_uploads')) ? '<dt><span class="text-warning">' . I18n::msg('pool_upload') . '</span></dt><dd><span class="text-warning">' . I18n::msg('pool_upload_disabled') . '</span></dd>' : '') . '
                            <dt>' . I18n::msg('pool_max_uploadsize') . ':</dt><dd>' . Formatter::bytes(ini_get('upload_max_filesize')) . '</dd>
                            <dt>' . I18n::msg('pool_max_uploadtime') . ':</dt><dd>' . ini_get('max_input_time') . 's</dd>
                        </dl>';

            $fragment = new Fragment();
            $fragment->setVar('elements', [$e], false);
            $panel .= $fragment->parse('core/form/form.php');
        }
        $panel .= '</fieldset>';

        $formElements = [];

        $e = [];
        $e['field'] = '<button class="btn btn-save rex-form-aligned" type="submit" name="save" value="' . $buttonTitle . '"' . Core::getAccesskey($buttonTitle, 'save') . '>' . $buttonTitle . '</button>';
        $formElements[] = $e;

        $e = [];
        $e['field'] = $addSubmit;
        $formElements[] = $e;

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);
        $buttons = $fragment->parse('core/form/submit.php');

        $fragment = new Fragment();
        $fragment->setVar('class', 'edit', false);
        $fragment->setVar('title', $formTitle, false);
        $fragment->setVar('body', $panel, false);
        $fragment->setVar('buttons', $buttons, false);
        $content = $fragment->parse('core/page/section.php');

        $s .= ' <form action="' . Url::currentBackendPage() . '" method="post" enctype="multipart/form-data" data-pjax="false">
                ' . CsrfToken::factory('mediapool')->getHiddenField() . '
                <fieldset>
                    <input type="hidden" name="media_method" value="add_file" />
                    ' . $argFields . '
                    ' . $content . '
                </fieldset>
            ';

        if ($closeForm) {
            $s .= '</form>' . "\n";
        }

        return $s;
    }
}
