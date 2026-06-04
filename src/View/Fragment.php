<?php

namespace Redaxo\Core\View;

use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Timer;
use Redaxo\Core\Util\Type;
use Stringable;

use function array_key_exists;
use function sprintf;

use const DIRECTORY_SEPARATOR;
use const E_USER_WARNING;

/**
 * Methods declared here are available to be called from within a fragment-file, even if not public:.
 *
 * @phpstan-method void   subfragment(string $filename, array $params = [])
 * @phpstan-method string getSubfragment(string $filename, array $params = [])
 * @phpstan-method string i18n(string $key, ...$replacements)
 * @phpstan-method mixed  escape($value, $strategy = 'html')
 */
class Fragment
{
    /** filename of the actual fragmentfile. */
    private string $filename;

    /** another fragment which can optionaly be used to decorate the current fragment. */
    private ?self $decorator = null;

    /**
     * array which contains all folders in which fragments will be searched for at runtime.
     *
     * @var list<string>
     */
    private static array $fragmentDirs = [];

    /**
     * Creates a fragment with the given variables.
     *
     * @param array<string, mixed> $vars A array of key-value pairs to pass as local parameters
     */
    public function __construct(
        private array $vars = [],
    ) {}

    /** Returns the value of the given variable $name. */
    public function getVar(string $name, mixed $default = null): mixed
    {
        return $this->vars[$name] ?? $default;
    }

    /**
     * Set the variable $name to the given value.
     *
     * @param bool $escape Flag which indicates if the value should be escaped or not
     */
    public function setVar(string $name, mixed $value, bool $escape = true): static
    {
        if ($escape) {
            $this->vars[$name] = $this->escape($value);
        } else {
            $this->vars[$name] = $value;
        }

        return $this;
    }

    /**
     * Parses the variables of the fragment into the file $filename.
     *
     * @param string $filename the filename of the fragment to parse
     */
    public function parse(string $filename): string
    {
        $this->filename = $filename;

        foreach (self::$fragmentDirs as $fragDir) {
            $fragment = $fragDir . $filename;
            if (is_readable($fragment)) {
                $content = Timer::measure('Fragment: ' . $filename, function () use ($fragment) {
                    ob_start();
                    require $fragment;

                    return Type::string(ob_get_clean());
                });

                if ($this->decorator) {
                    $this->decorator->setVar('rexDecoratedContent', $content, false);
                    $content = $this->decorator->parse($this->decorator->filename);
                }

                return $content;
            }
        }

        throw new InvalidArgumentException(sprintf('Fragment file "%s" not found.', $filename));
    }

    /**
     * Decorate the current fragment, with another fragment.
     * The decorated fragment receives the parameters which are passed to this method.
     *
     * @param string $filename The filename of the fragment used for decoration
     * @param array<string, mixed> $params A array of key-value pairs to pass as parameters
     *
     * @return $this
     */
    public function decorate(string $filename, array $params): static
    {
        $this->decorator = new self($params);
        $this->decorator->filename = $filename;

        return $this;
    }

    // -------------------------- in-fragment helpers

    /**
     * Escapes the value $val for proper use in the gui.
     *
     * @template T
     * @param T $value The value to escape
     * @param 'html'|'html_simplified'|'html_attr'|'js'|'css'|'url' $strategy One of "html", "html_attr", "css", "js", "url"
     *
     * @return (T is Stringable ? string : T)
     */
    protected function escape(mixed $value, string $strategy = 'html'): mixed
    {
        return escape($value, $strategy);
    }

    /**
     * Returns the content of a Subfragment from within a fragment.
     *
     * The Subfragment gets all variables of the current fragment, plus optional overrides from $params
     *
     * @param string $filename The filename of the fragment to use
     * @param array<string, mixed> $params A array of key-value pairs to pass as local parameters
     */
    protected function getSubfragment(string $filename, array $params = []): string
    {
        $fragment = new self(array_merge($this->vars, $params));
        return $fragment->parse($filename);
    }

    /**
     * Include a Subfragment from within a fragment.
     *
     * The Subfragment gets all variables of the current fragment, plus optional overrides from $params
     *
     * @param string $filename The filename of the fragment to use
     * @param array<string, mixed> $params A array of key-value pairs to pass as local parameters
     */
    protected function subfragment(string $filename, array $params = []): void
    {
        echo $this->getSubfragment($filename, $params);
    }

    /**
     * Translate the given key $key.
     *
     * @param string $key The key to translate
     * @param string|int ...$replacements A arbritary number of strings used for interpolating within the resolved message
     *
     * @return string Translation for the key
     */
    protected function i18n(string $key, string|int ...$replacements): string
    {
        return I18n::msg($key, ...$replacements);
    }

    /** Magic getter to reference variables from within the fragment. */
    public function __get(string $name): mixed
    {
        if (isset($this->vars[$name]) || array_key_exists($name, $this->vars)) {
            return $this->vars[$name];
        }

        trigger_error(sprintf('Undefined variable "%s" in Fragment "%s"', $name, $this->filename), E_USER_WARNING);

        return null;
    }

    /** Magic method to check if a variable is set. */
    public function __isset(string $name): bool
    {
        return isset($this->vars[$name]) || array_key_exists($name, $this->vars);
    }

    /**
     * Add a path to the fragment search path.
     *
     * @param string $dir A path to a directory where fragments can be found
     */
    public static function addDirectory(string $dir): void
    {
        // add the new directory in front of the already know dirs,
        // so a later caller can override core settings/fragments
        $dir = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        array_unshift(self::$fragmentDirs, $dir);
    }
}
