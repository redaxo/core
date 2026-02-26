<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @package redaxo\core
 *
 * @internal
 */
class rex_command_setup_run extends rex_console_command implements rex_command_only_setup_packages
{
    /** @var SymfonyStyle */
    private $io;

    /** @var InputInterface */
    private $input;

    /** @var bool */
    private $forceAsking = false;

    protected function configure(): void
    {
        $this
            ->setDescription('Perform redaxo setup')
            ->addOption('lang', null, InputOption::VALUE_REQUIRED, 'System language e.g. "de_de" or "en_gb"', null, static fn () => rex_i18n::getLocales())
            ->addOption('agree-license', null, InputOption::VALUE_NONE, 'Accept license terms and conditions') // BC, not used anymore
            ->addOption('server', null, InputOption::VALUE_REQUIRED, 'Website URL e.g. "https://example.org/"')
            ->addOption('servername', null, InputOption::VALUE_REQUIRED, 'Website name')
            ->addOption('error-email', null, InputOption::VALUE_REQUIRED, 'Error mail address e.g. "info@example.org"')
            ->addOption('timezone', null, InputOption::VALUE_REQUIRED, 'Timezone e.g. "Europe/Berlin"', null, static fn () => DateTimeZone::listIdentifiers())
            ->addOption('db-host', null, InputOption::VALUE_REQUIRED, 'Database hostname e.g. "localhost" or "127.0.0.1"')
            ->addOption('db-login', null, InputOption::VALUE_REQUIRED, 'Database username e.g. "root"')
            ->addOption('db-password', null, InputOption::VALUE_REQUIRED, 'Database user password')
            ->addOption('db-name', null, InputOption::VALUE_REQUIRED, 'Database name e.g. "redaxo"')
            ->addOption('db-createdb', null, InputOption::VALUE_REQUIRED, 'Creates the database "yes" or "no"', null, ['yes', 'no'])
            ->addOption('db-setup', null, InputOption::VALUE_REQUIRED, 'Database setup mode e.g. "normal", "override" or "import"', null, ['normal', 'override', 'import'])
            ->addOption('db-charset', null, InputOption::VALUE_REQUIRED, 'Database charset "utf8" or "utf8mb4"', null, ['utf8mb4', 'utf8'])
            ->addOption('db-import', null, InputOption::VALUE_REQUIRED, 'Database import filename if "import" is used as --db-setup')
            ->addOption('db-ssl-ca', null, InputOption::VALUE_OPTIONAL, 'Path to SSL Certificate Authority file or use without value to enable CA mode', false)
            ->addOption('db-ssl-key', null, InputOption::VALUE_REQUIRED, 'Path to SSL key file')
            ->addOption('db-ssl-cert', null, InputOption::VALUE_REQUIRED, 'Path to SSL certificate file')
            ->addOption('db-ssl-verify-server-cert', null, InputOption::VALUE_REQUIRED, 'Verify SSL server certificate (yes/no)', null, ['yes', 'no'])
            ->addOption('admin-username', null, InputOption::VALUE_REQUIRED, 'Creates a redaxo admin user with the given username')
            ->addOption('admin-password', null, InputOption::VALUE_REQUIRED, 'Sets the password for the admin user account')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getStyle($input, $output);

        $this->io = $io;
        $this->input = $input;

        $configFile = rex_path::coreData('config.yml');
        /**
         * @var array{
         *     setup: bool,
         *     instname: string|null,
         *     lang: string|null,
         *     server: string|null,
         *     servername: string|null,
         *     error_email: string|null,
         *     timezone: string|null,
         *     db: array{1: array{
         *         host: string|null,
         *         login: string|null,
         *         password: string|null,
         *         name: string|null,
         *         ssl_ca?: string|bool|null,
         *         ssl_key?: string|null,
         *         ssl_cert?: string|null,
         *         ssl_verify_server_cert?: bool
         *     }},
         * } $config
         */
        $config = array_merge(
            rex_file::getConfig(rex_path::core('default.config.yml')),
            rex_file::getConfig($configFile),
        );

        $requiredValue = static function ($value) {
            if (empty($value)) {
                throw new InvalidArgumentException('Value required');
            }
            return $value;
        };

        rex_setup::init();

        // ---------------------------------- Step 1 . Language
        $io->title('Step 1 of 5 / Language');
        $langs = [];
        foreach (rex_i18n::getLocales() as $locale) {
            $langs[$locale] = rex_i18n::msgInLocale('lang', $locale);
        }
        ksort($langs);

        $config['lang'] = $this->getOptionOrAsk(
            new ChoiceQuestion('Please select a language', $langs, $config['lang'] ?? null),
            'lang',
            null,
            'Language "%s" selected.',
            static function ($value) use ($langs) {
                if (!$value || !array_key_exists($value, $langs)) {
                    throw new InvalidArgumentException('Unknown language "' . $value . '" specified');
                }
                return $value;
            },
        );

        // ---------------------------------- Step 2 . Perms, Environment
        $io->title('Step 2 of 5 / System check');

        $io->warning('The checks are executed only in the cli environment and do not guarantee correctness in the web server environment.');

        if (0 !== $code = $this->performSystemcheck()) {
            return $code;
        }

        // ---------------------------------- step 3 . Config
        $io->title('Step 3 of 5 / Creating config');

        $io->section('General');

        $config['server'] = $this->getOptionOrAsk(
            'Website URL',
            'server',
            $config['server'],
            'Using website URL "%s"',
            $requiredValue,
        );

        $config['servername'] = $this->getOptionOrAsk(
            'Website name',
            'servername',
            $config['servername'],
            'Using website name "%s"',
            $requiredValue,
        );

        $config['error_email'] = $this->getOptionOrAsk(
            'E-mail address in case of errors',
            'error-email',
            $config['error_email'],
            'Using "%s" in case of errors',
            $requiredValue,
        );

        $timezones = rex_type::array(DateTimeZone::listIdentifiers());

        $q = new Question('Choose timezone', $config['timezone']);
        $q->setAutocompleterValues($timezones);
        $q->setValidator(static function ($value) {
            if (!@date_default_timezone_set($value)) {
                throw new InvalidArgumentException('Time zone invalid');
            }
            return $value;
        });

        $config['timezone'] = $this->getOptionOrAsk(
            $q,
            'timezone',
            $config['timezone'],
            'Timezone "%s" selected',
            static function ($value) use ($timezones) {
                if (!in_array($value, $timezones, true)) {
                    throw new InvalidArgumentException('Unknown timezone "' . $value . '" specified');
                }
                return $value;
            },
        );

        $io->section('Database information');

        do {
            $config['db'][1]['host'] = $this->getOptionOrAsk(
                'MySQL Host',
                'db-host',
                $config['db'][1]['host'],
                'Using MySQL Host "%s"',
                $requiredValue,
            );
            $config['db'][1]['login'] = $this->getOptionOrAsk(
                'Login',
                'db-login',
                $config['db'][1]['login'],
                'Using database login "%s"',
                $requiredValue,
            );

            $q = new Question('Password');
            $q->setHidden(true);

            $config['db'][1]['password'] = $this->getOptionOrAsk(
                $q,
                'db-password',
                '',
                'Using database password *secret*',
                null,
            );

            $config['db'][1]['name'] = $this->getOptionOrAsk(
                'Database name',
                'db-name',
                $config['db'][1]['name'],
                'Using database name "%s"',
                $requiredValue,
            );

            $sslRequired = $input->isInteractive() && $this->io->confirm('Configure SSL database connection?', false);
            $sslConfigured = false; // Track if any SSL option was configured

            $sslCa = $input->getOption('db-ssl-ca');
            if ($sslRequired && ($this->forceAsking || false === $sslCa)) {
                /** @var string $sslCaChoice */
                $sslCaChoice = $this->io->choice('SSL Certificate Authority', [
                    'none' => 'No CA verification',
                    'system' => 'Use system CA (recommended for managed databases)',
                    'file' => 'Specify CA certificate file path',
                ], 'none');

                if ('none' === $sslCaChoice) {
                    $config['db'][1]['ssl_ca'] = null;
                } elseif ('system' === $sslCaChoice) {
                    $config['db'][1]['ssl_ca'] = true;
                    $sslConfigured = true;
                } elseif ('file' === $sslCaChoice) {
                    $config['db'][1]['ssl_ca'] = rex_type::string($this->io->ask('Path to CA certificate file', null, static function (mixed $path): string {
                        if (!$path || !is_string($path)) {
                            throw new InvalidArgumentException('CA certificate file path required');
                        }
                        if (!is_file($path) || !is_readable($path)) {
                            throw new InvalidArgumentException('SSL CA file not found or not readable: ' . $path);
                        }
                        return $path;
                    }));
                    $sslConfigured = true;
                }
            } elseif (false === $sslCa) {
                $config['db'][1]['ssl_ca'] = null;
            } elseif (null === $sslCa || true === $sslCa) {
                $config['db'][1]['ssl_ca'] = true;
                $io->success('Using SSL system CA verification');
                $sslConfigured = true;
            } else {
                if (!is_file($sslCa) || !is_readable($sslCa)) {
                    throw new InvalidArgumentException('SSL CA file not found or not readable: ' . $sslCa);
                }
                $config['db'][1]['ssl_ca'] = $sslCa;
                $io->success(sprintf('Using SSL CA file "%s"', $sslCa));
                $sslConfigured = true;
            }

            $sslClientCertificateRequired = $sslRequired && $this->io->confirm('Use client certificate authentication?', false);

            foreach (['ssl_key' => 'key', 'ssl_cert' => 'certificate'] as $key => $name) {
                $value = rex_type::nullOrString($input->getOption('db-' . str_replace('_', '-', $key)));
                if ($sslClientCertificateRequired && (null === $value || $this->forceAsking)) {
                    $value = rex_type::string($io->ask("Path to SSL $name file", rex_type::nullOrString($config['db'][1][$key] ?? null), static function (string $value) use ($name) {
                        if (!$value) {
                            throw new InvalidArgumentException("SSL $name file path required");
                        }
                        if (!is_file($value) || !is_readable($value)) {
                            throw new InvalidArgumentException("SSL $name file not found or not readable: " . $value);
                        }
                        return $value;
                    }));
                    $sslConfigured = true;
                } elseif (is_string($value)) {
                    if (!is_file($value) || !is_readable($value)) {
                        throw new InvalidArgumentException("SSL $name file not found or not readable: " . $value);
                    }
                    $io->success(sprintf('Using SSL %s file "%s"', $name, $value));
                    $sslConfigured = true;
                }
                $config['db'][1][$key] = $value;
            }

            $sslVerifyServerCert = $input->getOption('db-ssl-verify-server-cert');
            if (
                $sslRequired && $sslConfigured
                && (null === $sslVerifyServerCert || $this->forceAsking)
            ) {
                $config['db'][1]['ssl_verify_server_cert'] = $this->io->confirm('Verify SSL server certificate?', true);
            } elseif (null !== $sslVerifyServerCert) {
                $config['db'][1]['ssl_verify_server_cert'] = 'yes' === $sslVerifyServerCert || 'true' === $sslVerifyServerCert;
                $io->success('SSL server certificate verification ' . ($config['db'][1]['ssl_verify_server_cert'] ? 'enabled' : 'disabled'));
            } else {
                $config['db'][1]['ssl_verify_server_cert'] = true;
            }

            $dbCreate = $this->getOptionOrAsk(
                new ConfirmationQuestion('Create database?', false),
                'db-createdb',
                false,
                null,
                static function ($value) {
                    if (!in_array($value, ['yes', 'no', 'true', 'false'], true)) {
                        throw new InvalidArgumentException('Unknown value "' . $value . '" specified');
                    }
                    return $value;
                },
            );

            if (is_string($dbCreate)) {
                $dbCreate = 'yes' === $dbCreate || 'true' === $dbCreate;
                $io->success('Database will ' . ($dbCreate ? '' : 'not ') . 'be created');
            }

            rex::setProperty('db', $config['db']);
            try {
                $err = rex_setup::checkDb($config, $dbCreate);
            } catch (PDOException $e) {
                $err = 'The following error occured: ' . $e->getMessage();
            }

            if ('' !== $err) {
                $io->error($err);
                if (!$input->isInteractive()) {
                    return 1;
                }
                $this->forceAsking = true;
            }
        } while ('' !== $err);
        $this->forceAsking = false;

        $io->success('Database connection successfully established');

        // ---------------------------------- step 4 . create db / demo
        $io->title('Step 4 of 5 / Database');

        $sql = rex_sql::factory();
        $dbEol = rex_setup::checkDbSecurity();
        if (!empty($dbEol)) {
            foreach ($dbEol as $warning) {
                $io->warning($warning);
            }
        } else {
            $io->block('Database version: ' . $sql->getDbType() . ' ' . $sql->getDbVersion());
        }

        // Search for exports
        $backups = [];

        if (rex_addon::exists('backup')) {
            // force loading rex_backup class, even if backup addon is not installed
            require_once rex_path::addon('backup', 'lib/backup.php');

            foreach (rex_backup::getBackupFiles('') as $file) {
                $file = preg_replace('/\.sql(?:\.gz)?$/', '', $file, -1, $count);
                if ($count) {
                    $backups[] = $file;
                }
            }
        }

        $tablesComplete = '' == rex_setup_importer::verifyDbSchema();

        // spaces before/after to make sf-console render the array-key instead of
        // our overlong description text
        $defaultDbMode = ' normal ';
        $createdbOptions = [
            'normal' => 'Setup database',
            'override' => 'Setup database and overwrite it if it exitsts already (Caution - All existing data will be deleted!)',
            'existing' => 'Database already exists (Continue without database import)',
            'update' => 'Update database (Update from previous version)',
            'import' => 'Import existing database export',
        ];

        if ($tablesComplete) {
            $defaultDbMode = ' existing ';
        } else {
            unset($createdbOptions['existing']);
        }
        if (0 === count($backups)) {
            unset($createdbOptions['import']);
        }

        $createdb = $this->getOptionOrAsk(
            new ChoiceQuestion('Choose database setup', $createdbOptions, $defaultDbMode),
            'db-setup',
            null,
            null,
            static function ($value) use ($createdbOptions) {
                if (!array_key_exists($value, $createdbOptions)) {
                    throw new InvalidArgumentException('Unknown db-setup value "' . $value . '".');
                }
                return $value;
            },
        );
        $io->success('Using "' . $createdb . '" database setup');

        if ('update' == $createdb) {
            $useUtf8mb4 = 'utf8mb4' === $this->getDbCharset();
            rex_sql_table::setUtf8mb4($useUtf8mb4);
            $error = rex_setup_importer::updateFromPrevious();
            rex::setConfig('utf8mb4', $useUtf8mb4);
            $io->success('Database successfully updated');
        } elseif ('import' == $createdb) {
            $importName = $input->getOption('db-import') ?? $io->askQuestion(new ChoiceQuestion('Please choose a database export', $backups));
            $importName = rex_type::string($importName);
            if (!in_array($importName, $backups, true)) {
                throw new InvalidArgumentException('Unknown import file "' . $importName . '" specified');
            }
            $error = rex_setup_importer::loadExistingImport($importName);
            $io->success('Database successfully imported using file "' . $importName . '"');
        } elseif ('existing' == $createdb && $tablesComplete) {
            $error = rex_setup_importer::databaseAlreadyExists();
            $io->success('Skipping database setup');
        } elseif ('override' == $createdb) {
            $useUtf8mb4 = 'utf8mb4' === $this->getDbCharset();
            rex_sql_table::setUtf8mb4($useUtf8mb4);
            $error = rex_setup_importer::overrideExisting();
            rex::setConfig('utf8mb4', $useUtf8mb4);
            $io->success('Database successfully overwritten');
        } elseif ('normal' == $createdb) {
            $useUtf8mb4 = 'utf8mb4' === $this->getDbCharset();
            rex_sql_table::setUtf8mb4($useUtf8mb4);
            $error = rex_setup_importer::prepareEmptyDb();
            rex::setConfig('utf8mb4', $useUtf8mb4);
            $io->success('Database successfully created');
        } else {
            $error = 'An undefinied error occurred';
        }

        if ('' !== $error) {
            $io->error($this->decodeMessage($error));
            return 1;
        }

        $error = rex_setup_importer::verifyDbSchema();
        if ('' != $error) {
            $io->error($this->decodeMessage($error));
            return 1;
        }

        rex_clang_service::generateCache();
        rex::setConfig('version', rex::getVersion());

        // ---------------------------------- Step 5 . Create User
        $io->title('Step 5 of 5 / User');

        $user = rex_sql::factory();
        $user
            ->setTable(rex::getTable('user'))
            ->select();

        $skipUserCreation = $user->getRows() > 0;

        // Admin creation not needed, but ask the cli user
        if ($input->isInteractive() && $skipUserCreation) {
            $skipUserCreation = $io->confirm('User(s) already exist. Skip user creation?');
        }

        // Admin account exists already, but the cli user wants to create another one
        if ($skipUserCreation && !$input->isInteractive() && (null !== $input->getOption('admin-username') || null !== $input->getOption('admin-password'))) {
            $skipUserCreation = false;
        }

        if (!$skipUserCreation) {
            $login = $this->getOptionOrAsk(
                'Username',
                'admin-username',
                null,
                'Settings admin username "%s"',
                static function ($login) {
                    if (empty($login)) {
                        throw new InvalidArgumentException('Provide a username.');
                    }
                    $user = rex_sql::factory();
                    $user
                        ->setTable(rex::getTable('user'))
                        ->setWhere(['login' => $login])
                        ->select();

                    if ($user->getRows()) {
                        throw new InvalidArgumentException(sprintf('User "%s" already exists.', $login));
                    }
                    return $login;
                },
            );

            $passwordPolicy = rex_backend_password_policy::factory();
            $pwValidator = static function ($password) use ($passwordPolicy) {
                if (true !== $msg = $passwordPolicy->check($password)) {
                    throw new InvalidArgumentException($msg);
                }

                return $password;
            };

            $description = $passwordPolicy->getDescription();
            $description = $description ? ' (' . $description . ')' : '';

            $pwQuestion = new Question('Password' . $description);
            $pwQuestion->setHidden(true);
            $pwQuestion->setValidator($pwValidator);
            $password = $this->getOptionOrAsk(
                $pwQuestion,
                'admin-password',
                null,
                'Setting admin password: *secret*',
                $pwValidator,
            );

            $passwordHash = rex_backend_login::passwordHash($password);

            $user = rex_sql::factory();
            $user->setTable(rex::getTablePrefix() . 'user');
            $user->setValue('login', $login);
            $user->setValue('password', $passwordHash);
            $user->setValue('admin', 1);
            $user->addGlobalCreateFields('console');
            $user->addGlobalUpdateFields('console');
            $user->setDateTimeValue('password_changed', time());
            $user->setArrayValue('previous_passwords', $passwordPolicy->updatePreviousPasswords(null, $passwordHash));
            $user->setValue('status', '1');
            $user->insert();

            $io->success(sprintf('User "%s" successfully created.', $login));
        } else {
            $io->success('No additional admin user created');
        }

        // ---------------------------------- last step. save config

        if (empty($config['instname'])) {
            $config['instname'] = 'rex' . date('YmdHis');
        }

        $config['setup'] = is_array($config['setup']) ? $config['setup'] : false;
        if (!rex_file::putConfig($configFile, $config)) {
            $io->error('Writing to config.yml failed.');
            return 1;
        }
        rex_file::delete(rex_path::coreCache('config.yml.cache'));

        $io->success('Congratulations! REDAXO has successfully been installed.');
        return 0;
    }

    /** @return bool|string false|utf8|utf8mb4 */
    private function getDbCharset()
    {
        $charset = $this->input->getOption('db-charset');

        if ($charset) {
            if (!in_array($charset, ['utf8', 'utf8mb4'])) {
                throw new InvalidArgumentException('unknown database charset "' . $charset . '" specified');
            }
            if ('utf8mb4' === $charset && !rex_setup_importer::supportsUtf8mb4()) {
                $sql = rex_sql::factory();
                throw new InvalidArgumentException('The utf8mb4 charset in REDAXO requires at least MySQL 5.7.7 or MariaDB 10.2. You are using ' . $sql->getDbType() . ' ' . $sql->getDbVersion());
            }
            $this->io->success('Using database charset "' . $charset . '"');
            return $charset;
        }

        if (!$this->input->isInteractive()) {
            $charset = rex_setup_importer::supportsUtf8mb4() ? 'utf8mb4' : 'utf8';
            $this->io->success('Using database charset "' . $charset . '"');
            return $charset;
        }

        if (!rex_setup_importer::supportsUtf8mb4()) {
            $sql = rex_sql::factory();
            $this->io->writeln('The utf8mb4 charset in REDAXO requires at least MySQL 5.7.7 or MariaDB 10.2. You are using ' . $sql->getDbType() . ' ' . $sql->getDbVersion());
            $this->io->writeln('utf8 is deprecated and will removed in future versions of REDAXO.');
            if ($this->io->confirm('Continue with charset utf8 ?', false)) {
                $this->io->success('Using database charset "utf8"');
                return 'utf8';
            }
            throw new Exception('You need to use utf8 or upgrade your database to newer version');
        }

        $charset = $this->io->askQuestion(new ChoiceQuestion('Choose database charset', [
            'utf8mb4' => '(recommended) Requires at least MySQL 5.7.7 or MariaDB 10.2. Complete unicode support including emojis and more special characters',
            'utf8' => '(deprecated) non-standard utf8 mode. Won\'t be support in future versions of REDAXO',
        ], ' utf8mb4 '));
        $this->io->success('Using database charset "' . $charset . '"');
        return $charset;
    }

    /**
     * Helper function for getting values by option or ask()
     * Respects non-/interactive mode.
     *
     * @param string|Question $question provide question string or full question object for ask()
     * @param string $option cli option name
     * @param string|bool|null $default default value for ask()
     * @param string|null $successMessage success message for using the option value
     * @param callable(mixed):mixed|null $validator validator callback for option value and ask()
     *
     * @return mixed
     */
    private function getOptionOrAsk($question, string $option, $default = null, ?string $successMessage = null, ?callable $validator = null)
    {
        $optionValue = $this->input->getOption($option);
        if (!$this->forceAsking && null !== $optionValue) {
            if ($validator && !$validator($optionValue)) {
                return $default;
            }
            if ($successMessage) {
                $this->io->success(sprintf($successMessage, rex_type::string($optionValue)));
            }
            return $optionValue;
        }

        if (!$this->input->isInteractive()) {
            if (null !== $default) {
                if ($successMessage) {
                    $this->io->success(sprintf($successMessage, rex_type::string($default)));
                }
                return $default;
            }
            throw new InvalidArgumentException(sprintf('Required option "--%s" is missing', $option));
        }

        if ($question instanceof Question) {
            return $this->io->askQuestion($question);
        }

        return $this->io->ask($question, rex_type::nullOrString($default), $validator);
    }

    private function performSystemcheck(): int
    {
        /** Cloned from comannd setup:check*/
        $errors = rex_setup::checkEnvironment();
        if (0 == count($errors)) {
            $phpEol = rex_setup::checkPhpSecurity();
            if (!empty($phpEol)) {
                foreach ($phpEol as $warning) {
                    $this->io->warning($warning);
                }
            } else {
                $this->io->success($this->decodeMessage(rex_i18n::msg('setup_208', PHP_VERSION)));
            }
        } else {
            $errors = array_map($this->decodeMessage(...), $errors);
            $this->io->error("PHP version errors:\n" . implode("\n", $errors));
            return 1;
        }

        $res = rex_setup::checkFilesystem();
        if (count($res) > 0) {
            $errors = [];
            foreach ($res as $key => $messages) {
                if (count($messages) > 0) {
                    $affectedFiles = [];
                    foreach ($messages as $message) {
                        $affectedFiles[] = '- ' . rex_path::relative($message);
                    }
                    $errors[] = rex_i18n::msg($key) . "\n" . implode("\n", $affectedFiles);
                }
            }

            $errors = array_map($this->decodeMessage(...), $errors);
            $this->io->error("Directory permissions error:\n" . implode("\n", $errors));
            return 1;
        }
        $this->io->success('Directory permissions ok');

        return 0;
    }
}
