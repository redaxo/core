REDAXO Composer Plugin
======================

Composer integration of [REDAXO](https://redaxo.org) projects, e.g. defaults that would otherwise have to be
configured in each project's `composer.json`. It is required by `redaxo/core`, so every REDAXO project gets it
automatically — it only has to be allowed in the project's `composer.json`:

```json
{
    "config": {
        "allow-plugins": {
            "redaxo/composer-plugin": true
        }
    }
}
```

This repository is a read-only subtree split of [redaxo/core](https://github.com/redaxo/core). Please open issues
and pull requests there.
