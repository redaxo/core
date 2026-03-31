<?php

namespace Redaxo\Core\Form;

use BadMethodCallException;
use Redaxo\Core\Backend\Controller;
use Redaxo\Core\Core;
use Redaxo\Core\Exception\InvalidArgumentException;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Form\Field\ArticleField;
use Redaxo\Core\Form\Field\BaseField;
use Redaxo\Core\Form\Field\CheckboxField;
use Redaxo\Core\Form\Field\ContainerField;
use Redaxo\Core\Form\Field\ControlField;
use Redaxo\Core\Form\Field\MediaField;
use Redaxo\Core\Form\Field\RadioField;
use Redaxo\Core\Form\Field\RawField;
use Redaxo\Core\Form\Field\SelectField;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Str;
use Redaxo\Core\Util\Type;
use Redaxo\Core\View\Message;

use function array_key_exists;
use function assert;
use function count;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function Redaxo\Core\View\escape;
use function sprintf;

abstract class AbstractForm
{
    /** @var string */
    protected $name;

    /** @var string "get" or "post" */
    protected $method;

    /** @var string */
    protected $fieldset;

    /** @var array<string, array<string, int|string|list<string>>> */
    private $fieldsetAttributes = [];

    /** @var array<string, list<BaseField>> */
    protected $elements;

    /** @var array<string, string|int|bool> */
    protected $params;

    /** @var bool */
    protected $debug;

    /** @var string|null */
    protected $applyUrl;

    /** @var string|null */
    protected $message;

    /** @var array<int, string> */
    protected $errorMessages = [];

    /** @var string|null */
    protected $warning;

    /** @var string|null */
    protected $formId;

    /** @var array<string, string> */
    private $formAttributes;

    /** @var CsrfToken */
    private $csrfToken;

    /**
     * Diese Konstruktor sollte nicht verwendet werden. Instanzen muessen ueber die factory() Methode erstellt werden!
     *
     * @param 'post'|'get' $method
     */
    protected function __construct(?string $fieldset, string $name, string $method = 'post', bool $debug = false)
    {
        if (!in_array($method, ['post', 'get'])) {
            throw new InvalidArgumentException('Parameter $method must be "post" or "get", but "' . $method . '" given.');
        }

        $this->name = $name;
        $this->method = $method;
        $this->elements = [];
        $this->params = [];
        $this->formAttributes = [];
        $this->addFieldset($fieldset ?: $this->name);
        $this->setMessage('');

        $this->debug = $debug;

        $this->csrfToken = CsrfToken::factory('rex_form_' . $this->getName());
    }

    /**
     * Initialisiert das Formular.
     * @return void
     */
    public function init()
    {
        // nichts tun
    }

    /**
     * Laedt die Konfiguration die noetig ist um AbstractForm im REDAXO Backend zu verwenden.
     * @return void
     */
    protected function loadBackendConfig()
    {
        $this->addParam('page', Controller::getCurrentPage());
    }

    /**
     * @param string|null $id
     * @return void
     */
    public function setFormId($id)
    {
        $this->formId = $id;
    }

    /**
     * Gibt eine Formular-Url zurück.
     *
     * @return string
     */
    public function getUrl(array $params = [])
    {
        $params = array_merge($this->getParams(), $params);
        $params['form'] = $this->getName();

        return Core::isBackend() ? Url::backendController($params) : Url::frontendController($params);
    }

    // --------- Sections

    /**
     * Fuegt dem Formular ein Fieldset hinzu.
     * Dieses dient dazu ein Formular in mehrere Abschnitte zu gliedern.
     *
     * @param string $fieldset
     * @param array<string, int|string|list<string>> $attributes
     * @return void
     */
    public function addFieldset($fieldset, array $attributes = [])
    {
        $this->fieldset = $fieldset;
        $this->fieldsetAttributes[$fieldset] = $attributes;
    }

    // --------- Fields
    /**
     * Fuegt dem Formular ein Input-Feld hinzu.
     *
     * @param string $tag
     * @param string $name
     * @param mixed $value
     * @param bool $addElement
     *
     * @return BaseField
     */
    public function addField($tag, $name, $value = null, array $attributes = [], $addElement = true)
    {
        $element = $this->createElement($tag, $name, $value, $attributes);

        if ($addElement) {
            $this->addElement($element);
            return $element;
        }

        return $element;
    }

    /**
     * Fuegt dem Formular ein Container-Feld hinzu.
     *
     * Ein Container-Feld wiederrum kann weitere Felder enthalten.
     *
     * @param string $name
     * @param mixed $value
     *
     * @return ContainerField
     */
    public function addContainerField($name, $value = null, array $attributes = [])
    {
        if (!isset($attributes['class'])) {
            $attributes['class'] = 'rex-form-container';
        }
        $attributes['internal::fieldClass'] = ContainerField::class;

        $field = $this->addField('', $name, $value, $attributes, true);
        assert($field instanceof ContainerField);
        return $field;
    }

    /**
     * Fuegt dem Formular ein Input-Feld mit dem Type $type hinzu.
     *
     * @param string $type
     * @param string $name
     * @param mixed $value
     * @param bool $addElement
     *
     * @return BaseField
     */
    public function addInputField($type, $name, $value = null, array $attributes = [], $addElement = true)
    {
        $attributes['type'] = $type;
        return $this->addField('input', $name, $value, $attributes, $addElement);
    }

    /**
     * Fuegt dem Formular ein Text-Feld hinzu.
     *
     * @param string $name
     * @param mixed $value
     *
     * @return BaseField
     */
    public function addTextField($name, $value = null, array $attributes = [])
    {
        if (!isset($attributes['class'])) {
            $attributes['class'] = 'form-control';
        }
        return $this->addInputField('text', $name, $value, $attributes);
    }

    /**
     * Fuegt dem Formular ein Read-Only-Text-Feld hinzu.
     * Dazu wird ein input-Element verwendet.
     *
     * @param string $name
     * @param mixed $value
     *
     * @return BaseField
     */
    public function addReadOnlyTextField($name, $value = null, array $attributes = [])
    {
        $attributes['readonly'] = 'readonly';
        if (!isset($attributes['class'])) {
            $attributes['class'] = 'form-control';
        }
        return $this->addInputField('text', $name, $value, $attributes);
    }

    /**
     * Fuegt dem Formular ein Read-Only-Feld hinzu.
     * Dazu wird ein span-Element verwendet.
     *
     * @param string $name
     * @param mixed $value
     *
     * @return BaseField
     */
    public function addReadOnlyField($name, $value = null, array $attributes = [])
    {
        $attributes['internal::fieldSeparateEnding'] = true;
        $attributes['internal::noNameAttribute'] = true;
        if (!isset($attributes['class'])) {
            // Wenn die class geaendert wird, muss auch
            // ContainerField::getSaveValue()
            // angepasst werden
            $attributes['class'] = 'form-control-static';
        }
        return $this->addField('p', $name, $value, $attributes, true);
    }

    /**
     * Fuegt dem Fomular ein Hidden-Feld hinzu.
     *
     * @param string $name
     * @param mixed $value
     *
     * @return BaseField
     */
    public function addHiddenField($name, $value = null, array $attributes = [])
    {
        return $this->addInputField('hidden', $name, $value, $attributes, true);
    }

    /**
     * Fuegt dem Fomular ein Checkbox-Feld hinzu.
     * Dies ermoeglicht die Mehrfach-Selektion aus einer vorgegeben Auswahl an Werten.
     *
     * @param string $name
     * @param mixed $value
     *
     * @return CheckboxField
     */
    public function addCheckboxField($name, $value = null, array $attributes = [])
    {
        $attributes['internal::fieldClass'] = CheckboxField::class;
        $field = $this->addField('', $name, $value, $attributes);
        assert($field instanceof CheckboxField);
        return $field;
    }

    /**
     * Fuegt dem Formular ein Radio-Feld hinzu.
     * Dies ermoeglicht eine Einfache-Selektion aus einer vorgegeben Auswahl an Werten.
     *
     * @param string $name
     * @param mixed $value
     *
     * @return RadioField
     */
    public function addRadioField($name, $value = null, array $attributes = [])
    {
        $attributes['internal::fieldClass'] = RadioField::class;
        $field = $this->addField('radio', $name, $value, $attributes);
        assert($field instanceof RadioField);
        return $field;
    }

    /**
     * Fuegt dem Formular ein Textarea-Feld hinzu.
     *
     * @param string $name
     * @param mixed $value
     *
     * @return BaseField
     */
    public function addTextAreaField($name, $value = null, array $attributes = [])
    {
        $attributes['internal::fieldSeparateEnding'] = true;
        if (!isset($attributes['class'])) {
            $attributes['class'] = 'form-control';
        }
        /*
        if (!isset($attributes['cols'])) {
            $attributes['cols'] = 50;
        }
        */
        if (!isset($attributes['rows'])) {
            $attributes['rows'] = 6;
        }
        return $this->addField('textarea', $name, $value, $attributes);
    }

    /**
     * Fuegt dem Formular ein Select/Auswahl-Feld hinzu.
     *
     * @param string $name
     * @param mixed $value
     *
     * @return SelectField
     */
    public function addSelectField($name, $value = null, array $attributes = [])
    {
        $attributes['internal::fieldClass'] = SelectField::class;
        if (!isset($attributes['class'])) {
            $attributes['class'] = 'form-control';
        }
        $field = $this->addField('', $name, $value, $attributes, true);
        assert($field instanceof SelectField);
        return $field;
    }

    /**
     * Fuegt dem Formular ein Feld hinzu mit dem der Medienpool angebunden werden kann.
     *
     * @param string $name
     * @param mixed $value
     * @return MediaField
     */
    public function addMediaField($name, $value = null, array $attributes = [])
    {
        $attributes['internal::fieldClass'] = MediaField::class;
        $field = $this->addField('', $name, $value, $attributes, true);
        assert($field instanceof MediaField);
        return $field;
    }

    /**
     * Fuegt dem Formular ein Feld hinzu mit dem die Struktur-Verwaltung angebunden werden kann.
     *
     * @param string $name
     * @param mixed $value
     * @return ArticleField
     */
    public function addArticleField($name, $value = null, array $attributes = [])
    {
        $attributes['internal::fieldClass'] = ArticleField::class;
        $field = $this->addField('', $name, $value, $attributes, true);
        assert($field instanceof ArticleField);
        return $field;
    }

    /**
     * Fuegt dem Fomualar ein Control-Feld hinzu.
     * Damit koennen versch. Aktionen mit dem Fomular durchgefuert werden.
     *
     * @param BaseField $saveElement
     * @param BaseField $applyElement
     * @param BaseField $deleteElement
     * @param BaseField $resetElement
     * @param BaseField $abortElement
     *
     * @return ControlField
     */
    public function addControlField($saveElement = null, $applyElement = null, $deleteElement = null, $resetElement = null, $abortElement = null)
    {
        $field = $this->addElement(new ControlField($this, $saveElement, $applyElement, $deleteElement, $resetElement, $abortElement));
        assert($field instanceof ControlField);
        return $field;
    }

    /**
     * Fuegt dem Formular beliebiges HTML zu.
     *
     * @param string $html HTML code
     *
     * @return RawField
     */
    public function addRawField($html)
    {
        $field = $this->addElement(new RawField($html, $this));
        assert($field instanceof RawField);
        return $field;
    }

    /**
     * Fuegt dem Formular eine Fehlermeldung hinzu.
     *
     * @param int $errorCode
     * @param string $errorMessage
     * @return void
     */
    public function addErrorMessage($errorCode, $errorMessage)
    {
        $this->errorMessages[$errorCode] = $errorMessage;
    }

    /**
     * Fuegt dem Formular einen Parameter hinzu.
     * Diese an den Stellen eingefuegt, an denen das Fomular neue Requests erzeugt.
     *
     * @param string $name
     * @param string|int|bool $value
     * @return void
     */
    public function addParam($name, $value)
    {
        $this->params[$name] = $value;
    }

    /**
     * Gibt alle Parameter des Fomulars zurueck.
     *
     * @return array<string, string|int|bool>
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * Gibt den Wert des Parameters $name zurueck,
     * oder $default kein Parameter mit dem Namen exisitiert.
     *
     * @param string $name
     * @param mixed $default
     *
     * @return mixed
     */
    public function getParam($name, $default = null)
    {
        return $this->params[$name] ?? $default;
    }

    /**
     * Allgemeine Bootleneck-Methode um Elemente in das Formular einzufuegen.
     *
     * @return BaseField
     */
    protected function addElement(BaseField $element)
    {
        $this->elements[$this->fieldset][] = $element;
        return $element;
    }

    /**
     * Erstellt ein Input-Element anhand des Strings $inputType.
     *
     * @param string $inputType
     * @param string $name
     * @param mixed $value
     *
     * @return BaseField
     */
    public function createInput($inputType, $name, $value = null, array $attributes = [])
    {
        $tag = self::getInputTagName($inputType);
        $className = self::getInputClassName($inputType);
        $attributes = array_merge(self::getInputAttributes($inputType), $attributes);
        $attributes['internal::fieldClass'] = $className;

        return $this->createElement($tag, $name, $value, $attributes);
    }

    /**
     * Erstellt ein Input-Element anhand von $tag.
     *
     * @param string $tag
     * @param string $name
     * @param mixed $value
     *
     * @return BaseField
     */
    protected function createElement($tag, $name, $value, array $attributes = [])
    {
        $id = $this->getId($name);

        // Evtl postwerte wieder übernehmen (auch externe Werte überschreiben)
        $postValue = $this->elementPostValue($this->getFieldsetName(), $name);
        if (null !== $postValue) {
            $value = $postValue;
        }

        // Wert aus Quelle nehmen, falls keiner extern und keiner im POST angegeben
        if (null === $value) {
            $value = $this->getValue($name);
        }

        if (!isset($attributes['internal::useArraySyntax'])) {
            $attributes['internal::useArraySyntax'] = true;
        }

        // Eigentlichen Feldnamen nochmals speichern
        $fieldName = $name;
        $fieldset = Str::normalize($this->fieldset);
        if (true === $attributes['internal::useArraySyntax']) {
            $name = $fieldset . '[' . $name . ']';
        } elseif (false === $attributes['internal::useArraySyntax']) {
            $name = $fieldset . '_' . $name;
        }
        unset($attributes['internal::useArraySyntax']);

        $class = BaseField::class;
        if (isset($attributes['internal::fieldClass'])) {
            /** @var class-string<BaseField> $class */
            $class = $attributes['internal::fieldClass'];
            unset($attributes['internal::fieldClass']);
        }

        $separateEnding = false;
        if (isset($attributes['internal::fieldSeparateEnding'])) {
            $separateEnding = $attributes['internal::fieldSeparateEnding'];
            unset($attributes['internal::fieldSeparateEnding']);
        }

        $internalAttr = ['name' => $name];
        if (isset($attributes['internal::noNameAttribute'])) {
            $internalAttr = [];
            unset($attributes['internal::noNameAttribute']);
        }

        // 1. Array: Eigenschaften, die via Parameter Überschrieben werden können/dürfen
        // 2. Array: Eigenschaften, via Parameter
        // 3. Array: Eigenschaften, die hier fest definiert sind / nicht veränderbar via Parameter
        $attributes = array_merge(['id' => $id], $attributes, $internalAttr);
        $element = new $class($tag, $this, $attributes, $separateEnding);
        $element->setFieldName($fieldName);
        $element->setValue($value);
        return $element;
    }

    /**
     * @param string $name
     * @return string
     */
    protected function getId($name)
    {
        return $this->fieldset . '_' . $name;
    }

    /**
     * @param string $name
     * @return mixed
     */
    abstract protected function getValue($name);

    /**
     * Setzt die Url die bei der apply-action genutzt wird.
     *
     * @param string|array $url
     * @return void
     */
    public function setApplyUrl($url)
    {
        if (is_array($url)) {
            $url = $this->getUrl($url);
        }

        $this->applyUrl = $url;
    }

    // --------- Static Methods
    /**
     * @param string $inputType
     * @return class-string<BaseField>
     */
    public static function getInputClassName($inputType)
    {
        // ----- EXTENSION POINT
        $className = Extension::registerPoint(new ExtensionPoint('REX_FORM_INPUT_CLASS', '', ['inputType' => $inputType]));

        if ($className) {
            return $className;
        }

        $className = match ($inputType) {
            'control' => ControlField::class,
            'checkbox' => CheckboxField::class,
            'radio' => RadioField::class,
            'select' => SelectField::class,
            'media' => MediaField::class,
            'article' => ArticleField::class,
            'hidden', 'readonly', 'readonlytext', 'text', 'textarea' => BaseField::class,
            default => throw new LogicException("Unexpected inputType '" . $inputType . "'!"),
        };

        return $className;
    }

    /**
     * @param string $inputType
     *
     * @return string
     */
    public static function getInputTagName($inputType)
    {
        // ----- EXTENSION POINT
        $inputTag = Extension::registerPoint(new ExtensionPoint('REX_FORM_INPUT_TAG', '', ['inputType' => $inputType]));

        if ($inputTag) {
            return $inputTag;
        }

        switch ($inputType) {
            case 'checkbox':
            case 'hidden':
            case 'radio':
            case 'readonlytext':
            case 'text':
                return 'input';
            case 'textarea':
                return $inputType;
            case 'readonly':
                return 'p';
            default:
                $inputTag = '';
        }
        return $inputTag;
    }

    /**
     * @param string $inputType
     *
     * @return array
     */
    public static function getInputAttributes($inputType)
    {
        // ----- EXTENSION POINT
        /** @var array<string, scalar> $inputAttr */
        $inputAttr = [];
        $inputAttr = Extension::registerPoint(new ExtensionPoint('REX_FORM_INPUT_ATTRIBUTES', $inputAttr, ['inputType' => $inputType]));

        if ($inputAttr) {
            return $inputAttr;
        }

        switch ($inputType) {
            case 'checkbox':
            case 'hidden':
            case 'radio':
                return [
                    'type' => $inputType,
                ];
            case 'select':
                return [
                    'class' => 'form-control',
                ];
            case 'text':
                return [
                    'type' => $inputType,
                    'class' => 'form-control',
                ];
            case 'textarea':
                return [
                    'internal::fieldSeparateEnding' => true,
                    'class' => 'form-control',
                    // 'cols' => 50,
                    'rows' => 6,
                ];
            case 'readonly':
                return [
                    'internal::fieldSeparateEnding' => true,
                    'internal::noNameAttribute' => true,
                    'class' => 'form-control-static',
                ];
            case 'readonlytext':
                return [
                    'type' => 'text',
                    'readonly' => 'readonly',
                    'class' => 'form-control-static',
                ];
            default:
                $inputAttr = [];
        }

        return $inputAttr;
    }

    // --------- Form Methods

    /** @return bool */
    protected function isHeaderElement(BaseField $element)
    {
        return 'input' == $element->getTag() && 'hidden' == $element->getAttribute('type');
    }

    /**
     * @return bool
     * @psalm-assert-if-true ControlField $element
     */
    protected function isFooterElement(BaseField $element)
    {
        return $this->isControlElement($element);
    }

    /**
     * @return bool
     * @psalm-assert-if-true ControlField $element
     */
    protected function isControlElement(BaseField $element)
    {
        return $element instanceof ControlField;
    }

    /**
     * @return bool
     * @psalm-assert-if-true RawField $element
     */
    protected function isRawElement(BaseField $element)
    {
        return $element instanceof RawField;
    }

    /** @return list<BaseField> */
    protected function getHeaderElements()
    {
        $headerElements = [];
        foreach ($this->elements as $fieldsetElementsArray) {
            foreach ($fieldsetElementsArray as $element) {
                if ($this->isHeaderElement($element)) {
                    $headerElements[] = $element;
                }
            }
        }
        return $headerElements;
    }

    /** @return list<BaseField> */
    protected function getFooterElements()
    {
        $footerElements = [];
        foreach ($this->elements as $fieldsetElementsArray) {
            foreach ($fieldsetElementsArray as $element) {
                if ($this->isFooterElement($element)) {
                    $footerElements[] = $element;
                }
            }
        }
        return $footerElements;
    }

    /** @return string */
    protected function getFieldsetName()
    {
        return $this->fieldset;
    }

    /** @return list<string> */
    protected function getFieldsets()
    {
        $fieldsets = [];
        foreach ($this->elements as $fieldsetName => $fieldsetElementsArray) {
            $fieldsets[] = $fieldsetName;
        }
        return $fieldsets;
    }

    /** @return array<string, list<BaseField>> */
    protected function getFieldsetElements()
    {
        $fieldsetElements = [];
        foreach ($this->elements as $fieldsetName => $fieldsetElementsArray) {
            $fieldsetElements[$fieldsetName] = [];

            foreach ($fieldsetElementsArray as $element) {
                if ($this->isHeaderElement($element)) {
                    continue;
                }
                if ($this->isFooterElement($element)) {
                    continue;
                }

                $fieldsetElements[$fieldsetName][] = $element;
            }
        }
        return $fieldsetElements;
    }

    /** @return array<string, list<BaseField>> */
    protected function getSaveElements()
    {
        $fieldsetElements = [];
        foreach ($this->elements as $fieldsetName => $fieldsetElementsArray) {
            $fieldsetElements[$fieldsetName] = [];

            foreach ($fieldsetElementsArray as $element) {
                if ($this->isFooterElement($element)) {
                    continue;
                }
                if ($this->isRawElement($element)) {
                    continue;
                }

                $fieldsetElements[$fieldsetName][] = $element;
            }
        }
        return $fieldsetElements;
    }

    /** @return ControlField|null */
    protected function getControlElement()
    {
        foreach ($this->elements as $fieldsetElementsArray) {
            foreach ($fieldsetElementsArray as $element) {
                if ($this->isControlElement($element)) {
                    return $element;
                }
            }
        }

        return null;
    }

    /**
     * @param string $fieldsetName
     * @param string $elementName
     *
     * @return BaseField|null
     */
    protected function getElement($fieldsetName, $elementName)
    {
        if (!is_array($this->elements[$fieldsetName])) {
            return null;
        }

        $normalizedName = Str::normalize($fieldsetName);
        $normalizedName .= '[' . Str::normalize($elementName) . ']';

        for ($i = 0; $i < count($this->elements[$fieldsetName]); ++$i) {
            if ($this->elements[$fieldsetName][$i]->getAttribute('name') == $normalizedName) {
                return $this->elements[$fieldsetName][$i];
            }
        }

        return null;
    }

    /** @return string */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string|null $warning
     * @return void
     */
    public function setWarning($warning)
    {
        $this->warning = $warning;
    }

    /** @return string */
    public function getWarning()
    {
        $warning = Request::request($this->getName() . '_warning', 'string');
        $warning = escape($warning, 'html_simplified');

        if ('' != $this->warning) {
            $warning .= "\n" . $this->warning;
        }
        return $warning;
    }

    /**
     * @param string|null $message
     * @return void
     */
    public function setMessage($message)
    {
        $this->message = $message;
    }

    /** @return string */
    public function getMessage()
    {
        $message = Request::request($this->getName() . '_msg', 'string');
        $message = escape($message, 'html_simplified');

        if ('' != $this->message) {
            $message .= "\n" . $this->message;
        }
        return $message;
    }

    public function setFormAttribute(string $attributeName, ?string $attributeValue): void
    {
        $attributeName = preg_replace('/[^\w\d\-]/', '', strtolower($attributeName));

        if ('' === $attributeName) {
            throw new InvalidArgumentException('The attribute name cannot be empty.');
        }

        if (null === $attributeValue) {
            if (array_key_exists($attributeName, $this->formAttributes)) {
                unset($this->formAttributes[$attributeName]);
            }
            return;
        }

        if ('id' === $attributeName) {
            $this->setFormId($attributeValue);
            return;
        }

        if (in_array($attributeName, ['method', 'action'], true)) {
            throw new LogicException(sprintf('Attribute "%s" can not be set via %s.', $attributeName, __FUNCTION__));
        }

        $this->formAttributes[$attributeName] = $attributeValue;
    }

    /**
     * Callbackfunktion, damit in subklassen der Value noch beeinflusst werden kann
     * wenn das Feld mit Datenbankwerten angezeigt wird.
     *
     * @param string $fieldsetName
     * @param string $fieldName
     * @param mixed $fieldValue
     * @return mixed
     */
    protected function preView($fieldsetName, $fieldName, $fieldValue)
    {
        return $fieldValue;
    }

    /**
     * @param string $fieldsetName
     *
     * @return array
     */
    public function fieldsetPostValues($fieldsetName)
    {
        return Request::post(Str::normalize($fieldsetName), 'array');
    }

    /**
     * @param string $fieldsetName
     * @param string $fieldName
     * @param string|null $default
     *
     * @return string|null
     */
    public function elementPostValue($fieldsetName, $fieldName, $default = null)
    {
        $fields = $this->fieldsetPostValues($fieldsetName);

        // name attributes are normalized
        $normalizedFieldName = Str::normalize($fieldName);

        return $fields[$normalizedFieldName] ?? $default;
    }

    /**
     * Validiert die Eingaben.
     * Gibt true zurück wenn alles ok war, false bei einem allgemeinen Fehler oder
     * einen String mit einer Fehlermeldung.
     *
     * Eingaben sind via
     *   $el    = $this->getElement($fieldSetName, $fieldName);
     *   $val   = $el->getValue();
     * erreichbar.
     *
     * @return bool|string
     */
    protected function validate()
    {
        $messages = [];

        if (!$this->csrfToken->isValid()) {
            $messages[] = I18n::msg('csrf_token_invalid');
        }

        foreach ($this->getSaveElements() as $fieldsetElements) {
            foreach ($fieldsetElements as $element) {
                if ($element->isReadOnly()) {
                    continue;
                }

                $validator = $element->getValidator();
                $saveValue = $element->getSaveValue();
                if (!$validator->isValid((string) $saveValue)) {
                    $messages[] = $validator->getMessage();
                }
            }
        }
        return empty($messages) ? true : implode('<br />', $messages);
    }

    /**
     * Übernimmt die POST-Werte in die FormElemente.
     * @return void
     */
    protected function processPostValues()
    {
        $saveElements = $this->getSaveElements();
        foreach ($saveElements as $fieldsetName => $fieldsetElements) {
            foreach ($fieldsetElements as $element) {
                // read-only-fields nicht speichern
                if ($element->isReadOnly()) {
                    continue;
                }

                $fieldName = $element->getFieldName();
                $fieldValue = $this->elementPostValue($fieldsetName, $fieldName);

                $element->setValue($fieldValue);
            }
        }
    }

    /**
     * Saves the form.
     *
     * @return bool|string|int `true` on success, an error message or an error code otherwise
     */
    abstract protected function save();

    /** @return bool|int */
    protected function delete()
    {
        throw new BadMethodCallException('delete() is not implemented.');
    }

    /**
     * @param string $listMessage
     * @param string $listWarning
     * @return never
     */
    protected function redirect($listMessage = '', $listWarning = '', array $params = [])
    {
        if ('' != $listMessage) {
            $listName = Request::request('list', 'string');
            $params[$listName . '_msg'] = $listMessage;
        }

        if ('' != $listWarning) {
            $listName = Request::request('list', 'string');
            $params[$listName . '_warning'] = $listWarning;
        }

        $paramString = '&' . Str::buildQuery($params);

        if ($this->debug) {
            echo 'redirect to: ' . escape($this->applyUrl . $paramString);
            exit;
        }

        header('Location: ' . $this->applyUrl . $paramString);
        exit;
    }

    /** @return string */
    public function get()
    {
        $this->init();

        Extension::registerPoint(new ExtensionPoint('REX_FORM_GET', $this, [], true));

        if (!$this->applyUrl) {
            $this->setApplyUrl($this->getUrl(['func' => '']));
        }

        if (null !== ($controlElement = $this->getControlElement())) {
            if ($controlElement->saved()) {
                $this->processPostValues();

                // speichern und umleiten
                // Nachricht in der Liste anzeigen
                if (true === ($result = $this->validate()) && true === ($result = $this->save())) {
                    $this->redirect(I18n::msg('form_saved'));
                } elseif (is_int($result) && isset($this->errorMessages[$result])) {
                    $this->setWarning($this->errorMessages[$result]);
                } elseif (is_string($result) && '' != $result) {
                    $this->setWarning($result);
                } else {
                    $this->setWarning(I18n::msg('form_save_error'));
                }
            } elseif ($controlElement->applied()) {
                $this->processPostValues();

                // speichern und wiederanzeigen
                // Nachricht im Formular anzeigen
                if (true === ($result = $this->validate()) && true === ($result = $this->save())) {
                    $this->setMessage(I18n::msg('form_applied'));
                } elseif (is_int($result) && isset($this->errorMessages[$result])) {
                    $this->setWarning($this->errorMessages[$result]);
                } elseif (is_string($result) && '' != $result) {
                    $this->setWarning($result);
                } else {
                    $this->setWarning(I18n::msg('form_save_error'));
                }
            } elseif ($controlElement->deleted()) {
                // speichern und wiederanzeigen
                // Nachricht in der Liste anzeigen
                if (true === ($result = $this->delete())) {
                    $this->redirect(I18n::msg('form_deleted'));
                } elseif (is_string($result) && '' != $result) {
                    $this->setWarning($result);
                } else {
                    $this->setWarning(I18n::msg('form_delete_error'));
                }
            } elseif ($controlElement->resetted()) {
                // verwerfen und wiederanzeigen
                // Nachricht im Formular anzeigen
                $this->setMessage(I18n::msg('form_resetted'));
            } elseif ($controlElement->aborted()) {
                // verwerfen und umleiten
                // Nachricht in der Liste anzeigen
                $this->redirect(I18n::msg('form_resetted'));
            }
        }

        $actionParams = [];
        if ('get' == strtolower($this->method)) {
            // Parameter dem Formular hinzufügen
            foreach ($this->getParams() as $name => $value) {
                $this->addHiddenField($name, $value, ['internal::useArraySyntax' => 'none']);
            }
        } else {
            $actionParams = $this->getParams();
        }

        $s = "\n";

        $warning = $this->getWarning();
        $message = $this->getMessage();
        if ('' != $warning) {
            $s .= '  ' . Message::error($warning) . "\n";
        } elseif ('' != $message) {
            $s .= '  ' . Message::success($message) . "\n";
        }

        $i = 0;
        $addHeaders = true;
        $fieldsets = $this->getFieldsetElements();
        $last = count($fieldsets);

        $id = '';
        if ($this->formId) {
            $id = ' id="' . escape($this->formId) . '"';
        }

        $s .= sprintf('<form %s %s action="%s" method="%s">' . "\n",
            $id,
            Str::buildAttributes($this->formAttributes),
            Url::backendController($actionParams),
            $this->method,
        );
        foreach ($fieldsets as $fieldsetName => $fieldsetElements) {
            $attributes = $this->fieldsetAttributes[$fieldsetName] ?? [];
            $s .= '<fieldset ' . Str::buildAttributes($attributes) . '>' . "\n";

            if ('' != $fieldsetName && $fieldsetName != $this->name) {
                $s .= '<legend>' . escape($fieldsetName) . '</legend>' . "\n";
            }

            // Die HeaderElemente nur im 1. Fieldset ganz am Anfang einfügen
            if (0 == $i && $addHeaders) {
                foreach ($this->getHeaderElements() as $element) {
                    // Callback
                    $element->setValue($this->preView($fieldsetName, $element->getFieldName(), $element->getValue()));
                    // HeaderElemente immer ohne <p>
                    $s .= $element->formatElement();
                }
                $addHeaders = false;
            }

            foreach ($fieldsetElements as $element) {
                // Callback
                $element->setValue($this->preView($fieldsetName, $element->getFieldName(), $element->getValue()));
                $s .= $element->get();
            }

            // Die FooterElemente nur innerhalb des letzten Fieldsets
            if (($i + 1) == $last) {
                foreach ($this->getFooterElements() as $element) {
                    // Callback
                    $element->setValue($this->preView($fieldsetName, $element->getFieldName(), $element->getValue()));
                    $s .= $element->get();
                }
            }

            $s .= '</fieldset>' . "\n";

            ++$i;
        }

        $s .= $this->csrfToken->getHiddenField() . "\n";
        $s .= '</form>' . "\n";

        return $s;
    }

    /** @return void */
    public function show()
    {
        echo $this->get();
    }
}
