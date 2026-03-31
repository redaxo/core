<?php

namespace Redaxo\Core\Form;

use Redaxo\Core\Base\FactoryTrait;
use Redaxo\Core\Config;
use Redaxo\Core\Core;
use Redaxo\Core\Translation\I18n;

use function is_string;

/**
 * Create forms for Config data.
 */
class ConfigForm extends AbstractForm
{
    use FactoryTrait;

    /** @var string */
    private $namespace;

    /** @param string $namespace `Config` namespace, usually the package key */
    protected function __construct(string $namespace, ?string $fieldset = null, bool $debug = false)
    {
        parent::__construct($fieldset, md5($namespace . (string) $fieldset), 'post', $debug);

        $this->namespace = $namespace;

        // --------- Load Env
        if (Core::isBackend()) {
            $this->loadBackendConfig();
        }
    }

    /** @param string $namespace `Config` namespace, usually the package key */
    public static function factory(string $namespace, ?string $fieldset = null, bool $debug = false): static
    {
        $class = static::getFactoryClass();
        return new $class($namespace, $fieldset, $debug);
    }

    protected function loadBackendConfig()
    {
        parent::loadBackendConfig();

        $attr = ['type' => 'submit', 'internal::useArraySyntax' => false, 'internal::fieldSeparateEnding' => true];
        $this->addControlField(
            null,
            $this->addField('button', 'save', I18n::msg('form_save'), $attr, false),
        );
    }

    protected function getValue($name)
    {
        return Config::get($this->namespace, $name);
    }

    protected function save()
    {
        foreach ($this->getSaveElements() as $fieldsetElements) {
            foreach ($fieldsetElements as $element) {
                // read-only-fields nicht speichern
                if ($element->isReadOnly()) {
                    continue;
                }

                $fieldName = $element->getFieldName();
                $fieldValue = $element->getSaveValue();

                if (is_string($fieldValue)) {
                    $fieldValue = trim($fieldValue);
                }

                Config::set($this->namespace, $fieldName, $fieldValue);
            }
        }

        return true;
    }
}
