<?php

/**
 * @package redaxo\backup
 */
class rex_cronjob_export extends rex_cronjob
{
    public const DEFAULT_FILENAME = '%REX_SERVER_%Y%m%d_%H%M_rex%REX_VERSION';

    public function execute()
    {
        $filename = $this->getParam('filename', self::DEFAULT_FILENAME);
        $filename = str_replace('%REX_SERVER', rex_string::normalize(rex::getServerName(), '-'), $filename);
        $filename = str_replace('%REX_VERSION', rex::getVersion(), $filename);
        $now = new DateTimeImmutable();
        $filename = str_replace(
            ['%Y', '%m', '%d', '%H', '%M', '%S'],
            [$now->format('Y'), $now->format('m'), $now->format('d'), $now->format('H'), $now->format('i'), $now->format('s')],
            $filename,
        );
        $file = $filename;
        $dir = rex_backup::getDir() . '/';
        $ext = '.cronjob.sql';
        $filename .= $ext;

        $excludedTables = $this->getParam('exclude_tables');
        $excludedTables = $excludedTables ? explode('|', $excludedTables) : [];
        $tables = array_diff(rex_backup::getTables(), $excludedTables);

        if (is_file($dir . $file . $ext)) {
            $i = 1;
            while (is_file($dir . $file . '_' . $i . $ext)) {
                ++$i;
            }
            $file = $file . '_' . $i;
        }
        $exportFilePath = $dir . $file . $ext;

        if (rex_backup::exportDb($exportFilePath, $tables)) {
            $message = rex_path::basename($exportFilePath) . ' created';

            if ($this->getParam('compress')) {
                $compressor = new rex_backup_file_compressor();
                $gzPath = $compressor->gzCompress($exportFilePath);
                if ($gzPath) {
                    rex_file::delete($exportFilePath);

                    $message = rex_path::basename($gzPath) . ' created';
                    $exportFilePath = $gzPath;
                    $filename .= '.gz';
                }
            }

            if ($this->getParam('delete_interval') || $this->getParam('delete_max_age')) {
                /** @var list<string> $allSqlfiles */
                $allSqlfiles = array_merge(
                    glob(rex_path::addonData('backup', '*' . $ext), GLOB_NOSORT),
                    glob(rex_path::addonData('backup', '*' . $ext . '.gz'), GLOB_NOSORT),
                );
                $countDeleted = 0;

                if ($this->getParam('delete_interval')) {
                    $backups = [];
                    $limit = strtotime('-1 month'); // Generelle Vorhaltezeit: 1 Monat

                    foreach ($allSqlfiles as $sqlFile) {
                        $timestamp = filectime($sqlFile);

                        if ($timestamp > $limit) {
                            // wenn es die generelle Vorhaltezeit unterschreitet
                            continue;
                        }

                        $backups[$sqlFile] = $timestamp;
                    }

                    asort($backups, SORT_NUMERIC);

                    $step = '';

                    foreach ($backups as $backup => $timestamp) {
                        $stepLast = $step;
                        $step = date($this->getParam('delete_interval'), (int) $timestamp);

                        if ($stepLast !== $step) {
                            // wenn es zu diesem Interval schon ein Backup gibt
                            continue;
                        }

                        // dann löschen
                        rex_file::delete($backup);
                        ++$countDeleted;
                    }
                }

                if ($this->getParam('delete_max_age')) {
                    $maxAgeLimit = strtotime('-' . (int) $this->getParam('delete_max_age') . ' months');

                    foreach ($allSqlfiles as $sqlFile) {
                        if (!is_file($sqlFile)) {
                            continue;
                        }

                        $timestamp = filectime($sqlFile);

                        if ($timestamp < $maxAgeLimit) {
                            rex_file::delete($sqlFile);
                            ++$countDeleted;
                        }
                    }
                }

                if ($countDeleted) {
                    $message .= ', ' . $countDeleted . ' old backup(s) deleted';
                }
            }

            if ($this->getParam('sendmail')) {
                if (!rex_addon::get('phpmailer')->isAvailable()) {
                    $this->setMessage($message . ', mail not sent (addon "phpmailer" isn\'t activated)');

                    return false;
                }
                $mail = new rex_mailer();
                $mail->addAddress($this->getParam('mailaddress'));
                $mail->Subject = rex_i18n::rawMsg('backup_mail_subject');
                $mail->Body = rex_i18n::rawMsg('backup_mail_body', rex::getServerName());
                $mail->addAttachment($exportFilePath, $filename);
                if ($mail->send()) {
                    $this->setMessage($message . ', mail sent');

                    return true;
                }
                $this->setMessage($message . ', mail not sent');

                return false;
            }

            $this->setMessage($message);

            return true;
        }
        $this->setMessage($file . $ext . ' not created');

        return false;
    }

    public function getTypeName()
    {
        return rex_i18n::msg('backup_database_export');
    }

    public function getParamFields()
    {
        $tables = rex_backup::getTables();

        $fields = [
            [
                'label' => rex_i18n::msg('backup_filename'),
                'name' => 'filename',
                'type' => 'text',
                'default' => self::DEFAULT_FILENAME,
                'notice' => rex_i18n::msg('backup_filename_notice'),
            ],
            [
                'label' => rex_i18n::msg('backup_exclude_tables'),
                'name' => 'exclude_tables',
                'type' => 'select',
                'attributes' => ['multiple' => 'multiple', 'data-live-search' => 'true'],
                'options' => array_combine($tables, $tables),
                'notice' => rex_i18n::msg('backup_exclude_tables_notice'),
            ],
            [
                'name' => 'sendmail',
                'type' => 'checkbox',
                'options' => [1 => rex_i18n::msg('backup_send_mail')],
            ],
        ];

        if (rex_addon::get('phpmailer')->isAvailable()) {
            $fields[] = [
                'label' => rex_i18n::msg('backup_mailaddress'),
                'name' => 'mailaddress',
                'type' => 'text',
                'visible_if' => ['sendmail' => 1],
            ];
        } else {
            $fields[2]['notice'] = rex_i18n::msg('backup_send_mail_notice');
            $fields[2]['attributes'] = ['disabled' => 'disabled'];
        }

        $fields[] = [
            'name' => 'compress',
            'type' => 'checkbox',
            'options' => [1 => rex_i18n::msg('backup_compress')],
        ];

        $fields[] = [
            'label' => rex_i18n::msg('backup_delete_interval'),
            'name' => 'delete_interval',
            'type' => 'select',
            'options' => [
                '0' => rex_i18n::msg('backup_delete_interval_off'),
                'YW' => rex_i18n::msg('backup_delete_interval_weekly'),
                'YM' => rex_i18n::msg('backup_delete_interval_monthly'), ],
            'default' => 'YW',
            'notice' => rex_i18n::msg('backup_delete_interval_notice'),
        ];

        $fields[] = [
            'label' => rex_i18n::msg('backup_delete_max_age'),
            'name' => 'delete_max_age',
            'type' => 'select',
            'options' => [
                '0' => rex_i18n::msg('backup_delete_max_age_off'),
                '3' => rex_i18n::msg('backup_delete_max_age_3months'),
                '6' => rex_i18n::msg('backup_delete_max_age_6months'),
                '12' => rex_i18n::msg('backup_delete_max_age_12months'),
                '24' => rex_i18n::msg('backup_delete_max_age_24months'),
            ],
            'default' => '0',
            'notice' => rex_i18n::msg('backup_delete_max_age_notice'),
        ];

        return $fields;
    }
}
