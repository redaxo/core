<?php

/**
 * @package redaxo\core
 */
class rex_editor
{
    use rex_factory_trait;

    public const EDITOR_ATOM = 'atom';
    public const EDITOR_EMACS = 'emacs';
    public const EDITOR_IDEA = 'idea';
    public const EDITOR_MACVIM = 'macvim';
    public const EDITOR_PHPSTORM = 'phpstorm';
    public const EDITOR_SUBLIME = 'sublime';
    public const EDITOR_TEXTMATE = 'textmate';
    public const EDITOR_VSCODE = 'vscode';
    public const EDITOR_XDEBUG = 'xdebug';

    // see https://github.com/filp/whoops/blob/master/docs/Open%20Files%20In%20An%20Editor.md
    // keep this list in sync with the array in getSupportedEditors() excluding xdebug
    /** @var array<self::EDITOR_*, string> */
    private array $editors = [
        self::EDITOR_ATOM => 'atom://core/open/file?filename=%f&line=%l',
        self::EDITOR_EMACS => 'emacs://open?url=file://%f&line=%l',
        self::EDITOR_IDEA => 'idea://open?file=%f&line=%l',
        self::EDITOR_MACVIM => 'mvim://open/?url=file://%f&line=%l',
        self::EDITOR_PHPSTORM => 'phpstorm://open?file=%f&line=%l',
        self::EDITOR_SUBLIME => 'subl://open?url=file://%f&line=%l',
        self::EDITOR_TEXTMATE => 'txmt://open?url=file://%f&line=%l',
        self::EDITOR_VSCODE => 'vscode://file/%f:%l',
    ];

    // we expect instantiation via factory()
    private function __construct() {}

    /**
     * Creates a rex_editor instance.
     *
     * @return static Returns a rex_editor instance
     */
    public static function factory()
    {
        $class = static::getFactoryClass();
        return new $class();
    }

    /**
     * @param string $filePath
     * @param int|numeric-string $line
     *
     * @return string|null
     */
    public function getUrl($filePath, $line)
    {
        $editor = $this->getName();

        $editorUrl = null;

        $editorBasepath = $this->getBasepath();
        if ($editorBasepath) {
            // replace remote base path with local base path
            $filePath = str_replace(rex_path::base(), $editorBasepath, $filePath);
        }

        if (null === $editor || str_contains($filePath, '://')) {
            // don't provide editor urls for paths containing "://", like "rex://..."
            // but they can be converted into an url by the extension point below
        } elseif (isset($this->editors[$editor]) || 'xdebug' === $editor) {
            if ('xdebug' === $editor) {
                // if xdebug is not enabled, use `get_cfg_var` to get the value directly from php.ini
                $editorUrl = ini_get('xdebug.file_link_format') ?: rex_type::string(get_cfg_var('xdebug.file_link_format'));
            } else {
                $editorUrl = $this->editors[$editor];
            }

            $editorUrl = str_replace('%l', (string) $line, $editorUrl);
            $editorUrl = str_replace('%f', $filePath, $editorUrl);
        }

        return rex_extension::registerPoint(new rex_extension_point('EDITOR_URL', $editorUrl, [
            'file' => $filePath,
            'line' => $line,
        ]));
    }

    /**
     * @return array<self::EDITOR_*, string>
     */
    public function getSupportedEditors()
    {
        return [
            self::EDITOR_ATOM => 'Atom',
            self::EDITOR_EMACS => 'Emacs',
            self::EDITOR_IDEA => 'IDEA',
            self::EDITOR_MACVIM => 'MacVim',
            self::EDITOR_PHPSTORM => 'PhpStorm',
            self::EDITOR_SUBLIME => 'Sublime Text',
            self::EDITOR_TEXTMATE => 'Textmate',
            self::EDITOR_VSCODE => 'Visual Studio Code',
            self::EDITOR_XDEBUG => 'Xdebug via xdebug.file_link_format (php.ini)',
        ];
    }

    /**
     * Returns the editor name, e.g. „atom“.
     *
     * @return self::EDITOR_*
     */
    public function getName(): ?string
    {
        $supportedEditors = $this->getSupportedEditors();

        $editor = array_key_exists('editor', $_COOKIE) ? $_COOKIE['editor'] : rex::getProperty('editor');

        if (null !== $editor && array_key_exists($editor, $supportedEditors)) {
            return $editor;
        }

        return null;
    }

    public function getBasepath(): ?string
    {
        $path = array_key_exists('editor_basepath', $_COOKIE) ? $_COOKIE['editor_basepath'] : rex::getProperty('editor_basepath');

        return $path ? rtrim($path, '\\/') . DIRECTORY_SEPARATOR : null;
    }
}
