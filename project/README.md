# REDAXO project skeleton

Create a new REDAXO project:

```bash
composer create-project redaxo/project:6.x-dev my-project
cd my-project
```

> [!NOTE]
> The `:6.x-dev` requests the development version, which is required as long as
> REDAXO 6 has no stable release yet. Once it does, plain
> `composer create-project redaxo/project my-project` is enough.

Point your web server's document root to `public/`, then run the setup:

```bash
php bin/console setup:run
```

## Structure

```
bin/console               Console entry point (Symfony Console)
public/                   Document root (point the web server here)
public/index.php          Frontend controller
public/redaxo/index.php   Backend controller
public/media/             Media pool files
src/                      Your project code (Project class, templates, modules, …)
src/Project.php           Project class (boot configuration)
var/                      Runtime data (cache, data, logs, …)
```

## Contributing

This repository is read-only. The project skeleton is developed in the [REDAXO core repository](https://github.com/redaxo/core) and split out here automatically. That's why issues and pull requests are disabled — please report issues and open pull requests in the core repository.
