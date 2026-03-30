<?php

/**
 * This is a base class for all functions which a component may provide for public use.
 * Those function will be called automatically by the core.
 * Inside an api function you might check the preconditions which have to be met (permissions, etc.)
 * and forward the call to an underlying service which does the actual job.
 *
 * There can only be one rex_api_function called per request, but not every request must have an api function.
 *
 * The classname of a possible implementation must start with "rex_api" or must be registered explicitly via `rex_api_function::register()`.
 *
 * A api function may also be called by an ajax-request.
 * In fact there might be ajax-requests which do nothing more than triggering an api function.
 *
 * The api functions return meaningfull error messages which the caller may display to the end-user.
 *
 * Calling a api function with the backend-frontcontroller (index.php) requires a valid page parameter and the current user needs permissions to access the given page.
 *
 * @author staabm
 *
 * @package redaxo\core
 */
abstract class rex_api_function
{
    use rex_factory_trait;

    public const REQ_CALL_PARAM = 'rex-api-call';
    public const REQ_RESULT_PARAM = 'rex-api-result';

    /**
     * Flag, indicating if this api function may be called from the frontend. False by default.
     *
     * @var bool
     */
    protected $published = false;

    /**
     * The result of the function call.
     *
     * @var rex_api_result|null
     */
    protected $result;

    /**
     * Explicitly registered api functions.
     *
     * @var array<string, class-string<self>>
     */
    private static $functions = [];

    /**
     * The api function which is bound to the current request.
     *
     * @var rex_api_function|null
     */
    private static $instance;

    protected function __construct()
    {
        // NOOP
    }

    /**
     * This method have to be overriden by a subclass and does all logic which the api function represents.
     *
     * In the first place this method may retrieve and validate parameters from the request.
     * Afterwards the actual logic should be executed.
     *
     * This function may also throw exceptions e.g. in case when permissions are missing or the provided parameters are invalid.
     *
     * @return rex_api_result The result of the api-function
     */
    abstract public function execute();

    /**
     * Returns the api function instance which is bound to the current request, or null if no api function was bound.
     *
     * @throws rex_exception
     *
     * @return self|null
     */
    public static function factory()
    {
        if (self::$instance) {
            return self::$instance;
        }

        $api = rex_request(self::REQ_CALL_PARAM, 'string');

        if ($api) {
            if (isset(self::$functions[$api])) {
                $apiClass = self::$functions[$api];
            } else {
                /** @psalm-taint-escape callable */ // It is intended that the class name suffix is coming from request param
                $apiClass = 'rex_api_' . $api;
            }

            if (class_exists($apiClass)) {
                $apiImpl = new $apiClass();
                if ($apiImpl instanceof self) {
                    self::$instance = $apiImpl;
                    return $apiImpl;
                }
                throw new rex_http_exception(new rex_exception('$apiClass is expected to define a subclass of rex_api_function, "' . $apiClass . '" given!'), rex_response::HTTP_NOT_FOUND);
            }
            throw new rex_http_exception(new rex_exception('$apiClass "' . $apiClass . '" not found!'), rex_response::HTTP_NOT_FOUND);
        }

        return null;
    }

    /** @param class-string<self> $class */
    public static function register(string $name, string $class): void
    {
        self::$functions[$name] = $class;
    }

    /**
     * Returns an array containing the `rex-api-call` and `_csrf_token` params.
     *
     * The method must be called on sub classes.
     *
     * @return array<string, string>
     */
    public static function getUrlParams()
    {
        $class = static::class;

        if (self::class === $class) {
            throw new BadMethodCallException(__FUNCTION__ . ' must be called on subclasses of "' . self::class . '".');
        }

        return [self::REQ_CALL_PARAM => self::getName($class), rex_csrf_token::PARAM => rex_csrf_token::factory($class)->getValue()];
    }

    /**
     * Returns the hidden fields for `rex-api-call` and `_csrf_token`.
     *
     * The method must be called on sub classes.
     *
     * @return string
     */
    public static function getHiddenFields()
    {
        $class = static::class;

        if (self::class === $class) {
            throw new BadMethodCallException(__FUNCTION__ . ' must be called on subclasses of "' . self::class . '".');
        }

        return sprintf('<input type="hidden" name="%s" value="%s"/>', self::REQ_CALL_PARAM, rex_escape(self::getName($class)))
            . rex_csrf_token::factory($class)->getHiddenField();
    }

    /**
     * checks whether an api function is bound to the current requests. If so, so the api function will be executed.
     *
     * @return void
     */
    public static function handleCall()
    {
        if ($factoryClass = static::getExplicitFactoryClass()) {
            $factoryClass::handleCall();
            return;
        }

        $apiFunc = self::factory();

        if (null != $apiFunc) {
            if (!$apiFunc->published) {
                if (!rex::isBackend()) {
                    throw new rex_http_exception(new rex_api_exception('the api function ' . $apiFunc::class . ' is not published, therefore can only be called from the backend!'), rex_response::HTTP_FORBIDDEN);
                }

                if (!rex::getUser()) {
                    throw new rex_http_exception(new rex_api_exception('missing backend session to call api function ' . $apiFunc::class . '!'), rex_response::HTTP_UNAUTHORIZED);
                }
            }

            $urlResult = rex_get(self::REQ_RESULT_PARAM, 'string');
            if ($urlResult) {
                // take over result from url and session and do not execute the apiFunc
                rex_login::startSession();
                $result = rex_session(self::REQ_RESULT_PARAM, 'array[string]', [])[$urlResult] ?? null;
                if (!is_string($result)) {
                    throw new rex_http_exception(new rex_api_exception('The result of the api function is not available in the session.'), rex_response::HTTP_NOT_FOUND);
                }

                $apiFunc->result = rex_api_result::fromJSON($result);
            } else {
                if ($apiFunc->requiresCsrfProtection() && !rex_csrf_token::factory($apiFunc::class)->isValid()) {
                    $result = new rex_api_result(false, rex_i18n::msg('csrf_token_invalid'));
                    $apiFunc->result = $result;

                    return;
                }

                try {
                    $result = $apiFunc->execute();

                    if (!$result instanceof rex_api_result) {
                        throw new rex_exception('Illegal result returned from api-function ' . rex_get(self::REQ_CALL_PARAM) . '. Expected a instance of rex_api_result but got "' . get_debug_type($result) . '".');
                    }

                    $apiFunc->result = $result;
                    if ($result->requiresReboot()) {
                        // add api call result to session
                        rex_login::startSession();
                        $results = rex_session(self::REQ_RESULT_PARAM, 'array', []);
                        $result = $result->toJSON();
                        $key = sha1($result);
                        $results[$key] = $result;
                        rex_set_session(self::REQ_RESULT_PARAM, $results);

                        // and redirect to SELF for reboot with session key as parameter
                        rex_response::sendRedirect(rex_context::fromGet()->getUrl([
                            self::REQ_RESULT_PARAM => $key,
                        ], false));
                    }
                } catch (rex_api_exception $e) {
                    $message = $e->getMessage();
                    $result = new rex_api_result(false, $message);
                    $apiFunc->result = $result;
                }
            }
        }
    }

    /** @return bool */
    public static function hasMessage()
    {
        $apiFunc = self::factory();

        if (!$apiFunc) {
            return false;
        }

        $result = $apiFunc->getResult();
        return $result && null !== $result->getMessage();
    }

    /**
     * @param bool $formatted
     * @return string
     */
    public static function getMessage($formatted = true)
    {
        $apiFunc = self::factory();
        $message = '';
        if ($apiFunc) {
            $apiResult = $apiFunc->getResult();
            if ($apiResult) {
                if ($formatted) {
                    $message = $apiResult->getFormattedMessage();
                } else {
                    $message = $apiResult->getMessage();
                }
            }
        }
        // return a placeholder which can later be used by ajax requests to display messages
        return '<div id="rex-message-container">' . $message . '</div>';
    }

    /** @return rex_api_result|null */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * Csrf validation is disabled by default for backwards compatiblity reasons. This default will change in a future version.
     * Prepare all your api functions to work with csrf token by using your-api-class::getUrlParams()/getHiddenFields(), otherwise they will stop work.
     *
     * @return bool
     */
    protected function requiresCsrfProtection()
    {
        return false;
    }

    private static function getName(string $class): string
    {
        $name = array_search($class, self::$functions, true);
        if (false !== $name) {
            return $name;
        }

        if (str_starts_with($class, 'rex_api_')) {
            return substr($class, 8);
        }

        throw new rex_exception('The api function "' . $class . '" is not registered.');
    }
}

/**
 * Class representing the result of a api function call.
 *
 * @author staabm
 *
 * @see rex_api_function
 *
 * @package redaxo\core
 */
class rex_api_result
{
    /**
     * Flag indicating whether the result of this api call needs to be rendered in a new sub-request.
     * This is required in rare situations, when some low-level data was changed by the api-function.
     *
     * @var bool
     */
    private $requiresReboot;

    /**
     * @param bool $succeeded flag indicating if the api function was executed successfully
     * @param string|null $message optional message which will be visible to the end-user
     */
    public function __construct(
        private $succeeded,
        private $message = null,
    ) {}

    /**
     * @param bool $requiresReboot
     * @return void
     */
    public function setRequiresReboot($requiresReboot)
    {
        $this->requiresReboot = $requiresReboot;
    }

    /** @return bool */
    public function requiresReboot()
    {
        return $this->requiresReboot;
    }

    /** @return string|null */
    public function getFormattedMessage()
    {
        if (null === $this->message) {
            return null;
        }

        if ($this->isSuccessfull()) {
            return rex_view::success($this->message);
        }
        return rex_view::error($this->message);
    }

    /**
     * Returns end-user friendly statusmessage.
     *
     * @return string|null a statusmessage
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Returns whether the api function was executed successfully.
     *
     * @return bool true on success, false on error
     */
    public function isSuccessfull()
    {
        return $this->succeeded;
    }

    /** @return string */
    public function toJSON()
    {
        return rex_type::string(json_encode([
            'succeeded' => $this->succeeded,
            'message' => $this->message,
        ]));
    }

    /**
     * @param string $json
     * @return self
     */
    public static function fromJSON($json)
    {
        $json = json_decode($json, true);

        if (!is_array($json)) {
            throw new rex_exception('Unable to decode json into an array.');
        }

        return new self(
            rex_type::bool($json['succeeded'] ?? null),
            rex_type::nullOrString($json['message'] ?? null),
        );
    }
}

/**
 * Exception-Type to indicate exceptions in an api function.
 * The messages of this exception will be displayed to the end-user.
 *
 * @author staabm
 *
 * @see rex_api_function
 *
 * @package redaxo\core
 */
class rex_api_exception extends rex_exception {}
