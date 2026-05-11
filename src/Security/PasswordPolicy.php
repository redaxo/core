<?php

namespace Redaxo\Core\Security;

use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\Translation\I18n;
use SensitiveParameter;

use function sprintf;

class PasswordPolicy
{
    /** @param array<string, array{min?: int, max?: int}> $options */
    public function __construct(
        private readonly array $options,
    ) {}

    /** @return true|string `true` on success, otherwise an error message */
    public function check(#[SensitiveParameter] string $password, ?int $id = null): true|string
    {
        if ($this->isValid($password)) {
            return true;
        }

        return I18n::msg('password_invalid', $this->getDescription() ?? '');
    }

    public function getDescription(): ?string
    {
        $parts = [];

        foreach ($this->options as $key => $options) {
            if (isset($options['min'], $options['max']) && $options['min']) {
                $constraint = I18n::msg('password_rule_between', $options['min'], $options['max']);
            } elseif (isset($options['max'])) {
                $constraint = I18n::msg('password_rule_max', $options['max']);
            } elseif (isset($options['min']) && $options['min']) {
                $constraint = I18n::msg('password_rule_min', $options['min']);
            } else {
                continue;
            }

            $parts[] = I18n::msg('password_rule_' . $key, $constraint);
        }

        return $parts ? implode('; ', $parts) : null;
    }

    /**
     * Generates the corresponding html attributes `minlength`, `maxlength` and `passwordrules`.
     *
     * @see https://github.com/whatwg/html/issues/3518
     * @see https://www.scottbrady91.com/authentication/perfecting-the-password-field-with-the-html-passwordrules-attribute
     *
     * @return array<string, string>
     */
    public function getHtmlAttributes(): array
    {
        $attr = [];

        if (isset($this->options['length']['min'])) {
            $attr['minlength'] = (string) $this->options['length']['min'];
        }
        if (isset($this->options['length']['max'])) {
            $attr['maxlength'] = (string) $this->options['length']['max'];
        }

        $rules = [];
        $mapping = [
            'uppercase' => 'upper',
            'lowercase' => 'lower',
            'digit' => 'digit',
            'symbol' => 'special',
        ];
        $allowed = $mapping;
        foreach ($mapping as $rexKey => $htmlKey) {
            if (($this->options[$rexKey]['min'] ?? 0) > 0) {
                $rules[] = 'required: ' . $htmlKey;
            }
            if (($this->options[$rexKey]['max'] ?? 1) <= 0) {
                unset($allowed[$rexKey]);
            }
        }
        $rules[] = 'allowed: ' . implode(', ', $allowed);
        $attr['passwordrules'] = implode('; ', $rules);

        return $attr;
    }

    protected function isValid(#[SensitiveParameter] string $password): bool
    {
        foreach ($this->options as $key => $options) {
            $count = match ($key) {
                'length' => mb_strlen($password),
                'letter' => preg_match_all('/[a-zA-Z]/', $password),
                'uppercase' => preg_match_all('/[A-Z]/', $password),
                'lowercase' => preg_match_all('/[a-z]/', $password),
                'digit' => preg_match_all('/\d/', $password),
                'symbol' => preg_match_all('/[^a-zA-Z0-9]/', $password),
                default => throw new LogicException(sprintf('Unknown password_policy key "%s".', $key)),
            };

            if (!$this->matchesCount($count, $options)) {
                return false;
            }
        }

        return true;
    }

    /** @param array{min?: int, max?: int} $options */
    protected function matchesCount(int $count, array $options): bool
    {
        if (isset($options['min']) && $count < $options['min']) {
            return false;
        }

        return !isset($options['max']) || $count <= $options['max'];
    }
}
