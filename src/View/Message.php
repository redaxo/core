<?php

namespace Redaxo\Core\View;

final class Message
{
    private function __construct() {}

    /**
     * Returns an info message.
     *
     * @param string $message
     * @param string $cssClass
     *
     * @psalm-taint-specialize
     */
    public static function info($message, $cssClass = ''): string
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
     * @param string $message
     * @param string $cssClass
     *
     * @psalm-taint-specialize
     */
    public static function success($message, $cssClass = ''): string
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
     * @param string $message
     * @param string $cssClass
     *
     * @psalm-taint-specialize
     */
    public static function warning($message, $cssClass = ''): string
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
     * @param string $message
     * @param string $cssClass
     *
     * @psalm-taint-specialize
     */
    public static function error($message, $cssClass = ''): string
    {
        $cssClassMessage = 'alert-danger';
        if ('' != $cssClass) {
            $cssClassMessage .= ' ' . $cssClass;
        }

        return self::message($message, $cssClassMessage);
    }

    /**
     * Returns a message.
     *
     * @param string $message
     * @param string $cssClass
     */
    private static function message($message, $cssClass): string
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
