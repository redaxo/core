# AGENTS.md

This file provides guidance to AI coding assistants working with code in this repository.

## Project Overview

REDAXO is a PHP Content Management System. This repository (`redaxo/core`) contains the core. PHP 8.5+ is required.

The default development branch is `6.x` — PRs target this branch unless stated otherwise. `5.x` is the maintenance branch.

## Common Commands

### Quality Checks
```bash
composer check          # Run all checks (cs + sa + phpunit + taint)
composer cs              # Code style: rector + php-cs-fixer (fixes in place — no dry-run script)
composer sa              # Static analysis: phpstan + psalm
composer phpunit         # Run all test suites
composer baseline        # Regenerate all analysis baselines
```

### Individual Tools
```bash
composer phpstan        # PHPStan
composer psalm          # Psalm
composer psalm:no-cache # Psalm with a cleared cache (see note below)
composer taint          # Psalm taint analysis
composer rector         # Rector
composer cs-fixer       # php-cs-fixer
```

> **Stale Psalm cache:** If `composer psalm` reports errors that look unrelated to your changes (or that disappear/reappear
> depending on which files you touched), rerun with `composer psalm:no-cache` before assuming the errors are real or going
> through git-stash gymnastics to compare against the baseline.

### Running a Single Test
```bash
vendor/bin/phpunit --filter testMethodName
vendor/bin/phpunit tests/Database/SqlTest.php
```

### Console & Docker
```bash
php project/bin/console                      # List all CLI commands (Symfony Console)
php project/bin/console setup:run            # Run setup
php project/bin/console migrate              # Bring the DB in line with the code after a code update
php project/bin/console migrate:status       # List pending migrations (exits non-zero if there are any)
php project/bin/console migrate:make <desc>  # Create a migration file (--package=core|project|<addon>)
docker-compose up -d                        # Start (port 80)
REDAXO_PORT=8080 docker-compose up -d       # Start on custom port
```

## Architecture

### What belongs to this repo
This repository **is** the `redaxo/core` package — the package is the whole repository root, not a subdirectory. Everything in the root ships as core except the dev-only paths excluded from the dist via `.gitattributes` `export-ignore` (notably `addons/`, `project/`, `tests/`, `assets/scss/`, `package.json`, the dotfiles and the analysis configs). Core's PHP classes (`Redaxo\Core\`) live in `src/`, but core equally comprises the boot sequence (`boot/`), backend pages (`pages/`), fragment templates (`fragments/`), translations (`lang/`), setup (`setup/`), DB schemas (`schemas/`) and the committed `assets/` (compiled CSS plus vendor JS/fonts).

### Backend assets build
The backend CSS is compiled from the SCSS sources in `assets/scss/` with Dart Sass, and the external libraries (Bootstrap, Font Awesome, jQuery etc.) are pulled in via npm (`package.json`). Both the compiled CSS and the copied vendor files are **committed** to `assets/` so that Composer consumers never run npm/sass. Rebuild with `npm install && npm run build` (`build:css` runs Dart Sass, `build:js` copies vendor files via `.tools/build/copy-vendor-assets.mjs`); the `Build Assets` workflow does this automatically on PRs. A couple of vendor files that can't be consumed without a bundler (`jquery-ui.custom.min.js`, `clipboard-copy-element.js`) stay hand-vendored in `assets/`. There is no runtime SCSS compilation and no way for addons to inject SCSS — backend restyling is done with plain CSS.

In `package.json` the split is by *what ends up in the product*: `dependencies` are the libraries whose files are copied or compiled into `assets/`, `devDependencies` are tools that only run while building or testing (Dart Sass, esbuild, Playwright). As with the composer dev tools, all versions are pinned exactly and `package-lock.json` is not committed; `.tools/bin/update-tools` bumps the `devDependencies`, while the shipped libraries are updated deliberately because they change the built output. All font files live in `assets/fonts/`, one subdirectory per library (`font-awesome/`, `bootstrap/`).

Two addons live under `addons/`, but they are different in nature: **`debug`** is the `redaxo/debug` package — a real, published addon that is split out into a standalone repository via git subtree split and required here only as a dev dependency. **`test`** is an internal helper used solely within this repo for testing and is *not* published.

The **`project/` skeleton** is the `redaxo/project` package — published standalone via git subtree split and used with `composer create-project` — which also doubles as this repo's local development instance (see *Local dev instance* below). Visual-test fixtures (modules/templates) live under `.tools/fixtures/` (namespace `Redaxo\Core\Fixtures`), so they stay out of the shipped skeleton.

### Key concepts
- **`Core` static class** (`src/Core.php`) — central application registry for paths, config, request, current user.
- **Addon system** — `Addon` / `AddonManager` (`src/Addon/`). Each addon is a subclass of `Addon`, registered via composer.json `extra.redaxo.addon-class`. Metadata comes from composer.json; integration happens through overridable hooks — `boot()` (runtime init), `install()`/`uninstall()` (schema/data setup — must be idempotent, runs on every `console migrate`), `getPages()` (backend pages) — plus the `$load` and `$defaultConfig` properties.
- **Schema & migrations** — two complementary mechanisms, both driven by `console migrate`. *Convergent:* core, every installed addon and the project describe the target state of the tables they own in their `install()` hook (via `Table`/`Column`), which is re-applied on every run and must be idempotent. *Versioned:* steps that should run exactly once — an expensive data backfill, DDL for tables no package owns — live as `Migration` files in a `migrations/` directory of the core, an addon or the project, found by `glob` and tracked in the ledger table `rex_migration` (mechanism in `src/Migration/`). Both cover installed addons that are currently *deactivated*, so their schema does not fall behind. A fresh install/setup *baselines* migrations (records them without running), so everything a fresh instance needs must be in `install()`, not in a migration.  
  Which of the two fits is a case-by-case call. `install()` is often the better place even for renames and cleanups: it is idempotent, works from any starting state, and you control the order within the hook, so rescue-before-destroy can be written as one guarded block (`if ($table->hasColumn(…)) { …; $table->removeColumn(…); }`). A migration buys you the ledger — use it when repeating the check on every deploy is what you want to avoid.
- **Extension points** — REDAXO's hook/event system: register listeners with `Extension::register('NAME', ...)`, fire points with `Extension::dispatch(new ExtensionPoint(...))`. Classes live under `Redaxo\Core\ExtensionPoint`. This is the primary integration mechanism for addons.
- **Fragments** (`fragments/`) — template snippets rendered via `Fragment` (`src/View/Fragment.php`).
- **Boot flow** — `AbstractProject` (Symfony `RuntimeInterface`) drives boot via `boot/core.php` → `boot/addons.php` → environment entry (`boot/backend.php`, `boot/frontend.php`, `boot/console.php`). The entry points live in the project (`project/public/index.php`, `project/public/redaxo/index.php`, `project/bin/console`). The project hooks into boot twice: `configure()` during the core boot (static configuration, before any addon is loaded) and `boot()` after the core and all addons have booted (runtime initialization).
- **Local dev instance** — `project/` is loaded as a Composer path repo and symlinked into `vendor/redaxo/project`. `composer install`/`update` runs `.tools/bin/init-project`, which writes the gitignored `project/vendor/autoload_runtime.php` shim (delegating to the root autoloader). Run the app via `project/bin/console` and the `project/public/` document root; runtime data lives in `project/var/` (gitignored).

### Class naming
All core classes live in the `Redaxo\Core\` namespace, mapped to `src/`. Tests live under `tests/` mirroring the `src/` layout, namespace `Redaxo\Core\Tests`, files named `*Test.php`.

## Coding Standards

- 4 spaces indentation, LF line endings, UTF-8
- Soft line limit ~120 chars (applies to comments too — don't wrap at 80)
- In multi-line phpdoc descriptions (e.g. for `@param`), indent continuation lines by 4 spaces — don't align them with the start of the description text
- Code style enforced by rector + php-cs-fixer (custom REDAXO config) — run `composer cs` after edits
- PHPStan level 6 + Psalm level 1, both with baselines in `.tools/phpstan/` and `.tools/psalm/`
- PHPUnit strict mode: warnings, notices, deprecations all fail the build
- In tests, prefer data providers over repeating similar test methods; name providers with a `provide` prefix and place each one directly after its test method
- In YAML files, prefer single quotes when quoting is needed
- Comments and commit messages in English; commits use conventional commits (`feat:`, `fix:`, `refactor:`, `docs:`, `chore:`, `test:`, `style:`, `ci:`). The same rules (English + conventional commit prefix) apply to PR titles, since they become the commit message on squash merge
- Don't write bare `@name` tokens in commit messages (e.g. annotations like `@internal`/`@deprecated`, or anything that looks like a username): GitHub autolinks them into user mentions and pings a random account. Wrap them in backticks (`` `@internal` ``) — GitHub renders code spans in commit messages and doesn't link mentions inside them
- **Properties**: prefer modern public properties (with `readonly`, asymmetric visibility, property hooks as appropriate) over getter/setter methods
- Don't put issue references (`Fixes #123`, `Closes #123`) in commit messages — they belong in the PR description instead

### Comments
The measure for a code comment is whether it helps someone looking at this code in a year — not whether it explains the change being made right now.

- Don't comment the diff ("this used to be X") — that belongs in the commit message or PR description.
- Leave out what the code already says, e.g. restating a method or variable name.
- Keep what stays compact — one precise sentence beats three explanatory ones.

### Baselines
The static analysis baselines exist to grandfather pre-existing issues. **New code must not add to the baselines** — fix the issue instead. Only regenerate baselines (`composer baseline`) when intentionally accepting new findings, and call that out in the PR.

## Backwards Compatibility

- `6.x` is actively under development and has not shipped a stable release yet. Breaking changes to public APIs are acceptable while we still can — prefer **fixing the design** over piling on `@deprecated` shims.
- `@internal`-marked symbols may change without notice.
- Mark new classes `@internal` by default. Only omit it when the class is deliberately part of the public API. In that case, review every public property, constant, and method individually and mark `@internal` each one that isn't meant to be a public contract — public visibility alone shouldn't lock you into supporting it.
- DB schema changes belong in the relevant install/update path.
- Behavior that needs to stay aligned with `5.x` (because changes are regularly merged up) should be kept structurally close to its `5.x` counterpart unless the divergence is intentional.
