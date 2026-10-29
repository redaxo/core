<?php

use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\View\Component\Breakpoint;
use Redaxo\Core\View\Component\Grid;
use Redaxo\Core\View\Component\GridColumn;
use Redaxo\Core\View\Component\GridColumnRule;
use Redaxo\Core\View\Component\GridLayoutSplit;
use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\Html;

/**
 * Adapter of {@see Grid} for fragments, prefer the component.
 *
 * `content` holds the HTML of each column. `spans` the widths of each column as tokens `[bp:]tracks` (e.g.
 * `['md:8', 'md:4 xl:6']`); without `spans` the columns share one row equally from `md` on. A single content is
 * output without grid.
 *
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */
?>
<?php

/** @var string|array<int, string> $content */
$content = $this->content;
$contents = is_string($content) ? [$content] : array_values($content);

if (isset($this->classes)) {
    @trigger_error('The variable "classes" of the fragment "core/page/grid.php" is no longer supported, use "spans" or the component ' . Grid::class . '.', E_USER_DEPRECATED);
}

if (1 === count($contents)) {
    echo $contents[0];

    return;
}

/** @var list<string>|null $spans */
$spans = $this->spans ?? null;

if (null === $spans) {
    echo new Grid(array_map(Html::raw(...), $contents), layout: new GridLayoutSplit(Breakpoint::Md))->render();

    return;
}

$columns = [];
foreach ($contents as $key => $html) {
    $rules = [];
    foreach (preg_split('/\s+/', trim($spans[$key] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
        if (!preg_match('/^(?:(sm|md|lg|xl):)?([1-9]|1[0-2])$/', $token, $match)) {
            throw new InvalidArgumentException('Invalid span "' . $token . '" for the fragment "core/page/grid.php", expected e.g. "6" or "md:6".');
        }
        /** @var int<1, 12> $width */
        $width = (int) $match[2];
        $rules[] = new GridColumnRule(Breakpoint::from('' === $match[1] ? 'xs' : $match[1]), width: $width);
    }
    $columns[] = new GridColumn(Html::raw($html), $rules);
}

echo new Grid($columns)->render();
