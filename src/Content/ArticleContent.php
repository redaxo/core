<?php

namespace Redaxo\Core\Content;

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
    public function renderContent(?int $contentSectionId = null): string
    {
        $this->contentSectionId = $contentSectionId;

        // In eval mode (history/work version, single slice) the content is rendered live from the
        // database; otherwise it comes from the published content cache file.
        if (!$this->eval && !$this->singleSliceId && 0 !== $this->articleId) {
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
            $CONTENT = parent::renderContent($contentSectionId);
        }

        return Extension::dispatch(new ExtensionPoint('ART_CONTENT', $CONTENT, [
            'ctype' => $contentSectionId,
            'article' => $this,
        ]));
    }
}
