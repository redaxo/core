<?php

namespace Redaxo\Core\Form\Select;

use Collator;
use Locale;
use Redaxo\Core\Content\Template;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Translation\I18n;

use function count;

final class TemplateSelect extends Select
{
    private bool $loaded = false;
    /** @var array<string, Template>|null */
    private ?array $templates = null;
    private readonly int $clangId;

    public function __construct(
        private readonly ?int $categoryId = null,
        ?int $clangId = null,
    ) {
        $this->clangId = $clangId ?? Language::getCurrentId();

        parent::__construct();
    }

    /** @return string */
    public function get()
    {
        if (!$this->loaded) {
            $templates = $this->getTemplates();

            if (count($templates) > 0) {
                $templateNames = array_map(static fn (Template $template) => I18n::translate($template->name), $templates);
                new Collator(Locale::getDefault())->asort($templateNames);

                foreach ($templateNames as $templateKey => $templateName) {
                    $this->addOption($templateName, $templateKey);
                }
            } else {
                $this->addOption(I18n::msg('option_no_template'), '');
            }

            $this->loaded = true;
        }

        return parent::get();
    }

    /** @return void */
    public function setSelectedFromStartArticle()
    {
        $selected = null;

        // Inherit template from start article
        if ($this->categoryId > 0) {
            $sql = Sql::factory();
            $sql->setQuery('SELECT template FROM ' . Core::getTable('article') . ' WHERE id = ? AND clang_id = ? AND startarticle = 1', [
                $this->categoryId,
                $this->clangId,
            ]);
            if (1 == $sql->getRows()) {
                $selected = $sql->getValue('template');
            }
        }

        $templates = $this->getTemplates();
        if (!$selected || !isset($templates[$selected])) {
            $selected = Template::getDefaultKey();
        }

        if (null !== $selected && isset($templates[$selected])) {
            parent::setSelected($selected);
        }
    }

    /** @return array<string, Template> */
    public function getTemplates(): array
    {
        if (null !== $this->templates) {
            return $this->templates;
        }

        if (null === $this->categoryId) {
            return $this->templates = Template::getAll();
        }

        return $this->templates = Template::getTemplatesForCategory($this->categoryId);
    }
}
