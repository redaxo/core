<?php

use Redaxo\Core\Http\Response;
use Redaxo\Core\SystemReport;
use Redaxo\Core\Translation\I18n;

use function Redaxo\Core\View\escape;

$report = SystemReport::factory()->asMarkdown();

echo '<p><clipboard-copy for="rex-system-report-markdown" class="btn btn-copy btn-primary">' . I18n::msg('copy_to_clipboard') . '</clipboard-copy></p>';

// there must be no whitespace between div and pre and within pre
// otherwise the copied markdown via clipboard-copy can be invalid
echo '<div id="rex-system-report-markdown" contenteditable="true" spellcheck="false">';
echo '<pre><code>' . escape($report) . '</code></pre>';
echo '</div>';

echo '
    <script nonce="' . Response::getNonce() . '">
        $("#rex-system-report-markdown")
            .on("cut paste", function (event) {
                event.preventDefault();
            })
            .on("keydown", function (event) {
                if (!event.metaKey) {
                    event.preventDefault();
                }
            });
    </script>
';
