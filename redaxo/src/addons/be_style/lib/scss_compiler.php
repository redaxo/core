<?php

use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\Formatter;
use ScssPhp\ScssPhp\Formatter\Compressed;

/**
 * @package redaxo\be-style
 */
class rex_scss_compiler
{
    /** @var string */
    protected $root_dir;
    /** @var string|list<string> */
    protected $scss_file;
    /** @var string */
    protected $css_file;
    /** @var class-string<Formatter> */
    protected $formatter = Compressed::class;

    public function __construct()
    {
        $this->root_dir = rex_path::addon('be_style');
        $this->scss_file = rex_path::addon('be_style', 'assets') . 'styles.scss';
        $this->css_file = rex_path::addon('be_style', 'assets') . 'styles.css';
    }

    /**
     * @param string $value
     * @return void
     */
    public function setRootDir($value)
    {
        $this->root_dir = $value;
    }

    /**
     * @param string|list<string> $value
     * @return void
     */
    public function setScssFile($value)
    {
        $this->scss_file = $value;
    }

    /**
     * @param string $value
     * @return void
     */
    public function setCssFile($value)
    {
        $this->css_file = $value;
    }

    /**
     * @param class-string<Formatter> $value scss_formatter (default) or scss_formatter_nested or scss_formatter_compressed
     * @return void
     */
    public function setFormatter($value)
    {
        $this->formatter = $value;
    }

    /** @return void */
    public function compile()
    {
        // go on even if user "stops" the script by closing the browser, closing the terminal etc.
        ignore_user_abort(true);
        // set script running time to unlimited
        set_time_limit(0);

        $rootDir = $this->root_dir;

        $scssCompiler = new Compiler();

        $scssCompiler->addImportPath(static function ($path) use ($rootDir) {
            $path = $rootDir . $path . '.scss';

            $pathParts = pathinfo($path);
            $underscoreFile = $pathParts['dirname'] . '/_' . $pathParts['basename'];

            if (is_file($underscoreFile)) {
                $path = $underscoreFile;
            }

            if (!is_file($path)) {
                return null;
            }

            return $path;
        });
        // set the path to your to-be-imported mixins. please note: custom paths are coming up on future releases!
        // $scss_compiler->setImportPaths($scss_folder);

        // set css formatting (normal, nested or minimized), @see https://leafo.net/scssphp/docs/#output_formatting
        /** @psalm-suppress DeprecatedMethod */
        $scssCompiler->setFormatter($this->formatter); /** @phpstan-ignore-line */

        // get .scss's content, put it into $string_sass
        $stringSass = '';
        if (is_array($this->scss_file)) {
            foreach ($this->scss_file as $scssFile) {
                $stringSass .= rex_file::require($scssFile);
            }
        } else {
            $stringSass = rex_file::require($this->scss_file);
        }

        // try/catch block to prevent script stopping when scss compiler throws an error
        try {
            // compile this SASS code to CSS
            $stringCss = $scssCompiler->compileString($stringSass)->getCss() . "\n";

            // $string_css = csscrush_string($string_css, $options = array('minify' => true));

            // write CSS into file with the same filename, but .css extension
            rex_file::put($this->css_file, $stringCss);
        } catch (Exception $e) {
            // here we could put the exception message, but who cares ...
            echo $e->getMessage();
            exit(1);
        }
    }
}
