Changelog
=========

Version 2.17.2 – 29.06.2026
---------------------------

### Security

* XSS via Debug/Error-Ausgabe (@gharlan)


Version 2.17.0 – 09.04.2026
---------------------------

### Neu

* Update auf PHPMailer 7 (@gharlan)


Version 2.16.0 – 05.09.2025
---------------------------

### Neu

* Neue Archiv-Seite (@skerbis)

### Bugfixes

* Umgang mit Fehlern bei den "MS Graph Credentials" optimiert (@tyrant88)


Version 2.15.0 – 17.07.2025
---------------------------

### Neu

* Neuer Mailer-Typ `Microsoft365`, der über die "Microsoft Graph API" versendet (@tyrant88, @skerbis)


Version 2.14.0 – 22.10.2024
---------------------------

### Neu

* Optimierung Error-Mails (bessere Vermeidung von Mehrfachversand, `X-Mailer`-Header für Identifizierung) (@skerbis)
* Neues Konfigurationsfeld für `Return-Path` (@tyrant88)
* Hilfe-Menüpunkt nach rechts versetzt (@alxndr-w)


Version 2.13.1 – 12.03.2024
---------------------------

* Code-Optimierung (@skerbis)


Version 2.13.0 – 09.02.2024
---------------------------

### Neu

* Error-Mails:
    - Doppelte Mails werden vermieden (@skerbis)
    - URLs, wo die Fehler auftraten, werden mit ausgegeben (@skerbis)


Version 2.12.0 – 28.02.2023
---------------------------

### Neu

* Neue EPs `PHPMAILER_PRE_SEND` und `PHPMAILER_POST_SEND` (@skerbis)
* Log: Reply-To wird mitgeloggt (@skerbis)
* Archiv: Auch bei nicht erfolgreichen Versand wird die Mail archiviert (@skerbis)
* Readme erweitert (@skerbis)


Version 2.11.2 – 03.05.2022
---------------------------

### Bugfixes

* Error-Mails hatten als Absender die Error-Mailadresse, statt der in PHPMailer hinterlegten Absenderadresse, und konnten deshalb teils nicht verschickt werden (@skerbis)


Version 2.11.1 – 29.11.2021
---------------------------

### Bugfixes

* PHP 8.1: Deprecated-Meldung im Log entfernt (@gharlan)


Version 2.11.0 – 17.11.2021
---------------------------

### Neu

* Neuer EP `PHPMAILER_CONFIG`, über den die Einstellungen dynamisch angepasst werden können (@skerbis)
* Mails werden im Archiv als `.eml`-Datei abgelegt, statt in einem eigenen Format (@skerbis)
* Neuer Cronjob-Typ "Mailer-Archiv bereinigen", der die Archivdateien nach X Tagen löschen kann (@skerbis)
* Readme erweitert (@skerbis)


Version 2.10.2 – 21.06.2021
---------------------------

### Security

* Update auf PHPMailer 6.5.0, inklusive Security-Fix: https://github.com/PHPMailer/PHPMailer/releases/tag/v6.5.0


Version 2.10.1 – 29.04.2021
---------------------------

### Security

* Update auf PHPMailer 6.4.1, inklusive Security-Fix: https://github.com/PHPMailer/PHPMailer/releases/tag/v6.4.1

### Bugfixes

* Einstellungen-Seite:
    - `SMTPDebug`-Schalter wird von PHPMailer inzwischen auch bei anderen Versandmethoden verwendet, deswegen ist die Einstellung dazu nun immer sichtbar (@skerbis)
    - E-Mail-Archivierung: `for`-Attribut für Label korrigiert (@aeberhard)


Version 2.10.0 – 03.03.2021
---------------------------

### Neu

* Voreinstellung für den Mailer ist nun `smtp` statt `mail` (@gharlan)
* Mailer `mail` steht nur noch zur Auswahl, wenn die PHP-Funktion auch verfügbar ist (@skerbis)
* E-Mail-Archiv kann über neuen Button in den Einstellungen geleert werden (@skerbis)
* `phpmailer[]`-Recht mit Textbeschreibung „PHPMailer-Einstellungen“ (@skerbis)


Version 2.9.1 – 11.11.2020
--------------------------

### Bugfixes

* Testmailversand: Prüfung auf leere E-Mailadresse korrigiert (@gharlan)


Version 2.9.0 – 01.07.2020
--------------------------

### Neu

* Es kann eine E-Mailadresse angegeben werden, an die der gesamte E-Mailversand umgeleitet wird (@novinet-markusd, @gharlan)
* Readme erweitert (@skerbis)


Version 2.8.2 – 28.05.2020
--------------------------

### Security

* Update PHPMailer 6.1.6, inklusive Security-Fix für [CVE-2020-13625](https://github.com/advisories/GHSA-f7hx-fqxw-rvvj) (@gharlan)


Version 2.8.0 – 10.03.2020
--------------------------

### Neu

* Default-Verschlüsselung auf "keine" gesetzt (da "Auto" bei manchen Providern Probleme verursachte) (@skerbis)
* Log-Subpage auch im Addon verfügbar (nicht nur unter System/Log) (@skerbis)
* Erläuterungstexte verbessert (@skerbis)

### Bugfixes

* Debug-Ausgabe erscheint nun im Panel (@skerbis)


Version 2.7.0 – 02.02.2020
--------------------------

### Neu

* Vorhandene Log-Funktion (Ablegen der ganzen Mails) umbenannt in Archivierung (@skerbis)
* Neue Log-Funktion mit Zeit, Absender, Empfänger, Betreff und Meldung in Logdatei; optional für alle Mails, oder nur bei Fehlern, oder ganz deaktiviert (@skerbis)
* Hinweis in Readme, dass über SMTP keine leeren Bodys möglich sind (@skerbis)


Version 2.6.0 – 20.08.2019
--------------------------

### Neu

* Default-Timeout auf 10s gesetzt (statt 5min) (@skerbis)
* Englische Übersetzung der Readme (@skerbis)


Version 2.5.1 – 16.03.2019
--------------------------

### Bugfixes

* E-Mail-Benachrichtigung bei Fehlern wurde teilweise kontinuierlich bei jedem Seitenaufruf verschickt (@skerbis)


Version 2.5.0 – 12.03.2019
--------------------------

### Neu

* E-Mail-Benachrichtigung bei Fehlern (@phoebusryan, @skerbis)
* AutoTLS kann aktiviert/deaktiviert werden (@skerbis)
* SMTP-Einstellungen werden erst angezeigt, wenn Option gewählt. (@skerbis)
* Benutzername und Passwort werden erst angezeigt wenn Option gewählt (@skerbis)
* Bessere Test-Mails, Button-Verhalten geändert in "Speichern und testen" (@skerbis)
* Debug-Meldungen werden in Sprache des Benutzers ausgegeben (@skerbis)
* Aktualisierung Hilfe/Doku (@skerbis)


Version 2.4.1 – 10.12.2018
--------------------------

### Security

* Update des phpmailers wg. Sicherheitslücken


Version 2.4.0 – 05.06.2018
--------------------------

### Neu

* Update phpmailer 6.0.5 (@gharlan)
* Mail-Log ist default nicht mehr aktiviert (@skerbis)
* Aktualisierung Hilfe/Doku (@skerbis)


Version 2.3.0 – 21.12.2017
--------------------------

### Neu

* Update auf PHPMailer 6.0.2 (@skerbis)
* SMTP-Passwort-Feld wird nicht mehr im Klartext angezeigt (@metaphon)


Version 2.2.0 – 04.10.2017
--------------------------

### Neu

* Möglichkeit den Versand zu testen (Testmail) (@skerbis)
* Mail-Log kann global und pro Versand deaktiviert werden (@dergel)
* Bessere Hilfe und Beispiele (@skerbis)
* Einstellungen in Spalten (@skerbis)
* `X-Mailer`-Header auf "REXMailer" gesetzt (@skerbis)

### Bugfixes

* Das SMTP-Passwort-Feld wurde teilweise vorbelegt mit dem im Browser gespeicherten Passwort (@gharlan)


Version 2.1.2 – 08.01.2017
--------------------------

### Security

* Update phpmailer auf 5.2.21 mit Security-Fixes (remote code execution vulnerability)
  Mehr Informationen: https://github.com/PHPMailer/PHPMailer/wiki/About-the-CVE-2016-10033-and-CVE-2016-10045-vulnerabilities


Version 2.1.1 – 15.07.2016
--------------------------

* Update phpmailer auf 5.2.16
* SMTP-Debug kann in Backend konfiguriert werden (@alexplusde)


Version 2.1.0 – 24.03.2016
--------------------------

### Neu

* Es werden Backups der gesendeten Mails erstellt (@phoebusryan)

### Bugfixes

* Header X-Priority wird standardmäßig nicht mehr gesetzt, da die Mails sonst eher als Spam gewertet werden (@skerbis)
