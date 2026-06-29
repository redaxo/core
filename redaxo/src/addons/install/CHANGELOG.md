Changelog
=========

Version 2.14.1 – 29.06.2026
---------------------------

### Security

* CSRF-Schutz für Addon-Update-ApiFunction fehlte (gemeldet von @de3erve-hunter) (@gharlan)


Version 2.14.0 – 09.04.2026
---------------------------

### Bugfixes

* Layout der verfügbaren Core-Versionen an Layout der Addon-Versionen angepasst (@gharlan)


Version 2.13.1 – 05.09.2025
---------------------------

### Bugfixes

* Wenn bei Addon-Updates Plugins gelöscht wurden, konnte es im Anschluss zu Fehlern kommen (teilweise erst später nach weiteren Aktionen) (@gharlan)
* Kommunikation mit redaxo.org ohne "www.", dadurch immer SSL-Zertifikat-Validierung (@gharlan)


Version 2.13.0 – 17.07.2025
---------------------------

### Neu

* Kommunikation mit redaxo.org mit SSL-Zertifikat-Validierung (@skerbis)
* AddOn-Website-Link wird als externer Link markiert und in neuem Tab geöffnet (@crydotsnake)


Version 2.12.1 – 24.02.2024
---------------------------

### Bugfixes

* Bei Fehlern während des Updatens konnte Whoops oftmals nicht dargestellt werden, weil der Temp-Ordner zu früh gelöscht wurde (@gharlan)


Version 2.12.0 – 09.02.2024
---------------------------

### Neu

* Anzeige der Download-Anzahl und optional Sortierung danach (@eaCe)


Version 2.11.1 – 18.03.2023
---------------------------

### Bugfixes

* Nach AddOn-Updates wurde die neue `package.yml` teils nicht geladen und die alte blieb im Cache (@gharlan)


Version 2.11.0 – 28.02.2023
---------------------------

### Neu

* AddOns können nach Download direkt installiert werden (@pwechs)
* Bei den verfügbaren Versionsnummern wird das Veröffentlichungsdatum per Title-Attribut gesetzt (@pwechs)
* Stabilere Addon-Updates bei Rechteproblemen (@gharlan)
* Command `install:download`: Version-Constraints werden unterstützt (@staabm)


Version 2.10.1 – 20.02.2023
---------------------------

### Bugfixes

* Bei Addon-Updates konnten Plugins nicht gelöscht werden, sie wurden immer aus dem alten Release "gerettet" (@gharlan)
* Paket-Entpackung für Windows optimiert (@gharlan)
* Cache-Dateien optimiert (@gharlan)


Version 2.10.0 – 25.07.2022
---------------------------

### Neu

* Erläuterung zum API-Key und dass bei Hinterlegung auch eigene Offline-Addons erscheinen (@tbaddade)
* Beim Upload werden automatisch Git/PhpStorm/VSCode-Dateien ignoriert (@gharlan)

### Bugfixes

* Core-Updates: Neue Default-Config-Werte aus System-Plugins wurden nicht gesetzt (@gharlan)


Version 2.9.2 – 16.11.2021
--------------------------

### Bugfixes

* Core-Update:
    - Beim Update auf 5.13 kam es teils zu einem Fehler beim Erstellen der Erfolgsmeldung im Log (@gharlan)
    - Besserer Umgang mit fehlenden Schreibrechten (@gharlan)


Version 2.9.0 – 03.03.2021
--------------------------

### Neu

* Bei Core-/AddOn-Updates wird ein Info-Eintrag ins Systemlog geschrieben (@staabm)
* Beim Laden neuer Addons wird bei Entwicklungsversionen gewarnt, wie zuvor schon bei Updates (@anveno)
* In Versionslisten wird das aktuelle Stable-Release hervorgehoben (@skerbis)
* Bei Nutzung der Suche werden die Ergebnisse nach Relevanz sortiert (@xong)
* Im Backend-Menü steht der Installer direkt unterhalb von „AddOns“ (@gharlan)

### Bugfixes

* Löschen von AddOn-Versionen:
    - Lösch-Button erschien auch im Formular zum Hochladen neuer Versionen (@gharlan)
    - Wenn man den Confirm-Dialog verneint hat, wurde die Speichernroutine ausgelöst, statt gar keiner Aktion (@gharlan)
    - Nach dem Löschen landete man in der AddOn-Liste statt in der AddOn-Detailseite (@gharlan)


Version 2.8.1 – 25.01.2021
--------------------------

### Security

* Fehlendes Escaping ergänzt (@gharlan)


Version 2.8.0 – 01.07.2020
--------------------------

### Neu

* Neue Klasse `rex_install` mit PHP-Api zum Herunterladen/Aktualisieren von Addons (@bloep)
* In Addon-Details wird die Addon-Website ausgegeben (@gharlan)

### Bugfixes

* Nach Entpacken werden die Dateirechte entsprechend der `config.yml` angepasst (@Koala, @gharlan)


Version 2.7.1 – 08.05.2020
--------------------------

### Bugfixes

* Die PHP-Mindestversion 7.1 wurde nicht geprüft (@gharlan)


Version 2.7.0 – 10.03.2020
--------------------------

### Security

* Markdown-Ausgaben und teils andere Felder waren nicht gegen XSS geschützt (@gharlan)

### Neu

* Console-Commands eingeführt:
    - `install:list`: Abruf der verfügbaren Addons (optional nur Updates) (@bloep)
    - `install:download`: Addon herunterladen (@bloep)
    - `install:update`: Addon aktualisieren (@bloep)
* Vor dem Laden/Updaten wird eine Warnung ausgegeben, wenn es sich um eine Entwicklungsversion ("beta" etc.) handelt (@staabm)

### Bugfixes

* Probleme beim Core-Update unter Windows behoben (@gharlan)
* Bessere Fehlerbehandlung (@gharlan)


Version 2.6.0 – 02.02.2020
--------------------------

### Neu

* Update-Fehlermeldungen durch neue Formatierung/Formulierung besser verständlich gemacht (@gharlan)
* Nach Herunterladen eines Addons und Klick auf "Zur Addonverwaltung" ist das Addon dort markiert (@gharlan)
* Nach Hochladen einer Addon-Version landet man in den Addon-Details, statt in der Übersicht (@gharlan)

### Bugfixes

* Beim Öffnen der Details eines Addons wird korrekt nach oben gesprungen (@gharlan)


Version 2.5.0 – 12.03.2019
--------------------------

### Neu

* Über `installer_ignore` in der `package.yml` können Addons Ordner/Dateien angeben, die beim Upload ausgeschlossen werden sollen (@schuer)
* AddOn-Beschreibung und Versions-Beschreibungen werden als Markdown geparst (@tbaddade, @bloep)
* Erläuterung zu Backup-Option in den Einstellungen (@schuer)

### Bugfixes

* Beim Update wurde mit den alten Requirements/Conflicts geprüft, wenn die neue Version keine Requirements/Conflicts mehr enthielt (@gharlan)
* Besseres Escaping mittels `rex_escape` (@bloep)


Version 2.4.0 – 05.06.2018
--------------------------

### Neu

* "Veröffentlicht am" wird ausgegeben, Addons können danach sortiert werden (@bloep)


Version 2.3.0 – 21.12.2017
--------------------------

### Neu

* CSRF-Schutz (@gharlan)

### Bugfix

* Der Opcache wurde nicht gelöscht nach Updates (@gharlan)


Version 2.2.0 – 04.10.2017
--------------------------

### Neu

* Autofokus auf Suchefeld (@skerbis)

### Bugfixes

* Bei Reload nach dem Download/Update kam es zu einer Exception (@gharlan)


Version 2.1.2 – 14.02.2017
--------------------------

### Bugfixes

* Beim Updaten kam es teilweise zum Fehler „Cannot use string offset as an array“
* htmlspecialchars fehlte an vielen Stellen, dadurch konnten teilweise die Addondetails nicht aufgerufen werden
* Wenn die Einstellungen nicht gespeichert werden konnten, kam es trotzdem zur Erfolgsmeldung


Version 2.1.1 – 15.07.2016
--------------------------

### Bugfixes

* Nach Core-Updates wurde teilweise die gleiche Version direkt wieder zum Updaten angezeigt


Version 2.1.0 – 24.03.2016
--------------------------

### Neu

* Bei Einschränkung der Addons wird nur noch Hauptversion betrachtet (5.x)


Version 2.0.2 – 09.02.2016
--------------------------

### Bugfixes

* Bessere Darstellung der alten und neuen Versionsnummer beim Überschreiben (Upload) einer Addon-Version


Version 2.0.1 – 22.01.2016
--------------------------

### Bugfixes

* Beim Download/Update großer Addons kam es teilweise zu einer Fehlermeldung
* Api-Login und Api-Key werden nicht mehr in rex_config (in der DB) gespeichert, und sind somit nicht mehr im Export enthalten
