Debug-Addon
===========

Das Debug-Addon erweitert REDAXO um Werkzeuge zur besseren Performance- und Fehler-Analyse.

Es basiert auf [Clockwork](https://github.com/itsgoingd/clockwork) und bietet damit eine [browserbasierte Oberfläche](https://github.com/underground-works/clockwork-app),
um die Abläufe innerhalb des REDAXO CMS zu visualisieren.

<blockquote>
Clockwork ist ein Entwicklungswerkzeug für PHP, das direkt in Ihrem Browser verfügbar ist.
Clockwork gibt Ihnen einen Einblick in die Laufzeit Ihrer Anwendung - einschließlich Anfragedaten,
Performance-Metriken, Log-Einträge, Datenbankabfragen, Cache-Abfragen, Redis-Befehle, Dispatched Events, Queue-Jobs,
gerenderte Ansichten und mehr - für HTTP-Anfragen, Befehle, Warteschlangenjobs und Tests.
<footer>Clockwork Project</footer>
</blockquote>

Es kann sowohl direkt im Browser, als auch mit einer separaten Browser-Erweiterung verwendet werden.
Eine ausführliche Beschreibung und die Informationen zu optionalen Browser-Erweiterung sind auf der [Clockwork-Website](https://underground.works/clockwork) verfügbar.

Das AddOn integriert Informationen zu folgenden Klassen in Clockwork:
- `Redaxo\Core\Database\Sql`
- `rex_logger`
- `rex_timer`
- `rex_extension/rex_extension_point`

Um eigenen PHP-Code in Clockwork sichtbar zu machen und damit zu analysieren, kann dieser mittels `rex_timer` gemessen werden:

```php
<?php
    rex_timer::measure('ein-repraesentatives-label', function() {
        // beliebiger php-code
    });
```
