<?php

rex_sql_table::get(rex::getTable('clang'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('code', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('name', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('priority', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('status', 'tinyint(1)'))
    ->ensureColumn(new rex_sql_column('revision', 'int(10) unsigned'))
    ->ensure();

$sql = rex_sql::factory();
if (!$sql->setQuery('SELECT 1 FROM ' . rex::getTable('clang') . ' LIMIT 1')->getRows()) {
    $sql->setTable(rex::getTable('clang'));
    $sql->setValues(['id' => 1, 'code' => 'de', 'name' => 'deutsch', 'priority' => 1, 'status' => 1, 'revision' => 0]);
    $sql->insert();
}

rex_sql_table::get(rex::getTable('config'))
    ->removeColumn('id')
    ->ensureColumn(new rex_sql_column('namespace', 'varchar(75)'))
    ->ensureColumn(new rex_sql_column('key', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('value', 'text'))
    ->setPrimaryKey(['namespace', 'key'])
    ->ensure();

$table = rex_sql_table::get(rex::getTable('user'));
$hasPasswordChanged = $table->hasColumn('password_changed');
$table
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('name', 'varchar(255)', true))
    ->ensureColumn(new rex_sql_column('description', 'text', true))
    ->ensureColumn(new rex_sql_column('login', 'varchar(50)'))
    ->ensureColumn(new rex_sql_column('password', 'varchar(255)', true))
    ->ensureColumn(new rex_sql_column('email', 'varchar(255)', true))
    ->ensureColumn(new rex_sql_column('status', 'tinyint(1)'))
    ->ensureColumn(new rex_sql_column('admin', 'tinyint(1)'))
    ->ensureColumn(new rex_sql_column('language', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('startpage', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('role', 'text', true))
    ->ensureColumn(new rex_sql_column('theme', 'varchar(255)', true))
    ->ensureColumn(new rex_sql_column('login_tries', 'tinyint(4)', false, '0'))
    ->ensureGlobalColumns()
    ->ensureColumn(new rex_sql_column('password_changed', 'datetime', true))
    ->ensureColumn(new rex_sql_column('previous_passwords', 'text'))
    ->ensureColumn(new rex_sql_column('password_change_required', 'tinyint(1)'))
    ->ensureColumn(new rex_sql_column('lasttrydate', 'datetime', true))
    ->ensureColumn(new rex_sql_column('lastlogin', 'datetime', true))
    ->ensureColumn(new rex_sql_column('session_id', 'varchar(255)', true))
    ->ensureColumn(new rex_sql_column('revision', 'int(10) unsigned'))
    ->ensureIndex(new rex_sql_index('login', ['login'], rex_sql_index::UNIQUE))
    ->removeColumn('cookiekey')
    ->ensure();

if (!$hasPasswordChanged) {
    rex_sql::factory()
        ->setTable(rex::getTable('user'))
        ->setRawValue('password_changed', 'updatedate')
        ->update();
}

// Clean up invalid '0000-00-00 00:00:00' datetime values
rex_sql::factory()
    ->setTable(rex::getTable('user'))
    ->setWhere('password_changed = :invalid_date', ['invalid_date' => '0000-00-00 00:00:00'])
    ->setValue('password_changed', null)
    ->update();

rex_sql::factory()
    ->setTable(rex::getTable('user'))
    ->setWhere('lasttrydate = :invalid_date', ['invalid_date' => '0000-00-00 00:00:00'])
    ->setValue('lasttrydate', null)
    ->update();

// The foreign key references a varchar column (passkey id).
// We always remove the foreign key here, so that it is possible to change the charset of passkey id column.
$sessionTable = rex_sql_table::get(rex::getTable('user_session'));
if ($sessionTable->exists()) {
    $sessionTable->removeForeignKey(rex::getTable('user_session') . '_passkey_id')->alter();
}

rex_sql_table::get(rex::getTable('user_passkey'))
    ->ensureColumn(new rex_sql_column('id', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('user_id', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('public_key', 'text'))
    ->ensureColumn(new rex_sql_column('createdate', 'datetime'))
    ->setPrimaryKey('id')
    ->ensureForeignKey(new rex_sql_foreign_key(rex::getTable('user_passkey') . '_user_id', rex::getTable('user'), ['user_id' => 'id'], rex_sql_foreign_key::CASCADE, rex_sql_foreign_key::CASCADE))
    ->ensure();

$sessionTable
    ->ensureColumn(new rex_sql_column('session_id', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('user_id', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('cookie_key', 'varchar(255)', true))
    ->ensureColumn(new rex_sql_column('passkey_id', 'varchar(255)', true))
    ->ensureColumn(new rex_sql_column('ip', 'varchar(39)')) // max for ipv6
    ->ensureColumn(new rex_sql_column('useragent', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('starttime', 'datetime'))
    ->ensureColumn(new rex_sql_column('last_activity', 'datetime'))
    ->setPrimaryKey('session_id')
    ->ensureIndex(new rex_sql_index('cookie_key', ['cookie_key'], rex_sql_index::UNIQUE))
    ->ensureForeignKey(new rex_sql_foreign_key(rex::getTable('user_session') . '_user_id', rex::getTable('user'), ['user_id' => 'id'], rex_sql_foreign_key::CASCADE, rex_sql_foreign_key::CASCADE))
    ->ensureForeignKey(new rex_sql_foreign_key(rex::getTable('user_session') . '_passkey_id', rex::getTable('user_passkey'), ['passkey_id' => 'id'], rex_sql_foreign_key::CASCADE, rex_sql_foreign_key::CASCADE))
    ->ensure();
