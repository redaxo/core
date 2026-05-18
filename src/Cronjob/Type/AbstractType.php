<?php

namespace Redaxo\Core\Cronjob\Type;

use Redaxo\Core\Cronjob\CronjobExecutor;
use Redaxo\Core\Util\Type;

use function in_array;

abstract class AbstractType
{
    /** @var array<string, mixed> */
    private array $params = [];
    public protected(set) string $message = '';

    /**
     * @param class-string<AbstractType> $class
     *
     * @return class-string<AbstractType>|AbstractType
     */
    final public static function factory(string $class): self|string
    {
        if (!class_exists($class)) {
            /** @var class-string<AbstractType> */
            return $class;
        }

        if (!in_array($class, CronjobExecutor::getTypes())) {
            return $class;
        }

        return Type::instanceOf(new $class(), self::class);
    }

    public function setParam(string $key, mixed $value): void
    {
        $this->params[$key] = $value;
    }

    /** @param array<string, mixed> $params */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function getParam(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function getParams(): array
    {
        return $this->params;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->setParam($key, $value);
    }

    public function __get(string $key): mixed
    {
        return $this->getParam($key);
    }

    /** @return bool true on successfull execution, false on error */
    abstract public function execute(): bool;

    public function getTypeName(): string
    {
        // returns the name of the cronjob type
        return $this::class;
    }

    /**
     * Returns an array of environments in which the cronjob is available.
     *
     * @return list<'frontend'|'backend'|'script'>
     */
    public function getEnvironments(): array
    {
        return ['frontend', 'backend', 'script'];
    }

    /**
     * Returns an array of parameters which are required for the cronjob.
     *
     * @return list<array<string, mixed>>
     */
    public function getParamFields(): array
    {
        return [];
    }
}
