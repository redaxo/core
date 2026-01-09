<?php

use Redaxo\Core\View\Fragment;

/**
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */
?>
<?php

/**
 * Discussion Issue #1174
 * Manipulate this fragment to influence the selection of modules on the slice.
 * By default the core fragment is used.
 *
 * @var bool $block
 * @var string $button_label
 * @var array<int, array{id: string, key: string, title: string, href: string}> $items array contains all modules
 *             [0]        the index of array
 *             - [id]     the module id
 *             - [key]    the module key
 *             - [title]  the module name
 *             - [href]   the module url
 */

$this->subfragment('/core/dropdowns/dropdown.php');
