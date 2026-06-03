<?php

namespace Redaxo\Core\Content;

use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Path;

use function assert;
use function is_string;

/**
 * Klasse regelt den Zugriff auf Artikelinhalte.
 * DB Anfragen werden vermieden, caching läuft über generated Dateien.
 */
final class ArticleContent extends ArticleContentBase
{
    /**
     * @param int|null $articleId
     * @param int|null $clang
     */
    public function __construct($articleId = null, $clang = null)
    {
        parent::__construct($articleId, $clang);
    }

    public function setArticleId($articleId)
    {
        $articleId = (int) $articleId;
        $this->article_id = $articleId;

        $rexArticle = Article::get($articleId, $this->clang);
        if ($rexArticle instanceof Article) {
            $this->category_id = $rexArticle->categoryId ?? 0;
            $this->template = $rexArticle->templateKey;
            return true;
        }

        $this->article_id = 0;
        $this->template = null;
        $this->category_id = 0;
        return false;
    }

    public function getValue($value)
    {
        $value = $this->correctValue($value);

        $article = Article::get($this->article_id, $this->clang);

        if (!$article) {
            throw new LogicException('Article for id=' . $this->article_id . ' and clang=' . $this->clang . ' does not exist');
        }

        if (!$article->hasValue($value)) {
            throw new LogicException('Articles do not have the property "' . $value . '"');
        }

        return $article->getValue($value);
    }

    public function hasValue($value)
    {
        $value = $this->correctValue($value);

        return Article::get($this->article_id, $this->clang)?->hasValue($value) ?? false;
    }

    public function getArticle($curctype = -1)
    {
        $this->ctype = $curctype;

        // In eval mode (history/work version, single slice) the content is rendered live from the
        // database; otherwise it comes from the published content cache file.
        if (!$this->eval && !$this->getSlice && 0 != $this->article_id) {
            // article caching
            ob_start();
            try {
                ob_implicit_flush(false);

                $articleContentFile = Path::coreCache('structure/' . $this->article_id . '.' . $this->clang . '.content');

                if (!is_file($articleContentFile)) {
                    ContentHandler::generateArticleContent($this->article_id, $this->clang);
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
