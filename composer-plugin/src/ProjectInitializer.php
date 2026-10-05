<?php

namespace Redaxo\ComposerPlugin;

use Composer\Json\JsonManipulator;

use function basename;
use function bin2hex;
use function file_get_contents;
use function file_put_contents;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function random_bytes;
use function str_replace;
use function strtolower;
use function trim;

/**
 * Turns a freshly created `redaxo/project` skeleton into a clean project.
 *
 * @internal
 */
final readonly class ProjectInitializer
{
    private string $name;

    /** @param string $dir Absolute path with native directory separators */
    public function __construct(
        private string $dir,
    ) {
        /** @psalm-suppress ForbiddenCode */
        $this->name = basename($dir);
    }

    public function initialize(): void
    {
        $this->cleanUpComposerJson();
        $this->initEnv();
        $this->writeReadme();
    }

    private function cleanUpComposerJson(): void
    {
        $file = $this->dir . '/composer.json';
        // JsonManipulator preserves the formatting and blank lines of the file.
        $manipulator = new JsonManipulator((string) file_get_contents($file));

        // Strip the skeleton's own package identity.
        foreach (['name', 'description', 'keywords', 'homepage', 'authors', 'support'] as $key) {
            $manipulator->removeMainKey($key);
        }

        // A project created from this skeleton is private by default.
        $manipulator->addMainKey('license', 'proprietary');

        file_put_contents($file, $manipulator->getContents());
    }

    private function initEnv(): void
    {
        $file = $this->dir . '/.env';

        $slug = trim(strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $this->name)), '-');
        $id = ($slug ?: 'redaxo') . '-' . bin2hex(random_bytes(4));

        // single quotes keep dotenv from interpreting spaces, `#` or `$`, but cannot be escaped themselves
        $name = str_replace("'", '', $this->name);
        if (!preg_match('/^[\w.-]+$/', $name)) {
            $name = "'" . $name . "'";
        }

        $content = (string) file_get_contents($file);
        $content = (string) preg_replace('/^REX_INSTANCE_ID=.*$/m', 'REX_INSTANCE_ID=' . $id, $content, 1);
        $content = (string) preg_replace_callback('/^REX_INSTANCE_NAME=.*$/m', static fn () => 'REX_INSTANCE_NAME=' . $name, $content, 1);

        file_put_contents($file, $content);
    }

    private function writeReadme(): void
    {
        $readme = "# {$this->name}\n\n" . <<<'MARKDOWN'
            A website project based on [REDAXO](https://redaxo.org).

            ## Setup

            Install the dependencies and run the setup:

            ```bash
            composer install
            php bin/console setup
            ```

            Point your web server's document root at the `public/` directory.

            ## Updating

            After `composer update` — or after pulling changes that update REDAXO or its
            addons — sync the database schema with the code:

            ```bash
            php bin/console migrate
            ```

            MARKDOWN;

        file_put_contents($this->dir . '/README.md', $readme);
    }
}
