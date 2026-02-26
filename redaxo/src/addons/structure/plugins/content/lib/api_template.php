<?php

/**
 * Template Objekt.
 * Zuständig für die Verarbeitung eines Templates.
 *
 * @package redaxo\structure\content
 */
class rex_template
{
    private int $id;
    private ?string $key = '';

    public function __construct($templateId)
    {
        $this->id = (int) $templateId;
    }

    /** @return int */
    public static function getDefaultId()
    {
        return rex_config::get('structure/content', 'default_template_id', 1);
    }

    public static function forKey(string $templateKey): ?self
    {
        $mapping = self::getKeyMapping();

        if (false !== $id = array_search($templateKey, $mapping, true)) {
            $template = new self($id);
            $template->key = $templateKey;

            return $template;
        }

        return null;
    }

    /** @return int */
    public function getId()
    {
        return $this->id;
    }

    public function getKey(): ?string
    {
        // key will never be empty string in the db
        if ('' === $this->key) {
            $this->key = self::getKeyMapping()[$this->id] ?? null;
            assert('' !== $this->key);
        }

        return $this->key;
    }

    /** @return false|string */
    public function getFile()
    {
        if ($this->getId() < 1) {
            return false;
        }

        $file = rex_template_cache::getPath($this->id);

        if (!is_file($file)) {
            rex_template_cache::generate($this->id);
        }

        return $file;
    }

    /**
     * @deprecated since structure 2.11, use `rex_template_cache::getPath` instead
     *
     * @return false|string
     */
    public static function getFilePath($templateId)
    {
        if ($templateId < 1) {
            return false;
        }

        return rex_template_cache::getPath($templateId);
    }

    /**
     * @deprecated since structure 2.11, use `rex_template_cache` instead
     *
     * @return string
     */
    public static function getTemplatesDir()
    {
        return rex_path::addonCache('structure', 'templates');
    }

    /** @return false|string|null */
    public function getTemplate()
    {
        $file = $this->getFile();
        if (!$file) {
            return false;
        }

        return rex_file::get($file);
    }

    /**
     * @deprecated since structure 2.11, use `rex_template_cache::generate` instead
     *
     * @return bool
     */
    public function generate()
    {
        rex_template_cache::generate($this->id);
        return true;
    }

    /**
     * @deprecated since structure 2.11, use `rex_template_cache::delete` instead
     *
     * @return bool
     */
    public function deleteCache()
    {
        if ($this->id < 1) {
            return false;
        }

        rex_template_cache::delete($this->id);
        return true;
    }

    /**
     * Returns an array containing all templates which are available for the given category_id.
     * if the category_id is non-positive all templates in the system are returned.
     * if the category_id is invalid an empty array is returned.
     *
     * @param int $categoryId
     * @param bool $ignoreInactive
     *
     * @return array<int, string>
     */
    public static function getTemplatesForCategory($categoryId, $ignoreInactive = true)
    {
        $templates = [];
        $tSql = rex_sql::factory();
        $where = $ignoreInactive ? ' WHERE active=1' : '';
        $tSql->setQuery('select id,name,attributes from ' . rex::getTablePrefix() . 'template' . $where . ' order by name');

        if ($categoryId < 1) {
            // Alle globalen Templates
            foreach ($tSql as $row) {
                $attributes = $row->getArrayValue('attributes');
                $categories = $attributes['categories'] ?? [];
                if (!is_array($categories) || (isset($categories['all']) && 1 == $categories['all'])) {
                    $templates[(int) $row->getValue('id')] = (string) $row->getValue('name');
                }
            }
        } else {
            if ($c = rex_category::get($categoryId)) {
                $path = $c->getPathAsArray();
                $path[] = $categoryId;
                foreach ($tSql as $row) {
                    $attributes = $row->getArrayValue('attributes');
                    $categories = $attributes['categories'] ?? [];
                    // template ist nicht kategoriespezifisch -> includen
                    if (!is_array($categories) || (isset($categories['all']) && 1 == $categories['all'])) {
                        $templates[(int) $row->getValue('id')] = (string) $row->getValue('name');
                    } else {
                        // template ist auf kategorien beschraenkt..
                        // nachschauen ob eine davon im pfad der aktuellen kategorie liegt
                        foreach ($path as $p) {
                            if (in_array($p, $categories)) {
                                $templates[(int) $row->getValue('id')] = (string) $row->getValue('name');
                                break;
                            }
                        }
                    }
                }
            }
        }
        return $templates;
    }

    /** @return bool */
    public static function hasModule(array $templateAttributes, $ctype, $moduleId)
    {
        $templateModules = $templateAttributes['modules'] ?? [];
        if (!isset($templateModules[$ctype]['all']) || 1 == $templateModules[$ctype]['all']) {
            return true;
        }

        return is_array($templateModules[$ctype]) && in_array($moduleId, $templateModules[$ctype]);
    }

    /** @return array<int, string> */
    private static function getKeyMapping(): array
    {
        static $mapping;

        if (null !== $mapping) {
            return $mapping;
        }

        $file = rex_template_cache::getKeyMappingPath();
        $mapping = rex_file::getCache($file, null);

        if (null !== $mapping) {
            return $mapping;
        }

        rex_template_cache::generateKeyMapping();

        return $mapping = rex_file::getCache($file);
    }

    /** @return list<rex_ctype> */
    public function getCtypes(): array
    {
        return rex_ctype::forTemplate($this->id);
    }

    /** @return false|string */
    public static function templateIsInUse(int $templateId, string $msgKey)
    {
        $check = rex_sql::factory();
        $check->setQuery('
            SELECT article.id, article.clang_id, template.name
            FROM ' . rex::getTable('article') . ' article
            LEFT JOIN ' . rex::getTable('template') . ' template ON article.template_id=template.id
            WHERE article.template_id=?
            LIMIT 20
        ', [$templateId]);

        if (!$check->getRows()) {
            return false;
        }
        $templateInUseMessage = '';
        $error = '';
        $templatename = $check->getRows() ? $check->getValue('template.name') : null;
        while ($check->hasNext()) {
            $aid = (int) $check->getValue('article.id');
            $clangId = (int) $check->getValue('article.clang_id');
            $article = rex_article::get($aid, $clangId);
            if (null == $article) {
                continue;
            }
            $label = $article->getName() . ' [' . $aid . ']';
            if (rex_clang::count() > 1) {
                $clang = rex_clang::get($clangId);
                if (null == $clang) {
                    continue;
                }
                $label .= ' [' . $clang->getCode() . ']';
            }

            $templateInUseMessage .= '<li><a href="' . rex_url::backendPage('content', ['article_id' => $aid, 'clang' => $clangId]) . '">' . rex_escape($label) . '</a></li>';
            $check->next();
        }

        if (null == $templatename) {
            $check->setQuery('SELECT name FROM ' . rex::getTable('template') . ' WHERE id = ' . $templateId);
            $templatename = $check->getValue('name');
        }

        if ('' != $templateInUseMessage && null != $templatename) {
            $error .= rex_i18n::msg($msgKey, (string) $templatename);
            $error .= '<ul>' . $templateInUseMessage . '</ul>';
        }

        return $error;
    }

    public static function exists(int $templateId): bool
    {
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT 1 FROM ' . rex::getTable('template') . ' WHERE id = ?', [$templateId]);
        return 1 === $sql->getRows();
    }
}
