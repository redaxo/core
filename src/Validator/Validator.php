<?php

namespace Redaxo\Core\Validator;

use Redaxo\Core\Base\FactoryTrait;
use Redaxo\Core\Exception\InvalidArgumentException;

use function in_array;

/**
 * @psalm-consistent-constructor
 */
class Validator
{
    use FactoryTrait;

    /** @var list<ValidationRule> */
    private array $rules = [];

    private ?string $message = null;

    protected function __construct() {}

    public static function factory(): static
    {
        $class = static::getFactoryClass();
        return new $class();
    }

    /**
     * Adds a validation rule.
     *
     * @param string $type Validator type (any static method name of this class)
     * @param string|null $message Message which is used if this validator type does not match
     * @param mixed|null $option Type specific option
     *
     * @return $this
     */
    public function add(string $type, ?string $message = null, mixed $option = null): static
    {
        return $this->addRule(new ValidationRule($type, $message, $option));
    }

    /**
     * Adds a validation rule.
     *
     * @return $this
     */
    public function addRule(ValidationRule $rule): static
    {
        $type = $rule->getType();

        if (!method_exists($this, $type)) {
            throw new InvalidArgumentException('Unknown validator type "' . $type . '".');
        }

        $this->rules[] = $rule;

        return $this;
    }

    /** @return list<ValidationRule> */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Checks whether the given value matches all added validators.
     */
    public function isValid(string $value): bool
    {
        $this->message = null;
        foreach ($this->rules as $rule) {
            $type = $rule->getType();

            if ('' === $value && 'notempty' !== strtolower($type)) {
                continue;
            }

            if (!$this->$type($value, $rule->getOption())) {
                $this->message = $rule->getMessage();
                return false;
            }
        }
        return true;
    }

    /**
     * Returns the message.
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * Checks whether the value is not empty.
     */
    public function notEmpty(string $value): bool
    {
        return '' !== $value;
    }

    /**
     * Checks whether the value is from the given type.
     */
    public function type(string $value, string $type): bool
    {
        return match ($type) {
            'int', 'integer' => $this->match($value, '/^\d+$/'),
            'float', 'real' => is_numeric($value),
            default => throw new InvalidArgumentException('Unknown $type "' . $type . '".'),
        };
    }

    /**
     * Checks whether the value has the given min length.
     */
    public function minLength(string $value, int $minLength): bool
    {
        return mb_strlen($value) >= $minLength;
    }

    /**
     * Checks whether the value has the given max value.
     */
    public function maxLength(string $value, int $maxLength): bool
    {
        return mb_strlen($value) <= $maxLength;
    }

    /**
     * Checks whether the value is equal or greater than the given min value.
     */
    public function min(string $value, int $min): bool
    {
        return $value >= $min;
    }

    /**
     * Checks whether the value is equal or lower than the given max value.
     */
    public function max(string $value, int $max): bool
    {
        return $value <= $max;
    }

    /**
     * Checks whether the value is an URL.
     */
    public function url(string $value): bool
    {
        return $this->match($value, '@^\w+://(?:[\w-]+\.)*[\w-]+(?::\d+)?(?:/.*)?$@u');
    }

    /**
     * Checks whether the value is an email address.
     */
    public function email(string $value): bool
    {
        return $this->match($value, '/^[\w.-]+@[\w.-]+\.[a-z]{2,}$/ui');
    }

    /**
     * Checks whether the value matches the given regex.
     *
     * @param non-empty-string $regex
     */
    public function match(string $value, string $regex): bool
    {
        return (bool) preg_match($regex, $value);
    }

    /**
     * Checks whether the value does not match the given regex.
     *
     * @param non-empty-string $regex
     */
    public function notMatch(string $value, string $regex): bool
    {
        return !$this->match($value, $regex);
    }

    /**
     * Checks whether the value is one of the given valid values.
     *
     * @param list<string> $validValues
     */
    public function values(string $value, array $validValues): bool
    {
        return in_array($value, $validValues);
    }

    /**
     * Checks the value by using the given callable.
     *
     * @param callable(string):bool $callback
     */
    public function custom(string $value, callable $callback): bool
    {
        return $callback($value);
    }
}
