<?php

/**
 * @package redaxo\core\form
 */
abstract class rex_form_base
{
    /** @var string */
    protected $name;

    /** @var string "get" or "post" */
    protected $method;

    /** @var string */
    protected $fieldset;

    /** @var array<string, array<string, int|string|list<string>>> */
    private $fieldsetAttributes = [];

    /** @var array<string, list<rex_form_element>> */
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

    /** @var rex_csrf_token */
    private $csrfToken;

    /**
     * Diese Konstruktor sollte nicht verwendet werden. Instanzen muessen ueber die factory() Methode erstellt werden!
     *
     * @param string|null $fieldset
     * @param string $name
     * @param 'post'|'get' $method
     * @param bool $debug
     */
    protected function __construct($fieldset, $name, $method = 'post', $debug = false)
    {
        if (!in_array($method, ['post', 'get'])) {
            throw new InvalidArgumentException("rex_form: Method-Parameter darf nur die Werte 'post' oder 'get' annehmen!");
        }

        $this->name = $name;
        $this->method = $method;
        $this->elements = [];
        $this->params = [];
        $this->formAttributes = [];
        $this->addFieldset($fieldset ?: $this->name);
        $this->setMessage('');

        $this->debug = $debug;

        $this->csrfToken = rex_csrf_token::factory('rex_form_' . $this->getName());
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
     * Laedt die Konfiguration die noetig ist um rex_form im REDAXO Backend zu verwenden.
     * @return void
     */
    protected function loadBackendConfig()
    {
        $this->addParam('page', rex_be_controller::getCurrentPage());
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
     * @param bool $escape
     *
     * @return string
     */
    public function getUrl(array $params = [], $escape = true)
    {
        $params = array_merge($this->getParams(), $params);
        $params['form'] = $this->getName();

        return rex::isBackend() ? rex_url::backendController($params, $escape) : rex_url::frontendController($params, $escape);
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
     * @return rex_form_element
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
     * @return rex_form_container_element
     */
    public function addContainerField($name, $value = null, array $attributes = [])
    {
        if (!isset($attributes['class'])) {
            $attributes['class'] = 'rex-form-container';
        }
        $attributes['internal::fieldClass'] = rex_form_container_element::class;

        $field = $this->addField('', $name, $value, $attributes, true);
        assert($field instanceof rex_form_container_element);
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
     * @return rex_form_element
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
     * @return rex_form_element
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
     * @return rex_form_element
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
     * @return rex_form_element
     */
    public function addReadOnlyField($name, $value = null, array $attributes = [])
    {
        $attributes['internal::fieldSeparateEnding'] = true;
        $attributes['internal::noNameAttribute'] = true;
        if (!isset($attributes['class'])) {
            // Wenn die class geaendert wird, muss auch
            // rex_form_container_element::getSaveValue()
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
     * @return rex_form_element
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
     * @return rex_form_checkbox_element
     */
    public function addCheckboxField($name, $value = null, array $attributes = [])
    {
        $attributes['internal::fieldClass'] = rex_form_checkbox_element::class;
        $field = $this->addField('', $name, $value, $attributes);
        assert($field instanceof rex_form_checkbox_element);
        return $field;
    }

    /**
     * Fuegt dem Formular ein Radio-Feld hinzu.
     * Dies ermoeglicht eine Einfache-Selektion aus einer vorgegeben Auswahl an Werten.
     *
     * @param string $name
     * @param mixed $value
     *
     * @return rex_form_radio_element
     */
    public function addRadioField($name, $value = null, array $attributes = [])
    {
        $attributes['internal::fieldClass'] = rex_form_radio_element::class;
        $field = $this->addField('radio', $name, $value, $attributes);
        assert($field instanceof rex_form_radio_element);
        return $field;
    }

    /**
     * Fuegt dem Formular ein Textarea-Feld hinzu.
     *
     * @param string $name
     * @param mixed $value
     *
     * @return rex_form_element
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
     * @return rex_form_select_element
     */
    public function addSelectField($name, $value = null, array $attributes = [])
    {
        $attributes['internal::fieldClass'] = rex_form_select_element::class;
        if (!isset($attributes['class'])) {
            $attributes['class'] = 'form-control';
        }
        $field = $this->addField('', $name, $value, $attributes, true);
        assert($field instanceof rex_form_select_element);
        return $field;
    }

    /**
     * Fuegt dem Formular ein Feld hinzu mit dem der Medienpool angebunden werden kann.
     * Es kann nur ein Element aus dem Medienpool eingefuegt werden.
     *
     * @param string $name
     * @param mixed $value
     *
     * @throws rex_exception
     *
     * @return rex_form_widget_media_element
     */
    public function addMediaField($name, $value = null, array $attributes = [])
    {
        if (!rex_addon::get('mediapool')->isAvailable()) {
            throw new rex_exception(__METHOD__ . '() needs "mediapool" addon!');
        }
        $attributes['internal::fieldClass'] = rex_form_widget_media_element::class;
        $field = $this->addField('', $name, $value, $attributes, true);
        assert($field instanceof rex_form_widget_media_element);
        return $field;
    }

    /**
     * Fuegt dem Formular ein Feld hinzu mit dem der Medienpool angebunden werden kann.
     * Damit koennen mehrere Elemente aus dem Medienpool eingefuegt werden.
     *
     * @param string $name
     * @param mixed $value
     *
     * @throws rex_exception
     *
     * @return rex_form_widget_medialist_element
     */
    public function addMedialistField($name, $value = null, array $attributes = [])
    {
        if (!rex_addon::get('mediapool')->isAvailable()) {
            throw new rex_exception(__METHOD__ . '() needs "mediapool" addon!');
        }
        $attributes['internal::fieldClass'] = rex_form_widget_medialist_element::class;
        $field = $this->addField('', $name, $value, $attributes, true);
        assert($field instanceof rex_form_widget_medialist_element);
        return $field;
    }

    /**
     * Fuegt dem Formular ein Feld hinzu mit dem die Struktur-Verwaltung angebunden werden kann.
     * Es kann nur ein Element aus der Struktur eingefuegt werden.
     *
     * @param string $name
     * @param mixed $value
     *
     * @throws rex_exception
     *
     * @return rex_form_widget_linkmap_element
     */
    public function addLinkmapField($name, $value = null, array $attributes = [])
    {
        if (!rex_addon::get('structure')->isAvailable()) {
            throw new rex_exception(__METHOD__ . '() needs "structure" addon!');
        }
        $attributes['internal::fieldClass'] = rex_form_widget_linkmap_element::class;
        $field = $this->addField('', $name, $value, $attributes, true);
        assert($field instanceof rex_form_widget_linkmap_element);
        return $field;
    }

    /**
     * Fuegt dem Formular ein Feld hinzu mit dem die Struktur-Verwaltung angebunden werden kann.
     * Damit koennen mehrere Elemente aus der Struktur eingefuegt werden.
     *
     * @param string $name
     * @param mixed $value
     *
     * @throws rex_exception
     *
     * @return rex_form_widget_linklist_element
     */
    public function addLinklistField($name, $value = null, array $attributes = [])
    {
        if (!rex_addon::get('structure')->isAvailable()) {
            throw new rex_exception(__METHOD__ . '() needs "structure" addon!');
        }
        $attributes['internal::fieldClass'] = rex_form_widget_linklist_element::class;
        $field = $this->addField('', $name, $value, $attributes, true);
        assert($field instanceof rex_form_widget_linklist_element);
        return $field;
    }

    /**
     * Fuegt dem Fomualar ein Control-Feld hinzu.
     * Damit koennen versch. Aktionen mit dem Fomular durchgefuert werden.
     *
     * @param rex_form_element $saveElement
     * @param rex_form_element $applyElement
     * @param rex_form_element $deleteElement
     * @param rex_form_element $resetElement
     * @param rex_form_element $abortElement
     *
     * @return rex_form_control_element
     */
    public function addControlField($saveElement = null, $applyElement = null, $deleteElement = null, $resetElement = null, $abortElement = null)
    {
        $field = $this->addElement(new rex_form_control_element($this, $saveElement, $applyElement, $deleteElement, $resetElement, $abortElement));
        assert($field instanceof rex_form_control_element);
        return $field;
    }

    /**
     * Fuegt dem Formular beliebiges HTML zu.
     *
     * @param string $html HTML code
     *
     * @return rex_form_raw_element
     */
    public function addRawField($html)
    {
        $field = $this->addElement(new rex_form_raw_element($html, $this));
        assert($field instanceof rex_form_raw_element);
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
     * @return rex_form_element
     */
    protected function addElement(rex_form_element $element)
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
     * @return rex_form_element
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
     * @return rex_form_element
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
        $fieldset = rex_string::normalize($this->fieldset);
        if (true === $attributes['internal::useArraySyntax']) {
            $name = $fieldset . '[' . $name . ']';
        } elseif (false === $attributes['internal::useArraySyntax']) {
            $name = $fieldset . '_' . $name;
        }
        unset($attributes['internal::useArraySyntax']);

        $class = rex_form_element::class;
        if (isset($attributes['internal::fieldClass'])) {
            /** @var class-string<rex_form_element> $class */
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
            $url = $this->getUrl($url, false);
        }

        $this->applyUrl = $url;
    }

    // --------- Static Methods

    /**
     * @param string $inputType
     *
     * @throws rex_exception
     *
     * @return class-string<rex_form_element>
     */
    public static function getInputClassName($inputType)
    {
        // ----- EXTENSION POINT
        $className = rex_extension::registerPoint(new rex_extension_point('REX_FORM_INPUT_CLASS', '', ['inputType' => $inputType]));

        if ($className) {
            return $className;
        }

        $className = match ($inputType) {
            'control' => rex_form_control_element::class,
            'checkbox' => rex_form_checkbox_element::class,
            'radio' => rex_form_radio_element::class,
            'select' => rex_form_select_element::class,
            'media' => rex_form_widget_media_element::class,
            'medialist' => rex_form_widget_medialist_element::class,
            'link' => rex_form_widget_linkmap_element::class,
            'linklist' => rex_form_widget_linklist_element::class,
            'hidden', 'readonly', 'readonlytext', 'text', 'textarea' => rex_form_element::class,
            default => throw new rex_exception("Unexpected inputType '" . $inputType . "'!"),
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
        $inputTag = rex_extension::registerPoint(new rex_extension_point('REX_FORM_INPUT_TAG', '', ['inputType' => $inputType]));

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
        $inputAttr = rex_extension::registerPoint(new rex_extension_point('REX_FORM_INPUT_ATTRIBUTES', $inputAttr, ['inputType' => $inputType]));

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
    protected function isHeaderElement(rex_form_element $element)
    {
        return 'input' == $element->getTag() && 'hidden' == $element->getAttribute('type');
    }

    /**
     * @return bool
     * @psalm-assert-if-true rex_form_control_element $element
     */
    protected function isFooterElement(rex_form_element $element)
    {
        return $this->isControlElement($element);
    }

    /**
     * @return bool
     * @psalm-assert-if-true rex_form_control_element $element
     */
    protected function isControlElement(rex_form_element $element)
    {
        return $element instanceof rex_form_control_element;
    }

    /**
     * @return bool
     * @psalm-assert-if-true rex_form_raw_element $element
     */
    protected function isRawElement(rex_form_element $element)
    {
        return $element instanceof rex_form_raw_element;
    }

    /** @return list<rex_form_element> */
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

    /** @return list<rex_form_element> */
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

    /** @return array<string, list<rex_form_element>> */
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

    /** @return array<string, list<rex_form_element>> */
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

    /** @return rex_form_control_element|null */
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
     * @return rex_form_element|null
     */
    protected function getElement($fieldsetName, $elementName)
    {
        if (!is_array($this->elements[$fieldsetName])) {
            return null;
        }

        $normalizedName = rex_string::normalize($fieldsetName);
        $normalizedName .= '[' . rex_string::normalize($elementName) . ']';

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
        $warning = rex_request($this->getName() . '_warning', 'string');
        $warning = rex_escape($warning, 'html_simplified');

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
        $message = rex_request($this->getName() . '_msg', 'string');
        $message = rex_escape($message, 'html_simplified');

        if ('' != $this->message) {
            $message .= "\n" . $this->message;
        }
        return $message;
    }

    public function setFormAttribute(string $attributeName, ?string $attributeValue): void
    {
        $attributeName = preg_replace('/[^\w\d\-]/', '', strtolower($attributeName));

        if ('' === $attributeName) {
            throw new rex_exception('The attribute name cannot be empty.');
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
            throw new rex_exception(sprintf('Attribute "%s" can not be set via %s.', $attributeName, __FUNCTION__));
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
        return rex_post(rex_string::normalize($fieldsetName), 'array');
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
        $normalizedFieldName = rex_string::normalize($fieldName);

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
            $messages[] = rex_i18n::msg('csrf_token_invalid');
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
            $listName = rex_request('list', 'string');
            $params[$listName . '_msg'] = $listMessage;
        }

        if ('' != $listWarning) {
            $listName = rex_request('list', 'string');
            $params[$listName . '_warning'] = $listWarning;
        }

        $paramString = '&' . rex_string::buildQuery($params);

        if ($this->debug) {
            echo 'redirect to: ' . rex_escape($this->applyUrl . $paramString);
            exit;
        }

        header('Location: ' . $this->applyUrl . $paramString);
        exit;
    }

    /** @return string */
    public function get()
    {
        $this->init();

        rex_extension::registerPoint(new rex_extension_point('REX_FORM_GET', $this, [], true));

        if (!$this->applyUrl) {
            $this->setApplyUrl($this->getUrl(['func' => ''], false));
        }

        if (null !== ($controlElement = $this->getControlElement())) {
            if ($controlElement->saved()) {
                $this->processPostValues();

                // speichern und umleiten
                // Nachricht in der Liste anzeigen
                if (true === ($result = $this->validate()) && true === ($result = $this->save())) {
                    $this->redirect(rex_i18n::msg('form_saved'));
                } elseif (is_int($result) && isset($this->errorMessages[$result])) {
                    $this->setWarning($this->errorMessages[$result]);
                } elseif (is_string($result) && '' != $result) {
                    $this->setWarning($result);
                } else {
                    $this->setWarning(rex_i18n::msg('form_save_error'));
                }
            } elseif ($controlElement->applied()) {
                $this->processPostValues();

                // speichern und wiederanzeigen
                // Nachricht im Formular anzeigen
                if (true === ($result = $this->validate()) && true === ($result = $this->save())) {
                    $this->setMessage(rex_i18n::msg('form_applied'));
                } elseif (is_int($result) && isset($this->errorMessages[$result])) {
                    $this->setWarning($this->errorMessages[$result]);
                } elseif (is_string($result) && '' != $result) {
                    $this->setWarning($result);
                } else {
                    $this->setWarning(rex_i18n::msg('form_save_error'));
                }
            } elseif ($controlElement->deleted()) {
                // speichern und wiederanzeigen
                // Nachricht in der Liste anzeigen
                if (true === ($result = $this->delete())) {
                    $this->redirect(rex_i18n::msg('form_deleted'));
                } elseif (is_string($result) && '' != $result) {
                    $this->setWarning($result);
                } else {
                    $this->setWarning(rex_i18n::msg('form_delete_error'));
                }
            } elseif ($controlElement->resetted()) {
                // verwerfen und wiederanzeigen
                // Nachricht im Formular anzeigen
                $this->setMessage(rex_i18n::msg('form_resetted'));
            } elseif ($controlElement->aborted()) {
                // verwerfen und umleiten
                // Nachricht in der Liste anzeigen
                $this->redirect(rex_i18n::msg('form_resetted'));
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
            $s .= '  ' . rex_view::error($warning) . "\n";
        } elseif ('' != $message) {
            $s .= '  ' . rex_view::success($message) . "\n";
        }

        $i = 0;
        $addHeaders = true;
        $fieldsets = $this->getFieldsetElements();
        $last = count($fieldsets);

        $id = '';
        if ($this->formId) {
            $id = ' id="' . rex_escape($this->formId) . '"';
        }

        $s .= sprintf('<form %s %s action="%s" method="%s">' . "\n",
            $id,
            rex_string::buildAttributes($this->formAttributes),
            rex_url::backendController($actionParams),
            $this->method,
        );
        foreach ($fieldsets as $fieldsetName => $fieldsetElements) {
            $attributes = $this->fieldsetAttributes[$fieldsetName] ?? [];
            $s .= '<fieldset ' . rex_string::buildAttributes($attributes) . '>' . "\n";

            if ('' != $fieldsetName && $fieldsetName != $this->name) {
                $s .= '<legend>' . rex_escape($fieldsetName) . '</legend>' . "\n";
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
