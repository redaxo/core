<?php

namespace Redaxo\Core\Http;

use Override;
use Redaxo\Core\Core;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Util\Str;

use function is_array;
use function Redaxo\Core\View\escape;

/**
 * A generic implementation of ContextProvider.
 *
 * @psalm-import-type TUrlParam from Str
 * @psalm-import-type TUrlParams from Str
 */
class Context implements ContextProviderInterface
{
    /**
     * Constructs a Context with the given global parameters.
     *
     * @param TUrlParams $params
     */
    public function __construct(
        private array $params = [],
    ) {}

    #[Override]
    public function getUrl(array $params = []): string
    {
        // combine global params with local
        $params = array_merge($this->params, $params);

        return Core::isBackend() ? Url::backendController($params) : Url::frontendController($params);
    }

    /**
     * Set a global parameter.
     *
     * @param TUrlParam $value
     */
    public function setParam(string $name, string|int|bool|array|null $value): void
    {
        $this->params[$name] = $value;
    }

    /**
     * Returns the value associated with the given parameter $name.
     * When no value is set, $default will be returned.
     *
     * @param TUrlParam $default
     * @return TUrlParam
     */
    public function getParam(string $name, string|int|bool|array|null $default = null): string|int|bool|array|null
    {
        return $this->params[$name] ?? $default;
    }

    /**
     * Returns the global parameters.
     *
     * @return TUrlParams
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /** Returns whether the given parameter exists. */
    public function hasParam(string $name): bool
    {
        return isset($this->params[$name]);
    }

    /** Removes a global parameter. */
    public function removeParam(string $name): void
    {
        unset($this->params[$name]);
    }

    #[Override]
    public function getHiddenInputFields(array $params = []): string
    {
        // combine global params with local
        $params = array_merge($this->params, $params);

        return self::array2inputStr($params);
    }

    /** Returns a Context instance containing all GET and POST parameters. */
    public static function restore(): self
    {
        // $_REQUEST contains some server specific globals, therefore we merge GET and POST manually
        /** @psalm-suppress InvalidArgument */
        return new self($_GET + $_POST);
    }

    /** Returns a Context instance containing all GET parameters. */
    public static function fromGet(): self
    {
        /** @psalm-suppress InvalidArgument */
        return new self($_GET);
    }

    /** Returns a Context instance containing all POST parameters. */
    public static function fromPost(): self
    {
        /** @psalm-suppress InvalidArgument */
        return new self($_POST);
    }

    /**
     * Helper method to generate a html string with hidden input fields from an array key-value pairs.
     *
     * @param TUrlParams $array
     */
    private static function array2inputStr(array $array): string
    {
        $inputString = '';
        foreach ($array as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $valName => $valVal) {
                    $inputString .= '<input type="hidden" name="' . escape($name) . '[' . escape($valName) . ']" value="' . escape($valVal ?? '') . '" />';
                }
            } else {
                $inputString .= '<input type="hidden" name="' . escape($name) . '" value="' . escape($value ?? '') . '" />';
            }
        }

        return $inputString;
    }
}
