<?php

namespace Redaxo\Core\Cronjob\Form;

use Redaxo\Core\Cronjob\CronjobManager;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Form\Form;

/**
 * @internal
 */
final class CronjobForm extends Form
{
    private readonly string $mainFieldset;
    private ?IntervalField $intervalField = null;

    /**
     * @param non-empty-string $tableName
     * @param 'post'|'get' $method
     * @param positive-int $db DB connection ID
     */
    public function __construct(string $tableName, string $fieldset, string $whereCondition, string $method = 'post', bool $debug = false, int $db = 1)
    {
        parent::__construct($tableName, $fieldset, $whereCondition, $method, $debug, $db);
        $this->mainFieldset = $fieldset;
    }

    /**
     * @param string $name
     *
     * @return IntervalField
     */
    public function addIntervalField($name, $value = null, $attributes = [])
    {
        $attributes['internal::fieldClass'] = IntervalField::class;
        $attributes['class'] = 'form-control';
        /** @var IntervalField $field */
        $field = $this->addField('', $name, $value, $attributes, true);
        $this->intervalField = $field;
        return $field;
    }

    protected function save()
    {
        $nexttime = $this->getElement($this->mainFieldset, 'nexttime');
        $timestamp = CronjobManager::calculateNextTime($this->intervalField->getIntervalElements());
        $nexttime->setValue($timestamp ? Sql::datetime($timestamp) : null);

        $return = parent::save();
        CronjobManager::factory()->saveNextTime();
        return $return;
    }
}
