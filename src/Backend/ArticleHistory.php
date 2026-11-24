<?php

namespace Redaxo\Core\Backend;

use Redaxo\Core\View\Renderable;
use Redaxo\Core\View\RendersView;

/**
 * Article history: a timeline of the saved versions, previews of the current and the selected version side by side,
 * and the button restoring the selected one.
 *
 * @internal
 */
final class ArticleHistory implements Renderable
{
    use RendersView;

    public function __construct(
        /** Frontend url of the article; the previews add `rex_history_date` or `rex_version` */
        public string $previewUrl,
        /** @var list<array{history_date: string, history_type: string, history_user: string}> newest first */
        public array $snapshots,
        /** Whether the working version can be compared, too */
        public bool $workVersion = false,
        /** Form action restoring the selected version, `null` without the permission to do so */
        public ?string $restoreUrl = null,
    ) {}
}
