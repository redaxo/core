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
class ArticleContent extends ArticleContentBase
{
    // bc schalter
    /** @var bool */
    private $viasql = false;

    /**
     * @var ArticleSlice|null
     * @phpstan-ignore-next-line this property looks unread, but is written from content cache file
     */
    private $currentSlice;

    /**
     * @param int|null $articleId
     * @param int|null $clang
     */
    public function __construct($articleId = null, $clang = null)
    {
        parent::__construct($articleId, $clang);
    }

    // bc

    /**
     * @param bool $viasql
     * @return void
     */
    public function getContentAsQuery($viasql = true)
    {
        if (!$viasql) {
            $viasql = false;
        }
        $this->viasql = $viasql;
    }

    public function setArticleId($articleId)
    {
        // bc
        if ($this->viasql) {
            return parent::setArticleId($articleId);
        }

        $articleId = (int) $articleId;
        $this->article_id = $articleId;

        $rexArticle = Article::get($articleId, $this->clang);
        if ($rexArticle instanceof Article) {
            $this->category_id = $rexArticle->getCategoryId();
            $this->template = $rexArticle->getTemplateKey();
            return true;
        }

        $this->article_id = 0;
        $this->template = null;
        $this->category_id = 0;
        return false;
    }

    public function getValue($value)
    {
        // bc
        if ($this->viasql) {
            return parent::getValue($value);
        }

        $value = $this->correctValue($value);

        if (!Article::hasValue($value)) {
            throw new LogicException('Articles do not have the property "' . $value . '"');
        }

        $article = Article::get($this->article_id, $this->clang);

        if (!$article) {
            throw new LogicException('Article for id=' . $this->article_id . ' and clang=' . $this->clang . ' does not exist');
        }

        return $article->getValue($value);
    }

    public function hasValue($value)
    {
        // bc
        if ($this->viasql) {
            return parent::hasValue($value);
        }

        $value = $this->correctValue($value);

        return Article::hasValue($value);
    }

    public function getArticle($curctype = -1)
    {
        // bc
        if ($this->viasql) {
            return parent::getArticle($curctype);
        }

        $this->ctype = $curctype;

        if (!$this->getSlice && 0 != $this->article_id) {
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

        return Extension::registerPoint(new ExtensionPoint('ART_CONTENT', $CONTENT, [
            'ctype' => $curctype,
            'article' => $this,
        ]));
    }

    public function getCurrentSlice(): ArticleSlice
    {
        if ($this->viasql) {
            return parent::getCurrentSlice();
        }

        if (!$this->currentSlice) {
            throw new LogicException('There is no current slice; getCurrentSlice() can be called only while rendering slices');
        }

        return $this->currentSlice;
    }
}
