<?php

use JetBrains\PhpStorm\Deprecated;

/**
 * @author gharlan
 *
 * @package redaxo\core\login
 */
class rex_password_policy
{
    /** @param array<string, array{min?: int, max?: int}> $options */
    public function __construct(
        private array $options,
    ) {}

    /**
     * @param string $password
     * @param int|null $id
     *
     * @throws rex_exception
     *
     * @return true|string `true` on success, otherwise an error message
     */
    public function check(#[SensitiveParameter] $password, $id = null)
    {
        if ($this->isValid($password)) {
            return true;
        }

        return rex_i18n::msg('password_invalid', $this->getDescription());
    }

    public function getDescription(): ?string
    {
        $parts = [];

        foreach ($this->options as $key => $options) {
            if (isset($options['min'], $options['max']) && $options['min']) {
                $constraint = rex_i18n::msg('password_rule_between', $options['min'], $options['max']);
            } elseif (isset($options['max'])) {
                $constraint = rex_i18n::msg('password_rule_max', $options['max']);
            } elseif (isset($options['min']) && $options['min']) {
                $constraint = rex_i18n::msg('password_rule_min', $options['min']);
            } else {
                continue;
            }

            $parts[] = rex_i18n::msg('password_rule_' . $key, $constraint);
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

    /**
     * @return string
     *
     * @deprecated since 5.12, use `getDescription` instead
     */
    #[Deprecated(reason: 'since 5.12, use `getDescription` instead', replacement: '%class%->getDescription()')]
    protected function getRule()
    {
        return $this->getDescription() ?? '';
    }

    /**
     * @param string $password
     * @return bool
     */
    protected function isValid(#[SensitiveParameter] $password)
    {
        foreach ($this->options as $key => $options) {
            $count = match ($key) {
                'length' => mb_strlen($password),
                'letter' => preg_match_all('/[a-zA-Z]/', $password),
                'uppercase' => preg_match_all('/[A-Z]/', $password),
                'lowercase' => preg_match_all('/[a-z]/', $password),
                'digit' => preg_match_all('/\d/', $password),
                'symbol' => preg_match_all('/[^a-zA-Z0-9]/', $password),
                default => throw new rex_exception(sprintf('Unknown password_policy key "%s".', $key)),
            };

            if (!$this->matchesCount($count, $options)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param int $count
     * @param array{min?: int, max?: int} $options
     *
     * @return bool
     */
    protected function matchesCount($count, array $options)
    {
        if (isset($options['min']) && $count < $options['min']) {
            return false;
        }

        return !isset($options['max']) || $count <= $options['max'];
    }
}
