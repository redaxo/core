<?php

namespace Redaxo\Core\View;

use Redaxo\Core\Backend\Accesskey;
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
use Redaxo\Core\Form\Widget\SelectInput;
use Redaxo\Core\Http\Context;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\Language;
use Redaxo\Core\MediaPool\ExtensionPoint\MediaFormAdd;
use Redaxo\Core\MediaPool\MediaCategoryChoices;
use Redaxo\Core\MetaInfo\MetaForm;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Formatter;
use Redaxo\Core\View\Component\Breadcrumb;
use Redaxo\Core\View\Component\Button;
use Redaxo\Core\View\Component\ButtonGroup;
use Redaxo\Core\View\Component\Dropdown;
use Redaxo\Core\View\Component\PageHeader;
use Redaxo\Core\View\Component\Section;
use Redaxo\Core\View\Component\SectionVariant;
use Redaxo\Core\View\Component\Tabs;
use Redaxo\Core\View\Component\Toolbar;

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
        return (string) new Toolbar(Html::raw($content), Html::raw($brand ?? ''), $cssClass ?? '', $inverse)->render();
    }

    /** Returns a content block. */
    public static function content(string $content, string $title = ''): string
    {
        return (string) new Section(Html::raw($title), Html::raw($content))->render();
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
                $subtitle = (string) new Tabs($navigation)->render();
            } else {
                $subtitle = '';
            }
        } elseif (!is_string($subtitle)) {
            $subtitle = '';
        }

        $title = Extension::dispatch(new ExtensionPoint('PAGE_TITLE', $head));

        return (string) new PageHeader(Html::raw($title), navigation: Html::raw($subtitle))->render() . Extension::dispatch(new ExtensionPoint('PAGE_TITLE_SHOWN', ''));
    }

    /** Returns a language switch. */
    public static function languageSwitch(Context $context, bool $asDropDown = true): string
    {
        if (1 == Language::count()) {
            return '';
        }

        if ($asDropDown && Language::count() >= 4) {
            return self::languageSwitchAsDropdown($context);
        }

        $items = [];
        foreach (Language::getAll() as $id => $language) {
            if (Core::requireUser()->getComplexPerm('language')->hasPerm($id)) {
                $icon = $id === $context->getParam('language') ? '<i class="rex-icon rex-icon-language-active"></i> ' : '<i class="rex-icon rex-icon-language"></i> ';
                $item = [];
                $item['href'] = $context->getUrl(['language' => $id]);
                $item['title'] = $icon . I18n::translate($language->name);
                if ($id === $context->getParam('language')) {
                    $item['active'] = true;
                }
                $items[] = $item;
            }
        }

        return (string) new Tabs($items)->render();
    }

    /** Returns a language switch. */
    public static function languageSwitchAsButtons(Context $context, bool $asDropDown = true): string
    {
        if (1 == Language::count()) {
            return '';
        }

        if ($asDropDown && Language::count() >= 4) {
            return self::languageSwitchAsDropdown($context);
        }

        $buttons = [];
        foreach (Language::getAll() as $id => $language) {
            if (Core::requireUser()->getComplexPerm('language')->hasPerm($id)) {
                $buttons[] = new Button(
                    I18n::translate($language->name),
                    $context->getUrl(['language' => $id]),
                    $language->isOnline() ? 'online' : 'offline',
                    variant: null,
                    attributes: new HtmlAttributes([
                        'class' => ['btn-language', 'active' => $id === $context->getParam('language')],
                        'title' => I18n::translate($language->name),
                    ]),
                );
            }
        }

        return '<div class="rex-nav-btn rex-nav-language"><div class="btn-toolbar">' . (string) new ButtonGroup($buttons)->render() . '</div></div>';
    }

    /** Returns a language switch. */
    public static function languageSwitchAsDropdown(Context $context): string
    {
        if (1 == Language::count()) {
            return '';
        }

        $user = Core::requireUser();

        $buttonLabel = '';
        $items = [];
        foreach (Language::getAll() as $id => $language) {
            if ($user->getComplexPerm('language')->hasPerm($id)) {
                $item = [];
                $item['title'] = I18n::translate($language->name);
                $item['href'] = $context->getUrl(['language' => $id]);
                if ($id === $context->getParam('language')) {
                    $item['active'] = true;
                    $buttonLabel = I18n::translate($language->name);
                }
                $items[] = $item;
            }
        }

        return (string) new Dropdown(
            $items,
            label: $buttonLabel,
            prefix: I18n::msg('language'),
            header: I18n::msg('language_select'),
            footer: $user->admin ? Html::raw('<a href="' . Url::backendPage('system/lang') . '"><i class="fa fa-flag"></i> ' . I18n::msg('languages_edit') . '</a>') : '',
            attributes: new HtmlAttributes(['class' => ['rex-language']]),
        )->render();
    }

    /** @internal */
    public static function structureBreadcrumb(int $categoryId, int $articleId, int $languageId): string
    {
        $navigation = [];

        $objectId = $articleId > 0 ? $articleId : $categoryId;
        $object = Article::get($objectId, $languageId);
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
                        $n['href'] = Url::backendPage('structure', ['category_id' => $id, 'language' => $languageId]);
                    }
                    $navigation[] = $n;
                }
            }
        }

        $title = '<a class="rex-link-expanded" href="' . Url::backendPage('structure', ['category_id' => 0, 'language' => $languageId]) . '"><i class="rex-icon rex-icon-structure-root-level"></i> ' . I18n::msg('root_level') . '</a>';

        return (string) new Breadcrumb(
            array_map(static fn (array $item): array => ['title' => Html::raw($item['title']), 'href' => $item['href'] ?? ''], $navigation),
            Html::raw($title),
            'rex-js-structure-breadcrumb',
        )->render();
    }

    /**
     * Ausgabe des Medienpool Formulars.
     *
     * @internal
     */
    public static function mediaPoolMediaForm(string $formTitle, string $buttonTitle, int $rexFileCategory, bool $fileChooser, bool $closeForm, MetaForm $meta): string
    {
        $s = '';

        $catsSel = new SelectInput(
            'rex_file_category',
            new MediaCategoryChoices(root: true)->choices(),
            (string) $rexFileCategory,
            'rex-mediapool-category',
            search: true,
            attributes: new HtmlAttributes(['onchange' => 'this.form.requestSubmit()']),
        );

        $argFields = '';
        $types = Request::request('types', 'string');
        if ('' !== $types) {
            $argFields .= '<input type="hidden" name="types" value="' . escape($types) . '" />' . "\n";
        }

        $picker = Request::request('picker', 'string');
        if ('' !== $picker) {
            $argFields .= '<input type="hidden" name="picker" value="' . escape($picker) . '" />' . "\n";
        }

        $addSubmit = '';
        if ($closeForm && '' !== $picker) {
            $addSubmit = '<button class="btn btn-save" type="submit" name="saveandexit" value="' . I18n::msg('pool_file_upload_get') . '"' . Accesskey::attributes(I18n::msg('save_and_close_tooltip'), 'save') . '>' . I18n::msg('pool_file_upload_get') . '</button>';
        }

        $panel = '';
        $panel .= '<fieldset>';
        $formElements = [];

        $e = [];
        $e['label'] = '<label for="rex-mediapool-category">' . I18n::msg('pool_file_category') . '</label>';
        $e['field'] = $catsSel->render();
        $formElements[] = $e;

        $e = [];
        $e['label'] = '<label for="rex-mediapool-title">' . I18n::msg('pool_file_title') . '</label>';
        $e['field'] = '<input class="form-control" type="text" id="rex-mediapool-title" name="ftitle" value="' . escape(Request::post('ftitle', 'string')) . '" maxlength="255" />';
        $formElements[] = $e;

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);
        $panel .= $fragment->parse('core/form/form.php');

        $panel .= $meta->render();
        $panel .= Extension::dispatch(new MediaFormAdd());

        if ($fileChooser) {
            $e = [];
            $e['label'] = '<label for="rex-mediapool-choose-file">' . I18n::msg('pool_file_file') . '</label>';
            $e['field'] = '<input id="rex-mediapool-choose-file" type="file" name="file_new" />';
            $e['after'] = '<h3>' . I18n::msg('phpini_settings') . '</h3>
                        <dl class="dl-horizontal text-left">
                        ' . ((0 == ini_get('file_uploads')) ? '<dt><span class="text-warning">' . I18n::msg('pool_upload') . '</span></dt><dd><span class="text-warning">' . I18n::msg('pool_upload_disabled') . '</span></dd>' : '') . '
                            <dt>' . I18n::msg('pool_max_uploadsize') . ':</dt><dd>' . Formatter::bytes(Request::getMaxUploadSize()) . '</dd>
                            <dt>' . I18n::msg('pool_max_uploadtime') . ':</dt><dd>' . ini_get('max_input_time') . 's</dd>
                        </dl>';

            $fragment = new Fragment();
            $fragment->setVar('elements', [$e], false);
            $panel .= $fragment->parse('core/form/form.php');
        }
        $panel .= '</fieldset>';

        $formElements = [];

        $e = [];
        $e['field'] = '<button class="btn btn-save rex-form-aligned" type="submit" name="save" value="' . $buttonTitle . '"' . Accesskey::attributes($buttonTitle, 'save') . '>' . $buttonTitle . '</button>';
        $formElements[] = $e;

        $e = [];
        $e['field'] = $addSubmit;
        $formElements[] = $e;

        $fragment = new Fragment();
        $fragment->setVar('elements', $formElements, false);
        $buttons = $fragment->parse('core/form/submit.php');

        $content = (string) new Section(Html::raw($formTitle), Html::raw($panel), footer: Html::raw($buttons), variant: SectionVariant::Edit)->render();

        $s .= ' <form action="' . Url::currentBackendPage() . '" method="post" enctype="multipart/form-data">
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
