# AGENTS.md

This file provides guidance to AI coding assistants working with code in this repository.

## Project Overview

REDAXO is a PHP Content Management System (v5.x). This repository (`redaxo/core`) contains the core system and the bundled system addons. PHP 8.3+ is required.

The default development branch is `5.x` — PRs target this branch unless stated otherwise.

## Common Commands

### Quality Checks
```bash
composer check          # Run all checks (cs + sa + phpunit + taint)
composer cs             # Code style: rector dry-run + php-cs-fixer dry-run
composer cs-fix         # Code style: auto-fix
composer sa             # Static analysis: phpstan + psalm
composer phpunit        # Run all test suites
composer baseline       # Regenerate all analysis baselines
```

### Individual Tools
```bash
composer phpstan        # PHPStan
composer psalm          # Psalm
composer taint          # Psalm taint analysis
composer rector         # Rector
```

### Running a Single Test
```bash
vendor/bin/phpunit --filter test_method_name
vendor/bin/phpunit redaxo/src/core/tests/util/string_test.php
```

### Console & Docker
```bash
php redaxo/bin/console                      # List all CLI commands (Symfony Console)
php redaxo/bin/console setup:run            # Run setup
docker-compose up -d                        # Start (port 80)
REDAXO_PORT=8080 docker-compose up -d       # Start on custom port
```

## Architecture

### What belongs to this repo
Only the **core** (`redaxo/src/core/`) and the **system addons** explicitly whitelisted in `.gitignore` are part of this repository:

> backup, be_style, cronjob, debug, install, media_manager, mediapool, metainfo, phpmailer, project, structure, users

Any other addon directory under `redaxo/src/addons/` is a local-only checkout (third-party / WIP) and is **not** part of this repo. Don't edit those, don't reference them in changes, don't include them in analysis.

### Key concepts
- **`rex` static class** (`lib/rex.php`) — application state and config registry.
- **Package system** — `rex_package` (abstract) → `rex_addon` / `rex_plugin`. Each addon has a `package.yml` (metadata + page config) plus optional `boot.php` (runtime init) and `install.php` (install logic).
- **Extension points** — REDAXO's hook/event system: register listeners with `rex_extension::register('NAME', ...)`, fire points with `rex_extension::registerPoint(new rex_extension_point(...))`. This is the primary integration mechanism for addons.
- **Fragments** (`fragments/`) — template snippets rendered via `rex_fragment`.
- **Bundled vendor** — third-party libs are vendored via Composer's `replace` (see `composer.json`); `vendor/` is in the repo for runtime use.

### Class naming
All core classes use the `rex_` prefix (e.g., `rex_sql`, `rex_addon`, `rex_file`). Autoloading maps these to `lib/` directories. Tests live under `tests/` and are named `*_test.php`.

## Coding Standards

- 4 spaces indentation, LF line endings, UTF-8
- Soft line limit ~120 chars (applies to comments too — don't wrap at 80)
- Code style enforced by php-cs-fixer (custom REDAXO config) — run `composer cs-fix` after edits
- PHPStan level 6 + Psalm level 1, both with baselines in `.tools/phpstan/` and `.tools/psalm/`
- PHPUnit strict mode: warnings, notices, deprecations all fail the build
- In YAML files, prefer single quotes when quoting is needed
- Comments and commit messages in English; commits use conventional commits (`feat:`, `fix:`, `refactor:`, `docs:`, `chore:`, `test:`, `style:`, `ci:`)

### Baselines
The static analysis baselines exist to grandfather pre-existing issues. **New code must not add to the baselines** — fix the issue instead. Only regenerate baselines (`composer baseline`) when intentionally accepting new findings, and call that out in the PR.

## Backwards Compatibility

REDAXO has a large addon ecosystem; public APIs are part of the contract:

- Don't change signatures, remove public methods, or rename public classes/constants on the `5.x` branch — deprecate via `@deprecated` and keep a working shim.
- `@internal`-marked symbols may change without notice.
- New parameters on public methods must be optional.
- DB schema changes belong in the relevant addon's install/update path.
