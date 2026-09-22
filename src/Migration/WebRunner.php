<?php

namespace Redaxo\Core\Migration;

use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Console\Command\MigrateCommand;
use Redaxo\Core\Console\Command\UserCreateCommand;
use Redaxo\Core\Core;
use Redaxo\Core\Database\ConnectionConfig;
use Redaxo\Core\Database\Exception\CouldNotConnectException;
use Redaxo\Core\Database\Exception\SqlException;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Env;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Security\BackendLogin;
use Redaxo\Core\Setup\Setup;
use Redaxo\Core\Translation\I18n;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\Output;
use Throwable;

use function array_map;
use function hash_equals;
use function implode;
use function Redaxo\Core\View\escape;

/**
 * The two pages an instance shows while it is not usable yet.
 *
 * {@see showGettingStarted()} is plain signposting and needs no authorization, because it tells nobody
 * anything they could act on: an installation without a database or without a user has nothing to protect,
 * and the page offers no way to change that. It still spells out the way only in the dev mode — a live
 * instance that ends up here is a broken one, not one waiting to be installed, and gets a bare 503.
 *
 * {@see handle()} does the work, for instances that are deployed without shell access. It exists only while
 * `REX_MIGRATE_TOKEN` is set and never in the hardened mode, where a static secret is exactly the kind of
 * web-reachable shortcut that is being ruled out. All it can do is run the migration and, as long as nobody
 * exists, create the first admin; everything else is configuration and has to be in place before.
 *
 * The token is not invalidated afterwards, so removing or rotating it is the operator's job. It does however
 * live in the same place as the database connection, so an instance that loses its environment loses this
 * endpoint with it.
 *
 * @internal
 */
final class WebRunner
{
    /** Guards against a second request migrating the same database in parallel. */
    private const string LOCK = 'rex.migrate';

    private const string PARAM = 'rex_migrate';

    /** The backend palette, inlined: these pages run before any asset was published. */
    private const string STYLESHEET = <<<'CSS'
        :root {
            --bg: #f3f6fb; --panel: #fff; --border: #dfe3e9; --text: #324050;
            --blue: #4b9ad9; --logo: #324050; --code: #f3f6fb; --fail: #c9302c;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #151c22; --panel: #202b35; --border: #26323f; --text: rgba(255, 255, 255, .75);
                --blue: #409be4; --logo: rgba(255, 255, 255, .8); --code: #1b232c; --fail: #e77;
            }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 2rem 1rem; background: var(--bg); color: var(--text);
            font: 15px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        main { margin: 0 auto; max-width: 44rem; }
        .rex-redaxo-logo { height: 26px; margin-bottom: 1.5rem; }
        .rex-redaxo-logo-r, .rex-redaxo-logo-e, .rex-redaxo-logo-d, .rex-redaxo-logo-cms { fill: var(--blue); }
        .rex-redaxo-logo-a, .rex-redaxo-logo-x, .rex-redaxo-logo-o, .rex-redaxo-logo-reg { fill: var(--logo); }
        .panel { padding: 1.5rem; background: var(--panel); border: 1px solid var(--border); border-radius: 4px; }
        h1 { margin: 0 0 1rem; font-size: 1.5rem; font-weight: 500; }
        h2 { margin: 2rem 0 .5rem; font-size: 1.1rem; font-weight: 500; }
        p, ol, ul { margin: 0 0 1rem; }
        ol, ul { padding-left: 1.2rem; }
        li { margin-bottom: .4rem; }
        a { color: var(--blue); }
        code { padding: .1em .3em; background: var(--code); border-radius: 3px; font-size: .9em; }
        pre {
            margin: 0 0 1rem; padding: .8rem 1rem; background: var(--code); border: 1px solid var(--border);
            border-radius: 3px; overflow-x: auto; font-size: .85rem; line-height: 1.5;
        }
        pre.log { white-space: pre-wrap; }
        label { display: block; margin-bottom: .25rem; font-weight: 500; }
        input {
            padding: .5rem .6rem; width: 100%; max-width: 22rem; background: var(--bg); color: inherit;
            border: 1px solid var(--border); border-radius: 3px; font: inherit;
        }
        button, .button {
            display: inline-block; padding: .6rem 1.4rem; background: var(--blue); color: #fff; border: 0;
            border-radius: 3px; font: inherit; text-decoration: none; cursor: pointer;
        }
        button:hover, .button:hover { filter: brightness(1.1); }
        .ok, .fail { font-weight: 500; }
        .fail { color: var(--fail); }
        .note {
            margin: 1.5rem 0 0; padding-top: 1rem; border-top: 1px solid var(--border); font-size: .9rem;
            opacity: .8;
        }
        CSS;

    private function __construct() {}

    /** Whether the current request addresses this endpoint at all — the token is verified in {@see handle()}. */
    public static function isRequested(): bool
    {
        return null !== Request::request(self::PARAM, 'string', null);
    }

    /** Whether the instance is missing something that keeps anybody from logging in. */
    public static function isInstallationPending(): bool
    {
        return !ConnectionConfig::exists() || !self::hasUser();
    }

    public static function handle(): never
    {
        $token = Core::isHardenedMode() ? null : Env::get('REX_MIGRATE_TOKEN');

        // one and the same response for a disabled endpoint and for a wrong token, so that neither reveals
        // whether this instance has one at all
        if (null === $token || !hash_equals($token, Request::request(self::PARAM, 'string', ''))) {
            Response::setStatus(Response::HTTP_NOT_FOUND);
            Response::sendContent('');

            exit;
        }

        set_time_limit(0);

        // english like the console, so that the page and the checks it prints do not end up in two languages
        I18n::setLocale('en_gb');

        // The migration converges the schema of every addon, so they have to be known here. Enlisted like on
        // the console, but deliberately not booted: a boot hook may well expect the very schema that is about
        // to be brought in line.
        Addon::initialize();
        foreach (Addon::getBootOrder() as $addon) {
            Addon::require($addon)->enlist();
        }
        Core::getProject()->enlist();

        self::sendHeaders();

        $install = !self::hasUser();
        $done = false;

        echo self::documentStart('Migration', $install ? 'Finish the installation' : 'Run the migration');

        try {
            if ('run' === Request::post('func', 'string', '')) {
                $done = self::run($install);
            }
        } catch (Throwable $e) {
            // the error handler would render its own page into the half-sent document
            echo '<p class="fail">' . escape($e::class . ': ' . $e->getMessage()) . '</p>';
        }

        // once it worked, what is left to do is to go to the backend and to drop the token - not to run the
        // same thing again, so the form makes way for the two things that are actually next
        echo $done ? self::renderDone($install) : self::renderForm($token, $install),
        self::renderTokenNote(),
        self::documentEnd();

        exit;
    }

    /**
     * Tells the visitor how to get this instance installed, without doing or revealing anything: the console
     * is the way, the endpoint above is the fallback when there is no shell on the server.
     */
    public static function showGettingStarted(): never
    {
        // Outside the dev mode this is a live instance whose environment is gone or wrong, not one that is
        // waiting to be installed. It gets no roadmap, and a status that says "not ready" rather than "ok".
        if (!Core::isDevMode()) {
            Response::setStatus(Response::HTTP_SERVICE_UNAVAILABLE);
            Response::sendContent(
                self::documentStart('Installation', 'This installation is not ready')
                . '<p>Please try again later.</p>'
                . self::documentEnd(),
            );

            exit;
        }

        I18n::setLocale('en_gb');

        self::sendHeaders();

        echo self::documentStart('Installation', 'REDAXO is not installed yet'),
        self::renderGettingStarted(),
        self::documentEnd();

        exit;
    }

    /** Migration and first admin in one go — on a fresh instance they are one step, not two. */
    /** @return bool Whether everything went through */
    private static function run(bool $createUser): bool
    {
        $login = Request::post('login', 'string', '');
        $password = Request::post('password', 'string', '');

        // checked before anything is written, so that a rejected password does not cost a migration run
        if ($createUser && '' !== $message = self::checkCredentials($login, $password)) {
            echo '<p class="fail">' . escape($message) . '</p>';

            return false;
        }

        if (!self::lock()) {
            echo '<p class="fail">Another migration is running right now. Wait for it to finish.</p>';

            return false;
        }

        try {
            echo '<pre class="log">';
            $code = self::runCommand(new MigrateCommand(), []);

            if (Command::SUCCESS === $code && $createUser) {
                $code = self::runCommand(new UserCreateCommand(), [
                    'login' => $login,
                    'password' => $password,
                    '--admin' => true,
                ]);
            }
            echo '</pre>';
        } finally {
            self::unlock();
        }

        if (Command::SUCCESS !== $code) {
            echo '<p class="fail">Something went wrong, see above.</p>';

            return false;
        }

        return true;
    }

    private static function renderDone(bool $installed): string
    {
        $message = $installed ? 'REDAXO is installed.' : 'The database is in line with the code.';
        $url = escape(Url::backendController());

        return <<<HTML
            <p class="ok">{$message}</p>
            <p><a class="button" href="{$url}">Open the backend</a></p>
            HTML;
    }

    /** @return string Error message, empty if the credentials are fine */
    private static function checkCredentials(string $login, string $password): string
    {
        if ('' === $login) {
            return 'Please enter a login.';
        }

        $check = BackendLogin::getPasswordPolicy()->check($password);

        return true === $check ? '' : $check;
    }

    /** @param array<string, mixed> $parameters */
    private static function runCommand(Command $command, array $parameters): int
    {
        $input = new ArrayInput($parameters);
        $input->setInteractive(false);

        // written straight to the browser, so that a long migration reports progress instead of going silent
        $output = new class extends Output {
            protected function doWrite(string $message, bool $newline): void
            {
                echo escape($message) . ($newline ? "\n" : '');
                flush();
            }
        };

        try {
            return $command->run($input, $output);
        } catch (Throwable $e) {
            echo escape("\n" . $e::class . ': ' . $e->getMessage() . "\n");

            return Command::FAILURE;
        }
    }

    private static function hasUser(): bool
    {
        return [] !== (self::selectUser() ?? []);
    }

    private static function hasSchema(): bool
    {
        return null !== self::selectUser();
    }

    /** @return list<array<string, mixed>>|null `null` if the table does not exist yet */
    private static function selectUser(): ?array
    {
        try {
            return Sql::factory()->getArray('SELECT 1 FROM rex_user LIMIT 1');
        } catch (CouldNotConnectException $e) {
            throw $e;
        } catch (SqlException $e) {
            if (Sql::ERRNO_TABLE_OR_VIEW_DOESNT_EXIST !== $e->sql?->getErrno()) {
                throw $e;
            }

            return null;
        }
    }

    private static function lock(): bool
    {
        $sql = Sql::factory();
        $sql->setQuery('SELECT GET_LOCK(?, 0) AS acquired', [self::LOCK]);

        return '1' === (string) $sql->getValue('acquired');
    }

    private static function unlock(): void
    {
        Sql::factory()->setQuery('SELECT RELEASE_LOCK(?)', [self::LOCK]);
    }

    private static function sendHeaders(): void
    {
        Response::cleanOutputBuffers();
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
    }

    private static function renderGettingStarted(): string
    {
        $param = self::PARAM;

        if (!ConnectionConfig::exists()) {
            return <<<HTML
                <p>No database connection is configured. That is configuration, and configuration comes before
                    the application — REDAXO does not ask for it in the browser.</p>

                <h2>With shell access on the server</h2>
                <pre>php project/bin/console setup</pre>
                <p>Asks for everything it needs and writes the connection to <code>project/.env.local</code>.</p>

                <h2>Without shell access</h2>
                <ol>
                    <li>Create <code>project/.env.local</code> and set <code>DATABASE_URL</code> in it. The
                        shipped <code>project/.env</code> documents the format.</li>
                    <li>Add <code>REX_MIGRATE_TOKEN</code> with a long random value.</li>
                    <li>Reload this page with <code>?{$param}=&lt;token&gt;</code> appended to its address and
                        finish the installation there.</li>
                </ol>
                HTML;
        }

        $missing = self::hasSchema() ? 'there is no user yet' : 'the database schema is not in place yet';

        return <<<HTML
            <p>The database connection is configured, but {$missing}.</p>

            <h2>With shell access on the server</h2>
            <pre>php project/bin/console migrate
            php project/bin/console user:create &lt;login&gt; --admin</pre>

            <h2>Without shell access</h2>
            <ol>
                <li>Set <code>REX_MIGRATE_TOKEN</code> in <code>project/.env.local</code> to a long random
                    value.</li>
                <li>Reload this page with <code>?{$param}=&lt;token&gt;</code> appended to its address and
                    finish the installation there.</li>
            </ol>
            HTML;
    }

    /**
     * The filesystem check the setup used to do, at the only moment it can be done properly: as the web server
     * user, which is not who runs the console. Limited to the initial installation - it walks the media and
     * assets trees, which is not something to do on every call of this page.
     */
    private static function renderSystemCheck(): string
    {
        $messages = array_map(static fn (string $error): string => '<p>' . escape($error) . '</p>', Setup::checkEnvironment());

        foreach (Setup::checkFilesystem() as $key => $paths) {
            $items = array_map(static fn (string $path): string => '<li>' . escape(Path::relative($path)) . '</li>', $paths);
            $messages[] = '<p>' . I18n::msg($key) . '</p><ul>' . implode('', $items) . '</ul>';
        }

        if ([] === $messages) {
            return '<p class="ok">PHP version and directory permissions ok.</p>';
        }

        return '<div class="fail">' . implode('', $messages) . '</div>';
    }

    private static function renderForm(string $token, bool $install): string
    {
        $param = self::PARAM;
        $action = escape(Url::backendController());
        $token = escape($token);
        $fields = '';
        $submit = 'Run migration';

        if ($install) {
            $login = escape(Request::post('login', 'string', ''));
            $submit = 'Install';
            $check = self::renderSystemCheck();
            $fields = <<<HTML
                <h2>System check</h2>
                {$check}

                <h2>Installation</h2>
                <p>The migration brings the database schema in line with the code. The admin account is created
                    along with it — offered here only until the first user exists.</p>
                <p>
                    <label for="login">Login</label>
                    <input type="text" id="login" name="login" value="{$login}" autocomplete="username" required>
                </p>
                <p>
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" autocomplete="new-password" required>
                </p>
                HTML;
        }

        return <<<HTML
            <form method="post" action="{$action}">
                <input type="hidden" name="{$param}" value="{$token}">
                <input type="hidden" name="func" value="run">
                {$fields}
                <p><button type="submit">{$submit}</button></p>
            </form>
            HTML;
    }

    private static function renderTokenNote(): string
    {
        return <<<'HTML'
            <p class="note">This page is reachable by anyone who knows <code>REX_MIGRATE_TOKEN</code>. Remove
                the variable from <code>project/.env.local</code> once the installation is up.</p>
            HTML;
    }

    /** The pages have to work before an asset was ever published, so everything is inlined. */
    private static function documentStart(string $title, string $heading): string
    {
        $title = escape($title);
        $heading = escape($heading);
        $logo = File::get(Path::core('assets/redaxo-logo.svg')) ?? '';
        $stylesheet = self::STYLESHEET;

        return <<<HTML
            <!doctype html>
            <html lang="en">
            <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="robots" content="noindex, nofollow">
            <link rel="icon" href="data:,">
            <title>REDAXO {$title}</title>
            <style>
            {$stylesheet}
            </style>
            </head>
            <body>
            <main>
            {$logo}
            <div class="panel">
            <h1>{$heading}</h1>

            HTML;
    }

    private static function documentEnd(): string
    {
        return <<<'HTML'
            </div>
            </main>
            </body>
            </html>

            HTML;
    }
}
