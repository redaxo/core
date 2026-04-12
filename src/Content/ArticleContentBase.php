<?php

namespace Redaxo\Core\Content;

use Redaxo\Core\Content\Exception\ArticleNotFoundException;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Timer;
use Redaxo\Core\Util\Type;

use function assert;
use function in_array;
use function is_int;
use function is_object;
use function is_string;

/**
 * Klasse regelt den Zugriff auf Artikelinhalte.
 * Alle benötigten Daten werden von der DB bezogen.
 */
class ArticleContentBase
{
    /** @var string */
    public $warning;
    /** @var string */
    public $info;
    /** @var bool */
    public $debug = false;

    public ?string $template = null;

    /** @var int */
    protected $category_id;
    /** @var int */
    protected $article_id = 0;
    /** @var int */
    protected $slice_id = 0;
    /** @var int */
    protected $getSlice = 0;
    /** @var 'view'|'edit' */
    protected $mode = 'view';
    /** @var 'add'|'edit' */
    protected $function;

    /** @var int */
    protected $ctype = -1;
    /** @var int */
    protected $clang;

    /** @var bool */
    protected $eval = false;

    /** @var int */
    protected $slice_revision = 0;

    /** @var Sql|null */
    protected $ARTICLE;

    /** @var Sql|null */
    private $sliceSql;

    /**
     * @param int|null $articleId
     * @param int|null $clang
     */
    public function __construct($articleId = null, $clang = null)
    {
        if (null !== $clang) {
            $this->setClang($clang);
        } else {
            $this->setClang(Language::getCurrentId());
        }

        // ----- EXTENSION POINT
        Extension::registerPoint(new ExtensionPoint('ART_INIT', '', [
            'article' => $this,
            'article_id' => $articleId,
            'clang' => $this->clang,
        ]));

        if (null !== $articleId) {
            $this->setArticleId($articleId);
        }
    }

    /** @return Sql */
    protected function getSqlInstance()
    {
        if (!is_object($this->ARTICLE)) {
            $this->ARTICLE = Sql::factory();
            if ($this->debug) {
                $this->ARTICLE->setDebug();
            }
        }
        return $this->ARTICLE;
    }

    /**
     * @param int $sr
     * @return void
     */
    public function setSliceRevision($sr)
    {
        $this->slice_revision = (int) $sr;
    }

    // ----- Slice Id setzen für Editiermodus

    /**
     * @param int $value
     * @return void
     */
    public function setSliceId($value)
    {
        $this->slice_id = $value;
    }

    /**
     * @param int $value
     * @return void
     */
    public function setClang($value)
    {
        if (!Language::exists($value)) {
            $value = Language::getCurrentId();
        }
        $this->clang = $value;
    }

    /** @return int */
    public function getArticleId()
    {
        return $this->article_id;
    }

    /** @return int */
    public function getClangId()
    {
        return $this->clang;
    }

    /**
     * @param int $articleId
     * @return bool
     */
    public function setArticleId($articleId)
    {
        $articleId = (int) $articleId;
        $this->article_id = $articleId;

        // ---------- select article
        $sql = $this->getSqlInstance();
        $sql->setQuery('SELECT * FROM ' . Core::getTablePrefix() . 'article WHERE ' . Core::getTablePrefix() . 'article.id=? AND clang_id=?', [$articleId, $this->clang]);

        if (1 == $sql->getRows()) {
            $template = $this->getValue('template');
            $this->template = $template ? (string) $template : null;
            $this->category_id = (int) $this->getValue('category_id');
            return true;
        }

        $this->article_id = 0;
        $this->template = null;
        $this->category_id = 0;
        return false;
    }

    public function setTemplateKey(?string $templateKey): void
    {
        $this->template = $templateKey;
    }

    public function getTemplateKey(): ?string
    {
        return $this->template;
    }

    /**
     * @param 'view'|'edit' $mode
     * @return void
     */
    public function setMode($mode)
    {
        $this->mode = $mode;
    }

    /**
     * @param 'add'|'edit' $function
     * @return void
     */
    public function setFunction($function)
    {
        $this->function = $function;
    }

    /**
     * @param bool $value
     * @return void
     */
    public function setEval($value)
    {
        if ($value) {
            $this->eval = true;
        } else {
            $this->eval = false;
        }
    }

    /**
     * @param string $value
     * @return string
     */
    protected function correctValue($value)
    {
        if ('category_id' == $value) {
            if (1 != $this->getValue('startarticle')) {
                $value = 'parent_id';
            } else {
                $value = 'id';
            }
        } elseif ('article_id' == $value) {
            $value = 'id';
        }

        return $value;
    }

    /**
     * @param string $value
     * @return string|int|null
     */
    protected function _getValue($value)
    {
        $value = $this->correctValue($value);

        // use same timestamp format like in frontend via `ArticleContent`
        if (in_array($value, ['createdate', 'updatedate'], true)) {
            return $this->getSqlInstance()->getDateTimeValue($value);
        }

        $value = $this->getSqlInstance()->getValue($value);
        assert(null === $value || is_int($value) || is_string($value));

        return $value;
    }

    /**
     * @param string $value
     * @return string|int|null
     */
    public function getValue($value)
    {
        // damit alte rex_article felder wie teaser, online_from etc
        // noch funktionieren
        // gleicher BC code nochmals in StructureElement::getValue
        foreach (['', 'art_', 'cat_'] as $prefix) {
            $val = $prefix . $value;
            if ($this->_hasValue($val)) {
                return $this->_getValue($val);
            }
        }

        throw new LogicException('Articles do not have the property "' . $value . '"');
    }

    /**
     * @param string $value
     * @return bool
     */
    public function hasValue($value)
    {
        foreach (['', 'art_', 'cat_'] as $prefix) {
            $val = $prefix . $value;
            if ($this->_hasValue($val)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $value
     * @return bool
     */
    private function _hasValue($value)
    {
        return $this->getSqlInstance()->hasValue($this->correctValue($value));
    }

    /**
     * Outputs a slice.
     *
     * @param Sql $artDataSql A Sql instance containing all slice and module data
     * @param string $moduleKeyToAdd The key of the module, which was selected using the ModuleSelect
     */
    protected function outputSlice(Sql $artDataSql, string $moduleKeyToAdd): string
    {
        $moduleKey = (string) $artDataSql->getValue(Core::getTablePrefix() . 'article_slice.module');
        $slice = ArticleSlice::fromSql($artDataSql);

        $module = Module::get($moduleKey);

        if (null === $module) {
            return '';
        }

        $output = $module->output($slice);

        $output = Extension::registerPoint(new ExtensionPoint(
            'SLICE_OUTPUT',
            $output,
            [
                'article_id' => $this->article_id,
                'clang' => $this->clang,
                'slice_data' => $artDataSql,
            ],
        ));

        return $output;
    }

    public function getCurrentSlice(): ArticleSlice
    {
        if (!$this->sliceSql || !$this->sliceSql->valid()) {
            throw new LogicException('There is no current slice; getCurrentSlice() can be called only while rendering slices');
        }

        return ArticleSlice::fromSql($this->sliceSql);
    }

    /**
     * Returns the content of the given slice-id.
     *
     * @param int $sliceId A article-slice id
     *
     * @return string
     */
    public function getSlice($sliceId)
    {
        $oldEval = $this->eval;
        $this->setEval(true);

        $this->getSlice = $sliceId;
        $sliceContent = $this->getArticle();
        $this->getSlice = 0;

        $this->setEval($oldEval);
        return $this->replaceLinks($sliceContent);
    }

    /**
     * Returns the content of the article of the given ctype. If no ctype is given, content of all ctypes is returned.
     *
     * @param int $curctype The ctype to fetch, or -1 for all ctypes
     *
     * @return string
     */
    public function getArticle($curctype = -1)
    {
        $this->ctype = $curctype;

        if (0 == $this->article_id && 0 == $this->getSlice) {
            return I18n::msg('no_article_available');
        }

        $articleLimit = '';
        if (0 != $this->article_id) {
            $articleLimit = ' AND ' . Core::getTablePrefix() . 'article_slice.article_id=' . (int) $this->article_id;
        }

        $sliceLimit = '';
        if (0 != $this->getSlice) {
            $sliceLimit = ' AND ' . Core::getTablePrefix() . "article_slice.id = '" . ((int) $this->getSlice) . "' ";
        }
        if ('edit' !== $this->mode) {
            $sliceLimit .= ' AND ' . Core::getTablePrefix() . 'article_slice.status = 1';
        }

        // ----- start: article caching
        ob_start();
        try {
            ob_implicit_flush(false);

            $this->renderSlices($articleLimit, $sliceLimit);
        } finally {
            // ----- end: article caching
            $CONTENT = ob_get_clean();
        }

        return $CONTENT;
    }

    /** Method which gets called, before the slices of the article are processed. */
    protected function preArticle(string $articleContent, string $moduleKey): string
    {
        // nichts tun
        return $articleContent;
    }

    /** Method which gets called, after all slices have been processed. */
    protected function postArticle(string $articleContent, string $moduleKey): string
    {
        // nichts tun
        return $articleContent;
    }

    // ----- Template inklusive Artikel zurückgeben

    /**
     * @throws ArticleNotFoundException
     * @return string
     */
    public function getArticleTemplate()
    {
        if (null !== $this->template && 0 != $this->article_id) {
            $template = Template::get($this->template);

            if (null === $template) {
                return 'no template';
            }

            ob_start();
            try {
                ob_implicit_flush(false);

                Timer::measure('Template: ' . $template->key, function () use ($template) {
                    Type::instanceOf($this, ArticleContent::class);

                    echo $template->render($this);
                });
            } finally {
                $CONTENT = ob_get_clean();
            }

            return $this->replaceLinks($CONTENT);
        }

        return 'no template';
    }

    /**
     * @param string $content
     * @return string
     */
    protected function replaceLinks($content)
    {
        $result = preg_replace_callback(
            '@redaxo://(\d+)(?:-(\d+))?/?@i',
            function (array $matches) {
                return Url::article((int) $matches[1], (int) ($matches[2] ?? $this->clang));
            },
            $content,
        );

        if (null === $result) {
            throw new LogicException('Error while replacing links.');
        }

        return $result;
    }

    private function renderSlices(string $articleLimit, string $sliceLimit): void
    {
        $moduleKey = Request::request('module', 'string', '');

        // ---------- alle teile/slices eines artikels auswaehlen
        $prefix = Core::getTablePrefix();
        $query = <<<SQL
            SELECT
                {$prefix}article_slice.*,
                {$prefix}article.parent_id
            FROM {$prefix}article_slice
            LEFT JOIN {$prefix}article ON {$prefix}article_slice.article_id = {$prefix}article.id
            WHERE
                {$prefix}article_slice.clang_id = {$this->clang} AND
                {$prefix}article.clang_id = {$this->clang} AND
                {$prefix}article_slice.revision = {$this->slice_revision}
                {$articleLimit}
                {$sliceLimit}
            ORDER BY {$prefix}article_slice.priority
            SQL;

        $query = Extension::registerPoint(new ExtensionPoint('ART_SLICES_QUERY', $query, ['article' => $this]));

        $artDataSql = Sql::factory();
        $artDataSql->setDebug($this->debug);
        $artDataSql->setQuery($query);

        // pre hook
        $articleContent = '';
        $articleContent = $this->preArticle($articleContent, $moduleKey);

        // ---------- SLICES AUSGEBEN

        $this->sliceSql = $artDataSql;

        try {
            $prevCtype = null;
            $artDataSql->reset();
            $rows = $artDataSql->getRows();
            for ($i = 0; $i < $rows; ++$i) {
                $sliceId = (int) $artDataSql->getValue($prefix . 'article_slice.id');
                $sliceCtypeId = (int) $artDataSql->getValue($prefix . 'article_slice.ctype_id');
                /**
                 * Module key from internal DB table, safe to embed in generated cache code.
                 * @psalm-taint-escape html
                 * @psalm-taint-escape has_quotes
                 */
                $sliceModuleKey = (string) $artDataSql->getValue($prefix . 'article_slice.module');

                // ----- ctype unterscheidung
                if ('edit' != $this->mode && !$this->eval) {
                    if (0 == $i) {
                        $articleContent = "<?php\n\nif (\$this->ctype == '" . $sliceCtypeId . "' || \$this->ctype == '-1') {\n";
                    } elseif (null !== $prevCtype && $sliceCtypeId != $prevCtype) {
                        // ----- zwischenstand: ctype .. wenn ctype neu dann if
                        $articleContent .= "}\n\nif (\$this->ctype == '" . $sliceCtypeId . "' || \$this->ctype == '-1') {\n";
                    }

                    $slice = ArticleSlice::fromSql($artDataSql);
                    $articleContent .= '$this->currentSlice = ' . var_export($slice, true) . ";\n";
                    $articleContent .= 'echo \\' . Module::class . '::get(' . var_export($sliceModuleKey, true) . ')?->output($this->getCurrentSlice()) ?? \'\';' . "\n";
                }

                // ------------- EINZELNER SLICE - AUSGABE
                if ('edit' == $this->mode || $this->eval) {
                    $sliceContent = $this->outputSlice(
                        $artDataSql,
                        $moduleKey,
                    );
                } else {
                    $sliceContent = '';
                }
                // --------------- ENDE EINZELNER SLICE

                // --------------- EP: SLICE_SHOW
                $sliceContent = Extension::registerPoint(
                    new ExtensionPoint(
                        'SLICE_SHOW',
                        $sliceContent,
                        [
                            'article_id' => $this->article_id,
                            'clang' => $this->clang,
                            'ctype' => $sliceCtypeId,
                            'module_key' => $sliceModuleKey,
                            'slice_id' => $sliceId,
                            'function' => $this->function,
                            'function_slice_id' => $this->slice_id,
                            'sql' => $artDataSql,
                        ],
                    ),
                );

                // ---------- slice in ausgabe speichern wenn ctype richtig
                if (-1 == $this->ctype || $this->ctype == $sliceCtypeId) {
                    $articleContent .= $sliceContent;
                }

                $prevCtype = $sliceCtypeId;

                $artDataSql->flushValues();
                $artDataSql->next();
            }
        } finally {
            $this->sliceSql = null;
        }

        // ----- end: ctype unterscheidung
        if ('edit' != $this->mode && !$this->eval && $i > 0) {
            $articleContent .= "}\n";
        }

        // ----- post hook
        $articleContent = $this->postArticle($articleContent, $moduleKey);

        // -------------------------- schreibe content
        echo $articleContent;
    }
}
