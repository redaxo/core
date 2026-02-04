Changelog
=========

Version 2.9.4 – 05.01.2026
--------------------------

### Security

* Path Traversal beim Datei-Export verhindert (gemeldet von @lukasz-rybak) (@gharlan)


Version 2.9.1 – 09.02.2024
--------------------------

### Bugfixes

* Backup-Cronjob: Bei Mailversand hatten gz-kodierte Backup-Anhänge eine falsche Dateiendung (@gharlan)


Version 2.8.3 – 20.02.2023
--------------------------

### Bugfixes

* Backup-Cronjob: Deprecated-Meldung entfernt (@gharlan)


Version 2.8.2 – 25.07.2022
--------------------------

### Bugfixes

* Umlaute in Sprachdatei korrigiert (@frood)


Version 2.8.1 – 03.05.2022
--------------------------

### Bugfixes

* Cronjob: Beim Löschen älterer Backups wurden komprimierte Backups nicht berücksichtigt (@VIEWSION)
* Fehlermeldung optimiert beim Dateiexport (@staabm)


Version 2.8.0 – 17.11.2021
--------------------------

### Neu

* Begrifflichkeiten optimiert (@gharlan)


Version 2.7.0 – 03.03.2021
--------------------------

### Neu

* Datenbank-Backups können im Cronjob optional gz-komprimiert werden, solche können auch wieder importiert werden (@staabm)
* Es können deutlich größere Dateibackups ex-/importiert werden (bei programmatischer Nutzung von `rex_backup::exportFiles` sollte dafür der neue zweite Parameter mit einem Zielpfad gesetzt werden) (@staabm)
* Code entfernt, der die User-Tabellen anlegt nach Import, falls nicht vorhanden, da die Tabellendefinition veraltet war und die Situation im Normalfall nicht vorkommen kann (@gharlan)


Version 2.6.3 – 25.01.2021
--------------------------

### Security

* Fehlendes Escaping ergänzt (@gharlan)

### Bugfixes

* Nach Import wurde der Cache nicht gelöscht, und die Erfolgsmeldung erschien in rot (@gharlan)
* Beim Dateiimport wurde der `media`-Ordner grundsätzlich geleert, auch wenn das Backup den `media`-Ordner gar nicht enthält (@gharlan)
* Beim Download der vorhandenen Backups wurde die Datei immer doppelt geladen (erst über PJAX, dann normal) (@gharlan)
* Nach Dateiexport wurden im Formular fälschlich Tabellen- und Ordner-Auswahl angezeigt (@gharlan)


Version 2.6.2 – 11.11.2020
--------------------------

### Bugfixes

* Bei Fehlern während des Imports wurde die SQL-Query nicht escaped in der Fehlermeldung (@gharlan)


Version 2.6.1 – 01.07.2020
--------------------------

### Bugfixes

* Backup erstellen: Fehlermeldung bei ungültigen Zeichen im Dateinamen wurde fälschlich als Erfolgsmeldung ausgegeben (@frood)


Version 2.6.0 – 10.03.2020
--------------------------

### Neu

* Backups werden nach Dateiname sortiert (@bloep)

### Bugfixes

* Backup-Cronjob: Die Mail-Checkbox war nicht direkt über dem Mailadress-Feld (@gharlan)


Version 2.5.1 – 02.02.2020
--------------------------

### Bugfixes

* `NULL`-Werte wurden nicht als solche exportiert, was zu Problemen bei den neuen Template-Keys führte (@gharlan)


Version 2.5.0 – 02.02.2020
--------------------------

### Neu

* Default-Dateiname: Datum vor REDAXO-Version für bessere Sortierung (@bloep)
* Cronjob: Tabellen können ausgeschlossen werden, User-Tabelle default nun mit im Backup (@alexplusde)
* Es wird davor gewarnt, dass Import von Backups älterer REDAXO- und Addon-Versionen zu Problemen führen können (@gharlan)
* Upload-Limits werden angezeigt (@skerbis)
* Wording optimiert (@marcohanke)

### Bugfixes

* Beim Datei-Import kam es mit PHP 7.4 zu Notices (@gharlan)


Version 2.4.0 – 20.08.2019
--------------------------

### Neu

* Speicheroptimierung beim Export (@staabm)


Version 2.3.0 – 12.03.2019
--------------------------

### Neu

* Performance-Verbesserungen beim Export (@staabm)

### Bugfixes

* Views nicht exportieren (@gharlan)


Version 2.2.2 – 10.07.2018
--------------------------

### Bugfixes

* Fehlermeldung ausgeben, wenn Datei nicht gelöscht werden kann wegen fehlender Schreibrechte (@staabm)


Version 2.2.1 – 05.06.2018
--------------------------

### Bugfixes

* Kompatibilität zu PHP 7.2 (@gharlan)
* Speicherbedarf beim Export reduziert (@staabm)
* Es wird sichergestellt, dass keine unvollständigen Backups erstellt werden (@staabm)
* Backup-Cronjob: Wenn die automatische Löschung aktiviert war, funktioniert der Mailversand nicht mehr (@staabm)
* EP `BACKUP_AFTER_DB_IMPORT`: Während der Ausführung war der Cache veraltet (@gharlan)


Version 2.2.0 – 21.12.2017
--------------------------

### Neu

* CSRF-Schutz (@gharlan)


Version 2.1.0 – 04.10.2017
--------------------------

### Neu

* Neue Option im Backup-Cronjob zum automatischen Löschen alter Backups (@alexplusde, @gharlan)

### Bugfixes

* Multi-Select-Feld für Tabellen-Auswahl war zu klein (@alexplusde)


Version 2.0.4 – 14.02.2017
--------------------------

### Security

* Für Backend-Benutzer mit Zugriff auf die Export-Page waren SQL-Injections möglich

### Bugfixes

* Wenn beim Export keine Tabelle ausgewählt wurde, kam die falsche Fehlermeldung (@zorker)
* Bei fehlenden Dateirechten wurde eine Meldung ohne Übersetzung angezeigt (@skerbis)
* Im Mailtext des Backup-Cronjobs erschien `&quot;`
* Richtiger Umgang mit Foreign Keys in Backup-Dateien


Version 2.0.1 – 12.01.2016
--------------------------

### Bugfixes

* Datei-Export funktionierte nicht
