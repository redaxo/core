<?php

/**
 * @package redaxo\structure\content
 */
class rex_template_select extends rex_select
{
    /** @var bool */
    private $loaded = false;
    /** @var int|null */
    private $categoryId;
    /** @var array<int, string>|null */
    private $templates;
    /** @var int */
    private $clangId;

    /**
     * @param int|null $categoryId
     * @param int|null $clangId
     */
    public function __construct($categoryId = null, $clangId = null)
    {
        $this->categoryId = $categoryId;
        $this->clangId = null === $clangId ? rex_clang::getCurrentId() : (int) $clangId;

        parent::__construct();
    }

    /** @return string */
    public function get()
    {
        if (!$this->loaded) {
            $templates = $this->getTemplates();

            if (count($templates) > 0) {
                foreach ($templates as $templateId => $templateName) {
                    $this->addOption($templateName, $templateId);
                }
            } else {
                $this->addOption(rex_i18n::msg('option_no_template'), '0');
            }

            $this->loaded = true;
        }

        return parent::get();
    }

    /**
     * @throws rex_sql_exception
     * @return void
     */
    public function setSelectedFromStartArticle()
    {
        $selected = null;

        // Inherit template_id from start article
        if ($this->categoryId > 0) {
            $sql = rex_sql::factory();
            $sql->setQuery('SELECT template_id FROM ' . rex::getTable('article') . ' WHERE id = ? AND clang_id = ? AND startarticle = 1', [
                $this->categoryId,
                $this->clangId,
            ]);
            if (1 == $sql->getRows()) {
                $selected = $sql->getValue('template_id');
            }
        }

        $templates = $this->getTemplates();
        if (!$selected || !isset($templates[$selected])) {
            $selected = rex_template::getDefaultId();
        }

        if ($selected && isset($templates[$selected])) {
            parent::setSelected($selected);
        }
    }

    /** @return array<int, string> */
    public function getTemplates()
    {
        if (null === $this->templates) {
            $this->templates = [];

            if (null !== $this->categoryId) {
                $templates = rex_template::getTemplatesForCategory($this->categoryId);
            } else {
                $templates = rex_sql::factory()->getArray('SELECT id, name FROM ' . rex::getTable('template') . ' WHERE active = 1 ORDER BY name');
                $templates = array_column($templates, 'name', 'id');
            }

            foreach ($templates as $templateId => $templateName) {
                $this->templates[$templateId] = rex_i18n::translate($templateName, false);
            }
        }

        return $this->templates;
    }
}
