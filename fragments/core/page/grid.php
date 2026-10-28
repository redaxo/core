<?php

use Redaxo\Core\View\Fragment;

/**
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */
?>
<?php

/** @var array<int, string> $contents */
$contents = is_string($this->content) ? [$this->content] : $this->content;
$count = count($contents);

/** @var array<int, string> $classes */
$classes = isset($this->classes) && (is_array($this->classes) && count($this->classes) == $count) ? $this->classes : [];

if (1 === $count) {
    echo $contents[0];

    return;
}

// column classes of the Bootstrap 3 grid (`col-sm-8` …) given by the caller become the split of `.rex-grid`, as long
// as all columns switch at the same breakpoint and the split is supported; otherwise the Bootstrap markup stays
$breakpoint = 'md';
$widths = array_fill(0, $count, (string) intdiv(12, max(1, $count)));
$supported = ['6 6', '8 4', '4 8', '7 5', '5 7', '9 3', '3 9', '4 4 4', '3 3 3 3'];
if ($classes) {
    $breakpoints = [];
    foreach ($classes as $key => $class) {
        if (!preg_match('/^col-(sm|md|lg)-(\d+)$/', trim($class), $match)) {
            $breakpoints = null;
            break;
        }
        $breakpoints[] = $match[1];
        $widths[$key] = $match[2];
    }
    $breakpoint = null === $breakpoints || 1 !== count(array_unique($breakpoints)) ? null : $breakpoints[0];
}

if (null === $breakpoint || !in_array(implode(' ', $widths), $supported, true)) {
    echo '<div class="row">';
    foreach ($contents as $key => $content) {
        echo '<div class="' . ($classes[$key] ?? 'col-md-' . intdiv(12, $count)) . '">' . $content . '</div>';
    }
    echo '</div>';

    return;
}

echo '<div class="rex-grid" data-from="' . $breakpoint . '" data-columns="' . implode(' ', $widths) . '">';
foreach ($contents as $content) {
    echo '<div>' . $content . '</div>';
}
echo '</div>';
