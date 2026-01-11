<?php

use Redaxo\Core\Config;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Column;
use Redaxo\Core\Database\ForeignKey;
use Redaxo\Core\Database\Index;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Table;
use Redaxo\Core\MetaInfo\Database\Table as MetaInfoTable;
use Redaxo\Core\MetaInfo\Form\DefaultType;

Table::get(Core::getTable('clang'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new Column('code', 'varchar(255)'))
    ->ensureColumn(new Column('name', 'varchar(255)'))
    ->ensureColumn(new Column('priority', 'int(10) unsigned'))
    ->ensureColumn(new Column('status', 'tinyint(1)'))
    ->ensure();

$sql = Sql::factory();
if (!$sql->setQuery('SELECT 1 FROM ' . Core::getTable('clang') . ' LIMIT 1')->getRows()) {
    $sql->setTable(Core::getTable('clang'));
    $sql->setValues(['id' => 1, 'code' => 'de', 'name' => 'deutsch', 'priority' => 1, 'status' => 1]);
    $sql->insert();
}

Table::get(Core::getTable('config'))
    ->removeColumn('id')
    ->ensureColumn(new Column('namespace', 'varchar(75)'))
    ->ensureColumn(new Column('key', 'varchar(255)'))
    ->ensureColumn(new Column('value', 'text'))
    ->setPrimaryKey(['namespace', 'key'])
    ->ensure();

Table::get(Core::getTable('action'))
    ->ensureColumn(new Column('id', 'int(10) unsigned', false, null, 'AUTO_INCREMENT'))
    ->ensureColumn(new Column('name', 'varchar(255)'))
    ->ensureColumn(new Column('preview', 'text', true))
    ->ensureColumn(new Column('presave', 'text', true))
    ->ensureColumn(new Column('postsave', 'text', true))
    ->ensureColumn(new Column('previewmode', 'tinyint(4)', true))
    ->ensureColumn(new Column('presavemode', 'tinyint(4)', true))
    ->ensureColumn(new Column('postsavemode', 'tinyint(4)', true))
    ->ensureGlobalColumns()
    ->setPrimaryKey('id')
    ->ensure();

Table::get(Core::getTable('article'))
    ->ensureColumn(new Column('pid', 'int(10) unsigned', false, null, 'AUTO_INCREMENT'))
    ->ensureColumn(new Column('id', 'int(10) unsigned'))
    ->ensureColumn(new Column('parent_id', 'int(10) unsigned'))
    ->ensureColumn(new Column('name', 'varchar(255)'))
    ->ensureColumn(new Column('catname', 'varchar(255)'))
    ->ensureColumn(new Column('catpriority', 'int(10) unsigned'))
    ->ensureColumn(new Column('startarticle', 'tinyint(1)'))
    ->ensureColumn(new Column('priority', 'int(10) unsigned'))
    ->ensureColumn(new Column('path', 'varchar(255)'))
    ->ensureColumn(new Column('status', 'tinyint(1)'))
    ->ensureColumn(new Column('template_id', 'int(10) unsigned'))
    ->ensureColumn(new Column('clang_id', 'int(10) unsigned'))
    ->ensureGlobalColumns()
    ->setPrimaryKey('pid')
    ->ensureIndex(new Index('find_articles', ['id', 'clang_id'], Index::UNIQUE))
    ->ensureIndex(new Index('clang_id', ['clang_id']))
    ->ensureIndex(new Index('parent_id', ['parent_id']))
    ->removeIndex('id')
    ->ensure();

Table::get(Core::getTable('article_slice'))
    ->ensureColumn(new Column('id', 'int(10) unsigned', false, null, 'AUTO_INCREMENT'))
    ->ensureColumn(new Column('article_id', 'int(10) unsigned'))
    ->ensureColumn(new Column('clang_id', 'int(10) unsigned'))
    ->ensureColumn(new Column('ctype_id', 'int(10) unsigned'))
    ->ensureColumn(new Column('module_id', 'int(10) unsigned'))
    ->ensureColumn(new Column('revision', 'int(11)'))
    ->ensureColumn(new Column('priority', 'int(10) unsigned'))
    ->ensureColumn(new Column('status', 'tinyint(1)', false, '1'))
    ->ensureColumn(new Column('value1', 'mediumtext', true))
    ->ensureColumn(new Column('value2', 'mediumtext', true))
    ->ensureColumn(new Column('value3', 'mediumtext', true))
    ->ensureColumn(new Column('value4', 'mediumtext', true))
    ->ensureColumn(new Column('value5', 'mediumtext', true))
    ->ensureColumn(new Column('value6', 'mediumtext', true))
    ->ensureColumn(new Column('value7', 'mediumtext', true))
    ->ensureColumn(new Column('value8', 'mediumtext', true))
    ->ensureColumn(new Column('value9', 'mediumtext', true))
    ->ensureColumn(new Column('value10', 'mediumtext', true))
    ->ensureColumn(new Column('value11', 'mediumtext', true))
    ->ensureColumn(new Column('value12', 'mediumtext', true))
    ->ensureColumn(new Column('value13', 'mediumtext', true))
    ->ensureColumn(new Column('value14', 'mediumtext', true))
    ->ensureColumn(new Column('value15', 'mediumtext', true))
    ->ensureColumn(new Column('value16', 'mediumtext', true))
    ->ensureColumn(new Column('value17', 'mediumtext', true))
    ->ensureColumn(new Column('value18', 'mediumtext', true))
    ->ensureColumn(new Column('value19', 'mediumtext', true))
    ->ensureColumn(new Column('value20', 'mediumtext', true))
    ->ensureColumn(new Column('media1', 'varchar(255)', true))
    ->ensureColumn(new Column('media2', 'varchar(255)', true))
    ->ensureColumn(new Column('media3', 'varchar(255)', true))
    ->ensureColumn(new Column('media4', 'varchar(255)', true))
    ->ensureColumn(new Column('media5', 'varchar(255)', true))
    ->ensureColumn(new Column('media6', 'varchar(255)', true))
    ->ensureColumn(new Column('media7', 'varchar(255)', true))
    ->ensureColumn(new Column('media8', 'varchar(255)', true))
    ->ensureColumn(new Column('media9', 'varchar(255)', true))
    ->ensureColumn(new Column('media10', 'varchar(255)', true))
    ->ensureColumn(new Column('medialist1', 'text', true))
    ->ensureColumn(new Column('medialist2', 'text', true))
    ->ensureColumn(new Column('medialist3', 'text', true))
    ->ensureColumn(new Column('medialist4', 'text', true))
    ->ensureColumn(new Column('medialist5', 'text', true))
    ->ensureColumn(new Column('medialist6', 'text', true))
    ->ensureColumn(new Column('medialist7', 'text', true))
    ->ensureColumn(new Column('medialist8', 'text', true))
    ->ensureColumn(new Column('medialist9', 'text', true))
    ->ensureColumn(new Column('medialist10', 'text', true))
    ->ensureColumn(new Column('link1', 'varchar(10)', true))
    ->ensureColumn(new Column('link2', 'varchar(10)', true))
    ->ensureColumn(new Column('link3', 'varchar(10)', true))
    ->ensureColumn(new Column('link4', 'varchar(10)', true))
    ->ensureColumn(new Column('link5', 'varchar(10)', true))
    ->ensureColumn(new Column('link6', 'varchar(10)', true))
    ->ensureColumn(new Column('link7', 'varchar(10)', true))
    ->ensureColumn(new Column('link8', 'varchar(10)', true))
    ->ensureColumn(new Column('link9', 'varchar(10)', true))
    ->ensureColumn(new Column('link10', 'varchar(10)', true))
    ->ensureColumn(new Column('linklist1', 'text', true))
    ->ensureColumn(new Column('linklist2', 'text', true))
    ->ensureColumn(new Column('linklist3', 'text', true))
    ->ensureColumn(new Column('linklist4', 'text', true))
    ->ensureColumn(new Column('linklist5', 'text', true))
    ->ensureColumn(new Column('linklist6', 'text', true))
    ->ensureColumn(new Column('linklist7', 'text', true))
    ->ensureColumn(new Column('linklist8', 'text', true))
    ->ensureColumn(new Column('linklist9', 'text', true))
    ->ensureColumn(new Column('linklist10', 'text', true))
    ->ensureGlobalColumns()
    ->setPrimaryKey('id')
    ->ensureIndex(new Index('slice_priority', ['article_id', 'priority', 'module_id']))
    ->ensureIndex(new Index('find_slices', ['clang_id', 'article_id']))
    ->removeIndex('clang_id')
    ->removeIndex('article_id')
    ->ensure();

Table::get(Core::getTable('article_slice_history'))
    ->ensureColumn(new Column('id', 'int(10) unsigned', false, null, 'AUTO_INCREMENT'))
    ->ensureColumn(new Column('slice_id', 'int(10) unsigned'))
    ->ensureColumn(new Column('history_type', 'varchar(255)'))
    ->ensureColumn(new Column('history_date', 'datetime'))
    ->ensureColumn(new Column('history_user', 'varchar(255)'))
    ->ensureColumn(new Column('clang_id', 'int(10) unsigned'))
    ->ensureColumn(new Column('ctype_id', 'int(10) unsigned'))
    ->ensureColumn(new Column('priority', 'int(10) unsigned'))
    ->ensureColumn(new Column('value1', 'mediumtext', true))
    ->ensureColumn(new Column('value2', 'mediumtext', true))
    ->ensureColumn(new Column('value3', 'mediumtext', true))
    ->ensureColumn(new Column('value4', 'mediumtext', true))
    ->ensureColumn(new Column('value5', 'mediumtext', true))
    ->ensureColumn(new Column('value6', 'mediumtext', true))
    ->ensureColumn(new Column('value7', 'mediumtext', true))
    ->ensureColumn(new Column('value8', 'mediumtext', true))
    ->ensureColumn(new Column('value9', 'mediumtext', true))
    ->ensureColumn(new Column('value10', 'mediumtext', true))
    ->ensureColumn(new Column('value11', 'mediumtext', true))
    ->ensureColumn(new Column('value12', 'mediumtext', true))
    ->ensureColumn(new Column('value13', 'mediumtext', true))
    ->ensureColumn(new Column('value14', 'mediumtext', true))
    ->ensureColumn(new Column('value15', 'mediumtext', true))
    ->ensureColumn(new Column('value16', 'mediumtext', true))
    ->ensureColumn(new Column('value17', 'mediumtext', true))
    ->ensureColumn(new Column('value18', 'mediumtext', true))
    ->ensureColumn(new Column('value19', 'mediumtext', true))
    ->ensureColumn(new Column('value20', 'mediumtext', true))
    ->ensureColumn(new Column('media1', 'varchar(255)', true))
    ->ensureColumn(new Column('media2', 'varchar(255)', true))
    ->ensureColumn(new Column('media3', 'varchar(255)', true))
    ->ensureColumn(new Column('media4', 'varchar(255)', true))
    ->ensureColumn(new Column('media5', 'varchar(255)', true))
    ->ensureColumn(new Column('media6', 'varchar(255)', true))
    ->ensureColumn(new Column('media7', 'varchar(255)', true))
    ->ensureColumn(new Column('media8', 'varchar(255)', true))
    ->ensureColumn(new Column('media9', 'varchar(255)', true))
    ->ensureColumn(new Column('media10', 'varchar(255)', true))
    ->ensureColumn(new Column('medialist1', 'text', true))
    ->ensureColumn(new Column('medialist2', 'text', true))
    ->ensureColumn(new Column('medialist3', 'text', true))
    ->ensureColumn(new Column('medialist4', 'text', true))
    ->ensureColumn(new Column('medialist5', 'text', true))
    ->ensureColumn(new Column('medialist6', 'text', true))
    ->ensureColumn(new Column('medialist7', 'text', true))
    ->ensureColumn(new Column('medialist8', 'text', true))
    ->ensureColumn(new Column('medialist9', 'text', true))
    ->ensureColumn(new Column('medialist10', 'text', true))
    ->ensureColumn(new Column('link1', 'varchar(10)', true))
    ->ensureColumn(new Column('link2', 'varchar(10)', true))
    ->ensureColumn(new Column('link3', 'varchar(10)', true))
    ->ensureColumn(new Column('link4', 'varchar(10)', true))
    ->ensureColumn(new Column('link5', 'varchar(10)', true))
    ->ensureColumn(new Column('link6', 'varchar(10)', true))
    ->ensureColumn(new Column('link7', 'varchar(10)', true))
    ->ensureColumn(new Column('link8', 'varchar(10)', true))
    ->ensureColumn(new Column('link9', 'varchar(10)', true))
    ->ensureColumn(new Column('link10', 'varchar(10)', true))
    ->ensureColumn(new Column('linklist1', 'text', true))
    ->ensureColumn(new Column('linklist2', 'text', true))
    ->ensureColumn(new Column('linklist3', 'text', true))
    ->ensureColumn(new Column('linklist4', 'text', true))
    ->ensureColumn(new Column('linklist5', 'text', true))
    ->ensureColumn(new Column('linklist6', 'text', true))
    ->ensureColumn(new Column('linklist7', 'text', true))
    ->ensureColumn(new Column('linklist8', 'text', true))
    ->ensureColumn(new Column('linklist9', 'text', true))
    ->ensureColumn(new Column('linklist10', 'text', true))
    ->ensureColumn(new Column('article_id', 'int(10) unsigned'))
    ->ensureColumn(new Column('module_id', 'int(10) unsigned'))
    ->ensureGlobalColumns()
    ->ensureColumn(new Column('revision', 'int(11)'))
    ->setPrimaryKey('id')
    ->ensureIndex(new Index('snapshot', ['article_id', 'clang_id', 'revision', 'history_date']))
    ->ensure();

Table::get(Core::getTable('module'))
    ->ensureColumn(new Column('id', 'int(10) unsigned', false, null, 'AUTO_INCREMENT'))
    ->ensureColumn(new Column('key', 'varchar(191)', true))
    ->ensureColumn(new Column('name', 'varchar(255)'))
    ->ensureColumn(new Column('output', 'mediumtext'))
    ->ensureColumn(new Column('input', 'mediumtext'))
    ->ensureGlobalColumns()
    ->setPrimaryKey('id')
    ->ensureIndex(new Index('key', ['key'], Index::UNIQUE))
    ->ensure();

Table::get(Core::getTable('module_action'))
    ->ensureColumn(new Column('id', 'int(10) unsigned', false, null, 'AUTO_INCREMENT'))
    ->ensureColumn(new Column('module_id', 'int(10) unsigned'))
    ->ensureColumn(new Column('action_id', 'int(10) unsigned'))
    ->setPrimaryKey('id')
    ->ensure();

Table::get(Core::getTable('template'))
    ->ensureColumn(new Column('id', 'int(10) unsigned', false, null, 'AUTO_INCREMENT'))
    ->ensureColumn(new Column('key', 'varchar(191)', true))
    ->ensureColumn(new Column('name', 'varchar(255)', true))
    ->ensureColumn(new Column('content', 'mediumtext', true))
    ->ensureColumn(new Column('active', 'tinyint(1)', true))
    ->ensureGlobalColumns()
    ->ensureColumn(new Column('attributes', 'text', true))
    ->setPrimaryKey('id')
    ->ensureIndex(new Index('key', ['key'], Index::UNIQUE))
    ->ensure();

$sql = Sql::factory();
$sql->setQuery('UPDATE ' . Core::getTablePrefix() . 'article_slice set revision=0 where revision<1 or revision IS NULL');
$sql->setQuery('SELECT 1 FROM ' . Core::getTable('template') . ' LIMIT 1');
if (!$sql->getRows()) {
    $sql
        ->setTable(Core::getTable('template'))
        ->setValue('id', 1)
        ->setValue('name', 'Default')
        ->setValue('content', 'REX_ARTICLE[]')
        ->setValue('active', 1)
        ->setValue('attributes', '{"ctype":[],"modules":{"1":{"all":"1"}},"categories":{"all":"1"}}')
        ->addGlobalCreateFields()
        ->addGlobalUpdateFields()
        ->insert();
}

Table::get(Core::getTable('cronjob'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new Column('name', 'varchar(255)', true))
    ->ensureColumn(new Column('description', 'varchar(255)', true))
    ->ensureColumn(new Column('type', 'varchar(255)', true))
    ->ensureColumn(new Column('parameters', 'text', true))
    ->ensureColumn(new Column('interval', 'text'))
    ->ensureColumn(new Column('nexttime', 'datetime', true))
    ->ensureColumn(new Column('environment', 'varchar(255)'))
    ->ensureColumn(new Column('execution_moment', 'tinyint(1)'))
    ->ensureColumn(new Column('execution_start', 'datetime', true))
    ->ensureColumn(new Column('status', 'tinyint(1)'))
    ->ensureGlobalColumns()
    ->ensure();

Table::get(Core::getTable('media'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new Column('category_id', 'int(10) unsigned'))
    ->ensureColumn(new Column('filetype', 'varchar(255)', true))
    ->ensureColumn(new Column('filename', 'varchar(255)', true))
    ->ensureColumn(new Column('originalname', 'varchar(255)', true))
    ->ensureColumn(new Column('filesize', 'varchar(255)', true))
    ->ensureColumn(new Column('width', 'int(10) unsigned', true))
    ->ensureColumn(new Column('height', 'int(10) unsigned', true))
    ->ensureColumn(new Column('title', 'varchar(255)', true))
    ->ensureGlobalColumns()
    ->ensureIndex(new Index('category_id', ['category_id']))
    ->ensureIndex(new Index('filename', ['filename'], Index::UNIQUE))
    ->ensure();

Table::get(Core::getTable('media_category'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new Column('name', 'varchar(255)'))
    ->ensureColumn(new Column('parent_id', 'int(10) unsigned'))
    ->ensureColumn(new Column('path', 'varchar(255)'))
    ->ensureGlobalColumns()
    ->ensureIndex(new Index('parent_id', ['parent_id']))
    ->ensure();

Table::get(Core::getTable('media_manager_type'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new Column('status', 'tinyint(1) unsigned', false, '0'))
    ->ensureColumn(new Column('name', 'varchar(255)'))
    ->ensureColumn(new Column('description', 'varchar(255)'))
    ->ensureIndex(new Index('name', ['name'], Index::UNIQUE))
    ->ensureGlobalColumns()
    ->ensure();

Table::get(Core::getTable('media_manager_type_effect'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new Column('type_id', 'int(10) unsigned'))
    ->ensureColumn(new Column('effect', 'varchar(255)'))
    ->ensureColumn(new Column('parameters', 'text'))
    ->ensureColumn(new Column('priority', 'int(10) unsigned'))
    ->ensureGlobalColumns()
    ->ensure();

$data = [
    ['id' => 1, 'name' => 'rex_media_small', 'description' => '200 × 200 px'],
    ['id' => 2, 'name' => 'rex_media_medium', 'description' => '600 × 600 px'],
    ['id' => 3, 'name' => 'rex_media_large', 'description' => '1200 × 1200 px'],
];

$sql = Sql::factory();
$sql->setTable(Core::getTable('media_manager_type'));

foreach ($data as $row) {
    $sql->addRecord(static function (Sql $record) use ($row) {
        $record
            ->setValues($row)
            ->setValue('status', 1)
            ->addGlobalCreateFields()
            ->addGlobalUpdateFields();
    });
}

$sql->insertOrUpdate();

$data = [
    ['id' => 1, 'type_id' => 1, 'effect' => 'Redaxo\Core\MediaManager\Effect\ResizeEffect', 'parameters' => '{"redaxo_core_mediamanager_effect_roundedcornerseffect":{"redaxo_core_mediamanager_effect_roundedcornerseffect_topleft":"","redaxo_core_mediamanager_effect_roundedcornerseffect_topright":"","redaxo_core_mediamanager_effect_roundedcornerseffect_bottomleft":"","redaxo_core_mediamanager_effect_roundedcornerseffect_bottomright":""},"redaxo_core_mediamanager_effect_workspaceeffect":{"redaxo_core_mediamanager_effect_workspaceeffect_set_transparent":"colored","redaxo_core_mediamanager_effect_workspaceeffect_width":"","redaxo_core_mediamanager_effect_workspaceeffect_height":"","redaxo_core_mediamanager_effect_workspaceeffect_hpos":"left","redaxo_core_mediamanager_effect_workspaceeffect_vpos":"top","redaxo_core_mediamanager_effect_workspaceeffect_padding_x":"0","redaxo_core_mediamanager_effect_workspaceeffect_padding_y":"0","redaxo_core_mediamanager_effect_workspaceeffect_bg_r":"","redaxo_core_mediamanager_effect_workspaceeffect_bg_g":"","redaxo_core_mediamanager_effect_workspaceeffect_bg_b":"","redaxo_core_mediamanager_effect_workspaceeffect_bgimage":""},"redaxo_core_mediamanager_effect_cropeffect":{"redaxo_core_mediamanager_effect_cropeffect_width":"","redaxo_core_mediamanager_effect_cropeffect_height":"","redaxo_core_mediamanager_effect_cropeffect_offset_width":"","redaxo_core_mediamanager_effect_cropeffect_offset_height":"","redaxo_core_mediamanager_effect_cropeffect_hpos":"center","redaxo_core_mediamanager_effect_cropeffect_vpos":"middle"},"redaxo_core_mediamanager_effect_insertimageeffect":{"redaxo_core_mediamanager_effect_insertimageeffect_brandimage":"","redaxo_core_mediamanager_effect_insertimageeffect_hpos":"left","redaxo_core_mediamanager_effect_insertimageeffect_vpos":"top","redaxo_core_mediamanager_effect_insertimageeffect_padding_x":"-10","redaxo_core_mediamanager_effect_insertimageeffect_padding_y":"-10"},"redaxo_core_mediamanager_effect_rotateeffect":{"redaxo_core_mediamanager_effect_rotateeffect_rotate":"0"},"redaxo_core_mediamanager_effect_filtercolorizeeffect":{"redaxo_core_mediamanager_effect_filtercolorizeeffect_filter_r":"","redaxo_core_mediamanager_effect_filtercolorizeeffect_filter_g":"","redaxo_core_mediamanager_effect_filtercolorizeeffect_filter_b":""},"redaxo_core_mediamanager_effect_imagepropertieseffect":{"redaxo_core_mediamanager_effect_imagepropertieseffect_jpg_quality":"","redaxo_core_mediamanager_effect_imagepropertieseffect_png_compression":"","redaxo_core_mediamanager_effect_imagepropertieseffect_webp_quality":"","redaxo_core_mediamanager_effect_imagepropertieseffect_avif_quality":"","redaxo_core_mediamanager_effect_imagepropertieseffect_avif_speed":"","redaxo_core_mediamanager_effect_imagepropertieseffect_interlace":null},"redaxo_core_mediamanager_effect_filterbrightnesseffect":{"redaxo_core_mediamanager_effect_filterbrightnesseffect_brightness":""},"redaxo_core_mediamanager_effect_flipeffect":{"redaxo_core_mediamanager_effect_flipeffect_flip":"X"},"redaxo_core_mediamanager_effect_imageformateffect":{"redaxo_core_mediamanager_effect_imageformateffect_convert_to":"webp"},"redaxo_core_mediamanager_effect_filtercontrasteffect":{"redaxo_core_mediamanager_effect_filtercontrasteffect_contrast":""},"redaxo_core_mediamanager_effect_filtersharpeneffect":{"redaxo_core_mediamanager_effect_filtersharpeneffect_amount":"80","redaxo_core_mediamanager_effect_filtersharpeneffect_radius":"0.5","redaxo_core_mediamanager_effect_filtersharpeneffect_threshold":"3"},"redaxo_core_mediamanager_effect_resizeeffect":{"redaxo_core_mediamanager_effect_resizeeffect_width":"200","redaxo_core_mediamanager_effect_resizeeffect_height":"200","redaxo_core_mediamanager_effect_resizeeffect_style":"maximum","redaxo_core_mediamanager_effect_resizeeffect_allow_enlarge":"not_enlarge"},"redaxo_core_mediamanager_effect_filterblureffect":{"redaxo_core_mediamanager_effect_filterblureffect_repeats":"10","redaxo_core_mediamanager_effect_filterblureffect_type":"gaussian","redaxo_core_mediamanager_effect_filterblureffect_smoothit":""},"redaxo_core_mediamanager_effect_mirroreffect":{"redaxo_core_mediamanager_effect_mirroreffect_height":"","redaxo_core_mediamanager_effect_mirroreffect_opacity":"100","redaxo_core_mediamanager_effect_mirroreffect_set_transparent":"colored","redaxo_core_mediamanager_effect_mirroreffect_bg_r":"","redaxo_core_mediamanager_effect_mirroreffect_bg_g":"","redaxo_core_mediamanager_effect_mirroreffect_bg_b":""},"redaxo_core_mediamanager_effect_headereffect":{"redaxo_core_mediamanager_effect_headereffect_download":"open_media","redaxo_core_mediamanager_effect_headereffect_cache":"no_cache","redaxo_core_mediamanager_effect_headereffect_filename":"filename","redaxo_core_mediamanager_effect_headereffect_index":"index"},"redaxo_core_mediamanager_effect_converttoimageeffect":{"redaxo_core_mediamanager_effect_converttoimageeffect_convert_to":"jpg","redaxo_core_mediamanager_effect_converttoimageeffect_density":"150","redaxo_core_mediamanager_effect_converttoimageeffect_color":""},"redaxo_core_mediamanager_effect_mediapatheffect":{"redaxo_core_mediamanager_effect_mediapatheffect_mediapath":""}}'],
    ['id' => 2, 'type_id' => 2, 'effect' => 'Redaxo\Core\MediaManager\Effect\ResizeEffect', 'parameters' => '{"redaxo_core_mediamanager_effect_roundedcornerseffect":{"redaxo_core_mediamanager_effect_roundedcornerseffect_topleft":"","redaxo_core_mediamanager_effect_roundedcornerseffect_topright":"","redaxo_core_mediamanager_effect_roundedcornerseffect_bottomleft":"","redaxo_core_mediamanager_effect_roundedcornerseffect_bottomright":""},"redaxo_core_mediamanager_effect_workspaceeffect":{"redaxo_core_mediamanager_effect_workspaceeffect_set_transparent":"colored","redaxo_core_mediamanager_effect_workspaceeffect_width":"","redaxo_core_mediamanager_effect_workspaceeffect_height":"","redaxo_core_mediamanager_effect_workspaceeffect_hpos":"left","redaxo_core_mediamanager_effect_workspaceeffect_vpos":"top","redaxo_core_mediamanager_effect_workspaceeffect_padding_x":"0","redaxo_core_mediamanager_effect_workspaceeffect_padding_y":"0","redaxo_core_mediamanager_effect_workspaceeffect_bg_r":"","redaxo_core_mediamanager_effect_workspaceeffect_bg_g":"","redaxo_core_mediamanager_effect_workspaceeffect_bg_b":"","redaxo_core_mediamanager_effect_workspaceeffect_bgimage":""},"redaxo_core_mediamanager_effect_cropeffect":{"redaxo_core_mediamanager_effect_cropeffect_width":"","redaxo_core_mediamanager_effect_cropeffect_height":"","redaxo_core_mediamanager_effect_cropeffect_offset_width":"","redaxo_core_mediamanager_effect_cropeffect_offset_height":"","redaxo_core_mediamanager_effect_cropeffect_hpos":"center","redaxo_core_mediamanager_effect_cropeffect_vpos":"middle"},"redaxo_core_mediamanager_effect_insertimageeffect":{"redaxo_core_mediamanager_effect_insertimageeffect_brandimage":"","redaxo_core_mediamanager_effect_insertimageeffect_hpos":"left","redaxo_core_mediamanager_effect_insertimageeffect_vpos":"top","redaxo_core_mediamanager_effect_insertimageeffect_padding_x":"-10","redaxo_core_mediamanager_effect_insertimageeffect_padding_y":"-10"},"redaxo_core_mediamanager_effect_rotateeffect":{"redaxo_core_mediamanager_effect_rotateeffect_rotate":"0"},"redaxo_core_mediamanager_effect_filtercolorizeeffect":{"redaxo_core_mediamanager_effect_filtercolorizeeffect_filter_r":"","redaxo_core_mediamanager_effect_filtercolorizeeffect_filter_g":"","redaxo_core_mediamanager_effect_filtercolorizeeffect_filter_b":""},"redaxo_core_mediamanager_effect_imagepropertieseffect":{"redaxo_core_mediamanager_effect_imagepropertieseffect_jpg_quality":"","redaxo_core_mediamanager_effect_imagepropertieseffect_png_compression":"","redaxo_core_mediamanager_effect_imagepropertieseffect_webp_quality":"","redaxo_core_mediamanager_effect_imagepropertieseffect_avif_quality":"","redaxo_core_mediamanager_effect_imagepropertieseffect_avif_speed":"","redaxo_core_mediamanager_effect_imagepropertieseffect_interlace":null},"redaxo_core_mediamanager_effect_filterbrightnesseffect":{"redaxo_core_mediamanager_effect_filterbrightnesseffect_brightness":""},"redaxo_core_mediamanager_effect_flipeffect":{"redaxo_core_mediamanager_effect_flipeffect_flip":"X"},"redaxo_core_mediamanager_effect_imageformateffect":{"redaxo_core_mediamanager_effect_imageformateffect_convert_to":"webp"},"redaxo_core_mediamanager_effect_filtercontrasteffect":{"redaxo_core_mediamanager_effect_filtercontrasteffect_contrast":""},"redaxo_core_mediamanager_effect_filtersharpeneffect":{"redaxo_core_mediamanager_effect_filtersharpeneffect_amount":"80","redaxo_core_mediamanager_effect_filtersharpeneffect_radius":"0.5","redaxo_core_mediamanager_effect_filtersharpeneffect_threshold":"3"},"redaxo_core_mediamanager_effect_resizeeffect":{"redaxo_core_mediamanager_effect_resizeeffect_width":"600","redaxo_core_mediamanager_effect_resizeeffect_height":"600","redaxo_core_mediamanager_effect_resizeeffect_style":"maximum","redaxo_core_mediamanager_effect_resizeeffect_allow_enlarge":"not_enlarge"},"redaxo_core_mediamanager_effect_filterblureffect":{"redaxo_core_mediamanager_effect_filterblureffect_repeats":"10","redaxo_core_mediamanager_effect_filterblureffect_type":"gaussian","redaxo_core_mediamanager_effect_filterblureffect_smoothit":""},"redaxo_core_mediamanager_effect_mirroreffect":{"redaxo_core_mediamanager_effect_mirroreffect_height":"","redaxo_core_mediamanager_effect_mirroreffect_opacity":"100","redaxo_core_mediamanager_effect_mirroreffect_set_transparent":"colored","redaxo_core_mediamanager_effect_mirroreffect_bg_r":"","redaxo_core_mediamanager_effect_mirroreffect_bg_g":"","redaxo_core_mediamanager_effect_mirroreffect_bg_b":""},"redaxo_core_mediamanager_effect_headereffect":{"redaxo_core_mediamanager_effect_headereffect_download":"open_media","redaxo_core_mediamanager_effect_headereffect_cache":"no_cache","redaxo_core_mediamanager_effect_headereffect_filename":"filename","redaxo_core_mediamanager_effect_headereffect_index":"index"},"redaxo_core_mediamanager_effect_converttoimageeffect":{"redaxo_core_mediamanager_effect_converttoimageeffect_convert_to":"jpg","redaxo_core_mediamanager_effect_converttoimageeffect_density":"150","redaxo_core_mediamanager_effect_converttoimageeffect_color":""},"redaxo_core_mediamanager_effect_mediapatheffect":{"redaxo_core_mediamanager_effect_mediapatheffect_mediapath":""}}'],
    ['id' => 3, 'type_id' => 3, 'effect' => 'Redaxo\Core\MediaManager\Effect\ResizeEffect', 'parameters' => '{"redaxo_core_mediamanager_effect_roundedcornerseffect":{"redaxo_core_mediamanager_effect_roundedcornerseffect_topleft":"","redaxo_core_mediamanager_effect_roundedcornerseffect_topright":"","redaxo_core_mediamanager_effect_roundedcornerseffect_bottomleft":"","redaxo_core_mediamanager_effect_roundedcornerseffect_bottomright":""},"redaxo_core_mediamanager_effect_workspaceeffect":{"redaxo_core_mediamanager_effect_workspaceeffect_set_transparent":"colored","redaxo_core_mediamanager_effect_workspaceeffect_width":"","redaxo_core_mediamanager_effect_workspaceeffect_height":"","redaxo_core_mediamanager_effect_workspaceeffect_hpos":"left","redaxo_core_mediamanager_effect_workspaceeffect_vpos":"top","redaxo_core_mediamanager_effect_workspaceeffect_padding_x":"0","redaxo_core_mediamanager_effect_workspaceeffect_padding_y":"0","redaxo_core_mediamanager_effect_workspaceeffect_bg_r":"","redaxo_core_mediamanager_effect_workspaceeffect_bg_g":"","redaxo_core_mediamanager_effect_workspaceeffect_bg_b":"","redaxo_core_mediamanager_effect_workspaceeffect_bgimage":""},"redaxo_core_mediamanager_effect_cropeffect":{"redaxo_core_mediamanager_effect_cropeffect_width":"","redaxo_core_mediamanager_effect_cropeffect_height":"","redaxo_core_mediamanager_effect_cropeffect_offset_width":"","redaxo_core_mediamanager_effect_cropeffect_offset_height":"","redaxo_core_mediamanager_effect_cropeffect_hpos":"center","redaxo_core_mediamanager_effect_cropeffect_vpos":"middle"},"redaxo_core_mediamanager_effect_insertimageeffect":{"redaxo_core_mediamanager_effect_insertimageeffect_brandimage":"","redaxo_core_mediamanager_effect_insertimageeffect_hpos":"left","redaxo_core_mediamanager_effect_insertimageeffect_vpos":"top","redaxo_core_mediamanager_effect_insertimageeffect_padding_x":"-10","redaxo_core_mediamanager_effect_insertimageeffect_padding_y":"-10"},"redaxo_core_mediamanager_effect_rotateeffect":{"redaxo_core_mediamanager_effect_rotateeffect_rotate":"0"},"redaxo_core_mediamanager_effect_filtercolorizeeffect":{"redaxo_core_mediamanager_effect_filtercolorizeeffect_filter_r":"","redaxo_core_mediamanager_effect_filtercolorizeeffect_filter_g":"","redaxo_core_mediamanager_effect_filtercolorizeeffect_filter_b":""},"redaxo_core_mediamanager_effect_imagepropertieseffect":{"redaxo_core_mediamanager_effect_imagepropertieseffect_jpg_quality":"","redaxo_core_mediamanager_effect_imagepropertieseffect_png_compression":"","redaxo_core_mediamanager_effect_imagepropertieseffect_webp_quality":"","redaxo_core_mediamanager_effect_imagepropertieseffect_avif_quality":"","redaxo_core_mediamanager_effect_imagepropertieseffect_avif_speed":"","redaxo_core_mediamanager_effect_imagepropertieseffect_interlace":null},"redaxo_core_mediamanager_effect_filterbrightnesseffect":{"redaxo_core_mediamanager_effect_filterbrightnesseffect_brightness":""},"redaxo_core_mediamanager_effect_flipeffect":{"redaxo_core_mediamanager_effect_flipeffect_flip":"X"},"redaxo_core_mediamanager_effect_imageformateffect":{"redaxo_core_mediamanager_effect_imageformateffect_convert_to":"webp"},"redaxo_core_mediamanager_effect_filtercontrasteffect":{"redaxo_core_mediamanager_effect_filtercontrasteffect_contrast":""},"redaxo_core_mediamanager_effect_filtersharpeneffect":{"redaxo_core_mediamanager_effect_filtersharpeneffect_amount":"80","redaxo_core_mediamanager_effect_filtersharpeneffect_radius":"0.5","redaxo_core_mediamanager_effect_filtersharpeneffect_threshold":"3"},"redaxo_core_mediamanager_effect_resizeeffect":{"redaxo_core_mediamanager_effect_resizeeffect_width":"1200","redaxo_core_mediamanager_effect_resizeeffect_height":"1200","redaxo_core_mediamanager_effect_resizeeffect_style":"maximum","redaxo_core_mediamanager_effect_resizeeffect_allow_enlarge":"not_enlarge"},"redaxo_core_mediamanager_effect_filterblureffect":{"redaxo_core_mediamanager_effect_filterblureffect_repeats":"10","redaxo_core_mediamanager_effect_filterblureffect_type":"gaussian","redaxo_core_mediamanager_effect_filterblureffect_smoothit":""},"redaxo_core_mediamanager_effect_mirroreffect":{"redaxo_core_mediamanager_effect_mirroreffect_height":"","redaxo_core_mediamanager_effect_mirroreffect_opacity":"100","redaxo_core_mediamanager_effect_mirroreffect_set_transparent":"colored","redaxo_core_mediamanager_effect_mirroreffect_bg_r":"","redaxo_core_mediamanager_effect_mirroreffect_bg_g":"","redaxo_core_mediamanager_effect_mirroreffect_bg_b":""},"redaxo_core_mediamanager_effect_headereffect":{"redaxo_core_mediamanager_effect_headereffect_download":"open_media","redaxo_core_mediamanager_effect_headereffect_cache":"no_cache","redaxo_core_mediamanager_effect_headereffect_filename":"filename","redaxo_core_mediamanager_effect_headereffect_index":"index"},"redaxo_core_mediamanager_effect_converttoimageeffect":{"redaxo_core_mediamanager_effect_converttoimageeffect_convert_to":"jpg","redaxo_core_mediamanager_effect_converttoimageeffect_density":"150","redaxo_core_mediamanager_effect_converttoimageeffect_color":""},"redaxo_core_mediamanager_effect_mediapatheffect":{"redaxo_core_mediamanager_effect_mediapatheffect_mediapath":""}}'],
];

$sql = Sql::factory();
$sql->setTable(Core::getTable('media_manager_type_effect'));

foreach ($data as $row) {
    $sql->addRecord(static function (Sql $record) use ($row) {
        $record
            ->setValues($row)
            ->setValue('priority', 1)
            ->addGlobalCreateFields()
            ->addGlobalUpdateFields();
    });
}

$sql->insertOrUpdate();

Table::get(Core::getTable('metainfo_type'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new Column('label', 'varchar(255)', true))
    ->ensureColumn(new Column('dbtype', 'varchar(255)'))
    ->ensureColumn(new Column('dblength', 'int(11)'))
    ->ensure();

Table::get(Core::getTable('metainfo_field'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new Column('title', 'varchar(255)', true))
    ->ensureColumn(new Column('name', 'varchar(255)', true))
    ->ensureColumn(new Column('priority', 'int(10) unsigned'))
    ->ensureColumn(new Column('attributes', 'text'))
    ->ensureColumn(new Column('type_id', 'int(10) unsigned', true))
    ->ensureColumn(new Column('default', 'varchar(255)'))
    ->ensureColumn(new Column('params', 'text', true))
    ->ensureColumn(new Column('validate', 'text', true))
    ->ensureColumn(new Column('restrictions', 'text', true))
    ->ensureColumn(new Column('templates', 'text', true))
    ->ensureGlobalColumns()
    ->ensureIndex(new Index('name', ['name'], Index::UNIQUE))
    ->ensure();

Table::get(Core::getTable('user'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new Column('name', 'varchar(255)', true))
    ->ensureColumn(new Column('description', 'text', true))
    ->ensureColumn(new Column('login', 'varchar(50)'))
    ->ensureColumn(new Column('password', 'varchar(255)', true))
    ->ensureColumn(new Column('email', 'varchar(255)', true))
    ->ensureColumn(new Column('status', 'tinyint(1)'))
    ->ensureColumn(new Column('admin', 'tinyint(1)', false, '0'))
    ->ensureColumn(new Column('language', 'varchar(255)', true))
    ->ensureColumn(new Column('startpage', 'varchar(255)', true))
    ->ensureColumn(new Column('role', 'text', true))
    ->ensureColumn(new Column('theme', 'varchar(255)', true))
    ->ensureColumn(new Column('login_tries', 'tinyint(4)', false, '0'))
    ->ensureGlobalColumns()
    ->ensureColumn(new Column('password_changed', 'datetime'))
    ->ensureColumn(new Column('previous_passwords', 'text'))
    ->ensureColumn(new Column('password_change_required', 'tinyint(1)', false, '0'))
    ->ensureColumn(new Column('lasttrydate', 'datetime', true))
    ->ensureColumn(new Column('lastlogin', 'datetime', true))
    ->ensureColumn(new Column('session_id', 'varchar(255)', true))
    ->ensureIndex(new Index('login', ['login'], Index::UNIQUE))
    ->removeColumn('cookiekey')
    ->ensure();

Table::get(Core::getTable('user_passkey'))
    ->ensureColumn(new Column('id', 'varchar(255)'))
    ->ensureColumn(new Column('user_id', 'int(10) unsigned'))
    ->ensureColumn(new Column('public_key', 'text'))
    ->ensureColumn(new Column('createdate', 'datetime'))
    ->setPrimaryKey('id')
    ->ensureForeignKey(new ForeignKey(Core::getTable('user_passkey') . '_user_id', Core::getTable('user'), ['user_id' => 'id'], ForeignKey::CASCADE, ForeignKey::CASCADE))
    ->ensure();

Table::get(Core::getTable('user_role'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new Column('name', 'varchar(255)', true))
    ->ensureColumn(new Column('description', 'text', true))
    ->ensureColumn(new Column('perms', 'text'))
    ->ensureGlobalColumns()
    ->ensure();

Table::get(Core::getTable('user_session'))
    ->ensureColumn(new Column('session_id', 'varchar(255)'))
    ->ensureColumn(new Column('user_id', 'int(10) unsigned'))
    ->ensureColumn(new Column('cookie_key', 'varchar(255)', true))
    ->ensureColumn(new Column('passkey_id', 'varchar(255)', true))
    ->ensureColumn(new Column('ip', 'varchar(39)')) // max for ipv6
    ->ensureColumn(new Column('useragent', 'varchar(255)'))
    ->ensureColumn(new Column('starttime', 'datetime'))
    ->ensureColumn(new Column('last_activity', 'datetime'))
    ->setPrimaryKey('session_id')
    ->ensureIndex(new Index('cookie_key', ['cookie_key'], Index::UNIQUE))
    ->ensureForeignKey(new ForeignKey(Core::getTable('user_session') . '_user_id', Core::getTable('user'), ['user_id' => 'id'], ForeignKey::CASCADE, ForeignKey::CASCADE))
    ->ensureForeignKey(new ForeignKey(Core::getTable('user_session') . '_passkey_id', Core::getTable('user_passkey'), ['passkey_id' => 'id'], ForeignKey::CASCADE, ForeignKey::CASCADE))
    ->ensure();

$defaultConfig = [
    'start_article_id' => 1,
    'notfound_article_id' => 1,
    'article_history' => false,
    'article_work_version' => false,
    'be_style_compile' => false,
    'be_style_labelcolor' => '#3bb594',
    'be_style_showlink' => true,
    'media_manager_jpg_quality' => 80,
    'media_manager_png_compression' => 5,
    'media_manager_webp_quality' => 85,
    'media_manager_avif_quality' => 60,
    'media_manager_avif_speed' => 6,
    'media_manager_interlace' => ['jpg'],
    'phpmailer_from' => '',
    'phpmailer_test_address' => '',
    'phpmailer_fromname' => 'Mailer',
    'phpmailer_confirmto' => '',
    'phpmailer_bcc' => '',
    'phpmailer_returnto' => '',
    'phpmailer_mailer' => 'smtp',
    'phpmailer_host' => 'localhost',
    'phpmailer_port' => 587,
    'phpmailer_charset' => 'utf-8',
    'phpmailer_wordwrap' => 120,
    'phpmailer_encoding' => '8bit',
    'phpmailer_priority' => 0,
    'phpmailer_security_mode' => false,
    'phpmailer_smtpsecure' => 'tls',
    'phpmailer_smtpauth' => true,
    'phpmailer_username' => '',
    'phpmailer_password' => '',
    'phpmailer_smtp_debug' => '0',
    'phpmailer_logging' => 0,
    'phpmailer_errormail' => 0,
    'phpmailer_archive' => false,
    'phpmailer_detour_mode' => false,
];

Config::refresh();
foreach ($defaultConfig as $key => $value) {
    if (!Core::hasConfig($key)) {
        Core::setConfig($key, $value);
    }
}

$data = [
    ['id' => DefaultType::TEXT, 'label' => 'text', 'dbtype' => 'text', 'dblength' => 0],
    ['id' => DefaultType::TEXTAREA, 'label' => 'textarea', 'dbtype' => 'text', 'dblength' => 0],
    ['id' => DefaultType::SELECT, 'label' => 'select', 'dbtype' => 'varchar', 'dblength' => 255],
    ['id' => DefaultType::RADIO, 'label' => 'radio', 'dbtype' => 'varchar', 'dblength' => 255],
    ['id' => DefaultType::CHECKBOX, 'label' => 'checkbox', 'dbtype' => 'varchar', 'dblength' => 255],
    ['id' => DefaultType::REX_MEDIA_WIDGET, 'label' => 'REX_MEDIA_WIDGET', 'dbtype' => 'text', 'dblength' => 0],
    ['id' => DefaultType::REX_LINK_WIDGET, 'label' => 'REX_LINK_WIDGET', 'dbtype' => 'text', 'dblength' => 0],
    ['id' => DefaultType::DATE, 'label' => 'date', 'dbtype' => 'text', 'dblength' => 0],
    ['id' => DefaultType::DATETIME, 'label' => 'datetime', 'dbtype' => 'text', 'dblength' => 0],
    ['id' => DefaultType::LEGEND, 'label' => 'legend', 'dbtype' => 'text', 'dblength' => 0],
    ['id' => DefaultType::TIME, 'label' => 'time', 'dbtype' => 'text', 'dblength' => 0],
    // XXX neue konstanten koennen hier nicht verwendet werden, da die updates mit der vorherigen version der klasse ausgefuehrt werden
];

$sql = Sql::factory();
$sql->setTable(Core::getTable('metainfo_type'));
foreach ($data as $row) {
    $sql->addRecord(static function (Sql $record) use ($row) {
        $record->setValues($row);
    });
}
$sql->insertOrUpdate();

$tablePrefixes = ['article' => ['art_', 'cat_'], 'media' => ['med_'], 'clang' => ['clang_']];
$columns = ['article' => [], 'media' => [], 'clang' => []];
foreach ($tablePrefixes as $table => $prefixes) {
    foreach (Sql::showColumns(Core::getTable($table)) as $column) {
        $column = $column['name'];
        if (in_array(substr($column, 0, 4), $prefixes)) {
            $columns[$table][$column] = true;
        }
    }
}

$sql = Sql::factory();
$sql->setQuery('SELECT p.name, p.default, t.dbtype, t.dblength FROM ' . Core::getTable('metainfo_field') . ' p, ' . Core::getTable('metainfo_type') . ' t WHERE p.type_id = t.id');
$managers = [
    'article' => new MetaInfoTable(Core::getTable('article')),
    'media' => new MetaInfoTable(Core::getTable('media')),
    'clang' => new MetaInfoTable(Core::getTable('clang')),
];
for ($i = 0; $i < $sql->getRows(); ++$i) {
    $column = (string) $sql->getValue('name');
    if (str_starts_with($column, 'med_')) {
        $table = 'media';
    } elseif (str_starts_with($column, 'clang_')) {
        $table = 'clang';
    } else {
        $table = 'article';
    }

    $default = $sql->getValue('default');
    $default = null === $default ? $default : (string) $default;

    if (isset($columns[$table][$column])) {
        $managers[$table]->editColumn($column, $column, (string) $sql->getValue('dbtype'), (int) $sql->getValue('dblength'), $default);
    } else {
        $managers[$table]->addColumn($column, (string) $sql->getValue('dbtype'), (int) $sql->getValue('dblength'), $default);
    }

    unset($columns[$table][$column]);
    $sql->next();
}
