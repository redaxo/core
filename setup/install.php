<?php

use Redaxo\Core\Config;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Column;
use Redaxo\Core\Database\ForeignKey;
use Redaxo\Core\Database\Index;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Database\Table;

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
    ->ensureColumn(new Column('template', 'varchar(191)', true))
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
    ->ensureColumn(new Column('module', 'varchar(191)'))
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
    ->ensureIndex(new Index('slice_priority', ['article_id', 'priority', 'module']))
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
    ->ensureColumn(new Column('module', 'varchar(191)'))
    ->ensureGlobalColumns()
    ->ensureColumn(new Column('revision', 'int(11)'))
    ->setPrimaryKey('id')
    ->ensureIndex(new Index('snapshot', ['article_id', 'clang_id', 'revision', 'history_date']))
    ->ensure();

$sql = Sql::factory();
$sql->setQuery('UPDATE ' . Core::getTablePrefix() . 'article_slice set revision=0 where revision<1 or revision IS NULL');

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
