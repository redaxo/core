<?php

namespace Redaxo\Core\Console\Command;

use DateTimeZone;
use PDOException;
use Redaxo\Core\Backup\Backup;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Language\LanguageHandler;
use Redaxo\Core\Security\BackendLogin;
use Redaxo\Core\Security\BackendPasswordPolicy;
use Redaxo\Core\Setup\Importer;
use Redaxo\Core\Setup\Setup;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\Util\Type;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

use function array_key_exists;
use function count;
use function in_array;
use function is_array;
use function is_string;
use function sprintf;

use const PHP_VERSION;

/**
 * @internal
 */
#[AsCommand(name: 'setup:run', description: 'Perform redaxo setup')]
class SetupRunCommand extends AbstractCommand implements OnlySetupAddonsInterface, AvailableInSetupInterface
{
    private SymfonyStyle $io;
    private InputInterface $input;
    private bool $forceAsking = false;

    public function __invoke(
        InputInterface $input,
        SymfonyStyle $io,
        #[Option('System language e.g. "de_de" or "en_gb"', suggestedValues: I18n::getLocales(...))] ?string $lang = null,
        #[Option('Accept license terms and conditions')] bool $agreeLicense = false, // BC, not used anymore
        #[Option('Website URL e.g. "https://example.org/"')] ?string $server = null,
        #[Option('Website name')] ?string $servername = null,
        #[Option('Error mail address e.g. "info@example.org"')] ?string $errorEmail = null,
        #[Option('Timezone e.g. "Europe/Berlin"', suggestedValues: static function () {
            return DateTimeZone::listIdentifiers();
        })] ?string $timezone = null,
        #[Option('Database hostname e.g. "localhost" or "127.0.0.1"')] ?string $dbHost = null,
        #[Option('Database username e.g. "root"')] ?string $dbLogin = null,
        #[Option('Database user password')] ?string $dbPassword = null,
        #[Option('Database name e.g. "redaxo"')] ?string $dbName = null,
        #[Option('Creates the database "yes" or "no"', suggestedValues: ['yes', 'no'])] ?string $dbCreatedb = null,
        #[Option('Database setup mode e.g. "normal", "override" or "import"', suggestedValues: ['normal', 'override', 'import'])] ?string $dbSetup = null,
        #[Option('Database import filename if "import" is used as --db-setup')] ?string $dbImport = null,
        #[Option('Path to SSL Certificate Authority file or use without value to enable CA mode')] bool|string $dbSslCa = false,
        #[Option('Path to SSL key file')] ?string $dbSslKey = null,
        #[Option('Path to SSL certificate file')] ?string $dbSslCert = null,
        #[Option('Verify SSL server certificate (yes/no)', suggestedValues: ['yes', 'no'])] ?string $dbSslVerifyServerCert = null,
        #[Option('Creates a redaxo admin user with the given username')] ?string $adminUsername = null,
        #[Option('Sets the password for the admin user account')] ?string $adminPassword = null,
    ): int {
        $this->io = $io;
        $this->input = $input;

        $configFile = Path::coreData('config.yml');
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
            File::getConfig(Path::core('setup/default.config.yml')),
            File::getConfig($configFile),
        );

        $requiredValue = static function ($value) {
            if (empty($value)) {
                throw new InvalidArgumentException('Value required');
            }
            return $value;
        };

        Setup::init();

        // ---------------------------------- Step 1 . Language
        $io->title('Step 1 of 5 / Language');
        $langs = [];
        foreach (I18n::getLocales() as $locale) {
            $langs[$locale] = I18n::msgInLocale('lang', $locale);
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

        if (Command::SUCCESS !== $code = $this->performSystemcheck()) {
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

        $timezones = Type::array(DateTimeZone::listIdentifiers());

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

        $previousHost = $config['db'][1]['host'] ?? null;
        $previousLogin = $config['db'][1]['login'] ?? null;
        $previousPassword = $config['db'][1]['password'] ?? null;

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

            $keepPassword = false;
            if ($previousPassword
                && $config['db'][1]['host'] === $previousHost
                && $config['db'][1]['login'] === $previousLogin
                && null === $input->getOption('db-password')
                && $input->isInteractive()
            ) {
                $keepPassword = $io->confirm('Keep existing database password?', true);
            }

            if (!$keepPassword) {
                $q = new Question('Password');
                $q->setHidden(true);

                $config['db'][1]['password'] = (string) $this->getOptionOrAsk(
                    $q,
                    'db-password',
                    '',
                    'Using database password *secret*',
                    null,
                );
            }

            $config['db'][1]['name'] = $this->getOptionOrAsk(
                'Database name',
                'db-name',
                $config['db'][1]['name'],
                'Using database name "%s"',
                $requiredValue,
            );

            $sslRequired = $input->isInteractive() && $this->io->confirm('Configure SSL database connection?', false);
            $sslConfigured = false; // Track if any SSL option was configured

            if ($sslRequired && ($this->forceAsking || false === $dbSslCa)) {
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
                    $config['db'][1]['ssl_ca'] = Type::string($this->io->ask('Path to CA certificate file', null, static function (mixed $path): string {
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
            } elseif (false === $dbSslCa) {
                $config['db'][1]['ssl_ca'] = null;
            } elseif (true === $dbSslCa) {
                $config['db'][1]['ssl_ca'] = true;
                $io->success('Using SSL system CA verification');
                $sslConfigured = true;
            } else {
                if (!is_file($dbSslCa) || !is_readable($dbSslCa)) {
                    throw new InvalidArgumentException('SSL CA file not found or not readable: ' . $dbSslCa);
                }
                $config['db'][1]['ssl_ca'] = $dbSslCa;
                $io->success(sprintf('Using SSL CA file "%s"', $dbSslCa));
                $sslConfigured = true;
            }

            $sslClientCertificateRequired = $sslRequired && $this->io->confirm('Use client certificate authentication?', false);

            foreach (['ssl_key' => 'key', 'ssl_cert' => 'certificate'] as $key => $name) {
                $value = Type::nullOrString($input->getOption('db-' . str_replace('_', '-', $key)));
                if ($sslClientCertificateRequired && (null === $value || $this->forceAsking)) {
                    $value = Type::string($io->ask("Path to SSL $name file", Type::nullOrString($config['db'][1][$key] ?? null), static function (string $value) use ($name) {
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

            $sslVerifyServerCert = $dbSslVerifyServerCert;
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

            Core::setProperty('db', $config['db']);
            try {
                $err = Setup::checkDb($config, $dbCreate);
            } catch (PDOException $e) {
                $err = 'The following error occured: ' . $e->getMessage();
            }

            if ('' !== $err) {
                $io->error($err);
                if (!$input->isInteractive()) {
                    return Command::FAILURE;
                }
                $this->forceAsking = true;
            }
        } while ('' !== $err);
        $this->forceAsking = false;

        $io->success('Database connection successfully established');

        // ---------------------------------- step 4 . create db / demo
        $io->title('Step 4 of 5 / Database');

        $sql = Sql::factory();
        $dbEol = Setup::checkDbSecurity();
        if (!empty($dbEol)) {
            foreach ($dbEol as $warning) {
                $io->warning($warning);
            }
        } else {
            $io->block('Database version: ' . $sql->getDbType() . ' ' . $sql->getDbVersion());
        }

        // Search for exports
        $backups = [];

        foreach (Backup::getBackupFiles('') as $file) {
            $file = preg_replace('/\.sql(?:\.gz)?$/', '', $file, -1, $count);
            if ($count) {
                $backups[] = $file;
            }
        }

        $tablesComplete = '' == Importer::verifyDbSchema();

        // spaces before/after to make sf-console render the array-key instead of
        // our overlong description text
        $defaultDbMode = ' normal ';
        $createdbOptions = [
            'normal' => 'Setup database',
            'override' => 'Setup database and overwrite it if it exitsts already (Caution - All existing data will be deleted!)',
            'existing' => 'Database already exists (Continue without database import)',
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

        if ('import' == $createdb) {
            $importName = $input->getOption('db-import') ?? $io->askQuestion(new ChoiceQuestion('Please choose a database export', $backups));
            $importName = Type::string($importName);
            if (!in_array($importName, $backups, true)) {
                throw new InvalidArgumentException('Unknown import file "' . $importName . '" specified');
            }
            $error = Importer::loadExistingImport($importName);
            $io->success('Database successfully imported using file "' . $importName . '"');
        } elseif ('existing' == $createdb && $tablesComplete) {
            $error = Importer::databaseAlreadyExists();
            $io->success('Skipping database setup');
        } elseif ('override' == $createdb) {
            $error = Importer::overrideExisting();
            $io->success('Database successfully overwritten');
        } elseif ('normal' == $createdb) {
            $error = Importer::prepareEmptyDb();
            $io->success('Database successfully created');
        } else {
            $error = 'An undefinied error occurred';
        }

        if ('' !== $error) {
            $io->error($this->decodeMessage($error));
            return Command::FAILURE;
        }

        $error = Importer::verifyDbSchema();
        if ('' != $error) {
            $io->error($this->decodeMessage($error));
            return Command::FAILURE;
        }

        LanguageHandler::generateCache();
        Core::setConfig('version', Core::getVersion());

        // ---------------------------------- Step 5 . Create User
        $io->title('Step 5 of 5 / User');

        $user = Sql::factory();
        $user
            ->setTable(Core::getTable('user'))
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
                    $user = Sql::factory();
                    $user
                        ->setTable(Core::getTable('user'))
                        ->setWhere(['login' => $login])
                        ->select();

                    if ($user->getRows()) {
                        throw new InvalidArgumentException(sprintf('User "%s" already exists.', $login));
                    }
                    return $login;
                },
            );

            $passwordPolicy = BackendPasswordPolicy::factory();
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

            $passwordHash = BackendLogin::passwordHash($password);

            $user = Sql::factory();
            $user->setTable(Core::getTablePrefix() . 'user');
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
        if (!File::putConfig($configFile, $config)) {
            $io->error('Writing to config.yml failed.');
            return Command::FAILURE;
        }
        File::delete(Path::coreCache('config.yml.cache'));

        $io->success('Congratulations! REDAXO has successfully been installed.');
        return Command::SUCCESS;
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
     */
    private function getOptionOrAsk(string|Question $question, string $option, string|bool|null $default = null, ?string $successMessage = null, ?callable $validator = null): mixed
    {
        $optionValue = $this->input->getOption($option);
        if (!$this->forceAsking && null !== $optionValue) {
            if ($validator && !$validator($optionValue)) {
                return $default;
            }
            if ($successMessage) {
                $this->io->success(sprintf($successMessage, Type::string($optionValue)));
            }
            return $optionValue;
        }

        if (!$this->input->isInteractive()) {
            if (null !== $default) {
                if ($successMessage) {
                    $this->io->success(sprintf($successMessage, Type::string($default)));
                }
                return $default;
            }
            throw new InvalidArgumentException(sprintf('Required option "--%s" is missing', $option));
        }

        if ($question instanceof Question) {
            return $this->io->askQuestion($question);
        }

        return $this->io->ask($question, Type::nullOrString($default), $validator);
    }

    private function performSystemcheck(): int
    {
        /** Cloned from comannd setup:check*/
        $errors = Setup::checkEnvironment();
        if (0 == count($errors)) {
            $phpEol = Setup::checkPhpSecurity();
            if (!empty($phpEol)) {
                foreach ($phpEol as $warning) {
                    $this->io->warning($warning);
                }
            } else {
                $this->io->success($this->decodeMessage(I18n::msg('setup_208', PHP_VERSION)));
            }
        } else {
            $errors = array_map($this->decodeMessage(...), $errors);
            $this->io->error("PHP version errors:\n" . implode("\n", $errors));
            return Command::FAILURE;
        }

        $res = Setup::checkFilesystem();
        if (count($res) > 0) {
            $errors = [];
            foreach ($res as $key => $messages) {
                if (count($messages) > 0) {
                    $affectedFiles = [];
                    foreach ($messages as $message) {
                        $affectedFiles[] = '- ' . Path::relative($message);
                    }
                    $errors[] = I18n::msg($key) . "\n" . implode("\n", $affectedFiles);
                }
            }

            $errors = array_map($this->decodeMessage(...), $errors);
            $this->io->error("Directory permissions error:\n" . implode("\n", $errors));
            return Command::FAILURE;
        }
        $this->io->success('Directory permissions ok');

        return Command::SUCCESS;
    }
}
