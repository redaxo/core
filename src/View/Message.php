<?php

namespace Redaxo\Core\View;

use Redaxo\Core\View\Component\Alert;
use Redaxo\Core\View\Component\AlertType;

final class Message
{
    private function __construct() {}

    /**
     * Returns an info message.
     *
     * @psalm-taint-specialize
     */
    public static function info(string $message, string $cssClass = ''): string
    {
        return self::message($message, AlertType::Info, $cssClass);
    }

    /**
     * Returns a success message.
     *
     * @psalm-taint-specialize
     */
    public static function success(string $message, string $cssClass = ''): string
    {
        return self::message($message, AlertType::Success, $cssClass);
    }

    /**
     * Returns an warning message.
     *
     * @psalm-taint-specialize
     */
    public static function warning(string $message, string $cssClass = ''): string
    {
        return self::message($message, AlertType::Warning, $cssClass);
    }

    /**
     * Returns an error message.
     *
     * @psalm-taint-specialize
     */
    public static function error(string $message, string $cssClass = ''): string
    {
        return self::message($message, AlertType::Error, $cssClass);
    }

    /** Returns a message. */
    private static function message(string $message, AlertType $type, string $cssClass): string
    {
        return (string) new Alert(Html::raw($message), $type, new HtmlAttributes(['class' => array_values(array_filter(explode(' ', $cssClass)))]))->render();
    }
}
