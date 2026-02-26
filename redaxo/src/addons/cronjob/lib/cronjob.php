<?php

/**
 * Cronjob Addon.
 *
 * @author gharlan[at]web[dot]de Gregor Harlan
 *
 * @package redaxo\cronjob
 */

abstract class rex_cronjob
{
    /** @var array<string, mixed> */
    private $params = [];
    /** @var string */
    private $message = '';

    /**
     * @param class-string<rex_cronjob> $class
     *
     * @return class-string<rex_cronjob>|rex_cronjob
     */
    final public static function factory($class)
    {
        if (!rex_autoload::autoload($class)) {
            return $class;
        }

        if (!in_array($class, rex_cronjob_manager::getTypes())) {
            return $class;
        }

        return rex_type::instanceOf(new $class(), self::class);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function setParam($key, $value)
    {
        $this->params[$key] = $value;
    }

    /**
     * @param array<string, mixed> $params
     * @return void
     */
    public function setParams(array $params)
    {
        $this->params = $params;
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getParam($key, $default = null)
    {
        return $this->params[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function __set($key, $value)
    {
        $this->setParam($key, $value);
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->getParam($key);
    }

    /**
     * @param string $message
     * @return void
     */
    public function setMessage($message)
    {
        $this->message = $message;
    }

    /** @return string */
    public function getMessage()
    {
        return $this->message;
    }

    /** @return bool */
    public function hasMessage()
    {
        return !empty($this->message);
    }

    /** @return bool true on successfull execution, false on error */
    abstract public function execute();

    /** @return string */
    public function getTypeName()
    {
        // returns the name of the cronjob type
        return $this->getType();
    }

    /** @return string */
    final public function getType()
    {
        return static::class;
    }

    /** @return list<'frontend'|'backend'|'script'> */
    public function getEnvironments()
    {
        // returns an array of environments in which the cronjob is available
        return ['frontend', 'backend', 'script'];
    }

    /** @return list<array<string, mixed>> */
    public function getParamFields()
    {
        // returns an array of parameters which are required for the cronjob
        return [];
    }
}
