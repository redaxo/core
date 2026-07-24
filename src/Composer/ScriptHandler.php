<?php

namespace Redaxo\Core\Composer;

use Composer\Json\JsonManipulator;

use function basename;
use function file_get_contents;
use function file_put_contents;
use function getcwd;
use function getenv;

/**
 * Handlers for Composer script events of a REDAXO project, referenced from the project skeleton's composer.json
 * `scripts`.
 *
 * @internal
 *
 * This relies on Composer's runtime API (`JsonManipulator`, used so the file's formatting and blank lines are
 * preserved). Those classes are available while Composer executes the script, but are not part of this package's
 * dependency tree — hence this file is excluded from static analysis.
 */
final class ScriptHandler
{
    /**
     * Runs after `composer create-project redaxo/project`: turns the freshly cloned skeleton into a clean project
     * (resets composer.json, writes a project README).
     */
    public static function postCreateProject(): void
    {
        self::cleanUpComposerJson();
        self::generateInstanceId();
        self::writeReadme();

        echo "Initialized composer.json, .env and README.md for the new project.\n";
    }

    private static function cleanUpComposerJson(): void
    {
        $file = getenv('COMPOSER') ?: getcwd() . '/composer.json';
        $manipulator = new JsonManipulator((string) file_get_contents($file));

        // Strip the skeleton's own package identity.
        foreach (['name', 'description', 'keywords', 'homepage', 'authors'] as $key) {
            $manipulator->removeMainKey($key);
        }

        // A project created from this skeleton is private by default.
        $manipulator->addMainKey('license', 'proprietary');

        // Drop the skeleton's scripts block (its only entry is this create-project hook).
        $manipulator->removeMainKey('scripts');

        file_put_contents($file, $manipulator->getContents());
    }

    private static function generateInstanceId(): void
    {
        $file = getcwd() . '/.env';

        $name = trim(strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', basename((string) getcwd()))), '-');
        $id = ($name ?: 'redaxo') . '-' . bin2hex(random_bytes(4));

        $content = (string) file_get_contents($file);
        $content = (string) preg_replace('/^REX_INSTANCE_ID=.*$/m', 'REX_INSTANCE_ID=' . $id, $content, 1);

        file_put_contents($file, $content);
    }

    private static function writeReadme(): void
    {
        $name = basename((string) getcwd());

        $readme = "# {$name}\n\n" . <<<'MARKDOWN'
            A website project based on [REDAXO](https://redaxo.org).

            ## Setup

            Install the dependencies and run the setup:

            ```bash
            composer install
            php bin/console setup:run
            ```

            Point your web server's document root at the `public/` directory.

            ## Updating

            After `composer update` — or after pulling changes that update REDAXO or its
            addons — sync the database schema with the code:

            ```bash
            php bin/console migrate
            ```

            MARKDOWN;

        file_put_contents(getcwd() . '/README.md', $readme);
    }
}
