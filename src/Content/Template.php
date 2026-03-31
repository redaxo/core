<?php

namespace Redaxo\Core\Content;

use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Translation\I18n;

use function assert;
use function in_array;
use function is_array;
use function Redaxo\Core\View\escape;

class Template
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
        return Core::getConfig('default_template_id', 1);
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

        $file = TemplateCache::getPath($this->id);

        if (!is_file($file)) {
            TemplateCache::generate($this->id);
        }

        return $file;
    }

    /**
     * @return false|string|null
     */
    public function getTemplate()
    {
        $file = $this->getFile();
        if (!$file) {
            return false;
        }

        return File::get($file);
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
        $tSql = Sql::factory();
        $where = $ignoreInactive ? ' WHERE active=1' : '';
        $tSql->setQuery('select id,name,attributes from ' . Core::getTablePrefix() . 'template' . $where . ' order by name');

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
            if ($c = Category::get($categoryId)) {
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

        $file = TemplateCache::getKeyMappingPath();
        $mapping = File::getCache($file, null);

        if (null !== $mapping) {
            return $mapping;
        }

        TemplateCache::generateKeyMapping();

        return $mapping = File::getCache($file);
    }

    /** @return list<ContentSection> */
    public function getCtypes(): array
    {
        return ContentSection::forTemplate($this->id);
    }

    /** @return false|string */
    public static function templateIsInUse(int $templateId, string $msgKey)
    {
        $check = Sql::factory();
        $check->setQuery('
            SELECT article.id, article.clang_id, template.name
            FROM ' . Core::getTable('article') . ' article
            LEFT JOIN ' . Core::getTable('template') . ' template ON article.template_id=template.id
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
            $article = Article::get($aid, $clangId);
            if (null == $article) {
                continue;
            }
            $label = $article->getName() . ' [' . $aid . ']';
            if (Language::count() > 1) {
                $clang = Language::get($clangId);
                if (null == $clang) {
                    continue;
                }
                $label .= ' [' . $clang->getCode() . ']';
            }

            $templateInUseMessage .= '<li><a href="' . Url::backendPage('content', ['article_id' => $aid, 'clang' => $clangId]) . '">' . escape($label) . '</a></li>';
            $check->next();
        }

        if (null == $templatename) {
            $check->setQuery('SELECT name FROM ' . Core::getTable('template') . ' WHERE id = ' . $templateId);
            $templatename = $check->getValue('name');
        }

        if ('' != $templateInUseMessage && null != $templatename) {
            $error .= I18n::msg($msgKey, (string) $templatename);
            $error .= '<ul>' . $templateInUseMessage . '</ul>';
        }

        return $error;
    }

    public static function exists(int $templateId): bool
    {
        $sql = Sql::factory();
        $sql->setQuery('SELECT 1 FROM ' . Core::getTable('template') . ' WHERE id = ?', [$templateId]);
        return 1 === $sql->getRows();
    }
}
