<?php

use Redaxo\Core\View\Fragment;
use Redaxo\Core\View\HtmlAttributes;

/**
 * Columns of `.rex-grid`: `content` holds the content of each column, `spans` the `data-span` of each column (e.g.
 * `['sm:8', 'sm:4']`); without `spans` the columns share the width equally from `md` on.
 *
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */
?>
<?php

/** @var array<int, string> $contents */
$contents = is_string($this->content) ? [$this->content] : $this->content;
$count = count($contents);

if (1 === $count) {
    echo $contents[0];

    return;
}

/** @var array<int, string> $spans */
$spans = $this->spans ?? array_fill(0, $count, 'md:' . intdiv(12, max(1, $count)));

echo '<div class="rex-grid">';
foreach ($contents as $key => $content) {
    echo '<div ' . new HtmlAttributes(['data-span' => $spans[$key] ?? null]) . '>' . $content . '</div>';
}
echo '</div>';
