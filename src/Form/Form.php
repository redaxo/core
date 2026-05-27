<?php

namespace Redaxo\Core\Form;

use Redaxo\Core\Base\FactoryTrait;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Exception\SqlException;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Form\Field\PriorityField;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Translation\I18n;

use function assert;
use function count;
use function in_array;
use function is_string;

/**
 * Form repraesentiert ein Formular in REDAXO.
 * Diese Klasse kann in Frontend u. Backend eingesetzt werden.
 *
 * Nach erzeugen eines Formulars mit der factory()-Methode muss dieses mit verschiedenen Input-Feldern bestueckt werden.
 * Dies geschieht Mittels der add*Field(...) Methoden.
 *
 * Nachdem alle Felder eingefuegt wurden, muss das Fomular mit get() oder show() ausgegeben werden.
 */
class Form extends AbstractForm
{
    use FactoryTrait;

    public const ERROR_VIOLATE_UNIQUE_KEY = 1062;

    /** @var non-empty-string */
    protected $tableName;
    /** @var string */
    protected $whereCondition;
    /** @var string */
    protected $mode;
    /** @var positive-int */
    protected $db;
    /** @var Sql */
    protected $sql;
    /** @var array */
    protected $languageSupport = [];

    /**
     * Diese Konstruktor sollte nicht verwendet werden. Instanzen muessen ueber die factory() Methode erstellt werden!
     *
     * @param non-empty-string $tableName
     * @param 'post'|'get' $method
     * @param positive-int $db DB connection ID
     */
    protected function __construct(string $tableName, string $fieldset, string $whereCondition, string $method = 'post', bool $debug = false, int $db = 1)
    {
        $name = md5($tableName . $whereCondition . $method);

        parent::__construct($fieldset, $name, $method, $debug);

        $this->tableName = $tableName;
        $this->whereCondition = $whereCondition;

        $this->db = $db;
        $this->sql = Sql::factory($db);
        $this->sql->setDebug($this->debug);
        $this->sql->setQuery('SELECT * FROM ' . $tableName . ' WHERE ' . $this->whereCondition . ' LIMIT 2');

        $this->setFormId('rex-addon-editmode');

        // --------- validate where-condition and determine editMode
        $numRows = $this->sql->getRows();
        if (0 == $numRows) {
            // Kein Datensatz gefunden => Mode: Add
            $this->setEditMode(false);
        } elseif (1 == $numRows) {
            // Ein Datensatz gefunden => Mode: Edit
            $this->setEditMode(true);
        } else {
            throw new LogicException('Form: Die gegebene Where-Bedingung führt nicht zu einem eindeutigen Datensatz!');
        }

        // --------- Load Env
        if (Core::isBackend()) {
            $this->loadBackendConfig();
        }
    }

    /**
     * Methode zum erstellen von Form Instanzen.
     *
     * @param non-empty-string $tableName
     * @param 'post'|'get' $method
     * @param positive-int $db DB connection ID
     */
    public static function factory(string $tableName, string $fieldset, string $whereCondition, string $method = 'post', bool $debug = false, int $db = 1): static
    {
        $class = static::getFactoryClass();
        return new $class($tableName, $fieldset, $whereCondition, $method, $debug, $db);
    }

    /** Laedt die Konfiguration die noetig ist um Form im REDAXO Backend zu verwenden. */
    protected function loadBackendConfig()
    {
        parent::loadBackendConfig();

        $func = Request::request('func', 'string');

        $this->addParam('func', $func);
        $this->addParam('list', Request::request('list', 'string'));

        $controlFields = [];
        $controlFields['save'] = I18n::msg('form_save');
        $controlFields['apply'] = 'edit' == $func ? I18n::msg('form_apply') : '';
        $controlFields['delete'] = 'edit' == $func ? I18n::msg('form_delete') : '';
        $controlFields['reset'] = ''; // I18n::msg('form_reset');
        $controlFields['abort'] = I18n::msg('form_abort');

        // ----- EXTENSION POINT
        $controlFields = Extension::registerPoint(new ExtensionPoint('REX_FORM_CONTROL_FIELDS', $controlFields, ['form' => $this]));

        $controlElements = [];
        foreach ($controlFields as $name => $label) {
            if ($label) {
                $attr = ['type' => 'submit', 'internal::useArraySyntax' => false, 'internal::fieldSeparateEnding' => true];

                if ('abort' === $name || 'delete' === $name) {
                    $attr['formnovalidate'] = 'formnovalidate';
                }
                if ('save' === $name) {
                    $attr['title'] = I18n::msg('save_and_close_tooltip');
                } elseif ('apply' === $name) {
                    $attr['title'] = I18n::msg('save_and_goon_tooltip');
                }
                $controlElements[$name] = $this->addField(
                    'button',
                    $name,
                    $label,
                    $attr,
                    false,
                );
            } else {
                $controlElements[$name] = null;
            }
        }

        $this->addControlField(
            $controlElements['save'],
            $controlElements['apply'],
            $controlElements['delete'],
            $controlElements['reset'],
            $controlElements['abort'],
        );
    }

    /**
     * Fuegt dem Formular ein Feld hinzu mitdem die Prioritaet von Datensaetzen verwaltet werden kann.
     *
     * @param string $name
     * @param mixed $value
     *
     * @return PriorityField
     */
    public function addPrioField($name, $value = null, array $attributes = [])
    {
        $attributes['internal::fieldClass'] = PriorityField::class;
        if (!isset($attributes['class'])) {
            $attributes['class'] = 'form-control';
        }
        $field = $this->addField('', $name, $value, $attributes, true);
        assert($field instanceof PriorityField);
        return $field;
    }

    /**
     * Gibt die Where-Bedingung des Formulars zurueck.
     *
     * @return string
     */
    public function getWhereCondition()
    {
        return $this->whereCondition;
    }

    /**
     * Mehrsprachigkeit unterstuetzen.
     *
     * @param string $idField
     * @param string $clangField
     * @return void
     */
    public function setLanguageSupport($idField, $clangField)
    {
        $this->languageSupport['id'] = $idField;
        $this->languageSupport['clang'] = $clangField;
    }

    /**
     * Wechselt den Modus des Formulars.
     *
     * @param bool $isEditMode
     * @return void
     */
    public function setEditMode($isEditMode)
    {
        if ($isEditMode) {
            $this->mode = 'edit';
        } else {
            $this->mode = 'add';
        }
    }

    /**
     * Prueft ob sich das Formular im Edit-Modus befindet.
     *
     * @return bool
     */
    public function isEditMode()
    {
        return 'edit' == $this->mode;
    }

    /** @return non-empty-string */
    public function getTableName()
    {
        return $this->tableName;
    }

    /** @return Sql */
    public function getSql()
    {
        return $this->sql;
    }

    protected function getId($name)
    {
        return $this->tableName . '_' . $this->fieldset . '_' . $name;
    }

    protected function getValue($name)
    {
        if (1 == $this->sql->getRows() && $this->sql->hasValue($name)) {
            return $this->sql->getValue($name);
        }

        return null;
    }

    /**
     * Callbackfunktion, damit in subklassen der Value noch beeinflusst werden kann
     * kurz vorm speichern.
     *
     * @param string $fieldsetName
     * @param string $fieldName
     * @param string|int|null $fieldValue
     *
     * @return string|int|null
     */
    protected function preSave($fieldsetName, $fieldName, $fieldValue, Sql $saveSql)
    {
        /** @var bool $setOnce */
        static $setOnce = false;

        if (!$setOnce) {
            $this->setGlobalSqlFields($saveSql);
            $setOnce = true;
        }

        return $fieldValue;
    }

    /** Sets the sql fields `updateuser`, `updatedate`, `createuser` and `createdate` (if available). */
    private function setGlobalSqlFields(Sql $saveSql): void
    {
        $fieldnames = $this->sql->getFieldnames();

        if (in_array('updateuser', $fieldnames)) {
            $saveSql->setValue('updateuser', Core::requireUser()->login);
        }

        if (in_array('updatedate', $fieldnames)) {
            $saveSql->setDateTimeValue('updatedate', time());
        }

        if (!$this->isEditMode()) {
            if (in_array('createuser', $fieldnames)) {
                $saveSql->setValue('createuser', Core::requireUser()->login);
            }

            if (in_array('createdate', $fieldnames)) {
                $saveSql->setDateTimeValue('createdate', time());
            }
        }
    }

    /**
     * @param object $form
     *
     * @return bool
     */
    public function equals($form)
    {
        return
            $form instanceof self
            && $this->getTableName() == $form->getTableName()
            && $this->getWhereCondition() == $form->getWhereCondition();
    }

    /**
     * Speichert das Formular.
     *
     * Übernimmt die Werte aus den FormElementen in die Datenbank.
     *
     * Gibt true zurück wenn alles ok war, false bei einem allgemeinen Fehler,
     * einen String mit einer Fehlermeldung oder den von der Datenbank gelieferten ErrorCode.
     *
     * @return bool|int
     */
    protected function save()
    {
        $sql = Sql::factory($this->db);
        $sql->setDebug($this->debug);
        $sql->setTable($this->tableName);

        $values = [];
        foreach ($this->getSaveElements() as $fieldsetName => $fieldsetElements) {
            foreach ($fieldsetElements as $element) {
                // read-only-fields nicht speichern
                if ($element->isReadOnly()) {
                    continue;
                }

                $fieldName = $element->getFieldName();
                $fieldValue = $element->getSaveValue();

                // Callback, um die Values vor dem Speichern noch beeinflussen zu können
                $fieldValue = $this->preSave($fieldsetName, $fieldName, $fieldValue, $sql);

                if (is_string($fieldValue)) {
                    $fieldValue = trim($fieldValue);
                }
                $values[$fieldName] = $fieldValue;
            }
        }

        try {
            if ($this->isEditMode()) {
                $sql->setValues($values);
                $sql->setWhere($this->whereCondition);
                $sql->update();
            } else {
                if (count($this->languageSupport)) {
                    foreach (Language::getAllIds() as $clangId) {
                        $sql->setTable($this->tableName);
                        $this->setGlobalSqlFields($sql);
                        if (!isset($id)) {
                            $id = $sql->setNewId($this->languageSupport['id']);
                        } else {
                            $sql->setValue($this->languageSupport['id'], $id);
                        }
                        $sql->setValue($this->languageSupport['clang'], $clangId);
                        $sql->setValues($values);
                        $sql->insert();
                    }
                } else {
                    $sql->setValues($values);
                    $sql->insert();
                }
            }
        } catch (SqlException $e) {
            $errno = $e->getErrorCode();
            if (null === $errno || !isset($this->errorMessages[$errno])) {
                throw $e;
            }
            return $errno;
        }

        // ----- EXTENSION POINT
        return Extension::registerPoint(new ExtensionPoint('REX_FORM_SAVED', true, ['form' => $this, 'sql' => $sql]));
    }

    /** @return bool|int */
    protected function delete()
    {
        $deleteSql = Sql::factory($this->db);
        $deleteSql->setDebug($this->debug);
        $deleteSql->setTable($this->tableName);
        $deleteSql->setWhere($this->whereCondition);

        try {
            $deleteSql->delete();
        } catch (SqlException $e) {
            $errno = $e->getErrorCode();
            if (null === $errno || !isset($this->errorMessages[$errno])) {
                throw $e;
            }
            return $errno;
        }

        // ----- EXTENSION POINT
        return Extension::registerPoint(new ExtensionPoint('REX_FORM_DELETED', true, ['form' => $this, 'sql' => $deleteSql]));
    }
}
