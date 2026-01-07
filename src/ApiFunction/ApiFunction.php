<?php

namespace Redaxo\Core\ApiFunction;

use BadMethodCallException;
use Redaxo\Core\Addon\ApiFunction\AddonOperation;
use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\Base\FactoryTrait;
use Redaxo\Core\Content\ApiFunction as ContentApiFunction;
use Redaxo\Core\Core;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Http\Context;
use Redaxo\Core\Http\Exception\HttpException;
use Redaxo\Core\Http\Exception\NotFoundHttpException;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\MetaInfo\ApiFunction\DefaultFieldsCreate;
use Redaxo\Core\Security\ApiFunction as SecurityApiFunction;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Security\Login;
use Redaxo\Core\Translation\I18n;

use function is_string;
use function Redaxo\Core\View\escape;
use function sprintf;

/**
 * This is a base class for all functions which a component may provide for public use.
 * Those function will be called automatically by the core.
 * Inside an api function you might check the preconditions which have to be met (permissions, etc.)
 * and forward the call to an underlying service which does the actual job.
 *
 * There can only be one ApiFunction called per request, but not every request must have an api function.
 *
 * The classname of a possible implementation must start with "rex_api" or must be registered explicitly via `ApiFunction::register()`.
 *
 * A api function may also be called by an ajax-request.
 * In fact there might be ajax-requests which do nothing more than triggering an api function.
 *
 * The api functions return meaningfull error messages which the caller may display to the end-user.
 *
 * Calling a api function with the backend-frontcontroller (index.php) requires a valid page parameter and the current user needs permissions to access the given page.
 *
 * @psalm-consistent-constructor
 */
abstract class ApiFunction
{
    use FactoryTrait;

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
     * @var Result|null
     */
    protected $result;

    /**
     * Explicitly registered api functions.
     *
     * @var array<string, class-string<ApiFunction>>
     */
    private static $functions = [
        'addon_operation' => AddonOperation::class,
        'article_add' => ContentApiFunction\ArticleAdd::class,
        'article_copy' => ContentApiFunction\ArticleCopy::class,
        'article_delete' => ContentApiFunction\ArticleDelete::class,
        'article_edit' => ContentApiFunction\ArticleEdit::class,
        'article_move' => ContentApiFunction\ArticleMove::class,
        'article_slice_move' => ContentApiFunction\ArticleSliceMove::class,
        'article_slice_status_change' => ContentApiFunction\ArticleSliceStatusChange::class,
        'article_status_change' => ContentApiFunction\ArticleStatusChange::class,
        'article_to_category' => ContentApiFunction\ArticleToCategory::class,
        'article_to_startarticle' => ContentApiFunction\ArticleToStartArticle::class,
        'category_add' => ContentApiFunction\CategoryAdd::class,
        'category_delete' => ContentApiFunction\CategoryDelete::class,
        'category_edit' => ContentApiFunction\CategoryEdit::class,
        'category_move' => ContentApiFunction\CategoryMove::class,
        'category_status_change' => ContentApiFunction\CategoryStatusChange::class,
        'category_to_article' => ContentApiFunction\CategoryToArticle::class,
        'content_copy' => ContentApiFunction\ContentCopy::class,
        'metainfo_default_fields_create' => DefaultFieldsCreate::class,
        'user_has_session' => SecurityApiFunction\UserHasSession::class,
        'user_impersonate' => SecurityApiFunction\UserImpersonate::class,
        'user_remove_auth_method' => SecurityApiFunction\UserRemoveAuthMethod::class,
        'user_remove_session' => SecurityApiFunction\UserRemoveSession::class,
        'user_session_status' => SecurityApiFunction\UserSessionStatus::class,
    ];

    /**
     * The api function which is bound to the current request.
     *
     * @var ApiFunction|null
     */
    private static $instance;

    protected function __construct() {}

    /**
     * This method have to be overriden by a subclass and does all logic which the api function represents.
     *
     * In the first place this method may retrieve and validate parameters from the request.
     * Afterwards the actual logic should be executed.
     *
     * This function may also throw exceptions e.g. in case when permissions are missing or the provided parameters are invalid.
     *
     * @return Result The result of the api-function
     */
    abstract public function execute();

    /**
     * Returns the api function instance which is bound to the current request, or null if no api function was bound.
     */
    public static function factory(): ?self
    {
        if (self::$instance) {
            return self::$instance;
        }

        $api = Request::request(self::REQ_CALL_PARAM, 'string');

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
                throw new NotFoundHttpException('API class is expected to define a subclass of ApiFunction, "' . $apiClass . '" given.');
            }
            throw new NotFoundHttpException('API class "' . $apiClass . '" not found.');
        }

        return null;
    }

    /**
     * @param class-string<ApiFunction> $class
     */
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
            throw new LogicException(__FUNCTION__ . ' must be called on subclasses of "' . self::class . '".');
        }

        return [self::REQ_CALL_PARAM => self::getName($class), CsrfToken::PARAM => CsrfToken::factory($class)->getValue()];
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

        return sprintf('<input type="hidden" name="%s" value="%s"/>', self::REQ_CALL_PARAM, escape(self::getName($class)))
            . CsrfToken::factory($class)->getHiddenField();
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
                if (!Core::isBackend()) {
                    throw new HttpException(new ApiFunctionException('the api function ' . $apiFunc::class . ' is not published, therefore can only be called from the backend.'), Response::HTTP_FORBIDDEN);
                }

                if (!Core::getUser()) {
                    throw new HttpException(new ApiFunctionException('missing backend session to call api function ' . $apiFunc::class . '.'), Response::HTTP_UNAUTHORIZED);
                }
            }

            $urlResult = Request::get(self::REQ_RESULT_PARAM, 'string');
            if ($urlResult) {
                // take over result from url and session and do not execute the apiFunc
                Login::startSession();
                $result = Request::session(self::REQ_RESULT_PARAM, 'array[string]', [])[$urlResult] ?? null;
                if (!is_string($result)) {
                    throw new NotFoundHttpException(new ApiFunctionException('The result of the api function is not available in the session.'));
                }

                $apiFunc->result = Result::fromJSON($result);
            } else {
                if ($apiFunc->requiresCsrfProtection() && !CsrfToken::factory($apiFunc::class)->isValid()) {
                    $result = new Result(false, I18n::msg('csrf_token_invalid'));
                    $apiFunc->result = $result;

                    return;
                }

                try {
                    $result = $apiFunc->execute();

                    if (!($result instanceof Result)) {
                        throw new LogicException('Illegal result returned from api-function ' . Request::get(self::REQ_CALL_PARAM) . '. Expected a instance of ApiFunctionResult but got "' . get_debug_type($result) . '".');
                    }

                    $apiFunc->result = $result;
                    if ($result->requiresReboot()) {
                        // add api call result to session
                        Login::startSession();
                        $results = Request::session(self::REQ_RESULT_PARAM, 'array', []);
                        $result = $result->toJSON();
                        $key = sha1($result);
                        $results[$key] = $result;
                        Request::setSession(self::REQ_RESULT_PARAM, $results);

                        // and redirect to SELF for reboot with session key as parameter
                        Response::sendRedirect(Context::fromGet()->getUrl([
                            self::REQ_RESULT_PARAM => $key,
                        ]));
                    }
                } catch (ApiFunctionException $e) {
                    $message = $e->getMessage();
                    $result = new Result(false, $message);
                    $apiFunc->result = $result;
                }
            }
        }
    }

    /**
     * @return bool
     */
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

    /**
     * @return Result|null
     */
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

        throw new LogicException('The api function "' . $class . '" is not registered.');
    }
}
