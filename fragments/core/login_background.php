<?php

use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Response;
use Redaxo\Core\View\Fragment;

/**
 * @var Fragment $this
 * @psalm-scope-this Fragment
 */

$pictureName = 'and-machines-HErhYBxhreE-unsplash';

$url2400Avif = Url::coreAssets('images/' . $pictureName . '-2400.avif');
$url3500Avif = Url::coreAssets('images/' . $pictureName . '-3500.avif');
$url2400Webp = Url::coreAssets('images/' . $pictureName . '-2400.webp');
$url3500Webp = Url::coreAssets('images/' . $pictureName . '-3500.webp');

?>
<picture class="rex-background">
    <source
        srcset="
            <?= $url2400Avif ?> 2400w,
            <?= $url3500Avif ?> 3500w"
        sizes="100vw"
        type="image/avif"
    />
    <img
        alt=""
        src="<?= $url2400Webp ?>"
        srcset="
            <?= $url2400Webp ?> 2400w,
            <?= $url3500Webp ?> 3500w"
        sizes="100vw"
    />
</picture>

<style nonce="<?= Response::getNonce() ?>">
    #rex-page-login {
        background-color: #000000;
    }
    #rex-page-login .panel-default {
        background-color: rgba(33, 38, 41, 0.9);
    }

    #rex-page-login .btn-primary {
        background-color: #006ec6;
        border-color: rgba(0, 0, 0, 0.5);
    }
    #rex-page-login .btn-primary:hover,
    #rex-page-login .btn-primary:focus {
        background-color: #BB017A;
    }

    #rex-page-login .rex-global-footer {
        mix-blend-mode: difference;
    }
    @supports (mix-blend-mode: plus-lighter) {
        #rex-page-login .rex-global-footer {
            mix-blend-mode: plus-lighter;
        }
    }
</style>

<script nonce="<?= Response::getNonce() ?>">
    var picture = document.querySelector('.rex-background');
    picture.classList.add('rex-background--process');
    picture.querySelector('img').onload = function() {
        picture.classList.add('rex-background--ready');
    }
</script>

<footer class="rex-global-footer">
    <nav class="rex-nav-footer">
        <ul class="list-inline">
            <li><a href="https://www.yakamara.de" target="_blank" rel="noreferrer noopener">yakamara.de</a></li>
            <li><a href="https://www.redaxo.org" target="_blank" rel="noreferrer noopener">redaxo.org</a></li>
            <li class="rex-background-credits">Photo by <a href="https://unsplash.com/de/@and_machines" target="_blank" rel="noreferrer noopener">@and_machines</a> on <a href="https://unsplash.com/de/fotos/ein-schwarzer-hintergrund-mit-blauen-und-rosa-linien-HErhYBxhreE"  target="_blank" rel="noreferrer noopener">Unsplash</a></li>
        </ul>
    </nav>
</footer>
