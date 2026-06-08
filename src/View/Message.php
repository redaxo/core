<?php

namespace Redaxo\Core\View;

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
        $cssClassMessage = 'alert-info';
        if ('' != $cssClass) {
            $cssClassMessage .= ' ' . $cssClass;
        }

        return self::message($message, $cssClassMessage);
    }

    /**
     * Returns a success message.
     *
     * @psalm-taint-specialize
     */
    public static function success(string $message, string $cssClass = ''): string
    {
        $cssClassMessage = 'alert-success';
        if ('' != $cssClass) {
            $cssClassMessage .= ' ' . $cssClass;
        }

        return self::message($message, $cssClassMessage);
    }

    /**
     * Returns an warning message.
     *
     * @psalm-taint-specialize
     */
    public static function warning(string $message, string $cssClass = ''): string
    {
        $cssClassMessage = 'alert-warning';
        if ('' != $cssClass) {
            $cssClassMessage .= ' ' . $cssClass;
        }

        return self::message($message, $cssClassMessage);
    }

    /**
     * Returns an error message.
     *
     * @psalm-taint-specialize
     */
    public static function error(string $message, string $cssClass = ''): string
    {
        $cssClassMessage = 'alert-danger';
        if ('' != $cssClass) {
            $cssClassMessage .= ' ' . $cssClass;
        }

        return self::message($message, $cssClassMessage);
    }

    /** Returns a message. */
    private static function message(string $message, string $cssClass): string
    {
        $cssClassMessage = 'alert';
        if ('' != $cssClass) {
            $cssClassMessage .= ' ' . $cssClass;
        }

        /*
        $fragment = new Fragment();
        $fragment->setVar('class', $cssClass);
        $fragment->setVar('message', $content, false);
        $return = $fragment->parse('message.php');
        */
        return '<div class="' . $cssClassMessage . '">' . $message . '</div>';
    }
}
