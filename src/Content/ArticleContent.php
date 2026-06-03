<?php

namespace Redaxo\Core\Content;

use Redaxo\Core\Content\Exception\ArticleNotFoundException;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Path;

use function assert;
use function is_string;
use function sprintf;

/**
 * Klasse regelt den Zugriff auf Artikelinhalte.
 * DB Anfragen werden vermieden, caching läuft über generated Dateien.
 */
final class ArticleContent extends ArticleContentBase
{
    protected function loadArticle(): void
    {
        $article = Article::get($this->articleId, $this->clangId);

        if (!$article instanceof Article) {
            throw new ArticleNotFoundException(sprintf('Article with id "%d" and clang "%d" does not exist.', $this->articleId, $this->clangId));
        }

        $this->category_id = $article->categoryId ?? 0;
        $this->template = $article->templateKey;
    }

    public function getValue($value)
    {
        $value = $this->correctValue($value);

        $article = Article::get($this->articleId, $this->clangId);

        if (!$article) {
            throw new LogicException('Article for id=' . $this->articleId . ' and clang=' . $this->clangId . ' does not exist');
        }

        if (!$article->hasValue($value)) {
            throw new LogicException('Articles do not have the property "' . $value . '"');
        }

        return $article->getValue($value);
    }

    public function hasValue($value)
    {
        $value = $this->correctValue($value);

        return Article::get($this->articleId, $this->clangId)?->hasValue($value) ?? false;
    }

    public function getArticle($curctype = -1)
    {
        $this->ctype = $curctype;

        // In eval mode (history/work version, single slice) the content is rendered live from the
        // database; otherwise it comes from the published content cache file.
        if (!$this->eval && !$this->getSlice && 0 != $this->articleId) {
            // article caching
            ob_start();
            try {
                ob_implicit_flush(false);

                $articleContentFile = Path::coreCache('structure/' . $this->articleId . '.' . $this->clangId . '.content');

                if (!is_file($articleContentFile)) {
                    ContentHandler::generateArticleContent($this->articleId, $this->clangId);
                }

                require $articleContentFile;
            } finally {
                $CONTENT = ob_get_clean();
                assert(is_string($CONTENT));
            }
        } else {
            // Inhalt ueber sql generierens
            $CONTENT = parent::getArticle($curctype);
        }

        return Extension::dispatch(new ExtensionPoint('ART_CONTENT', $CONTENT, [
            'ctype' => $curctype,
            'article' => $this,
        ]));
    }
}
