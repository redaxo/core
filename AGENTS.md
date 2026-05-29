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
php .tools/bin/console                      # List all CLI commands (Symfony Console)
php .tools/bin/console setup:run            # Run setup
php .tools/bin/console migrate              # Sync DB schema with core + addons after a code update
docker-compose up -d                        # Start (port 80)
REDAXO_PORT=8080 docker-compose up -d       # Start on custom port
```

## Architecture

### What belongs to this repo
This repository contains the **REDAXO core** under `src/` (`Redaxo\Core\`), backend pages (`pages/`), templates (`fragments/`), translations (`lang/`), setup (`setup/`) and assets (`assets/`, `assets_src/`). Two **dev-only addons** live under `addons/`: `debug` and `test`.

### Key concepts
- **`Core` static class** (`src/Core.php`) — central application registry for paths, config, request, current user.
- **Addon system** — `Addon` / `AddonManager` (`src/Addon/`). Each addon has a `package.yml` (metadata + page config) plus optional `boot.php` (runtime init) and `install.php` (schema/data setup — must be idempotent, runs on every `console migrate`).
- **Extension points** — REDAXO's hook/event system: register listeners with `Extension::register('NAME', ...)`, fire points with `Extension::registerPoint(new ExtensionPoint(...))`. Classes live under `Redaxo\Core\ExtensionPoint`. This is the primary integration mechanism for addons.
- **Fragments** (`fragments/`) — template snippets rendered via `Fragment` (`src/View/Fragment.php`).
- **Boot flow** — `AbstractProject` (Symfony `RuntimeInterface`) drives boot via `boot/core.php` → `boot/addons.php` → environment entry (`boot/backend.php`, `boot/frontend.php`, `boot/console.php`).

### Class naming
All core classes live in the `Redaxo\Core\` namespace, mapped to `src/`. Tests live under `tests/` mirroring the `src/` layout, namespace `Redaxo\Core\Tests`, files named `*Test.php`.

## Coding Standards

- 4 spaces indentation, LF line endings, UTF-8
- Soft line limit ~120 chars (applies to comments too — don't wrap at 80)
- Code style enforced by rector + php-cs-fixer (custom REDAXO config) — run `composer cs` after edits
- PHPStan level 6 + Psalm level 1, both with baselines in `.tools/phpstan/` and `.tools/psalm/`
- PHPUnit strict mode: warnings, notices, deprecations all fail the build
- In YAML files, prefer single quotes when quoting is needed
- Comments and commit messages in English; commits use conventional commits (`feat:`, `fix:`, `refactor:`, `docs:`, `chore:`, `test:`, `style:`, `ci:`)
- **Properties**: prefer modern public properties (with `readonly`, asymmetric visibility, property hooks as appropriate) over getter/setter methods

### Baselines
The static analysis baselines exist to grandfather pre-existing issues. **New code must not add to the baselines** — fix the issue instead. Only regenerate baselines (`composer baseline`) when intentionally accepting new findings, and call that out in the PR.

## Backwards Compatibility

- `6.x` is actively under development and has not shipped a stable release yet. Breaking changes to public APIs are acceptable while we still can — prefer **fixing the design** over piling on `@deprecated` shims.
- `@internal`-marked symbols may change without notice.
- DB schema changes belong in the relevant install/update path.
- Behavior that needs to stay aligned with `5.x` (because changes are regularly merged up) should be kept structurally close to its `5.x` counterpart unless the divergence is intentional.
