<?php

/**
 * @package redaxo\metainfo
 *
 * @internal
 */
class rex_api_metainfo_default_fields_create extends rex_api_function
{
    public function execute()
    {
        if (!rex::getUser()?->isAdmin()) {
            throw new rex_api_exception('user has no permission for this operation!');
        }

        $type = rex_get('type', 'string');

        switch ($type) {
            case 'articles':
                $prefix = 'art\_%';
                $defaultFields = [
                    ['translate:online_from', 'art_online_from', '1', '', '10', ''],
                    ['translate:online_to', 'art_online_to', '2', '', '10', ''],
                    ['translate:description', 'art_description', '3', '', '2', ''],
                ];
                break;
            case 'media':
                $prefix = 'med\_%';
                $defaultFields = [
                    ['translate:pool_file_description', 'med_description', '1', '', '2', ''],
                    ['translate:pool_file_copyright', 'med_copyright', '2', '', '1', ''],
                ];
                break;
            default:
                throw new rex_api_exception(sprintf('metainfo type "%s" does not have default field.', rex_escape($type)));
        }

        $existing = rex_sql::factory()->getArray('SELECT name FROM ' . rex::getTable('metainfo_field') . ' WHERE name LIKE ?', [$prefix]);
        $existing = array_column($existing, 'name', 'name');

        foreach ($defaultFields as $field) {
            if (!isset($existing[$field[1]])) {
                $return = call_user_func_array('rex_metainfo_add_field', $field);
                if (is_string($return)) {
                    throw new rex_api_exception($return);
                }
            }
        }

        return new rex_api_result(true, rex_i18n::msg('minfo_default_fields_created'));
    }

    protected function requiresCsrfProtection()
    {
        return true;
    }
}
