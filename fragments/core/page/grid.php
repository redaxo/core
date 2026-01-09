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

switch ($count) {
    case '4':
        echo '<div class="row">';

        foreach ($contents as $key => $content) {
            echo '<div class="' . ($classes[$key] ?? 'col-sm-6 col-md-3') . '">' . $content . '</div>';
        }

        echo '</div>';

        break;

    case '3':
        echo '<div class="row">';

        foreach ($contents as $key => $content) {
            echo '<div class="' . ($classes[$key] ?? 'col-md-4') . '">' . $content . '</div>';
        }

        echo '</div>';

        break;

    case '2':
        echo '<div class="row">';

        foreach ($contents as $key => $content) {
            echo '<div class="' . ($classes[$key] ?? 'col-md-6') . '">' . $content . '</div>';
        }

        echo '</div>';

        break;

    default:
        foreach ($contents as $content) {
            echo $content;
        }

        break;
}
