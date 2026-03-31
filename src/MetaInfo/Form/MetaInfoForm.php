<?php

namespace Redaxo\Core\MetaInfo\Form;

use Redaxo\Core\Cache;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Util;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Form\Form;
use Redaxo\Core\Form\Select\CategorySelect;
use Redaxo\Core\Form\Select\MediaCategorySelect;
use Redaxo\Core\Form\Select\TemplateSelect;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Language\Language;
use Redaxo\Core\MetaInfo\Database\Table;
use Redaxo\Core\MetaInfo\Form\Field\RestrictionField;
use Redaxo\Core\MetaInfo\Handler\ArticleHandler;
use Redaxo\Core\MetaInfo\Handler\CategoryHandler;
use Redaxo\Core\MetaInfo\Handler\LanguageHandler;
use Redaxo\Core\MetaInfo\Handler\MediaHandler;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Str;
use Redaxo\Core\Validator\ValidationRule;

use function assert;
use function strlen;

/**
 * @internal
 */
class MetaInfoForm extends Form
{
    private string $metaPrefix;
    private Table $tableManager;

    /** @param 'post'|'get' $method */
    public function __construct(string $metaPrefix, string $metaTable, string $tableName, string $whereCondition, string $method = 'post', bool $debug = false)
    {
        $this->metaPrefix = $metaPrefix;
        $this->tableManager = new Table($metaTable);

        parent::__construct($tableName, I18n::msg('minfo_field_fieldset'), $whereCondition, $method, $debug);
    }

    public function init()
    {
        $sql = Sql::factory();

        // ----- EXTENSION POINT
        // IDs aller Feldtypen bei denen das Parameter-Feld eingeblendet werden soll
        $typeFields = Extension::registerPoint(new ExtensionPoint('METAINFO_TYPE_FIELDS', [Table::FIELD_SELECT, Table::FIELD_RADIO, Table::FIELD_CHECKBOX, Table::FIELD_REX_MEDIA_WIDGET, Table::FIELD_REX_LINK_WIDGET, Table::FIELD_DATE, Table::FIELD_DATETIME]));

        $field = $this->addReadOnlyField('prefix', $this->metaPrefix);
        $field->setLabel(I18n::msg('minfo_field_label_prefix'));

        $field = $this->addTextField('name');
        $field->setLabel(I18n::msg('minfo_field_label_name'));
        $field->disableSpellcheckAndAutoCorrect();
        $field->getValidator()
            ->add(ValidationRule::NOT_EMPTY, I18n::msg('minfo_field_error_name'))
            ->add(ValidationRule::MAX_LENGTH, null, 255)
        ;

        $field = $this->addSelectField('priority');
        $field->setLabel(I18n::msg('minfo_field_label_priority'));
        $field->setAttribute('class', 'form-control selectpicker');
        $select = $field->getSelect();
        $select->setSize(1);
        $select->addOption(I18n::msg('minfo_field_first_priority'), 1);
        // Im Edit Mode das Feld selbst nicht als Position einfuegen
        $qry = 'SELECT name,priority FROM ' . $sql->escapeIdentifier($this->tableName) . ' WHERE `name` LIKE :name';
        $params = ['name' => $this->metaPrefix . '%'];
        if ($this->isEditMode()) {
            $qry .= ' AND id != :id';
            $params['id'] = $this->getParam('field_id');
        }
        $qry .= ' ORDER BY priority';
        $sql->setQuery($qry, $params);
        $value = 1;
        for ($i = 0; $i < $sql->getRows(); ++$i) {
            $value = (int) $sql->getValue('priority') + 1;
            $select->addOption(
                I18n::rawMsg('minfo_field_after_priority', (string) $sql->getValue('name')),
                $value,
            );
            $sql->next();
        }
        if (!$this->isEditMode()) {
            $select->setSelected($value);
        }

        $field = $this->addTextField('title');
        $field->setLabel(I18n::msg('minfo_field_label_title'));
        $field->setNotice(I18n::msg('minfo_field_notice_title'));
        $field->getValidator()
            ->add(ValidationRule::NOT_EMPTY)
            ->add(ValidationRule::MAX_LENGTH, null, 255)
        ;

        $gq = Sql::factory();
        $gq->setQuery('SELECT dbtype,id FROM ' . Core::getTablePrefix() . 'metainfo_type');
        $textFields = [];
        foreach ($gq->getArray() as $f) {
            if ('text' == $f['dbtype']) {
                $textFields[(int) $f['id']] = (int) $f['id'];
            }
        }

        $field = $this->addSelectField('type_id');
        $field->setLabel(I18n::msg('minfo_field_label_type'));
        $field->setAttribute('class', 'form-control selectpicker');
        $field->setAttribute('onchange', 'meta_checkConditionalFields(this, new Array(' . implode(',', $typeFields) . '), new Array(' . implode(',', $textFields) . '));');
        $select = $field->getSelect();
        $select->setSize(1);

        $qry = 'SELECT label,id FROM ' . Core::getTablePrefix() . 'metainfo_type';
        $select->addSqlOptions($qry);

        $notices = '';
        for ($i = 1; $i < Table::FIELD_COUNT; ++$i) {
            if (I18n::hasMsg('minfo_field_params_notice_' . $i)) {
                $notices .= '<span id="metainfo-field-params-notice-' . $i . '" style="display:none">' . I18n::msg('minfo_field_params_notice_' . $i) . '</span>' . "\n";
            }
        }
        $notices .= '
        <script type="text/javascript" nonce="' . Response::getNonce() . '">
            var needle = new getObj("' . $field->getAttribute('id') . '");
            meta_checkConditionalFields(needle.obj, new Array(' . implode(',', $typeFields) . '), new Array(' . implode(',', $textFields) . '));
        </script>';

        $field = $this->addTextAreaField('params');
        $field->setLabel(I18n::msg('minfo_field_label_params'));
        $field->setNotice($notices);
        $field->disableSpellcheckAndAutoCorrect();

        $field = $this->addTextAreaField('attributes');
        $field->setLabel(I18n::msg('minfo_field_label_attributes'));
        $notice = I18n::msg('minfo_field_attributes_notice') . "\n";
        $field->setNotice($notice);
        $field->disableSpellcheckAndAutoCorrect();

        $field = $this->addTextField('default');
        $field->setLabel(I18n::msg('minfo_field_label_default'));
        $field->getValidator()->add(ValidationRule::MAX_LENGTH, null, 255);

        if (LanguageHandler::PREFIX !== $this->metaPrefix) {
            $field = $this->addRestrictionsField('restrictions');
            $field->setLabel(I18n::msg('minfo_field_label_restrictions'));
            $field->setAllCheckboxLabel(I18n::msg('minfo_field_label_no_restrictions'));

            if (ArticleHandler::PREFIX == $this->metaPrefix || CategoryHandler::PREFIX == $this->metaPrefix) {
                $field->setSelect(new CategorySelect(false, false, true, false));
            } elseif (MediaHandler::PREFIX == $this->metaPrefix) {
                $field->setSelect(new MediaCategorySelect());
            } else {
                throw new LogicException('Unexpected TablePrefix "' . $this->metaPrefix . '".');
            }
        }

        if (ArticleHandler::PREFIX === $this->metaPrefix) {
            $field = $this->addRestrictionsField('templates');
            $field->setLabel(I18n::msg('minfo_field_label_templates'));
            $field->setAllCheckboxLabel(I18n::msg('minfo_field_label_all_templates'));
            $field->setSelect(new TemplateSelect(null, Language::getCurrentId()));
        }

        parent::init();
    }

    private function addRestrictionsField(string $name): RestrictionField
    {
        /** @var RestrictionField $field */
        $field = $this->addField('', $name, null, ['internal::fieldClass' => RestrictionField::class]);
        $field->setAttribute('size', 10);
        $field->setAttribute('class', 'form-control');

        return $field;
    }

    protected function delete()
    {
        // Infos zuerst selektieren, da nach parent::delete() nicht mehr in der db
        $sql = Sql::factory();
        $sql->setDebug($this->debug);
        $sql->setTable($this->tableName);
        $sql->setWhere($this->whereCondition);
        $sql->select('name');
        $columnName = (string) $sql->getValue('name');

        if (true === ($result = parent::delete())) {
            // Prios neu setzen, damit keine lücken entstehen
            $this->organizePriorities(1, 2);
            return $this->tableManager->deleteColumn($columnName);
        }

        return $result;
    }

    protected function preSave($fieldsetName, $fieldName, $fieldValue, Sql $saveSql)
    {
        if ($fieldsetName == $this->getFieldsetName() && 'name' == $fieldName) {
            // Den Namen mit Prefix speichern
            return $this->addPrefix((string) $fieldValue);
        }

        return parent::preSave($fieldsetName, $fieldName, $fieldValue, $saveSql);
    }

    protected function preView($fieldsetName, $fieldName, $fieldValue)
    {
        if ($fieldsetName == $this->getFieldsetName() && 'name' == $fieldName) {
            // Den Namen ohne Prefix anzeigen
            return $this->stripPrefix((string) $fieldValue);
        }
        return parent::preView($fieldsetName, $fieldName, $fieldValue);
    }

    public function addPrefix(string $string): string
    {
        $lowerString = strtolower($string);
        if (!str_starts_with($lowerString, $this->metaPrefix)) {
            return $this->metaPrefix . $string;
        }
        return $string;
    }

    public function stripPrefix(string $string): string
    {
        $lowerString = strtolower($string);
        if (str_starts_with($lowerString, $this->metaPrefix)) {
            return substr($string, strlen($this->metaPrefix));
        }
        return $string;
    }

    /** @return bool|string */
    protected function validate()
    {
        $fieldName = $this->elementPostValue($this->getFieldsetName(), 'name');
        if (!$fieldName) {
            return I18n::msg('minfo_field_error_name');
        }

        if (preg_match('/[^a-zA-Z0-9\_]/', $fieldName)) {
            return I18n::msg('minfo_field_error_chars_name');
        }

        // Pruefen ob schon eine Spalte mit dem Namen existiert (nur beim add noetig)
        if (!$this->isEditMode()) {
            // die tabelle selbst checken
            if ($this->tableManager->hasColumn($this->addPrefix($fieldName))) {
                return I18n::msg('minfo_field_error_unique_name');
            }

            // das meta-schema checken
            $sql = Sql::factory();
            $sql->setQuery('SELECT * FROM ' . $sql->escapeIdentifier($this->tableName) . ' WHERE name = ? LIMIT 1', [$this->addPrefix($fieldName)]);
            if (1 == $sql->getRows()) {
                return I18n::msg('minfo_field_error_unique_name');
            }
        }

        return parent::validate();
    }

    protected function save()
    {
        $fieldName = $this->elementPostValue($this->getFieldsetName(), 'name');
        assert(null !== $fieldName);

        // Den alten Wert aus der DB holen
        // Dies muss hier geschehen, da in parent::save() die Werte fuer die DB mit den
        // POST werten ueberschrieben werden!
        $fieldOldName = '';
        $fieldOldPriority = 9_999_999_999_999; // dirty, damit die prio richtig laeuft...
        if (1 == $this->sql->getRows()) {
            $fieldOldName = (string) $this->sql->getValue('name');
            $fieldOldPriority = (int) $this->sql->getValue('priority');
        }

        if (parent::save()) {
            $this->organizePriorities((int) $this->elementPostValue($this->getFieldsetName(), 'priority'), $fieldOldPriority);

            $fieldName = $this->addPrefix($fieldName);
            $fieldType = (int) $this->elementPostValue($this->getFieldsetName(), 'type_id');
            $fieldDefault = $this->elementPostValue($this->getFieldsetName(), 'default');
            $fieldAttributes = $this->elementPostValue($this->getFieldsetName(), 'attributes');

            $sql = Sql::factory();
            $sql->setDebug($this->debug);
            $result = $sql->getArray('SELECT `dbtype`, `dblength` FROM `' . Core::getTablePrefix() . 'metainfo_type` WHERE id = ?', [$fieldType]);
            $fieldDbType = (string) $result[0]['dbtype'];
            $fieldDbLength = (int) $result[0]['dblength'];

            if (
                strlen($fieldDefault)
                && (Table::FIELD_CHECKBOX === $fieldType || Table::FIELD_SELECT === $fieldType && isset(Str::split($fieldAttributes)['multiple']))
            ) {
                $fieldDefault = '|' . trim($fieldDefault, '|') . '|';
            }

            if ($this->isEditMode()) {
                // Spalte in der Tabelle veraendern
                $tmRes = $this->tableManager->editColumn($fieldOldName, $fieldName, $fieldDbType, $fieldDbLength, $fieldDefault);
            } else {
                // Spalte in der Tabelle anlegen
                $tmRes = $this->tableManager->addColumn($fieldName, $fieldDbType, $fieldDbLength, $fieldDefault);
            }
            Cache::delete();

            return $tmRes;
        }

        return false;
    }

    public function getPrefix(): string
    {
        return $this->metaPrefix;
    }

    protected function organizePriorities(int $newPrio, int $oldPrio): void
    {
        if ($newPrio == $oldPrio) {
            return;
        }

        $sql = Sql::factory();
        $metaPrefix = $sql->escape($sql->escapeLikeWildcards($this->metaPrefix) . '%');

        Util::organizePriorities(
            $this->tableName,
            'priority',
            'name LIKE ' . $metaPrefix,
            'priority, updatedate desc',
        );
    }
}
