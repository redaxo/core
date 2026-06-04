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

use function sprintf;

/**
 * Klasse regelt den Zugriff auf Artikelinhalte.
 * Alle benötigten Daten werden von der DB bezogen.
 */
class ArticleContentBase
{
    public string $error = '';
    public string $success = '';
    public bool $debug = false;

    public readonly Article $article;

    public int $sliceId = 0;
    protected int $singleSliceId = 0;
    /** @var 'view'|'edit' */
    public string $mode = 'view';
    /** @var 'add'|'edit'|'' */
    public string $function = '';

    protected ?int $contentSectionId = null;

    public readonly int $clangId;

    public bool $eval = false;

    public int $sliceRevision = 0;

    /** @throws ArticleNotFoundException */
    public function __construct(
        public readonly int $articleId,
        ?int $clangId = null,
    ) {
        $this->clangId = null !== $clangId && Language::exists($clangId) ? $clangId : Language::getCurrentId();

        $article = Article::get($this->articleId, $this->clangId);

        if (!$article instanceof Article) {
            throw new ArticleNotFoundException(sprintf('Article with id "%d" and clang "%d" does not exist.', $this->articleId, $this->clangId));
        }

        $this->article = $article;
    }

    /**
     * Outputs a slice.
     *
     * @param Sql $artDataSql A Sql instance containing all slice and module data
     * @param string $moduleKeyToAdd The key of the module, which was selected using the ModuleSelect
     *
     * @throws ArticleNotFoundException
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

        $output = Extension::dispatch(new ExtensionPoint(
            'SLICE_OUTPUT',
            $output,
            [
                'article_id' => $this->articleId,
                'clang' => $this->clangId,
                'slice_data' => $artDataSql,
            ],
        ));

        return $output;
    }

    /**
     * Returns the content of the given slice-id.
     *
     * @param int $sliceId A article-slice id
     *
     * @throws ArticleNotFoundException
     * @return string
     */
    public function getSlice($sliceId)
    {
        $oldEval = $this->eval;
        $this->eval = true;

        $this->singleSliceId = $sliceId;
        $sliceContent = $this->getArticle();
        $this->singleSliceId = 0;

        $this->eval = $oldEval;
        return $this->replaceLinks($sliceContent);
    }

    /**
     * Returns the content of the article of the given content section. If no content section is given (null),
     * content of all content sections is returned.
     *
     * @throws ArticleNotFoundException
     * @return string
     */
    public function getArticle(?int $contentSectionId = null)
    {
        $this->contentSectionId = $contentSectionId;

        if (0 === $this->articleId && 0 === $this->singleSliceId) {
            return I18n::msg('no_article_available');
        }

        $articleLimit = '';
        if (0 !== $this->articleId) {
            $articleLimit = ' AND ' . Core::getTablePrefix() . 'article_slice.article_id=' . $this->articleId;
        }

        $sliceLimit = '';
        if (0 !== $this->singleSliceId) {
            $sliceLimit = ' AND ' . Core::getTablePrefix() . "article_slice.id = '" . $this->singleSliceId . "' ";
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
     * @throws ArticleNotFoundException if a template or module aborts rendering to switch to the not found article
     * @return string
     */
    public function getArticleTemplate()
    {
        if (null !== $this->article->templateKey && 0 !== $this->articleId) {
            $template = Template::get($this->article->templateKey);

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
                return Url::article((int) $matches[1], (int) ($matches[2] ?? $this->clangId));
            },
            $content,
        );

        if (null === $result) {
            throw new LogicException('Error while replacing links.');
        }

        return $result;
    }

    /** @throws ArticleNotFoundException */
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
                {$prefix}article_slice.clang_id = {$this->clangId} AND
                {$prefix}article.clang_id = {$this->clangId} AND
                {$prefix}article_slice.revision = {$this->sliceRevision}
                {$articleLimit}
                {$sliceLimit}
            ORDER BY {$prefix}article_slice.priority
            SQL;

        $query = Extension::dispatch(new ExtensionPoint('ART_SLICES_QUERY', $query, ['article' => $this]));

        $artDataSql = Sql::factory();
        $artDataSql->setDebug($this->debug);
        $artDataSql->setQuery($query);

        // pre hook
        $articleContent = '';
        $articleContent = $this->preArticle($articleContent, $moduleKey);

        // ---------- SLICES AUSGEBEN

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
            if ('edit' !== $this->mode && !$this->eval) {
                if (0 === $i) {
                    $articleContent = "<?php\n\nif (null === \$this->contentSectionId || " . $sliceCtypeId . " === \$this->contentSectionId) {\n";
                } elseif (null !== $prevCtype && $sliceCtypeId !== $prevCtype) {
                    // ----- zwischenstand: content section .. wenn neu dann if
                    $articleContent .= "}\n\nif (null === \$this->contentSectionId || " . $sliceCtypeId . " === \$this->contentSectionId) {\n";
                }

                $slice = ArticleSlice::fromSql($artDataSql);
                $articleContent .= 'echo \\' . Module::class . '::get(' . var_export($sliceModuleKey, true) . ')?->output(' . var_export($slice, true) . ') ?? \'\';' . "\n";
            }

            // ------------- EINZELNER SLICE - AUSGABE
            if ('edit' === $this->mode || $this->eval) {
                $sliceContent = $this->outputSlice(
                    $artDataSql,
                    $moduleKey,
                );
            } else {
                $sliceContent = '';
            }
            // --------------- ENDE EINZELNER SLICE

            // --------------- EP: SLICE_SHOW
            $sliceContent = Extension::dispatch(
                new ExtensionPoint(
                    'SLICE_SHOW',
                    $sliceContent,
                    [
                        'article_id' => $this->articleId,
                        'clang' => $this->clangId,
                        'ctype' => $sliceCtypeId,
                        'module_key' => $sliceModuleKey,
                        'slice_id' => $sliceId,
                        'function' => $this->function,
                        'function_slice_id' => $this->sliceId,
                        'sql' => $artDataSql,
                    ],
                ),
            );

            // ---------- slice in ausgabe speichern wenn content section richtig
            if (null === $this->contentSectionId || $sliceCtypeId === $this->contentSectionId) {
                $articleContent .= $sliceContent;
            }

            $prevCtype = $sliceCtypeId;

            $artDataSql->flushValues();
            $artDataSql->next();
        }

        // ----- end: ctype unterscheidung
        if ('edit' !== $this->mode && !$this->eval && $i > 0) {
            $articleContent .= "}\n";
        }

        // ----- post hook
        $articleContent = $this->postArticle($articleContent, $moduleKey);

        // -------------------------- schreibe content
        echo $articleContent;
    }
}
