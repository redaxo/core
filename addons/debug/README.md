Debug-Addon
===========

The debug addon extends REDAXO with tools for better performance and error analysis.

It is based on [Clockwork](https://github.com/itsgoingd/clockwork) and thus offers a [browser-based interface](https://github.com/underground-works/clockwork-app),
to visualise the processes within the REDAXO CMS.

<blockquote>
Clockwork is a development tool for PHP available right in your browser.
Clockwork gives you an insight into your application runtime - including request data,
performance metrics, log entries, database queries, cache queries, redis commands, dispatched events, queued jobs,
rendered views and more - for HTTP requests, commands, queue jobs and tests.
<footer>Clockwork Project</footer>
</blockquote>

It can be used directly in the browser or with a separate browser extension.
A detailed description and information on optional browser extensions are available on the [Clockwork website](https://underground.works/clockwork).

## Installation

The addon is intended for development only. Add it as a dev dependency and install it via the REDAXO console:

```bash
composer require --dev redaxo/debug
bin/console addon:install debug
```

## Usage

The addon integrates information on the following classes in Clockwork:
- `Redaxo\Core\Database\Sql`
- `Redaxo\Core\Log\Logger`
- `Redaxo\Core\Util\Timer`
- `Redaxo\Core\ExtensionPoint\Extension` / `Redaxo\Core\ExtensionPoint\ExtensionPoint`

To make your own PHP code visible in Clockwork and to analyse it, it can be measured using `Redaxo\Core\Util\Timer`:

```php
use Redaxo\Core\Util\Timer;

Timer::measure('a-representative-label', function () {
    // arbitrary php code
});
```
