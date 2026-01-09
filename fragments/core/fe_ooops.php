<?php

use Redaxo\Core\Http\Response;
use Redaxo\Core\View\Fragment;

/**
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title>Ooops, something went wrong!</title>

    <style type="text/css" nonce="<?= Response::getNonce() ?>">
        html, body {
            height: 100%;
            background-color: #f7f7f7;
        }

        body {
            display: flex;
            align-items: center;
        }

        .ooops-container {
            max-width: 500px;
            min-width: 300px;
            width: 50%;
            margin: 0 auto;
            color: #999;
            font-family: -apple-system, BlinkMacSystemFont, "Helvetica Neue", Arial, sans-serif;
            font-size: 15px;
            line-height: 1.5;
            text-align: center;
        }

        .ooops-error a {
            color: #666;
        }
        .ooops-error a:hover {
            color: #111;
        }

        .ooops-error-title {
            margin: 0;
            font-size: 50px;
            font-weight: 700;
            text-shadow: 0 2px 2px rgba(0, 0, 0, 0.2);
        }

        .ooops-error-message {
            padding: 0 20px;
        }

        @media (prefers-color-scheme: dark) {
            html, body {
                background-color: #333;
            }

            .ooops-error a {
                color: #b3b3b3;
            }
            .ooops-error a:hover {
                color: #c6c6c6;
            }
        }
    </style>
</head>
<body>
    <div class="ooops-container">
        <div class="ooops-error">
            <p class="ooops-error-title">Ooops</p>
            <p class="ooops-error-message">Looks like something went wrong.</p>
            <?= $this->getVar('content', '') ?>
        </div>
    </div>
</body>
</html>
