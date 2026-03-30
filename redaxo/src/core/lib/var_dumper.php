<?php

use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\ContextProvider\SourceContextProvider;
use Symfony\Component\VarDumper\Dumper\ContextualizedDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\VarDumper;

/**
 * @package redaxo\core
 */
abstract class rex_var_dumper
{
    private static ?VarCloner $cloner = null;
    private static ?ContextualizedDumper $dumper = null;

    /** @return void */
    public static function register()
    {
        VarDumper::setHandler(static function ($var, ?string $label = null) {
            if (rex::isDebugMode() || ($user = rex_backend_login::createUser()) && $user->isAdmin()) {
                VarDumper::setHandler(self::dump(...));
                self::dump($var, $label);

                return;
            }

            // register noop handler for non-admins (if not in debug mode)
            VarDumper::setHandler(static function () {
                // noop
            });
        });
    }

    /**
     * @param mixed $var
     * @return void
     */
    public static function dump($var, ?string $label = null)
    {
        if (!self::$cloner || !self::$dumper) {
            self::$cloner = new VarCloner();
            if ('cli' === PHP_SAPI) {
                $dumper = new CliDumper();
            } else {
                $styleAll = 'font-family: "Fira Code", Menlo, Monaco, Consolas, monospace; font-size: 14px; line-height: 1.4 !important;';
                $dumper = new HtmlDumper();
                $dumper->setDumpBoundaries('<pre class="rex-var-dumper sf-dump" id="%s" data-indent-pad="%s">', '</pre><script>Sfdump(%s)</script>');
                $dumper->setIndentPad('    ');
                $dumper->setStyles([
                    'default' => $styleAll . '
                        position: relative;
                        z-index: 99999;
                        background-color: #002635;
                        color: #FF8400;
                        white-space: pre-wrap;
                        word-break: break-all;
                        word-wrap: break-word;
                    ',
                    'const' => $styleAll . 'color: #F78C6C; font-weight: 700;',
                    'ellipsis' => $styleAll . 'color: #eeffff;',
                    'expanded' => $styleAll . '
                        white-space: pre;
                        background: unset;
                        color: inherit;
                    ',
                    'index' => $styleAll . 'color: #C3E88D;',
                    'key' => $styleAll . 'color: #C3E88D;',
                    'meta' => $styleAll . 'color: #89DDFF;',
                    'note' => $styleAll . 'color: #FFB62C;',
                    'num' => $styleAll . 'color: #F78C6C;',
                    'protected' => $styleAll . 'color: #C792EA;',
                    'private' => $styleAll . 'color: #C792EA;',
                    'public' => $styleAll . 'color: #C792EA;',
                    'ref' => $styleAll . 'color: #eeffff;',
                    'str' => $styleAll . 'color: #FF5370;',
                    'search-wrapper' => 'margin-bottom: 10px;',
                    'search-input' => 'height: 26px !important; background-color: #f3f6fb !important;',
                    'search-count' => 'height: 26px !important; line-height: 26px !important;',
                    'search-input-previous' => 'height: 26px !important;',
                    'search-input-next' => 'height: 26px !important;',
                ]);
            }

            $dumper->setDisplayOptions([
                'fileLinkFormat' => new class {
                    public function format(string $file, string $line): string|false
                    {
                        /** @var rex_editor|null $editor */
                        static $editor;
                        $editor ??= rex_editor::factory();

                        return $editor->getUrl($file, $line) ?? false;
                    }
                },
            ]);

            self::$dumper = new ContextualizedDumper($dumper, [new SourceContextProvider(null, rex_path::base())]);
        }

        $var = self::$cloner->cloneVar($var);
        if (null !== $label) {
            $var = $var->withContext(['label' => $label]);
        }

        self::$dumper->dump($var);
    }
}
