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
    ->ensureColumn(Column::varchar('code', 255))
    ->ensureColumn(Column::varchar('name', 255))
    ->ensureColumn(Column::int('priority', unsigned: true))
    ->ensureColumn(Column::bool('status'))
    ->ensure();

$sql = Sql::factory();
if (!$sql->setQuery('SELECT 1 FROM ' . Core::getTable('clang') . ' LIMIT 1')->getRows()) {
    $sql->setTable(Core::getTable('clang'));
    $sql->setValues(['id' => 1, 'code' => 'de', 'name' => 'deutsch', 'priority' => 1, 'status' => 1]);
    $sql->insert();
}

Table::get(Core::getTable('config'))
    ->removeColumn('id')
    ->ensureColumn(Column::varchar('namespace', 75))
    ->ensureColumn(Column::varchar('key', 255))
    ->ensureColumn(Column::text('value'))
    ->setPrimaryKey(['namespace', 'key'])
    ->ensure();

Table::get(Core::getTable('article'))
    ->ensureColumn(Column::int('pid', unsigned: true, autoIncrement: true))
    ->ensureColumn(Column::int('id', unsigned: true))
    ->ensureColumn(Column::int('parent_id', unsigned: true))
    ->ensureColumn(Column::varchar('name', 255))
    ->ensureColumn(Column::varchar('catname', 255))
    ->ensureColumn(Column::int('catpriority', unsigned: true))
    ->ensureColumn(Column::bool('startarticle'))
    ->ensureColumn(Column::int('priority', unsigned: true))
    ->ensureColumn(Column::varchar('path', 255))
    ->ensureColumn(Column::bool('status'))
    ->ensureColumn(Column::varchar('template', 191, nullable: true))
    ->ensureColumn(Column::int('clang_id', unsigned: true))
    ->ensureGlobalColumns()
    ->setPrimaryKey('pid')
    ->ensureIndex(new Index('find_articles', ['id', 'clang_id'], Index::UNIQUE))
    ->ensureIndex(new Index('clang_id', ['clang_id']))
    ->ensureIndex(new Index('parent_id', ['parent_id']))
    ->removeIndex('id')
    ->ensure();

Table::get(Core::getTable('article_slice'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(Column::int('article_id', unsigned: true))
    ->ensureColumn(Column::int('clang_id', unsigned: true))
    ->ensureColumn(Column::int('ctype_id', unsigned: true))
    ->ensureColumn(Column::varchar('module', 191))
    ->ensureColumn(Column::int('revision'))
    ->ensureColumn(Column::int('priority', unsigned: true))
    ->ensureColumn(Column::bool('status', default: true))
    ->ensureColumn(Column::mediumtext('value1', nullable: true))
    ->ensureColumn(Column::mediumtext('value2', nullable: true))
    ->ensureColumn(Column::mediumtext('value3', nullable: true))
    ->ensureColumn(Column::mediumtext('value4', nullable: true))
    ->ensureColumn(Column::mediumtext('value5', nullable: true))
    ->ensureColumn(Column::mediumtext('value6', nullable: true))
    ->ensureColumn(Column::mediumtext('value7', nullable: true))
    ->ensureColumn(Column::mediumtext('value8', nullable: true))
    ->ensureColumn(Column::mediumtext('value9', nullable: true))
    ->ensureColumn(Column::mediumtext('value10', nullable: true))
    ->ensureColumn(Column::mediumtext('value11', nullable: true))
    ->ensureColumn(Column::mediumtext('value12', nullable: true))
    ->ensureColumn(Column::mediumtext('value13', nullable: true))
    ->ensureColumn(Column::mediumtext('value14', nullable: true))
    ->ensureColumn(Column::mediumtext('value15', nullable: true))
    ->ensureColumn(Column::mediumtext('value16', nullable: true))
    ->ensureColumn(Column::mediumtext('value17', nullable: true))
    ->ensureColumn(Column::mediumtext('value18', nullable: true))
    ->ensureColumn(Column::mediumtext('value19', nullable: true))
    ->ensureColumn(Column::mediumtext('value20', nullable: true))
    ->ensureColumn(Column::varchar('media1', 255, nullable: true))
    ->ensureColumn(Column::varchar('media2', 255, nullable: true))
    ->ensureColumn(Column::varchar('media3', 255, nullable: true))
    ->ensureColumn(Column::varchar('media4', 255, nullable: true))
    ->ensureColumn(Column::varchar('media5', 255, nullable: true))
    ->ensureColumn(Column::varchar('media6', 255, nullable: true))
    ->ensureColumn(Column::varchar('media7', 255, nullable: true))
    ->ensureColumn(Column::varchar('media8', 255, nullable: true))
    ->ensureColumn(Column::varchar('media9', 255, nullable: true))
    ->ensureColumn(Column::varchar('media10', 255, nullable: true))
    ->ensureColumn(Column::text('medialist1', nullable: true))
    ->ensureColumn(Column::text('medialist2', nullable: true))
    ->ensureColumn(Column::text('medialist3', nullable: true))
    ->ensureColumn(Column::text('medialist4', nullable: true))
    ->ensureColumn(Column::text('medialist5', nullable: true))
    ->ensureColumn(Column::text('medialist6', nullable: true))
    ->ensureColumn(Column::text('medialist7', nullable: true))
    ->ensureColumn(Column::text('medialist8', nullable: true))
    ->ensureColumn(Column::text('medialist9', nullable: true))
    ->ensureColumn(Column::text('medialist10', nullable: true))
    ->ensureColumn(Column::varchar('link1', 10, nullable: true))
    ->ensureColumn(Column::varchar('link2', 10, nullable: true))
    ->ensureColumn(Column::varchar('link3', 10, nullable: true))
    ->ensureColumn(Column::varchar('link4', 10, nullable: true))
    ->ensureColumn(Column::varchar('link5', 10, nullable: true))
    ->ensureColumn(Column::varchar('link6', 10, nullable: true))
    ->ensureColumn(Column::varchar('link7', 10, nullable: true))
    ->ensureColumn(Column::varchar('link8', 10, nullable: true))
    ->ensureColumn(Column::varchar('link9', 10, nullable: true))
    ->ensureColumn(Column::varchar('link10', 10, nullable: true))
    ->ensureColumn(Column::text('linklist1', nullable: true))
    ->ensureColumn(Column::text('linklist2', nullable: true))
    ->ensureColumn(Column::text('linklist3', nullable: true))
    ->ensureColumn(Column::text('linklist4', nullable: true))
    ->ensureColumn(Column::text('linklist5', nullable: true))
    ->ensureColumn(Column::text('linklist6', nullable: true))
    ->ensureColumn(Column::text('linklist7', nullable: true))
    ->ensureColumn(Column::text('linklist8', nullable: true))
    ->ensureColumn(Column::text('linklist9', nullable: true))
    ->ensureColumn(Column::text('linklist10', nullable: true))
    ->ensureGlobalColumns()
    ->ensureIndex(new Index('slice_priority', ['article_id', 'priority', 'module']))
    ->ensureIndex(new Index('find_slices', ['clang_id', 'article_id']))
    ->removeIndex('clang_id')
    ->removeIndex('article_id')
    ->ensure();

Table::get(Core::getTable('article_slice_history'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(Column::int('slice_id', unsigned: true))
    ->ensureColumn(Column::varchar('history_type', 255))
    ->ensureColumn(Column::datetime('history_date'))
    ->ensureColumn(Column::varchar('history_user', 255))
    ->ensureColumn(Column::int('clang_id', unsigned: true))
    ->ensureColumn(Column::int('ctype_id', unsigned: true))
    ->ensureColumn(Column::int('priority', unsigned: true))
    ->ensureColumn(Column::bool('status', default: true))
    ->ensureColumn(Column::mediumtext('value1', nullable: true))
    ->ensureColumn(Column::mediumtext('value2', nullable: true))
    ->ensureColumn(Column::mediumtext('value3', nullable: true))
    ->ensureColumn(Column::mediumtext('value4', nullable: true))
    ->ensureColumn(Column::mediumtext('value5', nullable: true))
    ->ensureColumn(Column::mediumtext('value6', nullable: true))
    ->ensureColumn(Column::mediumtext('value7', nullable: true))
    ->ensureColumn(Column::mediumtext('value8', nullable: true))
    ->ensureColumn(Column::mediumtext('value9', nullable: true))
    ->ensureColumn(Column::mediumtext('value10', nullable: true))
    ->ensureColumn(Column::mediumtext('value11', nullable: true))
    ->ensureColumn(Column::mediumtext('value12', nullable: true))
    ->ensureColumn(Column::mediumtext('value13', nullable: true))
    ->ensureColumn(Column::mediumtext('value14', nullable: true))
    ->ensureColumn(Column::mediumtext('value15', nullable: true))
    ->ensureColumn(Column::mediumtext('value16', nullable: true))
    ->ensureColumn(Column::mediumtext('value17', nullable: true))
    ->ensureColumn(Column::mediumtext('value18', nullable: true))
    ->ensureColumn(Column::mediumtext('value19', nullable: true))
    ->ensureColumn(Column::mediumtext('value20', nullable: true))
    ->ensureColumn(Column::varchar('media1', 255, nullable: true))
    ->ensureColumn(Column::varchar('media2', 255, nullable: true))
    ->ensureColumn(Column::varchar('media3', 255, nullable: true))
    ->ensureColumn(Column::varchar('media4', 255, nullable: true))
    ->ensureColumn(Column::varchar('media5', 255, nullable: true))
    ->ensureColumn(Column::varchar('media6', 255, nullable: true))
    ->ensureColumn(Column::varchar('media7', 255, nullable: true))
    ->ensureColumn(Column::varchar('media8', 255, nullable: true))
    ->ensureColumn(Column::varchar('media9', 255, nullable: true))
    ->ensureColumn(Column::varchar('media10', 255, nullable: true))
    ->ensureColumn(Column::text('medialist1', nullable: true))
    ->ensureColumn(Column::text('medialist2', nullable: true))
    ->ensureColumn(Column::text('medialist3', nullable: true))
    ->ensureColumn(Column::text('medialist4', nullable: true))
    ->ensureColumn(Column::text('medialist5', nullable: true))
    ->ensureColumn(Column::text('medialist6', nullable: true))
    ->ensureColumn(Column::text('medialist7', nullable: true))
    ->ensureColumn(Column::text('medialist8', nullable: true))
    ->ensureColumn(Column::text('medialist9', nullable: true))
    ->ensureColumn(Column::text('medialist10', nullable: true))
    ->ensureColumn(Column::varchar('link1', 10, nullable: true))
    ->ensureColumn(Column::varchar('link2', 10, nullable: true))
    ->ensureColumn(Column::varchar('link3', 10, nullable: true))
    ->ensureColumn(Column::varchar('link4', 10, nullable: true))
    ->ensureColumn(Column::varchar('link5', 10, nullable: true))
    ->ensureColumn(Column::varchar('link6', 10, nullable: true))
    ->ensureColumn(Column::varchar('link7', 10, nullable: true))
    ->ensureColumn(Column::varchar('link8', 10, nullable: true))
    ->ensureColumn(Column::varchar('link9', 10, nullable: true))
    ->ensureColumn(Column::varchar('link10', 10, nullable: true))
    ->ensureColumn(Column::text('linklist1', nullable: true))
    ->ensureColumn(Column::text('linklist2', nullable: true))
    ->ensureColumn(Column::text('linklist3', nullable: true))
    ->ensureColumn(Column::text('linklist4', nullable: true))
    ->ensureColumn(Column::text('linklist5', nullable: true))
    ->ensureColumn(Column::text('linklist6', nullable: true))
    ->ensureColumn(Column::text('linklist7', nullable: true))
    ->ensureColumn(Column::text('linklist8', nullable: true))
    ->ensureColumn(Column::text('linklist9', nullable: true))
    ->ensureColumn(Column::text('linklist10', nullable: true))
    ->ensureColumn(Column::int('article_id', unsigned: true))
    ->ensureColumn(Column::varchar('module', 191))
    ->ensureGlobalColumns()
    ->ensureColumn(Column::int('revision'))
    ->ensureIndex(new Index('snapshot', ['article_id', 'clang_id', 'revision', 'history_date']))
    ->ensure();

$sql = Sql::factory();
$sql->setQuery('UPDATE ' . Core::getTablePrefix() . 'article_slice set revision=0 where revision<1 or revision IS NULL');

Table::get(Core::getTable('cronjob'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(Column::varchar('name', 255, nullable: true))
    ->ensureColumn(Column::varchar('description', 255, nullable: true))
    ->ensureColumn(Column::varchar('type', 255, nullable: true))
    ->ensureColumn(Column::text('parameters', nullable: true))
    ->ensureColumn(Column::text('interval'))
    ->ensureColumn(Column::datetime('nexttime', nullable: true))
    ->ensureColumn(Column::varchar('environment', 255))
    ->ensureColumn(Column::bool('execution_moment'))
    ->ensureColumn(Column::datetime('execution_start', nullable: true))
    ->ensureColumn(Column::bool('status'))
    ->ensureGlobalColumns()
    ->ensure();

Table::get(Core::getTable('media'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(Column::int('category_id', unsigned: true))
    ->ensureColumn(Column::varchar('filetype', 255, nullable: true))
    ->ensureColumn(Column::varchar('filename', 255, nullable: true))
    ->ensureColumn(Column::varchar('originalname', 255, nullable: true))
    ->ensureColumn(Column::varchar('filesize', 255, nullable: true))
    ->ensureColumn(Column::int('width', unsigned: true, nullable: true))
    ->ensureColumn(Column::int('height', unsigned: true, nullable: true))
    ->ensureColumn(Column::varchar('title', 255, nullable: true))
    ->ensureGlobalColumns()
    ->ensureIndex(new Index('category_id', ['category_id']))
    ->ensureIndex(new Index('filename', ['filename'], Index::UNIQUE))
    ->ensure();

Table::get(Core::getTable('media_category'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(Column::varchar('name', 255))
    ->ensureColumn(Column::int('parent_id', unsigned: true))
    ->ensureColumn(Column::varchar('path', 255))
    ->ensureGlobalColumns()
    ->ensureIndex(new Index('parent_id', ['parent_id']))
    ->ensure();

Table::get(Core::getTable('user'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(Column::varchar('name', 255, nullable: true))
    ->ensureColumn(Column::text('description', nullable: true))
    ->ensureColumn(Column::varchar('login', 50))
    ->ensureColumn(Column::varchar('password', 255, nullable: true))
    ->ensureColumn(Column::varchar('email', 255, nullable: true))
    ->ensureColumn(Column::bool('status'))
    ->ensureColumn(Column::bool('admin', default: false))
    ->ensureColumn(Column::varchar('language', 255, nullable: true))
    ->ensureColumn(Column::varchar('startpage', 255, nullable: true))
    ->ensureColumn(Column::text('role', nullable: true))
    ->ensureColumn(Column::varchar('theme', 255, nullable: true))
    ->ensureColumn(Column::tinyint('login_tries', default: 0))
    ->ensureGlobalColumns()
    ->ensureColumn(Column::datetime('password_changed'))
    ->ensureColumn(Column::text('previous_passwords'))
    ->ensureColumn(Column::bool('password_change_required', default: false))
    ->ensureColumn(Column::datetime('lasttrydate', nullable: true))
    ->ensureColumn(Column::datetime('lastlogin', nullable: true))
    ->ensureColumn(Column::varchar('session_id', 255, nullable: true))
    ->ensureIndex(new Index('login', ['login'], Index::UNIQUE))
    ->removeColumn('cookiekey')
    ->ensure();

Table::get(Core::getTable('user_passkey'))
    ->ensureColumn(Column::varchar('id', 255))
    ->ensureColumn(Column::int('user_id', unsigned: true))
    ->ensureColumn(Column::text('public_key'))
    ->ensureColumn(Column::datetime('createdate'))
    ->setPrimaryKey('id')
    ->ensureForeignKey(new ForeignKey(Core::getTable('user_passkey') . '_user_id', Core::getTable('user'), ['user_id' => 'id'], ForeignKey::CASCADE, ForeignKey::CASCADE))
    ->ensure();

Table::get(Core::getTable('user_role'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(Column::varchar('name', 255, nullable: true))
    ->ensureColumn(Column::text('description', nullable: true))
    ->ensureColumn(Column::text('perms'))
    ->ensureGlobalColumns()
    ->ensure();

Table::get(Core::getTable('user_session'))
    ->ensureColumn(Column::varchar('session_id', 255))
    ->ensureColumn(Column::int('user_id', unsigned: true))
    ->ensureColumn(Column::varchar('cookie_key', 255, nullable: true))
    ->ensureColumn(Column::varchar('passkey_id', 255, nullable: true))
    ->ensureColumn(Column::varchar('ip', 39)) // max for ipv6
    ->ensureColumn(Column::varchar('useragent', 255))
    ->ensureColumn(Column::datetime('starttime'))
    ->ensureColumn(Column::datetime('last_activity'))
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
