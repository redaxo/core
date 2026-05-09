<?php

namespace Redaxo\Core\MetaInfo\ApiFunction;

use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\ApiFunction\AsApiFunction;
use Redaxo\Core\ApiFunction\Exception\ApiFunctionException;
use Redaxo\Core\ApiFunction\Result;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Http\Request;
use Redaxo\Core\MetaInfo\MetaInfo;
use Redaxo\Core\Translation\I18n;

use function is_string;
use function Redaxo\Core\View\escape;
use function sprintf;

/**
 * @internal
 */
#[AsApiFunction('metainfo_default_fields_create')]
class DefaultFieldsCreate extends ApiFunction
{
    public function execute()
    {
        if (!Core::getUser()?->isAdmin()) {
            throw new ApiFunctionException('user has no permission for this operation!');
        }

        $type = Request::get('type', 'string');

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
                throw new ApiFunctionException(sprintf('metainfo type "%s" does not have default field.', escape($type)));
        }

        $existing = Sql::factory()->getArray('SELECT name FROM ' . Core::getTable('metainfo_field') . ' WHERE name LIKE ?', [$prefix]);
        $existing = array_column($existing, 'name', 'name');

        foreach ($defaultFields as $field) {
            if (!isset($existing[$field[1]])) {
                $return = MetaInfo::addField(...$field);
                if (is_string($return)) {
                    throw new ApiFunctionException($return);
                }
            }
        }

        return new Result(true, I18n::msg('minfo_default_fields_created'));
    }
}
