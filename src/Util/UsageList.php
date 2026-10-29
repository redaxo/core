<?php

namespace Redaxo\Core\Util;

use function Redaxo\Core\View\escape;

/**
 * The places an item is used in, grouped by their kind, e.g. to refuse the deletion of the item.
 */
final class UsageList
{
    /** @var array<string, list<array{label: string, url: ?string}>> The places, keyed by the (translated) name of their kind */
    public private(set) array $groups = [];

    /**
     * @param string $group Translated name of the kind of place as plain text, e.g. "Articles (content)"
     * @param string $label Name of the place as plain text, e.g. the article name
     * @param string|null $url Backend url to edit the place
     */
    public function add(string $group, string $label, ?string $url = null): void
    {
        $this->groups[$group][] = ['label' => $label, 'url' => $url];
    }

    public function isEmpty(): bool
    {
        return !$this->groups;
    }

    /** @internal */
    public function render(): string
    {
        $html = '';
        foreach ($this->groups as $group => $usages) {
            $html .= '<p>' . escape($group) . '</p><ul>';
            foreach ($usages as ['label' => $label, 'url' => $url]) {
                $label = escape($label);
                $html .= '<li>' . (null === $url ? $label : '<a href="' . escape($url) . '">' . $label . '</a>') . '</li>';
            }
            $html .= '</ul>';
        }

        return '<div>' . $html . '</div>';
    }
}
