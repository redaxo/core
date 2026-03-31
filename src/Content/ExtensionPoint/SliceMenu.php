<?php

namespace Redaxo\Core\Content\ExtensionPoint;

use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Http\Context;

/**
 * @extends ExtensionPoint<null>
 */
class SliceMenu extends ExtensionPoint
{
    public const NAME = 'SLICE_MENU';

    private array $additionalActions = [];

    /**
     * @param array{label?: string, url?: string, attributes?: array{class: list<string>, title: string}} $menuEditAction
     * @param array{label?: string, url?: string, attributes?: array{class: list<string>, title: string, data-confirm: string}} $menuDeleteAction
     * @param array{label?: string, url?: string, attributes?: array{class: list<string>}} $menuStatusAction
     * @param array{hidden_label?: string, url?: string, icon?: string, attributes?: array{class: list<string>, title: string}} $menuMoveupAction
     * @param array{hidden_label?: string, url?: string, icon?: string, attributes?: array{class: list<string>, title: string}} $menuMovedownAction
     */
    public function __construct(
        private array $menuEditAction,
        private array $menuDeleteAction,
        private array $menuStatusAction,
        private array $menuMoveupAction,
        private array $menuMovedownAction,
        private Context $context,
        private string $fragment,
        private int $articleId,
        private int $clang,
        private int $ctype,
        private int $moduleId,
        private int $sliceId,
        private bool $hasPerm,
    ) {
        parent::__construct(self::NAME);
    }

    /** @return array{label?: string, url?: string, attributes?: array{class: list<string>, title: string}} */
    public function getMenuEditAction(): array
    {
        return $this->menuEditAction;
    }

    /** @param array{label?: string, url?: string, attributes?: array{class: list<string>, title: string}} $menuEditAction */
    public function setMenuEditAction(array $menuEditAction): void
    {
        $this->menuEditAction = $menuEditAction;
    }

    /** @return array{label?: string, url?: string, attributes?: array{class: list<string>, title: string, data-confirm: string}} */
    public function getMenuDeleteAction(): array
    {
        return $this->menuDeleteAction;
    }

    /** @param array{label?: string, url?: string, attributes?: array{class: list<string>, title: string, data-confirm: string}} $menuDeleteAction */
    public function setMenuDeleteAction(array $menuDeleteAction): void
    {
        $this->menuDeleteAction = $menuDeleteAction;
    }

    /** @return array{label?: string, url?: string, attributes?: array{class: list<string>}} */
    public function getMenuStatusAction(): array
    {
        return $this->menuStatusAction;
    }

    /** @param array{label?: string, url?: string, attributes?: array{class: list<string>}} $menuStatusAction */
    public function setMenuStatusAction(array $menuStatusAction): void
    {
        $this->menuStatusAction = $menuStatusAction;
    }

    /** @return array{hidden_label?: string, url?: string, icon?: string, attributes?: array{class: list<string>, title: string}} */
    public function getMenuMoveupAction(): array
    {
        return $this->menuMoveupAction;
    }

    /** @param array{hidden_label?: string, url?: string, icon?: string, attributes?: array{class: list<string>, title: string}} $menuMoveupAction */
    public function setMenuMoveupAction(array $menuMoveupAction): void
    {
        $this->menuMoveupAction = $menuMoveupAction;
    }

    /** @return array{hidden_label?: string, url?: string, icon?: string, attributes?: array{class: list<string>, title: string}} */
    public function getMenuMovedownAction(): array
    {
        return $this->menuMovedownAction;
    }

    /** @param array{hidden_label?: string, url?: string, icon?: string, attributes?: array{class: list<string>, title: string}} $menuMovedownAction */
    public function setMenuMovedownAction(array $menuMovedownAction): void
    {
        $this->menuMovedownAction = $menuMovedownAction;
    }

    public function getAdditionalActions(): array
    {
        // ----- EXTENSION POINT / for BC reasons we wrap the old and pre-existing EP here
        $menuItemsEp = [];

        $menuItemsEp = Extension::registerPoint(new ExtensionPoint(
            'STRUCTURE_CONTENT_SLICE_MENU',
            $menuItemsEp,
            [
                'article_id' => $this->articleId,
                'clang' => $this->clang,
                'ctype' => $this->ctype,
                'module_id' => $this->moduleId,
                'slice_id' => $this->sliceId,
                'perm' => $this->hasPerm,
            ],
        ));

        return array_merge($this->additionalActions, $menuItemsEp);
    }

    public function setAdditionalActions(array $additionalActions): void
    {
        $this->additionalActions = $additionalActions;
    }

    public function addAdditionalActions(array $additionalActions): void
    {
        $this->additionalActions = array_merge($this->additionalActions, $additionalActions);
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getFragment(): string
    {
        return $this->fragment;
    }

    public function getArticleId(): int
    {
        return $this->articleId;
    }

    public function getClangId(): int
    {
        return $this->clang;
    }

    public function getCtypeId(): int
    {
        return $this->ctype;
    }

    public function getModuleId(): int
    {
        return $this->moduleId;
    }

    public function getSliceId(): int
    {
        return $this->sliceId;
    }

    public function hasPerm(): bool
    {
        return $this->hasPerm;
    }
}
