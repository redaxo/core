<?php

namespace Redaxo\Core\MetaInfo\Handler;

use PDO;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Exception\LogicException;
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;
use Redaxo\Core\Form\Select\Select;
use Redaxo\Core\Http\Request;
use Redaxo\Core\MetaInfo\Database\Table;
use Redaxo\Core\MetaInfo\Form\Input\ArticleInput;
use Redaxo\Core\MetaInfo\Form\Input\DateInput;
use Redaxo\Core\MetaInfo\Form\Input\DateTimeInput;
use Redaxo\Core\MetaInfo\Form\Input\MediaInput;
use Redaxo\Core\MetaInfo\Form\Input\TextareaInput;
use Redaxo\Core\MetaInfo\Form\Input\TextInput;
use Redaxo\Core\MetaInfo\Form\Input\TimeInput;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Str;
use Redaxo\Core\View\Fragment;

use function count;
use function in_array;
use function is_int;
use function Redaxo\Core\View\escape;

/**
 * @internal
 */
abstract class AbstractHandler
{
    /**
     * Erstellt den nötigen HTML Code um ein Formular zu erweitern.
     *
     * @param Sql $sqlFields Sql-objekt, dass die zu verarbeitenden Felder enthält
     * @param array $epParams Array of all EP parameters
     *
     * @return string
     */
    public function renderMetaFields(Sql $sqlFields, array $epParams)
    {
        $s = '';

        // Startwert für MEDIABUTTON, MEDIALIST, LINKLIST zähler
        $mediaId = 1;
        $linkId = 1;

        $activeItem = $epParams['activeItem'] ?? null;

        $sqlFields->reset();
        for ($i = 0; $i < $sqlFields->getRows(); $i++, $sqlFields->next()) {
            // Umschliessendes Tag von Label und Formularelement
            $tag = 'p';
            $tagAttr = '';

            $name = (string) $sqlFields->getValue('name');
            $title = (string) $sqlFields->getValue('title');
            /** @psalm-taint-escape sql */ // it is intended that admins can paste sql queries inside this field
            $params = (string) $sqlFields->getValue('params');
            $typeLabel = (string) $sqlFields->getValue('label');
            $attr = (string) $sqlFields->getValue('attributes');
            $dblength = (int) $sqlFields->getValue('dblength');

            $attrArray = Str::split($attr);
            if (isset($attrArray['perm'])) {
                if (!Core::requireUser()->hasPerm($attrArray['perm'])) {
                    continue;
                }
                unset($attrArray['perm']);
            }

            $note = null;
            if (isset($attrArray['note'])) {
                $note = I18n::translate($attrArray['note']);
                unset($attrArray['note']);
            }

            // `Str::split` transforms attributes without value (like `disabled`, `data-foo` etc.) to an int based array element
            // we transform them to array elements with the attribute name as key and empty value
            foreach ($attrArray as $key => $value) {
                if (is_int($key)) {
                    unset($attrArray[$key]);
                    $attrArray[$value] = '';
                }
            }

            $defaultValue = (string) $sqlFields->getValue('default');
            if ($activeItem) {
                $itemValue = $activeItem->getValue($name);

                if ($itemValue && str_contains($itemValue, '|+|')) {
                    // Alte notation mit |+| als Trenner
                    $dbvalues = explode('|+|', $itemValue);
                } else {
                    // Neue Notation mit | als Trenner
                    $dbvalues = explode('|', trim((string) $itemValue, '|'));
                }
            } else {
                $dbvalues = (array) $sqlFields->getValue('default');
            }

            if ('' != $title) {
                $label = I18n::translate($title);
            } else {
                $label = escape($name);
            }

            $id = 'rex-metainfo-' . escape(preg_replace('/[^a-zA-Z0-9_-]/', '_', $name));
            $labelIt = true;

            $label = '<label for="' . $id . '">' . $label . '</label>';

            $field = '';

            switch ($typeLabel) {
                case 'text':
                    $tagAttr = ' class="form-control"';

                    $rexInput = new TextInput();
                    $rexInput->addAttributes($attrArray);
                    $rexInput->setAttribute('id', $id);
                    $rexInput->setAttribute('name', $name);
                    if ($dblength > 0) {
                        $rexInput->setAttribute('maxlength', $dblength);
                    }
                    if ($activeItem) {
                        $rexInput->setValue($activeItem->getValue($name));
                    } else {
                        $rexInput->setValue($defaultValue);
                    }
                    $field = $rexInput->getHtml();

                    $e = [];
                    $e['label'] = $label;
                    $e['field'] = $field;
                    $e['note'] = $note;
                    $fragment = new Fragment();
                    $fragment->setVar('elements', [$e], false);
                    $field = $fragment->parse('core/form/form.php');

                    break;
                case 'checkbox':
                    // Beachte auch default values in multiple fields bei ADD.
                    // Im EDIT wurde dies bereits vorher gehandelt
                    if (!$activeItem) {
                        $defaultValue = explode('|', $defaultValue);
                    }

                    $name .= '[]';
                    // no break
                case 'radio':
                    $formElements = [];

                    $values = [];
                    if ('SELECT' == Sql::getQueryType($params)) {
                        $sql = Sql::factory();
                        $valueGroups = $sql->getDBArray($params, [], PDO::FETCH_NUM);
                        foreach ($valueGroups as $valueGroup) {
                            $key = $valueGroup[1] ?? $valueGroup[0];
                            $key = is_int($key) ? $key : (string) $key;
                            $values[$key] = (string) $valueGroup[0];
                        }
                    } else {
                        $valueGroups = explode('|', $params);
                        foreach ($valueGroups as $valueGroup) {
                            // check ob key:value paar
                            // und der wert beginnt nicht mit "translate:"
                            if (str_contains($valueGroup, ':') && !str_starts_with($valueGroup, 'translate:')) {
                                $temp = explode(':', $valueGroup, 2);
                                $values[$temp[0]] = I18n::translate($temp[1]);
                            } else {
                                $values[$valueGroup] = I18n::translate($valueGroup);
                            }
                        }
                    }

                    $oneValue = (1 == count($values));

                    $inline = isset($attrArray['inline']);
                    unset($attrArray['inline']);

                    $attrStr = Str::buildAttributes($attrArray);

                    if (!$activeItem) {
                        $dbvalues = (array) $defaultValue;
                    }

                    foreach ($values as $key => $value) {
                        // wenn man keine Werte angibt (Boolean Chkbox/Radio)
                        // Dummy Wert annehmen, damit an/aus unterscheidung funktioniert
                        if ($oneValue && '' == $key) {
                            $key = 'true';
                        }

                        $selected = '';
                        if (in_array((string) $key, $dbvalues, true)) {
                            $selected = ' checked="checked"';
                        }

                        $currentId = $id;

                        $e = [];
                        if ($oneValue) {
                            $e['label'] = $label;
                        } else {
                            $currentId .= '-' . escape((string) preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $key));
                            $e['label'] = '<label for="' . $currentId . '">' . escape($value) . '</label>';
                        }
                        $e['field'] = '<input type="' . escape($typeLabel) . '" name="' . escape($name) . '" value="' . escape($key) . '" id="' . $currentId . '" ' . $attrStr . $selected . ' />';
                        $e['note'] = $oneValue ? $note : null;
                        $formElements[] = $e;
                    }

                    $fragment = new Fragment();
                    $fragment->setVar('elements', $formElements, false);
                    $fragment->setVar('inline', $inline);

                    if ('radio' == $typeLabel) {
                        $field = $fragment->parse('core/form/radio.php');
                    } else {
                        if (!$oneValue) {
                            $fragment->setVar('grouped', true);
                        }
                        $field = $fragment->parse('core/form/checkbox.php');
                    }

                    if (!$oneValue) {
                        $e = [];
                        $e['label'] = $label;
                        $e['field'] = $field;
                        $e['note'] = $note;
                        $fragment = new Fragment();
                        $fragment->setVar('elements', [$e], false);
                        $field = $fragment->parse('core/form/form.php');
                    }

                    break;
                case 'select':
                    $tagAttr = ' class="form-control"';

                    $select = new Select();
                    $select->setStyle('class="form-control selectpicker"');
                    $select->setName($name);
                    $select->setId($id);

                    $multiple = false;
                    foreach ($attrArray as $attrName => $attrValue) {
                        $select->setAttribute($attrName, $attrValue);

                        if ('multiple' == $attrName) {
                            $multiple = true;
                            $select->setName($name . '[]');
                            $select->setMultiple();
                        }
                    }

                    // Beachte auch default values in multiple fields bei ADD.
                    // Im EDIT wurde dies bereits vorher gehandelt
                    if ($multiple && !$activeItem) {
                        $dbvalues = explode('|', $defaultValue);
                    }

                    // hier mit den "raw"-values arbeiten, da die Select klasse selbst escaped
                    $select->setSelected($dbvalues);

                    if ('SELECT' == Sql::getQueryType($params)) {
                        // Werte via SQL Laden
                        $select->addDBSqlOptions($params);
                    } else {
                        // Optionen mit | separiert
                        // eine einzelne Option kann mit key:value separiert werden
                        $values = [];
                        $valueGroups = explode('|', $params);
                        foreach ($valueGroups as $valueGroup) {
                            // check ob key:value paar
                            // und der wert beginnt nicht mit "translate:"
                            if (str_contains($valueGroup, ':') && !str_starts_with($valueGroup, 'translate:')) {
                                $temp = explode(':', $valueGroup, 2);
                                $values[$temp[0]] = I18n::translate($temp[1]);
                            } else {
                                $values[$valueGroup] = I18n::translate($valueGroup);
                            }
                        }
                        $select->addOptions($values);
                    }

                    $field .= $select->get();

                    $e = [];
                    $e['label'] = $label;
                    $e['field'] = $field;
                    $e['note'] = $note;
                    $fragment = new Fragment();
                    $fragment->setVar('elements', [$e], false);
                    $field = $fragment->parse('core/form/form.php');

                    break;
                case 'date':
                case 'time':
                case 'datetime':
                    if ('date' == $typeLabel) {
                        $rexInput = new DateInput();
                    } elseif ('time' == $typeLabel) {
                        $rexInput = new TimeInput();
                    } elseif ('datetime' == $typeLabel) {
                        $rexInput = new DateTimeInput();
                    } else {
                        throw new LogicException('Unexpected $typeLabel "' . $typeLabel . '"');
                    }
                    $tagAttr = ' class="form-control-date"';

                    $active = (bool) $dbvalues[0];
                    if ('' == $dbvalues[0]) {
                        $dbvalues[0] = time();
                    }

                    $timestamp = (int) $dbvalues[0];
                    $inputValue = [];
                    $inputValue['year'] = date('Y', $timestamp);
                    $inputValue['month'] = date('m', $timestamp);
                    $inputValue['day'] = date('d', $timestamp);
                    $inputValue['hour'] = date('H', $timestamp);
                    $inputValue['minute'] = date('i', $timestamp);

                    $rexInput->addAttributes($attrArray);
                    $rexInput->setAttribute('id', $id);
                    $rexInput->setAttribute('name', $name);
                    $rexInput->setValue($inputValue);

                    if (!$rexInput instanceof TimeInput) {
                        $paramArray = Str::split($params);

                        if (isset($paramArray['start-year'])) {
                            $rexInput->setStartYear((int) $paramArray['start-year']);
                        }
                        if (isset($paramArray['end-year'])) {
                            $rexInput->setEndYear((int) $paramArray['end-year']);
                        }
                    }

                    $field = $rexInput->getHtml();

                    $checked = $active ? ' checked="checked"' : '';
                    $field .= '<input class="rex-metainfo-checkbox" type="checkbox" name="' . escape($name) . '[active]" value="1"' . $checked . ' />';

                    $e = [];
                    $e['label'] = $label;
                    $e['field'] = $field;
                    $e['note'] = $note;
                    $fragment = new Fragment();
                    $fragment->setVar('elements', [$e], false);
                    $field = $fragment->parse('core/form/form.php');

                    break;
                case 'textarea':
                    $tagAttr = ' class="form-control"';

                    $rexInput = new TextareaInput();
                    $rexInput->addAttributes($attrArray);
                    $rexInput->setAttribute('id', $id);
                    $rexInput->setAttribute('name', $name);
                    if ($activeItem) {
                        $rexInput->setValue($activeItem->getValue($name));
                    } else {
                        $rexInput->setValue($defaultValue);
                    }
                    $field = $rexInput->getHtml();

                    $e = [];
                    $e['label'] = $label;
                    $e['field'] = $field;
                    $e['note'] = $note;
                    $fragment = new Fragment();
                    $fragment->setVar('elements', [$e], false);
                    $field = $fragment->parse('core/form/form.php');

                    break;
                case 'legend':
                    $tag = '';
                    $tagAttr = '';
                    $labelIt = false;

                    // tabindex entfernen, macht bei einer legend wenig sinn
                    unset($attrArray['tabindex']);

                    $attrStr = Str::buildAttributes($attrArray);

                    $field = '</fieldset><fieldset><legend id="' . $id . '"' . $attrStr . '>' . $label . '</legend>';
                    break;
                case 'REX_MEDIA_WIDGET':
                    $tag = 'div';
                    $tagAttr = ' class="rex-form-widget"';

                    $paramArray = Str::split($params);

                    $rexInput = new MediaInput();
                    $rexInput->addAttributes($attrArray);
                    $rexInput->setButtonId($mediaId);
                    $rexInput->setAttribute('name', $name);
                    if ($rexInput->hasAttribute('multiple')) {
                        $rexInput->setMultiple();
                        $rexInput->setValue(implode(',', $dbvalues));
                    } else {
                        $rexInput->setValue($dbvalues[0]);
                    }

                    if (isset($paramArray['category'])) {
                        $rexInput->setCategoryId((int) $paramArray['category']);
                    }
                    if (isset($paramArray['types'])) {
                        $rexInput->setTypes($paramArray['types']);
                    }
                    if (isset($paramArray['preview'])) {
                        $rexInput->setPreview((bool) $paramArray['preview']);
                    }

                    $id = (string) $rexInput->getAttribute('id');
                    $field = $rexInput->getHtml();

                    $e = [];
                    $e['label'] = $label;
                    $e['field'] = $field;
                    $e['note'] = $note;
                    $fragment = new Fragment();
                    $fragment->setVar('elements', [$e], false);
                    $field = $fragment->parse('core/form/form.php');

                    ++$mediaId;
                    break;
                case 'REX_LINK_WIDGET':
                    $tag = 'div';
                    $tagAttr = ' class="rex-form-widget"';

                    $paramArray = Str::split($params);
                    $category = null;
                    if (isset($paramArray['category'])) {
                        $category = $paramArray['category'];
                    } elseif ($activeItem) {
                        $category = $activeItem->getValue('category_id');
                    }

                    $rexInput = new ArticleInput();
                    $rexInput->addAttributes($attrArray);
                    $rexInput->setButtonId($linkId);
                    $rexInput->setCategoryId($category ? (int) $category : null);
                    $rexInput->setAttribute('name', $name);
                    if ($rexInput->hasAttribute('multiple')) {
                        $rexInput->setMultiple();
                        $rexInput->setValue(implode(',', $dbvalues));
                    } else {
                        $rexInput->setValue($dbvalues[0]);
                    }
                    $id = (string) $rexInput->getAttribute('id');
                    $field = $rexInput->getHtml();

                    $e = [];
                    $e['label'] = $label;
                    $e['field'] = $field;
                    $e['note'] = $note;
                    $fragment = new Fragment();
                    $fragment->setVar('elements', [$e], false);
                    $field = $fragment->parse('core/form/form.php');

                    ++$linkId;
                    break;
                default:
                    // ----- EXTENSION POINT
                    [$field, $tag, $tagAttr, $id, $label, $labelIt] =
                        Extension::dispatch(new ExtensionPoint(
                            'METAINFO_CUSTOM_FIELD',
                            [
                                $field,
                                $tag,
                                $tagAttr,
                                $id,
                                $label,
                                $labelIt,
                                'values' => $dbvalues,
                                'rawvalues' => $dbvalues,
                                'type' => $typeLabel,
                                'sql' => $sqlFields,
                            ],
                        ));
            }

            $s .= $this->renderFormItem($field, $tag, $tagAttr, $id, $label, $labelIt, $typeLabel);
        }

        return $s;
    }

    /**
     * Übernimmt die gePOSTeten werte in ein Sql-Objekt.
     *
     * @param array $params
     * @param Sql $sqlSave Sql-objekt, in das die aktuellen Werte gespeichert werden sollen
     * @param Sql $sqlFields Sql-objekt, dass die zu verarbeitenden Felder enthält
     * @return void
     */
    public static function fetchRequestValues(&$params, &$sqlSave, $sqlFields)
    {
        if ('post' != Request::requestMethod()) {
            return;
        }

        for ($i = 0; $i < $sqlFields->getRows(); $i++, $sqlFields->next()) {
            $fieldName = (string) $sqlFields->getValue('name');
            $fieldType = (int) $sqlFields->getValue('type_id');
            $fieldAttributes = (string) $sqlFields->getValue('attributes');

            // dont save restricted fields
            $attrArray = Str::split($fieldAttributes);
            if (isset($attrArray['perm'])) {
                if (!Core::requireUser()->hasPerm($attrArray['perm'])) {
                    continue;
                }
                unset($attrArray['perm']);
            }

            // Wert in SQL zum speichern
            $saveValue = self::getSaveValue($fieldName, $fieldType, $fieldAttributes);
            $sqlSave->setValue($fieldName, $saveValue);

            // Werte im aktuellen Objekt speichern, dass zur Anzeige verwendet wird
            if (isset($params['activeItem'])) {
                $params['activeItem']->setValue($fieldName, $saveValue);
            }
        }
    }

    /**
     * Retrieves the posted value for the given field and converts it into a saveable format.
     *
     * @param string $fieldName The name of the field
     * @param int $fieldType One of the Table::FIELD_* constants
     * @param string $fieldAttributes The attributes of the field
     *
     * @return string|int|null
     */
    public static function getSaveValue($fieldName, $fieldType, $fieldAttributes)
    {
        if ('post' != Request::requestMethod()) {
            return null;
        }

        $postValue = Request::post($fieldName, 'array');

        // handle date types with timestamps
        if (isset($postValue['year']) && isset($postValue['month']) && isset($postValue['day']) && isset($postValue['hour']) && isset($postValue['minute'])) {
            if (isset($postValue['active'])) {
                $saveValue = mktime((int) $postValue['hour'], (int) $postValue['minute'], 0, (int) $postValue['month'], (int) $postValue['day'], (int) $postValue['year']);
            } else {
                $saveValue = 0;
            }
        }
        // handle date types without timestamps
        elseif (isset($postValue['year']) && isset($postValue['month']) && isset($postValue['day'])) {
            if (isset($postValue['active'])) {
                $saveValue = mktime(0, 0, 0, (int) $postValue['month'], (int) $postValue['day'], (int) $postValue['year']);
            } else {
                $saveValue = 0;
            }
        }
        // handle time types
        elseif (isset($postValue['hour']) && isset($postValue['minute'])) {
            if (isset($postValue['active'])) {
                $saveValue = mktime((int) $postValue['hour'], (int) $postValue['minute'], 0, 0, 0, 0);
            } else {
                $saveValue = 0;
            }
        } else {
            if (count($postValue) > 1) {
                // Mehrwertige Felder
                $saveValue = '|' . implode('|', $postValue) . '|';
            } else {
                $postValue = $postValue[0] ?? '';
                if (
                    Table::FIELD_SELECT == $fieldType && str_contains($fieldAttributes, 'multiple')
                    || Table::FIELD_CHECKBOX == $fieldType
                ) {
                    // Mehrwertiges Feld, aber nur ein Wert ausgewählt
                    $saveValue = '|' . $postValue . '|';
                } else {
                    // Einwertige Felder
                    $saveValue = $postValue;
                }
            }
        }

        return $saveValue;
    }

    /**
     * Ermittelt die metainfo felder mit dem Prefix $prefix limitiert auf die Kategorien $restrictions.
     *
     * @param string $prefix Feldprefix
     * @param string $filterCondition SQL Where-Bedingung zum einschränken der Metafelder
     *
     * @return Sql Metainfofelder
     */
    protected static function getSqlFields($prefix, $filterCondition = '')
    {
        $sqlFields = Sql::factory();
        $prefix = $sqlFields->escape($sqlFields->escapeLikeWildcards($prefix) . '%');

        $qry = 'SELECT
                            *
                        FROM
                            ' . Core::getTablePrefix() . 'metainfo_field p,
                            ' . Core::getTablePrefix() . 'metainfo_type t
                        WHERE
                            `p`.`type_id` = `t`.`id` AND
                            `p`.`name` LIKE ' . $prefix . '
                            ' . $filterCondition . '
                            ORDER BY
                            priority';

        // $sqlFields->setDebug();
        $sqlFields->setQuery($qry);

        return $sqlFields;
    }

    /**
     * Erweitert das Meta-Formular um die neuen Meta-Felder.
     *
     * @param string $prefix Feldprefix
     * @param array $params EP Params
     *
     * @return string
     */
    public function renderFormAndSave($prefix, array $params)
    {
        $filterCondition = $this->buildFilterCondition($params);
        $sqlFields = static::getSqlFields($prefix, $filterCondition);
        $params = $this->handleSave($params, $sqlFields);

        return self::renderMetaFields($sqlFields, $params);
    }

    /**
     * Build a SQL Filter String which fits for the current context params.
     *
     * @param array $params EP Params
     * @return string
     */
    abstract protected function buildFilterCondition(array $params);

    /**
     * Renders a field of the metaform. The rendered html will be returned.
     *
     * @param string $field The html-source of the field itself
     * @param string $tag The html-tag for the elements container, e.g. "p"
     * @param string $tagAttr Attributes for the elements container, e.g. " class='rex-widget'"
     * @param string $id The id of the field, used for current label or field-specific javascripts
     * @param string $label The textlabel of the field
     * @param bool $labelIt True when an additional label needs to be rendered, otherweise False
     * @param string $inputType The input type, e.g. "checkbox", "radio",..
     *
     * @return string The rendered html
     */
    abstract protected function renderFormItem($field, $tag, $tagAttr, $id, $label, $labelIt, $inputType);

    /**
     * Retrieves the activeItem from the current context.
     * Afterwards the actual metaForm extension will be rendered.
     *
     * @return string
     */
    abstract public function extendForm(ExtensionPoint $ep);

    /**
     * Retrieves the POST values from the metaform, fill it into a Sql object and save it to a database table.
     * @return array
     */
    abstract protected function handleSave(array $params, Sql $sqlFields);
}
