<?php

namespace Redaxo\Core\Validator;

final readonly class ValidationRule
{
    public const string NOT_EMPTY = 'notEmpty';
    public const string MIN_LENGTH = 'minLength';
    public const string MAX_LENGTH = 'maxLength';
    public const string MIN = 'min';
    public const string MAX = 'max';
    public const string URL = 'url';
    public const string EMAIL = 'email';
    public const string MATCH = 'match';
    public const string NOT_MATCH = 'notMatch';
    public const string VALUES = 'values';
    public const string CUSTOM = 'custom';

    /**
     * @param ValidationRule::*|string $type Validator type, e.g. one of ValidationRule::* but could also be extended via rex-factory
     * @param string|null $message Message which is used if this validator type does not match
     * @param mixed $option Type specific option
     */
    public function __construct(
        private string $type,
        private ?string $message = null,
        private mixed $option = null,
    ) {}

    /**
     * Validator type, e.g. one of ValidationRule::* but could also be extended via rex-factory.
     *
     * @return ValidationRule::*|string $type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /** Type specific option. */
    public function getOption(): mixed
    {
        return $this->option;
    }

    /** Message which is used if this validator type does not match. */
    public function getMessage(): ?string
    {
        return $this->message;
    }
}
