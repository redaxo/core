<?php

namespace Redaxo\Core\Util;

use Redaxo\Core\Core;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Security\BackendLogin;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\ContextProvider\SourceContextProvider;
use Symfony\Component\VarDumper\Dumper\ContextualizedDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\VarDumper as BaseVarDumper;

use const PHP_SAPI;

/** @internal */
final class VarDumper
{
    private static ?VarCloner $cloner = null;
    private static ?ContextualizedDumper $dumper = null;

    private function __construct() {}

    public static function register(): void
    {
        BaseVarDumper::setHandler(static function ($var, ?string $label = null) {
            if (Core::isDebugMode() || ($user = BackendLogin::createUser()) && $user->admin) {
                BaseVarDumper::setHandler(self::dump(...));
                self::dump($var, $label);

                return;
            }

            // register noop handler for non-admins (if not in debug mode)
            BaseVarDumper::setHandler(static function () {
                // noop
            });
        });
    }

    public static function dump(mixed $var, ?string $label = null): void
    {
        if (!self::$cloner || !self::$dumper) {
            self::$cloner = new VarCloner();
            if ('cli' === PHP_SAPI) {
                $dumper = new CliDumper();
            } else {
                $styleAll = 'font-family: "Fira Code", Menlo, Monaco, Consolas, monospace; font-size: 14px; line-height: 1.4 !important;';
                $dumper = new HtmlDumper();
                $dumper->setNonce(static fn () => Response::getNonce());
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
                        /** @var Editor|null $editor */
                        static $editor;
                        $editor ??= Editor::factory();

                        return $editor->getUrl($file, $line) ?? false;
                    }
                },
            ]);

            self::$dumper = new ContextualizedDumper($dumper, [new SourceContextProvider(null, Path::base())]);
        }

        $var = self::$cloner->cloneVar($var);
        if (null !== $label) {
            $var = $var->withContext(['label' => $label]);
        }

        self::$dumper->dump($var);
    }
}
