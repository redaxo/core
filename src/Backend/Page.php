<?php

namespace Redaxo\Core\Backend;

use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Security\User;
use Redaxo\Core\Util\Str;

use function is_array;
use function is_string;

/**
 * @psalm-import-type TUrlParams from Str
 */
class Page
{
    private readonly string $key;
    private string $fullKey;
    private string $title;

    private ?bool $popup = null;
    private ?string $href = null;
    /** @var array<string, string> */
    private array $itemAttr = [];
    /** @var array<string, string> */
    private array $linkAttr = [];

    private ?string $path = null;
    private ?string $subPath = null;

    private ?Page $parent = null;

    /** @var array<string, self> */
    private array $subpages = [];

    private ?bool $isActive = null;
    private bool $hidden = false;
    private bool $hasLayout = true;
    private bool $hasNavigation = true;
    private ?bool $pjax = null;
    private ?string $icon = null;
    /** @var list<string> */
    private array $requiredPermissions = [];

    public function __construct(string $key, string $title)
    {
        $this->key = $key;
        $this->fullKey = $key;
        $this->title = $title;
    }

    /** Returns the page key. */
    public function getKey(): string
    {
        return $this->key;
    }

    /** Returns the full page path. */
    public function getFullKey(): string
    {
        return $this->fullKey;
    }

    /**
     * Sets the page title.
     *
     * @return $this
     */
    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /** Returns the title. */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Sets whether the page is a popup page.
     *
     * The method adds (or removes) also the rex-popup CSS class and sets hasNavigation to false (true).
     * If $popup is a string, the variable will be used for the onclick attribute.
     *
     * @return $this
     */
    public function setPopup(bool|string $popup): static
    {
        if ($popup) {
            $this->popup = true;
            $this->setHasNavigation(false);
            $this->addItemClass('rex-popup');
            $this->addLinkClass('rex-popup');
            if (is_string($popup)) {
                $this->setLinkAttr('onclick', $popup);
            }
        } else {
            $this->popup = false;
            $this->setHasNavigation(true);
            $this->removeItemClass('rex-popup');
            $this->removeLinkClass('rex-popup');
            $this->removeLinkAttr('onclick');
        }

        return $this;
    }

    /** Returns whether the page is a popup. */
    public function isPopup(): bool
    {
        if (null !== $this->popup) {
            return $this->popup;
        }

        return $this->parent && $this->parent->isPopup();
    }

    /**
     * Sets the page href.
     *
     * @param string|TUrlParams $href Href string or array of params
     * @return $this
     */
    public function setHref(string|array $href): static
    {
        if (is_array($href)) {
            $href = Url::backendController($href);
        }
        $this->href = $href;

        return $this;
    }

    /** Returns whether the page has a custom href. */
    public function hasHref(): bool
    {
        return (bool) $this->href;
    }

    /** Returns the page href. */
    public function getHref(): string
    {
        if ($this->href) {
            return $this->href;
        }
        return Url::backendPage($this->getFirstSubpagesLeaf()->getFullKey());
    }

    /** Sets an item attribute. */
    public function setItemAttr(string $name, string|int $value): static
    {
        $this->itemAttr[$name] = (string) $value;

        return $this;
    }

    /**
     * Returns an item attribute or all item attributes.
     *
     * @template T as ?string
     * @param T $name
     * @return (T is string ? string : array<string, string>) Attribute value for given `$name` or attribute array if `$name` is `null`
     */
    public function getItemAttr(?string $name, string $default = ''): string|array
    {
        // return all attributes if null is passed as name
        if (null === $name) {
            return $this->itemAttr;
        }

        return $this->itemAttr[$name] ?? $default;
    }

    /** Removes an item attribute. */
    public function removeItemAttr(string $name): void
    {
        unset($this->itemAttr[$name]);
    }

    /** Adds an item class. */
    public function addItemClass(string $class): static
    {
        $classAttr = $this->getItemAttr('class');
        if (!preg_match('/\b' . preg_quote($class, '/') . '\b/', $classAttr)) {
            $this->setItemAttr('class', ltrim($classAttr . ' ' . $class));
        }

        return $this;
    }

    /** Removes an item class. */
    public function removeItemClass(string $class): void
    {
        $this->setItemAttr('class', preg_replace('/\b' . preg_quote($class, '/') . '\b/', '', $this->getItemAttr('class')));
    }

    /** Sets an link attribute. */
    public function setLinkAttr(string $name, string|int $value): static
    {
        $this->linkAttr[$name] = (string) $value;

        return $this;
    }

    /** Removes an link attribute. */
    public function removeLinkAttr(string $name): void
    {
        unset($this->linkAttr[$name]);
    }

    /**
     * Returns an link attribute or all link attributes.
     *
     * @template T as ?string
     * @param T $name
     * @return (T is string ? string : array<string, string>) Attribute value for given `$name` or attribute array if `$name` is `null`
     */
    public function getLinkAttr(?string $name, string $default = ''): string|array
    {
        // return all attributes if null is passed as name
        if (null === $name) {
            return $this->linkAttr;
        }

        return $this->linkAttr[$name] ?? $default;
    }

    /** Adds an link class. */
    public function addLinkClass(string $class): static
    {
        $classAttr = $this->getLinkAttr('class');
        if (!preg_match('/\b' . preg_quote($class, '/') . '\b/', $classAttr)) {
            $this->setLinkAttr('class', ltrim($classAttr . ' ' . $class));
        }

        return $this;
    }

    /** Removes an link class. */
    public function removeLinkClass(string $class): void
    {
        $this->setLinkAttr('class', preg_replace('/\b' . preg_quote($class, '/') . '\b/', '', $this->getLinkAttr('class')));
    }

    /**
     * Set the page path which will be included directly by the core.
     *
     * @return $this
     */
    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    /** Returns whether a path is set. */
    public function hasPath(): bool
    {
        return !empty($this->path) || $this->parent && $this->parent->hasPath();
    }

    /** Returns the path which will be included directly by the core. */
    public function getPath(): ?string
    {
        if (!empty($this->path)) {
            return $this->path;
        }
        return $this->parent ? $this->parent->getPath() : null;
    }

    /**
     * Set the page subpath which should be used by the packages to include this page inside their main page.
     *
     * @return $this
     */
    public function setSubPath(string $subPath): static
    {
        $this->subPath = $subPath;

        return $this;
    }

    /** Returns whether a subpath is set. */
    public function hasSubPath(): bool
    {
        return !empty($this->subPath);
    }

    /** Returns the subpath which should be used by packages to include this page inside their main page. */
    public function getSubPath(): ?string
    {
        return $this->subPath;
    }

    /**
     * Adds a subpage.
     *
     * @return $this
     */
    public function addSubpage(self $subpage): static
    {
        $this->subpages[$subpage->getKey()] = $subpage;
        $subpage->parent = $this;
        $subpage->setParentKey($this->getFullKey());

        return $this;
    }

    private function setParentKey(string $key): void
    {
        $this->fullKey = $key . '/' . $this->key;
        foreach ($this->subpages as $subpage) {
            $subpage->setParentKey($this->fullKey);
        }
    }

    /**
     * Sets all subpages.
     *
     * @param array<self> $subpages
     * @return $this
     */
    public function setSubpages(array $subpages): static
    {
        $this->subpages = [];
        array_walk($subpages, $this->addSubpage(...));

        return $this;
    }

    /** Returns the subpage for the given key. */
    public function getSubpage(string $key): ?self
    {
        return $this->subpages[$key] ?? null;
    }

    /**
     * Returns all subpages.
     *
     * @return array<string, self>
     */
    public function getSubpages(): array
    {
        return $this->subpages;
    }

    /** Returns the first leaf of the subpages tree. */
    public function getFirstSubpagesLeaf(): self
    {
        $page = $this;
        while ($subpages = $page->getSubpages()) {
            $page = reset($subpages);
        }
        return $page;
    }

    /**
     * Sets whether the page is active.
     *
     * @return $this
     */
    public function setIsActive(bool $isActive = true): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    /** Returns whether the page is active. */
    public function isActive(): bool
    {
        if (null !== $this->isActive) {
            return $this->isActive;
        }
        $page = Controller::requireCurrentPageObject();
        do {
            if ($page === $this) {
                return true;
            }
        } while ($page = $page->getParent());
        return false;
    }

    /** Returns the parent page object. */
    public function getParent(): ?self
    {
        return $this->parent;
    }

    /**
     * Sets whether the page is hidden.
     *
     * @return $this
     */
    public function setHidden(bool $hidden = true): static
    {
        $this->hidden = $hidden;

        return $this;
    }

    /** Returns whether the page is hidden. */
    public function isHidden(): bool
    {
        return $this->hidden;
    }

    /**
     * Sets whether the page has layout.
     *
     * @return $this
     */
    public function setHasLayout(bool $hasLayout): static
    {
        $this->hasLayout = $hasLayout;

        return $this;
    }

    /** Returns whether tha page has layout. */
    public function hasLayout(): bool
    {
        return $this->hasLayout && (!$this->parent || $this->parent->hasLayout());
    }

    /**
     * Sets whether the page has a navigation.
     *
     * @return $this
     */
    public function setHasNavigation(bool $hasNavigation): static
    {
        $this->hasNavigation = $hasNavigation;

        return $this;
    }

    /** Returns whether the page has a navigation. */
    public function hasNavigation(): bool
    {
        return $this->hasNavigation && (!$this->parent || $this->parent->hasNavigation());
    }

    /**
     * Sets whether the page allows pjax.
     *
     * @return $this
     */
    public function setPjax(bool $pjax = true): static
    {
        $this->pjax = $pjax;

        return $this;
    }

    /** Returns whether the page allows pjax. */
    public function allowsPjax(): bool
    {
        if (null !== $this->pjax) {
            return $this->pjax;
        }
        if ($this->parent) {
            return $this->parent->allowsPjax();
        }
        return false;
    }

    /**
     * Sets whether the page has an icon.
     *
     * @return $this
     */
    public function setIcon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /** Returns the icon. */
    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /** Returns whether the page has an icon. */
    public function hasIcon(): bool
    {
        return !empty($this->icon);
    }

    /**
     * Sets the required permissions.
     *
     * @param list<string>|string $perm
     * @return $this
     */
    public function setRequiredPermissions(string|array $perm): static
    {
        $this->requiredPermissions = (array) $perm;

        return $this;
    }

    /**
     * Returns the required permission.
     *
     * @return list<string>
     */
    public function getRequiredPermissions(): array
    {
        return $this->requiredPermissions;
    }

    /** Checks whether the given user has permission for the page. */
    public function checkPermission(User $user): bool
    {
        foreach ($this->requiredPermissions as $perm) {
            if (!$user->hasPerm($perm)) {
                return false;
            }
        }
        if ($parent = $this->getParent()) {
            return $parent->checkPermission($user);
        }
        return true;
    }
}
