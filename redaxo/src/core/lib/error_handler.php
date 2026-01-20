<?php

use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

/**
 * @package redaxo\core
 */
abstract class rex_error_handler
{
    private static bool $registered = false;

    /**
     * Registers the class as php-error/exception handler.
     * @return void
     */
    public static function register()
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        set_error_handler(self::handleError(...));
        set_exception_handler(self::handleException(...));
        register_shutdown_function([self::class, 'shutdown']);
    }

    /**
     * Unregisters the logger instance as php-error/exception handler.
     * @return void
     */
    public static function unregister()
    {
        if (!self::$registered) {
            return;
        }

        self::$registered = false;

        restore_error_handler();
        restore_exception_handler();
        // unregister of shutdown function is not possible
    }

    /**
     * Handles the given Exception.
     *
     * @param Throwable $exception The Exception to handle
     * @return never
     */
    public static function handleException($exception)
    {
        // make sure we regenerate the autoload cache, in case a class not found error
        // bubbled up into a oops/whoops error.
        if (preg_match('/^Class ".*" not found$/', $exception->getMessage())) {
            try {
                rex_autoload::removeCache();
            } catch (Throwable) {
                // ignore
            }
        }

        try {
            rex_logger::logException($exception, self::getUrl());

            // in case exceptions happen early - before symfony-console doRun()
            if ('cli' === PHP_SAPI) {
                /**
                 * actually it is not escaped, it is not necessary in cli output.
                 * @psalm-taint-escape has_quotes
                 * @psalm-taint-escape html
                 */
                $exceptionString = $exception->__toString();
                echo $exceptionString;
                exit(1);
            }

            while (ob_get_level()) {
                ob_end_clean();
            }

            $status = rex_response::HTTP_INTERNAL_ERROR;
            if ($exception instanceof rex_http_exception && $exception->getHttpCode()) {
                $status = $exception->getHttpCode();
            }
            rex_response::setStatus($status);

            if (rex::isSetup() || rex::isDebugMode() || !rex::isLiveMode() && rex_backend_login::createUser()?->isAdmin()) {
                [$errPage, $contentType] = self::renderWhoops($exception);
                rex_response::sendContent($errPage, $contentType);
                exit(1);
            }
        } catch (Throwable) {
            // fallback to the less feature rich error pages, when whoops rendering fails
        }

        try {
            $fragment = new rex_fragment();
            if (rex::isBackend()) {
                $errorPage = $fragment->parse('core/be_ooops.php');
            } else {
                $errorPage = $fragment->parse('core/fe_ooops.php');
            }
        } catch (Throwable) {
            // we werent even able to render the error page, without an error
            $errorPage = 'Oooops, an internal error occured!';
        }

        rex_response::sendContent($errorPage);
        exit(1);
    }

    /**
     * @param Throwable $exception
     * @return array{string, string}
     */
    private static function renderWhoops($exception)
    {
        $whoops = new Run();
        $whoops->writeToOutput(false);
        $whoops->allowQuit(false);

        $handler = new PrettyPageHandler();
        $handler->setApplicationRootPath(rtrim(rex_path::base(), '/\\'));

        $handler->setEditor([rex_editor::factory(), 'getUrl']);

        $handler->hideSuperglobalKey('_SESSION', rex_login::SESSION_ID);
        $handler->hideSuperglobalKey('_COOKIE', session_name());
        $handler->hideSuperglobalKey('_COOKIE', rex_backend_login::getStayLoggedInCookieName());

        $whoops->pushHandler($handler);

        $errPage = $whoops->handleException($exception);

        $logo = '
                <svg xmlns="http://www.w3.org/2000/svg" width="250px" height="30px" viewBox="0 0 250 30">
                    <path fill="#FFFFFF" d="M147.414,8.221c-0.344,0-5.73,0.003-5.73,0.003c-2.375,0-3.768,2.152-4.281,7.117c-0.571,5.528,1.038,6.839,3.428,6.787c0,0,4.383-0.018,4.983-0.018c2.578,0,3.868-1.401,4.424-6.75C150.776,9.795,150.006,8.221,147.414,8.221z M147.576,1.085c6.356,0,10.716,4.8,9.78,14.12c-0.93,9.264-4.338,14.051-11.097,14.039l-5.618,0.018c-7.919-0.022-11.169-6.19-10.311-14.756c0.813-8.112,4.823-13.417,10.842-13.417L147.576,1.085z M88.31,14.004h11.837l0.239-2.326c0.263-2.215-0.569-3.44-2.919-3.449h-5.445c-2.204,0.074-3.232,1.037-3.548,4.148L88.31,14.004z M99.5,20.268H87.678l-0.888,8.813h-7.095l1.755-17.412c0.727-6.39,4.402-10.456,10.553-10.456h5.471c6.285,0.021,10.614,4.443,9.936,11.176l-1.723,16.691H98.59L99.5,20.268L99.5,20.268z"/>
                    <path fill="#FFFFFF" d="M69.432,8.269h-6.758l-1.384,13.73h6.813c2.196,0,4.068-2.377,4.554-7.492C73.008,10.791,71.503,8.269,69.432,8.269z M69.713,1.213c14.65,0,12.27,27.844-1.457,27.844l-14.773,0.001l2.811-27.845H69.713L69.713,1.213z M53.957,8.239L40.618,8.237c-1.725,0-2.939,0.975-3.532,3.49h14.123l-0.631,6.263H36.413c0.11,2.896,1.056,3.845,3.481,3.854c2.037,0.009,8.634,0.106,12.677,0.127l-0.726,7.088H39.672c-7.177,0-11.146-5.596-10.233-14.52C30.402,5.116,34.176,1.21,40.609,1.21l14.05,0.003L53.957,8.239L53.957,8.239z M9.655,13.577h8.266c2.082,0,3.532-1.039,3.532-2.756c0-1.301-1.005-2.543-2.819-2.543c-1.406,0-5.36-0.021-8.44-0.038L9.655,13.577z M23.032,19.573l4.207,9.485h-7.722l-3.735-8.423H8.944l-0.85,8.423H1L3.802,1.215l14.655-0.006c6.585,0,10.478,5.11,10.075,9.686C28.215,14.502,26.893,17.467,23.032,19.573z"/>
                    <path fill="#FFFFFF" d="M114.552,1.279l5.167,8.52l5.461-8.52h8.835l-10.004,15.089l8.675,12.667h-8.309l-4.2-6.607l-4.398,6.607h-9.147l9.056-13.276l-9.043-14.479H114.552L114.552,1.279z"/>
                    <path fill="#FFFFFF" d="M167.358,21.674l-3.909-0.001c-0.938,0-1.487,0.334-1.746,2.553c-0.229,1.97,0.399,2.474,1.338,2.474c0.732,0,2.414,0.016,3.81,0.008l-0.26,2.581c-1.424,0.008-3.024-0.009-3.726-0.009c-2.691,0-4.008-2.053-3.729-5.312c0.258-3.015,1.581-4.874,4.075-4.874l4.408,0.001L167.358,21.674z M188.539,21.634l-4.229,0.002c-1.084,0-1.028,1.299-0.163,1.299l1.786,0.003c4.288,0,3.99,6.319-0.339,6.319l-5.103-0.008l0.261-2.581l4.935,0.008c0.994,0,0.983-1.383,0.154-1.383l-1.694-0.002c-4.316,0-4.096-6.236,0.33-6.236l4.321-0.002L188.539,21.634L188.539,21.634z M175.543,21.67l-0.769,7.624h-2.595l0.77-7.624l-1.809-0.017l-0.712,7.641h-2.591l0.954-10.255l7.51,0.051c2.297,0.008,3.88,1.641,3.632,4.102l-0.629,6.103h-2.595l0.657-6.363c0.093-0.923-0.276-1.256-1.067-1.26L175.543,21.67L175.543,21.67z"/>
                    <path fill="#FFFFFF" d="M165.465,1c2.893,0,5.236,2.345,5.236,5.25c0,2.891-2.344,5.236-5.236,5.236c-2.891,0-5.236-2.345-5.236-5.236C160.229,3.345,162.574,1,165.465,1z M162.703,9.082c1.515,1.553,4.011,1.554,5.539,0c1.557-1.57,1.557-4.109,0-5.679c-1.528-1.554-4.024-1.553-5.539,0c-0.752,0.752-1.175,1.773-1.175,2.846C161.528,7.308,161.95,8.329,162.703,9.082z M166.391,6.713l1.448,2.647h-1.78l-1.122-2.222l-0.272,2.222h-1.517l0.779-6.33h1.903c1.558,0,2.373,0.925,1.917,2.369C167.545,6.075,167.067,6.516,166.391,6.713L166.391,6.713z M165.318,5.761c0.533,0,0.822-0.219,0.935-0.577c0.188-0.528-0.034-0.854-0.652-0.854h-0.318l-0.175,1.432H165.318L165.318,5.761z"/>
                    <text fill="#FFFFFF" x="193" y="29" font-size="10">' . rex::getVersion() . '</text>
                </svg>';
        $styles = '
                <style nonce="' . rex_response::getNonce() . '">
                    .Whoops {
                        padding-top: 70px;
                    }
                    .panel {
                        top: 70px;
                        bottom: 0;
                        height: auto;
                    }
                    .exc-message {
                        vertical-align: middle;
                    }
                    .search-for-help {
                        width: auto;
                    }
                    .search-for-help li:nth-child(n+2) {
                        display: none;
                    }
                    .rex-whoops-header {
                        position: fixed;
                        top: 0;
                        left: 0;
                        right: 0;
                        height: 70px;
                        background: #b00;
                        z-index: 9999999999;
                    }
                    .rex-logo {
                        padding-left: 40px;
                        line-height: 70px;
                    }
                    .rex-logo > svg {
                        vertical-align: middle;
                    }
                    .rex-safemode {
                        position: absolute;
                        top: 17px;
                        right: 40px;
                        display: inline-block;
                        padding: 10px;
                        background-color: #f90;
                        border-radius: 4px;
                        color: #754600;
                        font-size: .875rem;
                        font-weight: 700;
                        transition: 0.2s ease-out;
                    }
                    .rex-safemode:hover {
                        background-color: #754600;
                        color: #f90;
                    }
                    .rex-report-bug {
                        position: absolute;
                        top: 17px;
                        right: 200px;
                        display: inline-block;
                        padding: 10px;
                        border-radius: 4px;
                        color: white;
                        font-size: .875rem;
                        font-weight: 700;
                        transition: 0.2s ease-out;
                    }
                    .rex-report-bug:hover {
                        background-color: white;
                        color: #b00;
                    }
                    button.clipboard {
                        margin: 10px 5px;
                        padding: 0 10px;
                        border: 0;
                        box-shadow: inset 0 0 0 2px rgba(255, 255, 255, 0.2);
                        color: #fff;
                        font-weight: bold;
                        line-height: 24px;
                        vertical-align: top;
                        cursor: pointer;
                        transition: 0.2s ease-out;
                    }
                    button.clipboard:hover {
                        box-shadow: inset 0 0 0 2px rgba(255, 255, 255, 1);
                        color: #fff;
                    }
                </style>';

        $saveModeLink = '';
        if (!rex::isSetup() && rex::isBackend() && !rex::isSafeMode()) {
            $saveModeLink = '<a class="rex-safemode" href="' . rex_url::backendPage('packages', ['safemode' => 1]) . '">activate safe mode</a>';
        }

        try {
            $markdownReport = self::getMarkdownReport($exception);
        } catch (Throwable) {
            $markdownReport = null;
        }

        $reportBugLink = '';
        if ($markdownReport) {
            $bugTitle = 'Exception: ' . $exception->getMessage();
            $bugLabel = 'Bug';
            $bugBody = $markdownReport;
            if (rex_server('REQUEST_URI')) {
                $bugBody =
                    '**Request-Uri:** ' . rex_server('REQUEST_URI') . "\n" .
                    '**Request-Method:** ' . strtoupper(rex_request::requestMethod()) . "\n" .
                    "\n" . $bugBody;
            }

            $bugBodyCompressed = rex_type::string(preg_replace('/ {2,}/u', ' ', $bugBody)); // replace multiple spaces with one space
            $reportBugLink = '<a class="rex-report-bug" href="https://github.com/redaxo/redaxo/issues/new?labels=' . rex_escape($bugLabel, 'url') . '&title=' . rex_escape($bugTitle, 'url') . '&body=' . rex_escape($bugBodyCompressed, 'url') . '" rel="noopener noreferrer" target="_blank">Report a REDAXO bug</a>';
        }

        $url = rex::isFrontend() ? rex_url::frontendController() : rex_url::backendController();

        $errPage = str_replace(
            [
                '</head>',
                '</body>',
            ], [
                $styles . '</head>',
                '<div class="rex-whoops-header"><a href="' . $url . '" class="rex-logo">' . $logo . '</a>' . $reportBugLink . $saveModeLink . '</div></body>',
            ],
            $errPage,
        );

        $errPage = str_replace(
            '<ul class="search-for-help">',
            '<ul class="search-for-help">
                            <li>
                                <a rel="noopener noreferrer" target="_blank" href="https://redaxo.org/doku/5.x" title="Search for help in the REDAXO Docs.">
                                    <svg version="1" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 700 700"><path d="M27.9 85.7c0 .4-.2 1.5-.3 2.3-.2.8-.7 4.9-1.1 9-.4 4.1-.8 8.2-1 9-.1.8-.6 5.1-.9 9.5-.4 4.4-.9 9.6-1.1 11.5-.2 1.9-.6 6-.9 9-1.2 12.5-2.7 26.7-3.1 30-.2 1.9-.7 6.9-1 11-.4 4.1-.8 8.6-1 10-.4 2.4-.7 5.2-2 19.5-.3 3.3-.8 7.6-1 9.5-.2 1.9-.7 6.4-1 10-.3 3.6-.8 8.1-1 10-.2 1.9-.6 6.4-1 10-.3 3.6-.8 8.1-1 10-.9 7.8-1.8 16.6-3 29.5-.3 3.3-.7 7.6-1 9.5-.2 1.9-.7 6.2-1 9.5-.3 3.3-.8 8-1 10.5-.3 2.5-.7 6.9-1 9.7l-.6 5.3H34c19.9 0 31-.4 31.1-1 .1-.6.2-1.3.3-1.8.1-.4.6-5 1-10.2.5-5.2 1-10.2 1.1-11 .4-2 1.5-12.3 2-18.5.2-2.8.6-6.8.9-9 .3-2.2.8-7.3 1.1-11.2.4-4 .8-8.1.9-9 .3-1.7 2.4-1.8 30.6-1.8h30.3l5.9 13.5c3.2 7.4 6.5 14.8 7.3 16.5.7 1.6 3.4 7.7 6 13.5s5.3 11.8 6 13.5c.8 1.6 2.7 6 4.4 9.7l3 6.8H200c18.8 0 33.9-.3 33.7-.8-.3-.4-4.2-9-8.7-19.2-4.4-10.2-8.7-19.9-9.5-21.5-8.4-18.4-18.6-41.8-18.3-42 .2-.1 3.5-2.2 7.3-4.7 7.3-4.6 19.3-15.7 23.7-21.9 13.9-19.1 20.4-49.6 15.3-71.5-3.1-13.5-12.8-30-22.8-39-12.6-11.3-22.9-16.8-40.7-21.7-7.2-2-10.4-2.1-79.7-2.4-39.8-.2-72.3-.1-72.4.4zm139.9 63.5c8.8 3.3 14.2 10.2 14.7 19 .7 11.8-6.6 20.8-20 24.6-1.1.3-20.3.6-42.8.8l-40.7.2.5-4.1c.3-2.3.8-7.1 1.1-10.7.3-3.6.7-7.5.9-8.7.2-1.2.7-6.1 1-10.8.6-7.3 1-10.4 1.4-12.3.1-.2 17.8-.2 39.4-.1 36.1.1 39.7.3 44.5 2.1zM337 85.6c-2 .3-12.7 2.4-15 3-.3 0-1.2.3-2 .6s-3.5 1.3-6 2.2c-11.4 4-24.5 14.1-33.1 25.4-11.7 15.5-22.5 44.2-25.4 67.7-.4 2.7-.8 5.7-1 6.5-1.1 4.5-2 17.3-2 31 0 19.7 1 28.3 4.6 41.5 5.2 18.8 11.8 30.6 23.8 42.5 11.9 11.8 23.7 18 42 22 7.6 1.7 14.1 1.9 67.7 2 32.6 0 59.4-.3 59.7-.7.3-.4.8-4.9 1.2-9.8.4-5 .8-9.2 1-9.5.1-.3.6-4.1.9-8.5.4-4.4.8-9.1 1.1-10.5.2-1.4.6-5.4 1-9 .3-3.6.7-8.2 1-10.3.3-2.5.1-3.7-.8-3.8-2.2-.2-39.3-.7-76.7-1-20.1-.2-38.9-.7-41.8-1.1-7-1.1-12.4-3.9-15.8-8.3-3-4-6.3-14.9-6.4-21v-4h62.2c61.3 0 62.1 0 62.4-2 .2-1.1.6-5.2 1-9 .3-3.9.7-8.1.9-9.5.2-2.5 1.5-15.3 2-20 .1-1.4.6-5.2.9-8.5l.7-6h-62c-34.2 0-62.1-.4-62.1-.8 0-1.7 4.2-13.1 5.7-15.6 4.4-7.1 11-12 18.2-13.6 1.8-.4 30.5-.7 63.7-.7 44.1 0 60.4-.3 60.5-1.2 0-.6.2-2.7.4-4.6.1-1.9.5-5.8.8-8.5.4-2.8.9-7.9 1.2-11.5.3-3.6.8-8.8 1.1-11.5.3-2.8.7-7.3.9-10 .3-2.8.7-7.1 1-9.8l.6-4.7-67.8.1c-37.3.1-68.9.3-70.3.5zm152.2.1c.2.8-1.1 14.5-1.7 18.8-.2 1.1-.6 5.1-.9 9-.4 3.8-.8 8.3-1 10-.3 1.6-.7 6.4-1.1 10.5-.4 4.1-.8 8.6-1 10-.1 1.4-.6 5.9-.9 10-.4 4.1-.9 8.6-1.1 10-.4 3.1-1.3 11.3-2 20-.3 3.6-.8 8.1-1 10-.9 8.2-1.5 13.9-2 20-.3 3.6-.8 8.1-1 10-.2 1.9-.9 8.2-1.5 14-1 10.1-1.4 13.7-2.6 26-.8 8.4-1.4 14.1-1.9 18.5-.2 2.2-.7 6.7-1 10-.3 3.3-.8 7.8-1 10-.7 5.7-1.3 12-2.1 20.3l-.6 7.3 71.3-.4c39.3-.1 72.8-.6 74.4-1 20.2-5 37.1-15 49-29.3 2.8-3.2 5.2-6.1 5.5-6.4 2.1-2.1 8.7-13.2 12.8-21.5 7.9-15.8 13.9-35.9 15.7-52 .4-3.3.9-6.9 1.1-8 .6-2.7.5-28.3 0-31.5-.2-1.4-.7-5.2-1.1-8.5-1.3-12.4-8.8-35.9-13.5-42.5-1.1-1.6-2-3.2-2-3.7 0-1.5-7.6-11.4-13.3-17.2-8.1-8.3-17.8-14.5-28.6-18.3-12.5-4.3-17.4-4.6-83.9-4.7-34.8-.1-63.1.2-63 .6zm123.4 62.8c8.6 3.6 15.2 12.2 18.8 24.7 2.1 7.3 2.4 10 2.1 21.8-.5 28.4-7.9 51.2-20.6 64-8.7 8.6-9.4 8.8-47.3 8.8l-31.8.1.7-8.7c.3-4.8.8-9.4 1-10.2.2-.8.6-5.1 1-9.5.3-4.4.8-8.7 1-9.5.2-.8.6-5.3 1-10 .3-4.7.8-9.4 1-10.5.2-1.1.7-5.4 1-9.5.3-4.1 1-11.6 1.6-16.5 1.6-14.4 1.8-16.6 2.4-25 .4-4.4.9-8.8 1.1-9.8.5-1.6 2.9-1.7 32-1.7 25.6 0 32.1.3 35 1.5zm-72.8 223c-.1.1-2.1.6-4.3.9-4.3.6-5.1.8-10.4 2-5.2 1.2-15.5 6.1-21.9 10.3-11.7 7.8-25.6 24.2-33.2 39.2-7.1 14.2-14.5 39.1-16.6 56.2-.3 3-.8 6.7-1 8.4-.9 6.6-1.1 36.4-.3 42 2.1 14.5 3.3 20.5 6 28.7 6.8 20.8 17.9 36 34 46.4 3.7 2.4 7 4.4 7.3 4.4.4 0 2.5.9 4.9 2 2.4 1.1 9 3.1 14.7 4.4 10.1 2.3 12 2.4 47 2.4 40.4 0 47.6-.7 61.3-6 22.5-8.7 40.1-28.5 50.3-56.8 4-11.1 8.5-29.3 9.8-39 .3-2.5.8-5.5 1-6.7.3-1.2.8-5.3 1.2-9 .4-3.7.8-8.2 1-9.8.6-5.4.5-28.8-.2-35-3.1-30.9-16.9-57.6-36.7-71-8.1-5.5-21.2-11-31.7-13.1-3.4-.7-81.5-1.6-82.2-.9zm69.2 62.9c9 1.2 14 5.5 17.5 15.1 3.3 9 2.8 38.6-.9 63.1-1.3 8.3-6.1 23.2-9.3 28.6-5.1 8.6-11.8 13.1-21.7 14.2-7.8.9-55.8.7-59.8-.3-8.8-2.1-15-8.5-18.4-18.7-1.5-4.8-1.9-8.8-1.9-21.9 0-18.1.7-26.1 3.6-39 6.1-27.5 15.5-39.7 31.7-41.2 3.1-.3 5.8-.6 5.9-.7.6-.4 49.2.3 53.3.8zm-504.1-62c-.2.2-3.1.7-6.3 1.1-12.8 1.4-29.5 8.3-39.6 16.3-15.5 12.2-27 30.7-32.8 52.7-1.1 4.4-2.3 9.6-2.6 11.5-.3 1.9-.8 5.1-1.1 7-.5 3.8-1.1 9.6-2 20-.3 3.6-.8 7.6-1 9-.2 1.4-.7 5.9-1 10-.4 4.1-.8 8.6-1 10-.2 1.4-.7 5.9-1 10-.3 4.1-.8 8.6-1 10-.2 1.4-.6 5.9-1 10-.3 4.1-.8 8.6-1 10-.3 1.4-.7 5.9-1 10-.3 4.1-.7 8.6-1 10-.2 1.4-.7 5.8-1 9.8-.4 4.1-.8 8.1-1 9-.5 2.8-1.8 19.4-1.9 24.1l-.1 4.5h30.3c16.7.1 30.6-.2 30.9-.6.7-1.3 1.2-4.8 1.9-12.7.3-4.2.7-8.2.9-9.1.2-.9.6-5.6 1-10.6.4-4.9.9-9.6 1-10.4.2-.8.6-5.1 1-9.5s.8-9.1 1-10.5c.2-1.4.7-5.1 1-8.3l.7-5.7h4.7c2.5-.1 25.9-.2 51.9-.3l47.4-.2-.7 7c-.4 3.8-.8 8.1-1 9.5-.2 1.4-.6 5.9-1 10s-.8 8.6-1 10c-.2 1.4-.7 5.4-1 9-.3 3.6-.8 7.6-1 9-.2 1.4-.7 5.9-1 10-.3 4.1-.8 8.8-1.1 10.5l-.4 3 31-.1c17 0 31.2-.2 31.4-.5.2-.2.7-4.2 1.1-8.8.4-4.6.8-9.1 1-10 .1-.9.6-5 1-9.1s.9-8.2 1-9.2c.2-.9.6-5.2 1-9.5.3-4.3.8-8.9 1-10.3.2-1.4.7-5.9 1.1-10 1.1-11.7 1.1-12.1 1.8-19 .4-3.6.9-8.5 1.1-11 .4-3.9 2-18.5 3-27.5.2-1.7.6-6.6 1-11s.9-8.9 1.1-10c.8-4 .5-21.2-.5-28-4.1-28.4-20.7-51-46.1-62.7-5.8-2.7-13.4-5.3-18.5-6.2-1.6-.4-4.1-.9-5.5-1.3-2.7-.7-74.4-1.6-75.1-.9zM173 435c8 2.2 13.4 7.3 15.5 14.8.8 2.7.8 13 .1 18.2-.3 1.9-.8 6.2-1.1 9.5-.4 3.3-.9 6.2-1.2 6.5-.2.3-23.7.5-52 .6H82.8l.6-7.9c.4-4.4 1.3-11.4 2.2-15.6 3.8-18.4 10.6-25.4 26.4-26.9 11.1-1.2 56.1-.5 61 .8z"/><path d="M249.1 381.2c2.9 4.6 9.7 15.5 15.2 24.3s11 17.6 12.2 19.5c17.4 27.1 46.5 74.2 46.5 75.2 0 .8-16.4 25.3-43.5 64.8-2.8 4.1-8.7 12.7-13 19-4.3 6.3-11.2 16.3-15.2 22.3l-7.4 10.7h80.6l19.3-29.1 19.4-29 12.5 19.8c6.9 10.9 14 22 15.8 24.8 1.8 2.7 4.4 6.9 5.9 9.3l2.6 4.2h73l-8-11.8c-4.4-6.4-9.3-13.5-10.8-15.7-1.5-2.2-8-11.7-14.4-21-17.7-26-30.1-44.1-36.8-53.7-3.8-5.5-5.8-9.3-5.4-10 2-3.3 26-39.6 54.4-82.3 17.2-25.9 31.4-47.6 31.7-48.3.4-1-7.4-1.2-38.3-1l-38.8.3-13.7 21.5c-7.6 11.8-17.7 27.6-22.4 35-9.6 15-11.3 17.5-11.8 16.9-.2-.2-6.4-10.3-13.7-22.4-7.3-12.1-17.3-28.6-22.3-36.8l-8.9-14.7H244l5.1 8.2z"/></svg>
                                </a>
                            </li>',
            $errPage,
        );

        if ($markdownReport) {
            $errPageMarkdownBtn = preg_replace(
                '@<button id="copy-button" .*?</button>@s',
                '$0<button class="rightButton clipboard" data-clipboard-text="' . rex_escape($markdownReport) . '" title="Copy exception details and system report as markdown to clipboard">COPY MARKDOWN</button>',
                $errPage,
            );
            if (null !== $errPageMarkdownBtn) {
                $errPage = $errPageMarkdownBtn;
            }
        }

        $errPage = str_replace('<button id="copy-button"', '<button ', $errPage);
        $errPage = preg_replace('@<button id="hide-error" .*?</button>@s', '', $errPage);

        return [$errPage, $handler->contentType()];
    }

    /**
     * Handles a error message.
     *
     * @param int $errno The error code to handle
     * @param string $errstr The error message
     * @param string $errfile The file in which the error occured
     * @param int $errline The line of the file in which the error occured
     *
     * @throws ErrorException
     * @return bool
     */
    public static function handleError($errno, $errstr, $errfile, $errline)
    {
        if (in_array($errno, [E_USER_ERROR, E_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR, E_PARSE])) {
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        }

        // silenced errors (via php.ini or "@" operator)
        if (!(error_reporting() & $errno)) {
            return false;
        }

        $debug = rex::getDebugFlags();
        $alwaysThrow = $debug['throw_always_exception'];

        if (
            true === $alwaysThrow
            || is_int($alwaysThrow) && $errno === ($errno & $alwaysThrow)
        ) {
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        }

        if (ini_get('display_errors') && (rex::isSetup() || rex::isDebugMode() || !rex::isLiveMode() && rex_backend_login::createUser()?->isAdmin())) {
            $file = rex_path::relative($errfile);
            if ('cli' === PHP_SAPI) {
                echo self::getErrorType($errno) . ": $errstr in $file on line $errline";
            } else {
                $file = rex_escape($file);
                if ($url = rex_editor::factory()->getUrl($errfile, $errline)) {
                    $file = '<a href="' . rex_escape($url) . '">' . $file . '</a>';
                }
                echo '<div><b>' . self::getErrorType($errno) . '</b>: ' . rex_escape($errstr) . " in <b>$file</b> on line <b>$errline</b></div>";
            }
        }

        rex_logger::logError($errno, $errstr, $errfile, $errline, self::getUrl());

        return true;
    }

    /**
     * Shutdown-handler which is called at the very end of the request.
     * @return void
     */
    public static function shutdown()
    {
        // catch fatal/parse errors
        if (self::$registered) {
            $error = error_get_last();
            if (!is_array($error)) {
                return;
            }
            if (!in_array($error['type'], [E_USER_ERROR, E_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR, E_PARSE])) {
                return;
            }
            self::handleException(new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']));
        }
    }

    /**
     * Get a human readable string representing the given php error code.
     *
     * @param int $errno a php error code, e.g. E_ERROR
     *
     * @return string
     */
    public static function getErrorType($errno)
    {
        return match ($errno) {
            E_USER_ERROR, E_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR => 'Fatal error',
            E_PARSE => 'Parse error',
            E_USER_WARNING, E_WARNING, E_COMPILE_WARNING => 'Warning',
            E_USER_NOTICE, E_NOTICE => 'Notice',
            E_USER_DEPRECATED, E_DEPRECATED => 'Deprecated',
            default => 'Unknown',
        };
    }

    private static function getUrl(): ?string
    {
        if ('cli' === PHP_SAPI) {
            return null;
        }

        try {
            $request = rex::getRequest();
        } catch (rex_exception) {
            return null;
        }

        // Backend URLs use `/` inside page param, and we try to use them unencoded
        // so for consistency we unencode them here too
        $uri = preg_replace_callback('@(?<=\?page=)[\w/]+@', static function (array $match) {
            return str_replace('%2F', '/', $match[0]);
        }, $request->getRequestUri(), 1);

        return $request->getSchemeAndHttpHost() . $uri;
    }

    /**
     * @param Throwable|Exception $exception
     *
     * @return string
     */
    private static function getMarkdownReport($exception)
    {
        $file = rex_path::relative($exception->getFile());
        $markdown = '**' . $exception::class . ":** {$exception->getMessage()}\n";
        $markdown .= "**File:** {$file}\n";
        $markdown .= "**Line:** {$exception->getLine()}\n\n";

        $trace = [];

        $headers = ['Function', 'File', 'Line'];
        $widths = [mb_strlen($headers[0]), mb_strlen($headers[0]), mb_strlen($headers[0])];

        foreach ($exception->getTrace() as $frame) {
            $function = $frame['function'];
            if (isset($frame['class'])) {
                $function = $frame['class'] . $frame['type'] . $function;
            }

            $file = isset($frame['file']) ? rex_path::relative($frame['file']) : '';
            $line = $frame['line'] ?? '';

            $trace[] = [$function, $file, $line];

            $widths[0] = max($widths[0], mb_strlen($function));
            $widths[1] = max($widths[1], mb_strlen($file));
            $widths[2] = max($widths[2], mb_strlen($line));
        }

        $table = '| ' . str_pad($headers[0], $widths[0]) . ' | ' . str_pad($headers[1], $widths[1]) . ' | ' . str_pad($headers[2], $widths[2]) . " |\n";
        $table .= '| ' . str_repeat('-', $widths[0]) . ' | ' . str_repeat('-', $widths[1]) . ' | ' . str_repeat('-', $widths[2]) . " |\n";

        foreach ($trace as $row) {
            $table .= '| ' . str_pad($row[0], $widths[0]) . ' | ' . str_pad($row[1], $widths[1]) . ' | ' . str_pad($row[2], $widths[2]) . " |\n";
        }

        $markdown .= <<<OUTPUT
            <details>
            <summary>Stacktrace</summary>

            $table
            </details>

            OUTPUT;
        $markdown .= rex_system_report::factory()->asMarkdown();

        return $markdown;
    }
}
