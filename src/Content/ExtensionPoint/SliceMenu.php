<?php

namespace Redaxo\Core\Content\ExtensionPoint;

use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Http\Context;

/**
 * @extends ExtensionPoint<null>
 */
final class SliceMenu extends ExtensionPoint
{
    public const string NAME = 'SLICE_MENU';

    /** @var list<array<string, mixed>> */
    public array $additionalActions = [];

    /**
     * @param array{label?: string, url?: string, attributes?: array{class: list<string>, title: string}} $menuEditAction
     * @param array{label?: string, url?: string, attributes?: array{class: list<string>, title: string, data-confirm: string}} $menuDeleteAction
     * @param array{label?: string, url?: string, attributes?: array{class: list<string>}} $menuStatusAction
     * @param array{hidden_label?: string, url?: string, icon?: string, attributes?: array{class: list<string>, title: string}} $menuMoveupAction
     * @param array{hidden_label?: string, url?: string, icon?: string, attributes?: array{class: list<string>, title: string}} $menuMovedownAction
     */
    public function __construct(
        public array $menuEditAction,
        public array $menuDeleteAction,
        public array $menuStatusAction,
        public array $menuMoveupAction,
        public array $menuMovedownAction,
        public readonly Context $context,
        public readonly string $fragment,
        public readonly int $articleId,
        public readonly int $languageId,
        public readonly int $contentSectionId,
        public readonly string $moduleKey,
        public readonly int $sliceId,
        public readonly bool $hasPerm,
    ) {
        parent::__construct(self::NAME);
    }
}
