<?php
/**
 * @var rex_fragment $this
 * @psalm-scope-this rex_fragment
 */

$pictureName = 'jr-korpa-UFx4NcvTRBE-unsplash';

$url2400Avif = rex_url::pluginAssets('be_style', 'redaxo', 'images/' . $pictureName . '-2400.avif');
$url3500Avif = rex_url::pluginAssets('be_style', 'redaxo', 'images/' . $pictureName . '-3500.avif');
$url2400Webp = rex_url::pluginAssets('be_style', 'redaxo', 'images/' . $pictureName . '-2400.webp');
$url3500Webp = rex_url::pluginAssets('be_style', 'redaxo', 'images/' . $pictureName . '-3500.webp');

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

<style nonce="<?= rex_response::getNonce() ?>">
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
        background-color: #b5400b;
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

<script nonce="<?= rex_response::getNonce() ?>">
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
            <li class="rex-background-credits">Photo by <a href="https://unsplash.com/de/@jrkorpa" target="_blank" rel="noreferrer noopener">@jrkorpa</a> on <a href="https://unsplash.com/de/fotos/braun-weisse-abstrakte-malerei-UFx4NcvTRBE"  target="_blank" rel="noreferrer noopener">Unsplash</a></li>
        </ul>
    </nav>
</footer>
