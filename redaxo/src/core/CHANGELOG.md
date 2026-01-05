Changelog
=========

Version 5.20.2 – 05.01.2026
---------------------------

### Bugfixes

* `rex_editor`: in PHP 8.5 kam es teils zu Deprecated-Meldungen (@gharlan)
* EOL-Daten für PHP/MySQL/MariaDB aktualisiert (@gharlan)


Version 5.20.1 – 25.11.2025
---------------------------

### Security

* Sicherheitslücke in `symfony/http-foundation` durch Update behoben  (@gharlan)


Version 5.20.0 – 05.09.2025
---------------------------

### Neu

* Setup: SSL-Datenbankverbindung kann konfiguriert werden (@skerbis, @gharlan)
* `config.yml`: Env-Variablen können genutzt werden über einen YAML-Tag `!env` (Beispiel: `password: !env DB_PASSWORD`) (@gharlan)
* `rex_string`: YAML-Decoding/Encoding untersützt YAML-Tags (@gharlan)

### Bugfixes

* Session-Ablauf-Overlay
  - Erschien teilweise, obwohl die Session noch gar nicht abgelaufen war (@gharlan)
  - Button-Beschriftung korrigiert (@skerbis)
* `rex_sql`:
  - Neue Klasse `Pdo\Mysql` nutzen, wenn verfügbar (Vorbereitung für Deprecations in PHP 8.5) (@gharlan)
  - Fehlermeldung bei Verbindungsproblemen korrigiert (@staabm)
* Error-Handler wird bereits früher registriert und ist robuster bzgl. Fehler während der Generierung der Whoops-Seite (@gharlan)
* Textkorrekturen (@skerbis)


Version 5.19.0 – 17.07.2025
---------------------------

### Neu

* Session-Ablauf wird über Modal-Overlay angekündigt, mit Verlängerungsoption (@dergel, @tbaddade, @gharlan)
* Neue Commands `user:list` und `user:delete` (@crydotsnake, @gharlan)

### Bugfixes

* Nach Umstellung auf PHP 8.4 gingen die Passwörter bei den Usern teils verloren, die gerade mit "Eingeloggt bleiben"-Option eingeloggt sind, oder die sich über einen Passkey einloggen (@gharlan)
* `rex_form`: Vorhanden `data-confirm`-Attribute bei Control-Elementen werden nicht mehr überschrieben (@gharlan)
* Command `user:create` wurden fälschlich auch im Setup-Modus angeboten, obwohl er erst danach funktioniert (@gharlan)


Version 5.18.3 – 04.03.2025
---------------------------

### Security

* API-Functions: Bei Nutzung von `setRequiresReboot(true)` war über den Result-Parameter in der URL Cross-Site-Scripting möglich (gemeldet von @0xadik) (@gharlan)

### Bugfixes

* Console: Aufwärts-Kompatibilität zu neueren `symfony/console`-Versionen (@crydotsnake)
* Text-Optimierungen (@alxndr-w, @crydotsnake)


Version 5.18.2 – 10.02.2025
---------------------------

### Bugfixes

* `rex_sql_could_not_connect_exception`: Es war nicht ersichtlich, für welche DB-Verbindung die Exception geworfen wurde (@danspringer)


Version 5.18.1 – 06.12.2024
---------------------------

### Bugfixes

* PHP 8.4: Deperecated-Notices entfernt (@gharlan)
* Bei Login über Passkey wurde der "letzte Login" und andere Daten nicht aktualisiert (@gharlan)
* Profil: Die Passwortänderung war nicht möglich bei Login über Passkey (@gharlan)
* EOL-Daten für PHP/MariaDB aktualisiert (@staabm, @gharlan)


Version 5.18.0 – 22.10.2024
---------------------------

### Neu

* `dump`: Unterstützung für Labels (`dump(foo: $foo)`) (@gharlan)
* `rex_package`: Neue Methode `getLicense` und ausgabe der Lizenz im Command `package:list` (@dergel)
* `rex_var`-Klassen können über `register`-Methode explizit registriert werden (@gharlan)
* Optimiertes Beenden der Response bei Nutzung von `RESPONSE_SHUTDOWN` (Code nach dem Senden der Response) (@gharlan)
* Optimierung Fehlermeldungen (@tyrant88, @gharlan)
* EOL-Daten für PHP/MySQL/MariaDB aktualisiert (@gharlan)

### Bugfixes

* Passkey-Login funktionierte nicht, wenn zuvor ein Benutzername im Feld eingegeben wurde (@gharlan)
* Safari: Password-Toggle funktionierte nicht für generierte Passwörter (@gharlan)
* `rex_string::highlight` und Highlighting in `rex_markdown` für PHP 8.3 korrigiert (@gharlan)
* `E_STRICT`-Konstante nicht mehr nutzen (Vorbereitung für PHP 8.4) (@gharlan)


Version 5.17.1 – 07.04.2024
---------------------------

### Bugfixes

* Exception-Messages wurden teils an Nicht-Admins ausgegeben (@gharlan)
* `rex_sql`: In der Debug-Ausgabe wurden in der `fullquery` Integerwerte als Strings eingesetzt (@gharlan)


Version 5.17.0 – 12.03.2024
---------------------------

### Neu

* `rex_get`/`rex_post` etc.: Es kann ein Array mit möglichen Werten als Typ übergeben werden (@gharlan)
* `rex_http_exception`: 
    - Bei 4xx-Fehlercode wird nur noch bei Debug-Mode oder Backend-Admin ins Systemlog geschrieben (@gharlan)
    - Verwendung an weiteren sinnvollen Stellen mit 4xx-Code (@gharlan)
* `rex_log_file`: Neue `::factory()`-Methode, Nutzung von `new rex_log_file` ist deprecated (@dergel)
* `rex_api_function`: Neue Methode `register` um API-Functions explizit registrieren zu können (relevant bei Namespaces) (@gharlan)
* `rex_form`:
    - `maxlength`- und `minlength`-Attribute werden automatisch entsprechend des Validators gesetzt (@gharlan)
    - Neue Methode `disableSpellcheckAndAutoCorrect` um entsprechende Attribute zu setzen (@gharlan)
* Formularfelder: Validierungen und geeignete weitere Feldattribute ergänzt (@gharlan)
* HTML-Element `<search>` wird an geeigneten Stellen verwendet (@gharlan)
* Code-Optimierungen (@tbaddade, @gharlan)

### Bugfixes

* Setup: Bei "Update der Datenbank" kam es bei Charset-Änderungen teils zum Fehler (@gharlan)
* `rex_file`: `put` und `move` funktionierten teilweise nicht, wenn mehrere Filesysteme/Mounts involviert sind (@dergel)
* `rex_list`: Der Page-Parameter wurde den Links nicht immer mitgegeben, sodass man nach Löschung z.B. auf der ersten Seite landete (@gharlan)
* `rex_sql`: In `checkDbConnection` wurde ein relevanter Fehlercode nicht berücksichtigt (@gharlan)
* Wenn nach Login das Passwort automatisch rehasht wurde, musste man sich danach erneut einloggen (@gharlan)
* Wenn nach DB-Import der aktuelle Benutzer nicht mehr existiert, kam es zum Fehler (statt nur zum Logout) (@gharlan)


Version 5.16.1 – 24.02.2024
---------------------------

### Bugfixes

* DB-Verbindung:
    - Die Konstante `PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT` wurde ohne Prüfung genutzt (nur bei Nutzung von mysqlnd verfügbar) (@gharlan)
    - Die Option `ssl_ca` konnte nicht einzeln genutzt werden, nur in Kombi mit `ssl_cert` und `ssl_key` (@gharlan)


Version 5.16.0 – 09.02.2024
---------------------------

### Neu

* Neuer Live-Mode (über die `config.yml` aktivierbar), der problematische Bereiche im Backend für Produktivumgebungen sperrt (@gharlan)
* Safe-Mode ist nur noch als eingeloggter Admin aktivierbar, oder neu alternativ global über die `config.yml` (@gharlan)
* Setup: PHP-Version wird ausgegeben (@staabm)
* DB-Config: Neue Option `ssl_verify_server_cert` (@gharlan)
* Packages installieren/aktivieren etc. wird ohne PJAX durchgeführt (@tbaddade)
* `rex_list`:
    - Default-Sortierung soll nicht mehr über die Query übergeben werden, sondern über neuen separaten Parameter (@gharlan)
      ACHTUNG: Bei Übergabe über die Query funktioniert aus Sicherheitsgründen die Sortierung bei Klick auf eine Spalte nicht mehr
    - Neue Methoden um das Markup via Kindklassen leichter anpassen zu können (@staabm)
* `rex_sql`: `getArrayValue` liefert bei `null` ein leeres Array und behandelt ungültiges JSON besser (@staabm)
* `rex_file`: Neue Methode `append` (@skerbis)
* `rex_response`: `sendFile` abbrechen, wenn die Verbindung zum Client abgebrochen wurde (@staabm)
* `rex_version`: `gitHash` beschleunigt (@staabm)
* `Permissions-Policy`-Header wird nicht mehr gesetzt, da Google FLoC beerdigt hat (@eznix86)
* Text-Optimierungen (@Koala, @skerbis, @staabm)
* Übersetzungen:
    - Russisch hinzugefügt (@cosmopolityan)
    - Italienisch korrigiert/ergänzt (@michael-kreatif)
* JS: AJAX-Loader-Indicator als `rex_loader`-API ausgelagert (@staabm)
* Schema für `package.yml` optimiert (@staabm, @gharlan)
* Code-Stabilität durch statische Code-Analyse verbessert (@staabm, @bloep, @gharlan)

### Bugfixes

* Escaping korrigiert/ergänzt (@staabm, @gharlan)
* Core-Update stabilisiert (@gharlan)
* Setup: gz-komprimierte Backups wurden nicht zum Import angeboten (@gharlan)
* Systemlog: Farbe für `success`-Meldungen korrigiert (@danspringer)
* `rex_sql`: Bei zusätzlichen Datenbanken wurde die Verbindung nicht korrekt initialisiert bzgl. SQL-Mode und Charset (@alxndr-w)
* `rex_markdown`: PHP-Highlighting mit PHP 8.3 korrigiert (@bloep)
* `rex_file`: `delete`-Methode stabilisiert (@staabm)
* `rex_autoload`: Kompatibilität zu symfony/cache hergestellt (@staabm)
* `dump()`: Layout in Kombination mit UiKit korrigiert (@skerbis)
* `package.yml`: Deprecated-Meldunge vermeiden, wenn `supportpage` gleich `null` ist (@tbaddade)
* Popup-Schließung korrigiert (@tbaddade)
* `rex_logger`: Kompatibilität zu neueren PSR-Log-Versionen hergestellt (@michael-kreatif)
* Whoops-Page bei sehr großen Fehlermeldungen korrigiert (@staabm)


Version 5.15.1 – 18.03.2023
---------------------------

### Bugfixes

* Passkeys funktionierten in Chrome nicht (@gharlan)
* Setup/Systembericht: MariaDB-Versionen wurden teils fälschlich als veraltet markiert (@skerbis)
* REX_VARs in PHP-Strings zusammen mit String-Interpolation funktionierten nicht (`"REX_VALUE[1] $myvar"`) (@gharlan)
* Commands `config:get/set` und `db:connection-options` konnten nicht verwenden werden, wenn die DB nocht nicht existiert (@gharlan)
* Bessere Exception in `rex_fragment::parse` (@staabm)
* `#[SensitiveParameter]` fehlte noch an manchen Parametern (@gharlan)


Version 5.15.0 – 28.02.2023
---------------------------

### Neu

* Neue PHP-Mindestversion 8.1 (@gharlan)
* Login optional per Passkey/WebAuthn statt Benutzername/Passwort (@gharlan)
* Sessions:
    - Backend-Sessions werden einzeln in der Datenbank gespeichert; im Profil wird die Liste der offenen Session ausgegeben und Sessions können einzeln beendet werden (@bloep, @gharlan)
    - Neue Option `session_max_overall_duration` in `config.yml` (@staabm)
    - `session.use_strict_mode` wird immer aktiviert (@gharlan)
    - `session.save_path/sid_length/sid_bits_per_character` können über `config.yml` gesetzt werden (@gharlan)
    - Neuer EP `SESSION_REGENERATED` (@gharlan)
* Syslog:
    - Darstellung im Backend optimiert (@tbaddade, @gharlan)
    - Aufgerufene URL wird mitgeloggt (@gharlan)
    - Zeitstempel inkl. Zeitzonen-Offset in Logdatei (@dergel) 
* `dump`: Suchfunktion innerhalb der Dumpausgabe aktiviert (@tbaddade, @gharlan)
* `rex_form`: Label kann optional über Felder gesetzt werden (`setLabelOnTop`) (@christophboecker)
* `rex_formatter`: Methode `truncate` nutzt richtiges Ellipsis-Zeichen (@skerbis)
* `rex_response`: Neue Methode `getNonce` (wird an vielen Stellen im Backend bereits verwendet) (@dergel, @gharlan)
* Console-Commands: Autocomplete für Argumente/Optionen (@staabm, @gharlan)
* Search-Fragment: Value kann vorbelegt werden (@aeberhard)
* Optimierung Fehlermeldung, wenn PHP-Version zu niedrig (@gharlan)
* Code-Stabilität durch statische Code-Analyse verbessert (@bloep, @thorol, @staabm, @gharlan)
* Vendor-Updates (u.a. Symfony 6) (@gharlan)

### Bugfixes

* `rex_backend_login`: "Headers already sent"-Fehler vermeiden (@gharlan)


Version 5.14.3 – 20.02.2023
---------------------------

### Bugfixes

* Whoops: Session-ID und Eingeloggt-bleiben-Cookie werden maskiert (@gharlan)
* `rex_sql`:
    - bei Aufruf von `escape` wurden teils vorher gesetzte Werte (Table etc.) wieder geleert (@gharlan)
    - nach `getArray` lieferte `getFieldnames` falsche Werte (@gharlan)
* `rex_sql_foreign_key`: Es fehlte `NO ACTION` als Variante für `ON UPDATE/DELETE` (@tyrant88)
* `rex_escape`: `stdClass`-Objekte wurden direkt geändert, statt ein Clone zu erzeugen (@gharlan)
* `rex_string::buildQuery`: Deprecated-Meldung entfernt (@tyrant88)
* `rex_markdown`: Deprecated-Meldungen entfernt (@gharlan)
* Command `user:set-password`: Login-Versuche wurden nicht zurückgesetzt (@dergel)
* Syslog: Debug-Meldungen erschienen in Rot statt in neutraler Farbe (@gharlan)
* EOL-Daten für PHP/MariaDB aktualisiert (@staabm, @gharlan)
* Englische Übersetzung korrigiert (@dgrothaus-mc)
* Vendor-Updates (@skerbis, @gharlan)


Version 5.14.2 – 13.12.2022
---------------------------

### Bugfixes

* Update der externen Bibliotheken, dadurch Deprecation-Meldungen in PHP 8.2 entfernt (@gharlan)
* Nach Setup über die Console war der `instname` nicht gesetzt (@gharlan)
* `rex_sql`: Bei `escapeLikeWildcards` wurde der Backslash nicht escaped (@gharlan)
* PHP-Funktion `error_log` nur aufrufen, wenn vorhanden (ist bei manchen Hostern deaktiviert) (@gharlan)
* Rechtschreibung korrigiert (@eaCe)


Version 5.14.1 – 02.08.2022
---------------------------

### Bugfixes

* `rex_sql`: Fehlercode stand teils nicht mehr korrekt zur Verfügung, dadurch Fehler im Setup (@gharlan)
* `rex_backend_login`: Cookie-Einstellungen aus `config.yml` wurden für Eingeloggt-bleiben-Cookie nicht berücksichtigt (@dergel)


Version 5.14.0 – 25.07.2022
---------------------------

### Neu

* Setup: 
    - Lizenzschritt entfernt (@gharlan)
    - Bei erneutem Setup ist die bisherige Sprache vorausgewählt (@gharlan)
* `rex_backend_login`:
    - Neue `backend_login_policy` (in `config.yml`) mit Optionen `login_tries_until_blocked`, `login_tries_until_delay`, `relogin_delay` und `enable_stay_logged_in` (@staabm)
    - Neue Methode `increaseLoginTries` (@staabm)
* `rex_password_policy`: Neue Methode `getHtmlAttributes`, die passend zur Policy die Attribute `minlength`, `maxlength` und `passwordrules` liefert (wird im Backend an passenden Stellen auch verwendet) (@gharlan)
* `rex_form_base`:
    - Neue Methode `setFormAttribute` (@pherzberger)
    - In `addFieldset` können Attribute als zweiten Parameter übergeben werden (@gharlan)
* `rex_select`: Optgroups können per `endOptgroup` beendet werden (@gharlan)
* `rex_context`: Neue Methoden `getParams`, `hasParam`, `removeParam` (@tbaddade)
* `rex_be_page`: Neuer Setter `setTitle` (@DanielWeitenauer)
* `rex_socket`:
    - gzip-Unterstützung, aktivierbar per `acceptCompression()` (@pherzberger)
    - Beispiel-Code optimiert (@marcohanke)
* `rex_path`: Neue Methode `findBinaryPath` (@staabm)
* `rex_type`: Neue Type-Assertion-Methoden wie `int`, `nullOrInt` etc. (@gharlan)
* `rex_sql`: Bei `factory` wird noch nicht die DB-Verbindung geöffnet, sondern erst wenn wirklich notwendig (@Sysix)
* Neuer EP `PACKAGE_CACHE_DELETED` (@gharlan)
* Eingabefelder teils mit spezifischeren Typen (`type="email"` etc.) und `required`/`autocomplete`-Attributen (@gharlan)
* System/Log: "Slow Query Log" wird als Subpage angeboten, wenn in der DB aktiviert (@staabm)
* Aktualisierung Übersetzungen: schwedisch (@interweave-media)
* Autoloading: Wenn eine Klasse nicht gefunden wird, wird automatisch der Autoload-Cache geleert (@staabm)
* PHP 8.2: `SensitiveParameter`-Attribut wird an geeigneten Stellen verwendet (@staabm)
* Code-Stabilität durch statische Code-Analyse verbessert (@staabm, @gharlan)

### Bugfixes

* `rex_request`: Vermeidung von Exceptions in der cli (@staabm)
* `rex_socket_proxy`: Der `Host`-Header wurde fälschlich inkl. Port gesetzt (@gharlan)
* Cookie `rex_htaccess_check` hat nicht die Cookie-Einstellungen aus der `config.yml` verwendet (@staabm)
* PHP 8.2: Deprecation-Warnings entfernt (@staabm, @gharlan)


Version 5.13.3 – 03.05.2022
---------------------------

### Bugfixes

* `rex_list`: Über `addLinkAttribute` konnten keine eigenen Classes gesetzt werden (@tbaddade)
* `rex_form`: Bei Fieldsets mit eckigen Klammern im Namen wurden die Werte nicht gespeichert (@gharlan)
* `rex_formatter`: Behandlung von `0000-00-00` korrigiert (@tbaddade)
* `rex_get`/`rex_post` etc. warfen Notice, wenn nach String gecastet wurde, und ein Array gesendet wurde (@gharlan)
* Rex-Vars: Bei `null`-Werten kam es mit PHP 8.1 zu Deprecation-Notices (@gharlan)
* Command `assets:sync`: Core-Assets wurden nicht korrekt synchronisiert (@gharlan)
* Cache-Handling der AddOns korrigiert (@gharlan)
* Systembericht: Bei fehlerhafter DB-Verbindung kam es zu einem Fehler (@gharlan)
* Beim Abfragen der REDAXO-Version inkl. Git-Hash (z.B. im Systembericht) kam es zu einem Fehler, wenn `exec` nicht verfügbar ist (@gharlan)


Version 5.13.2 – 10.01.2022
---------------------------

### Bugfixes

* "Eingeloggt bleiben" funktionierte nicht mehr korrekt (@gharlan)
* In der Sprachverwaltung wurde der online/offline-Status nicht mehr farblich unterschieden (@schuer)
* Klickfläche weiterer Icon-Links vergrößert (@schuer)
* Setup: Fehlermeldung bzgl. unsicherer Ordner verständlicher gemacht (@skerbis)
* Cli-Setup: Es wird darauf hingewiesen, dass die Setup-Checks dort nicht die Korrektheit innerhalb der Server-Umgebung garantieren können (@gharlan)
* `rex_sql`: Die Query-Parameter werden entsprechend ihrer PHP-Typen gebunden, dadurch z.B. Parameter auch in `LIMIT`-Ausdrücken möglich (@gharlan)
* EOL-Daten für PHP 8 und MariaDB 10.6 hinterlegt (@staabm)
* Fehlermeldung optimiert, wenn die Datei zu einer Package-Page nicht existiert (@gharlan)
* Deprecation-Meldungen vermieden (teilweise noch PHP 8.1, ansonsten schon für PHP 8.2) (@gharlan)


Version 5.13.1 – 29.11.2021
---------------------------

### Security

* Bei Passwortänderung wurden die vorhandenen Sessions des Users nicht beendet (@gharlan)

### Neu

* Update der externen Bibliotheken (@gharlan)

### Bugfixes

* Deprecated-Meldungen in PHP 8.1 entfernt (@gharlan)
* "Eingeloggt bleiben"-Cookies wurden teils unnötig invalidiert (z.B. bei Logout an einem anderen Rechner) (@gharlan)
* Firewalls haben teils die Assets-URLs blockert (@gharlan)
* Profilseite: Bei erzwungenem Passwortwechsel verständlichere Erläuterung und Reduzierung auf die Passwort-Felder (@schuer)
* `rex_sql_table`: Defaultwert `0` wurde nicht gesetzt (@TobiasKrais)
* `rex_markdown`: Korrekturen beim PHP-Syntaxthighlighting (@gharlan)


Version 5.13.0 – 17.11.2021
---------------------------

### Neu

* Es werden neu die PHP-Extensions `ctype`, `mbstring` und `intl` erfordert (@gharlan)
* Dark-Mode für das Backend (@schuer): 
    - Die Theme-Auswahl erfolgt automatisch im Browser
    - User können auf ihrer Profilseite ein Theme explizit auswählen
    - Über die `config.yml` kann ein Theme für alle User fest vorgegeben werden
* `rex_list`:
    - Spaltenposition können abgefragt/verändert werden über `getColumnPosition`/`setColumnPosition` (@christophboecker)
    - Paginierung kann deaktiviert werden (@gharlan)
    - Gesamtanzahl wird nicht mehr über deprecated `SQL_CALC_FOUND_ROWS` abgefragt (@gharlan)
* `rex_formatter`:
    - Neue Methoden `intlDateTime`, `intlDate`, `intlTime` für die Datumsformatierung über `IntlDateFormatter` (@gharlan)
    - Deprecated `strftime`, stattdessen die neuen `intl*`-Methoden verwenden (`strftime` wurde auch in PHP deprecated gesetzt) (@gharlan)
* `rex_select`: Bei `addSqlOptions` kann als zweiter Parameter die DB-ID gesetzt werden (@christophboecker)
* `rex_markdown`: Optional kann Highlighting für PHP-Codeblöcke aktiviert werden (wird in den Readme-Ausgaben im Backend verwendet) (@gharlan)
* `rex_pager`: 
    - Page/Cursor kann direkt gesetzt werden über `setPage`/`setCursor` (@gharlan)
    - Page/Cursor wird automatisch validiert und ggf. auf erste/letzte Page angepasst (@gharlan)
* `rex`: Neue Methode `requireUser` (nicht nullable) (@gharlan)
* `rex_socket`: Context-Options können gesetzt werden (z.B. `verify_peer` für SSL) (@dergel)
* `rex_socket_proxy`: Bei https wird TLS v1.2 und SNI verwendet (@develerik)
* `rex_response`: Neue Konstante `HTTP_BAD_REQUEST` für den entsprechenden HTTP-Status (@christophboecker)
* `rex_factory_trait` Neue Methode `getExplicitFactoryClass`, dafür `callFactoryClass` deprecated (@gharlan)
* `dump()`-Ausgaben enthalten einen Link (entsprechend der Editor-Einstellung in REDAXO) zu der Codestelle, wo die Ausgabe ausgelöst wurde (@gharlan)
* Neuer Console-Command `package:run-update-script`, der das Update-Skript eines Addons manuell anstößt (@gharlan)
* `use_gzip` wird in der `config.yml` default nicht mehr aktiviert (@gharlan)
* Aktualisierung Übersetzungen: schwedisch (@interweave-media)
* System-Page: Basis-Pfad der REDAXO-Installation wird ausgegeben (@skerbis)
* Im Backend wird der Opt-Out-Header für Google FLoC gesetzt (@staabm)
* Dark-Mode für die Frontend-Fehlerseite (@gharlan)
* Update der externen Bibliotheken (@skerbis, @gharlan)
* Code-Stabilität durch statische Code-Analyse und Tests verbessert (@staabm, @bloep, @gharlan)

### Bugfixes

* Deprecations in PHP 8.1 aufgelöst (@gharlan)
* Api-Functions haben immer einen gültigen `page`-Parameter erfordert (@gharlan)
* System-Log: `rex:///`-Pfade wurden nicht mit den Editor-URLs verlinkt (@gharlan)


Version 5.12.1 – 21.06.2021
---------------------------

### Neu

* Update der externen Bibliotheken

### Bugfixes

* `rex_version`:
    - Methode `compare` für Aufrufe ohne letzten Parameter `$comparator` korrigiert (@gharlan)
    - Methode `gitHash` für Aufrufe ohne zweiten Parameter `$repo` korrigiert (@gharlan)


Version 5.12.0 – 03.03.2021
---------------------------

### Neu

* Neue PHP-Mindestversion 7.3
* Update der externen Bibliotheken (u.a. Symfony Components 5.x, jQuery 3.6)
* `symfony/http-foundation` neu aufgenommen; das Request-Objekt kann über `rex::getRequest()` abgefragt werden (@gharlan)
* Setup:
    - Erneutes Setup (über Backend gestartet) aktiviert nicht mehr den globalen Setup-Modus, sondern läuft über einen URL-Token parallel zum normalen Seitenbetrieb (@gharlan)
    - Erneutes Setup kann jederzeit über Button abgebrochen/beendet werden (@staabm)
    - Bei erneutem Setup ist „Datenbank existiert schon“ vorausgewählt (@staabm)
    - Bei erneutem Setup wird die Backend-Session nicht mehr beendet (@gharlan)
    - Der DB-Host wird separat validiert, mit spezifischer Fehlermeldung (@trailsnail)
    - Bei „Datenbank erstellen“ wird die Collation `utf8mb4_unicode_ci` genutzt (@ixtension)
    - „End of life“-Daten für PHP 8.0, MySQL 8.0 und MariaDB 10.5 ergänzt (@staabm)
    - Lizenztext wird per Markdown geparsed (@schuer)
    - Textaktualisierungen/-verbesserungen (@schuer, @alxndr-w)
* Package-Installation: Packages können über neue `successmsg`-Property eine eigene Erfolgsmeldung setzen (@BlackScorp, @staabm)
* Über das Fragezeichen in der AddOn-Verwaltung ist über eine weitere Subpage die `CHANGELOG.md` der AddOns einsehbar (@staabm, @gharlan)
* Package-Abhängigkeiten:
    - Wenn ein nicht vorhandenes Package erfordert wird, wird direkt die Versionsbedingung mit ausgegeben (@skerbis)
    - In der Fehlermeldung sind die Abhängigkeiten verlinkt (Sprunglink oder Link in den Installer) (@staabm, @skerbis, @gharlan)
* Im Safe-Mode wird neu auch das `install`-AddOn geladen und ist nutzbar (@alxndr-w, @gharlan)
* Passwortregeln werden unterhalb der Passwortfelder angezeigt (@gharlan)
* Systembericht: Fehlerhandling bei invaliden `package.yml` optimiert (@staabm)
* REDAXO-Logo wird direkt als SVG ausgegeben, dadurch kein Flackern mehr (@schuer)
* Formulare können aus Textfeldern heraus per Strg/Cmd+Enter abgesendet werden (@schuer)
* Pflichtfelder werden an vielen Stellen mit einem roten Sternchen markiert (@staabm)
* Externe Links werden mit einem Icon markiert (@staabm, @schuer)
* Neues Fragment `core/form/search.php` für Suchfelder wie in der AddOn-Verwaltung, mit zugehöriger JS-Funktion `rex_searchfield_init` (@skerbis)
* Whoops-Page enthält Button „Report a bug“, der GitHub öffnet mit vorausgefüllter Issue-Maske (@staabm, @schuer)
* `rex`: Neue Methode `getDbConfig` liefert die DB-Config als Objekt der neuen Klasse `rex_config_db` (@staabm)
* `rex_markdown`:
    - Die Umwandlung einfacher Zeilenumbrüche zu `<br/>` (kein Markdown-Standard) kann deaktiviert werden und ist bei der Darstellung von Markdown-Dateien im Backend deaktiviert (@christophboecker)
    - Die Header-IDs sind im gleichen Format wie auf GitHub (@jelleschutter)
* `rex_validator`: Rules werden über neue Klasse `rex_validation_rule` abgebildet; Objekte der Klasse können über `addRule` hinzugefügt und über `getRules` abgefragt werden (@staabm)
* `rex_form`: Pflichtfelder (gesetzt über `notEmpty`-Validator) werden im Label markiert und erhalten das `required`-Attribut (@staabm)
* `rex_list`: Es können Attribute für die Table-Rows (`<tr>`) gesetzten werden (@christophboecker)
* `rex_user`: Neue Methode `forLogin` um User über den Benutzernamen abzufragen (@jelleschutter)
* `rex_file`: Neue Methode `require`, wie `get`, aber wirft Exception, wenn die Datei nicht gelesen werden kann (@staabm)
* `rex_response`: 
    - Bei `sendResource` ist der Client-Cache default deaktiviert, und kann vorab per `sendCacheControl` geändert werden (@alxndr-w)
    - Bei `sendRedirect` kann der Statuscode als zweiter Parameter übergeben werden (@staabm)
* `rex_package`: Neue Methode `splitId` um eine Package-ID in AddOn- und PlugIn-Part zu trennen (@gharlan)
* `rex_sql`: 
    - Neue statische Methode `in`, um die Parameter für die `IN (…)`-Clause mit Escaping zu erhalten (@gharlan)
    - Neue statische Methode `closeConnection` (@gharlan)
* `rex_sql_util`: Methode `importDump` prüft, ob es eine `*.sql`-Datei ist (@staabm)
* `rex_var`: Variablen können auch Ziffern im Namen enthalten (@gharlan)
* `rex_api_function`: Exception bei ungültigem JSON (@staabm)
* `rex_editor`: Die Editoren haben Konstanten erhalten, und die Klasse validiert den gesetzen Editor (@staabm)
* Console:
    - `config:get/set`: Über neue Option `--package` können die Packages-Properties (statt Core-Properties) verwaltet werden (@staabm)
    - `config:get/set`: `--type`-Option unterstützt den `octal`-Typ für `fileperm`/`dirperm` (@staabm)
    - `assets:sync`: Dateivergleich optimiert und Beschreibung/Hilfe verbessert (@staabm)
    - `setup:run`: Die Ordner/Dateien mit fehlenden Schreibrechten werden im Listen-Style aufgelistet (@staabm)
* `Server-Timing`-Header im Debug-Modus werden nicht mehr gesendet, da inzwischen das Debug-AddOn existiert und die Header sich als problematisch herausgestellt haben (@gharlan)
* Optimierte Fehlermeldung, wenn die Datenbankverbindung nicht aufgebaut werden kann (@staabm)
* Projekt-AddOn: Code-Beispiel für yform-Modelklassen in `boot.php` (@dtpop)
* Backend-Übersetzungsdateien:
    - Textkorrekturen/-verbesserungen (@alxndr-w, @pschuchmann, @gharlan)
    - Aktualisierung Übersetzungen: englisch (@ynamite, @skerbis), schwedisch (@interweave-media)
* Readme-Dateien der Addons erstellt/erweitert, englische Übersetzungen erstellt, und alte `help.php` entfernt (@skerbis)
* Einige Deprecated-Methods erhalten in PhpStorm automatische Ersetzungsvorschläge (@staabm)
* Code-Stabilität durch statische Code-Analyse verbessert (@staabm, @gharlan)
* Parameternamen in vielen Funktionen/Methoden optimiert (u.a. wegen Named Arguments in PHP 8) (@gharlan)

### Bugfixes

* Setup: Die erforderliche PHP-Extension `filter` wurde nicht geprüft (@gharlan)
* Wenn die Console mit nicht-unterstützter PHP-Version aufgerufen wird, war die Fehlermeldung dazu teils nicht sichtbar (@staabm)
* fail2ban-Blocking während des htaccess-Sicherheitschecks wird verhindert (@skerbis, @staabm)
* Systemlog: Beim Löschen der Logdatei fehlte der CSRF-Schutz (@staabm)
* Beim Umschalten des Debug-Modus über die Systemeinstellungen erschien/verschwand das Debug-Symbol erst nach nächstem Seitenload (@skerbis)
* `rex_autoload`: Cache-Handling korrigiert (@gharlan)
* `rex_markdown`: In Code-Snippets wurde die Zeichenkette `window.location` pauschal entfernt (@gharlan)
* `rex_form`: Bei aktiviertem Debug-Parameter wurde die Redirect-URL nicht escaped (@gharlan)
* `rex_extension`: Wenn der Runlevel als String übergeben wurde („EARLY“, „LATE“), wurde stillschweigend immer LATE verwendet; neu wird auf die korrekte Nutzung über die Integer-Konstanten `rex_extension::EARLY/LATE` per Warning hingewiesen (@gharlan)
* Console-Command `setup:run`: Wenn die Systemvoraussetzungen nicht erfüllt werden, wurde nach der Fehlermeldung trotzdem das Setup fortgesetzt (@gharlan)


Version 5.11.2 – 25.01.2021
---------------------------

### Security

* SQL-Injection im `rex_form`-Prio-Feld verhindert (@gharlan)
* XSS in `rex_form` verhindert (@staabm, @gharlan)
* Path-Traversal während des Setups verhindert (@staabm)

### Neu

* Update der externen Bibliotheken
* `rex_escape`: Neue Escape-Strategie `html_simplified`, bei der HTML escaped wird mit Ausnahme weniger einfacher Tags (`<b>`, `<code>` etc.) (@staabm)

### Bugfixes

* PHP 8:
    - Wenn `debug.throw_always_exception` aktiv ist, wurden Warnings/Notices trotz `@`-Operator nicht ignoriert (@gharlan)
    - In `rex_sql` kam es teilweise zu Warnings bzgl. `reset()` (@gharlan)
* Whoops: Button-Styles korrigiert, unnötigen "Hide"-Button entfernt (@gharlan)
* `rex_logger`: Bei `rex_`-Exceptions wurde im Log der erste Buchstabe großgeschrieben (`Rex_exception` etc.) (@gharlan)
* Bei manchen Proxy-Servern (z. B. im Boostmodus bei Strato) konnte es im Debug-Modus zu einem Fehler kommen aufgrund zu vieler Header (`Server-Timing`-Header) (@gharlan)
* Links mit `download`-Attribut wurden trotzdem über PJAX geladen (@gharlan)


Version 5.11.1 – 11.11.2020
---------------------------

### Neu

* REDAXO ist bereits seit 5.10.1 teilweise inkompatibel zu MySQL <= 5.5, daher wurden die DB-Mindestversionen nun explizit hochgesetzt auf MySQL 5.6 / MariaDB 10.1
* REDAXO 5.11.x ist die letzte Version, die noch zu PHP < 7.3 kompatibel ist, ab REDAXO 5.12 wird die Mindestversion entsprechend hochgesetzt
* Update der externen Bibliotheken

### Bugfixes

* Systembericht als Markdown: Bei Nutzung des Kopieren-Buttons kam teils ungültiges Markdown heraus wegen Leerzeichen am Anfang (@gharlan)
* Log: Darstellung "Info"-Meldungen korrigiert (@BlackScorp)
* Systembericht: Addons ohne Versionsangabe führten zu Fehler (@gharlan)
* Setup: Es kam teils fälschlich die Warnung, `session.auto_start` wäre aktiv (@gharlan)
* `rex_sql`:
    - `getLastId` lieferte im Frontend `0`, wenn der Debugmodus des SQL-Objekts aktiviert war (@gharlan)
    - `getQueryType` unterstützt Klammern um die Query (@BlackScorp, @staabm)
* `rex_sql_table`: Abfrage der Fremdschlüssel funktionierte nicht mit MySQL >= 8.0.21 (@gharlan)
* `rex_markdown`: Generierung der Sprungnavi geht korrekt um mit HTML/Markdown/SpecialChars innerhalb der Überschriften (@jelleschutter, @gharlan)
* `rex_dir`: `delete` führte zu Warnings, wenn während des rekursiven Löschens bereits von anderen Prozessen wieder neue Dateien in dem Ordner angelegt wurden (@gharlan)
* `rex_var`: `toArray` ging teils nicht korrekt mit Anführungszeichen im Inhalt um (@portux)


Version 5.11.0 – 01.07.2020
---------------------------

### Neu

* Update der externen Bibliotheken
* Setup: Bei Anlage des Administrators werden nun die Passwortregeln geprüft (@gharlan)
* Passwortregeln:
    - Passwortwechsel kann nach definiertem Zeitraum erfordert werden (@gharlan)
    - Wiederverwenden der letzten X Passwörter oder der Passwörter aus definiertem Zeitraum kann unterbunden werden (@gharlan)
* Admins können explizit einen Passwortwechsel nach Login für Benutzer verlangen (@gharlan)
* JSON-Schema-Dateien für die `config.yml` und `package.yml` (für Validierung/Autovervollständigung) (@gharlan)
* Editor-Einstellung kann optional clientbasiert als Cookie gespeichert werden, um auf Produktivsystemen den jeweils eigenen Editor und lokalen Projektpfad hinterlegen zu können (@gharlan)
* `rex_sql_table`: Spaltenkommentare können ausgelesen/gesetzt werden (werden auch beim Schema-Dump berücksichtigt) (@staabm)
* `rex_sql_util`: Neue Methoden `copyTable` und `copyTableWithData` (@tbaddade, @gharlan)
* `rex_user`: Neue statische Methoden `get`, `require` und `fromSql` für die Abfrage der Benutzer (@gharlan)
* `rex_fragment`: Neue Methode `getSubfragment` für Abfrage Subfragment, ohne dieses direkt auszugeben (@gharlan)
* Reihenfolge der Backend-Navi-Blöcke kann über neuen EP `PAGE_NAVIGATION` geändert werden (@gharlan)
* Console:
    - Neuer Command `package:list`: Auflistung der Addons (alle, nur installierte/aktivierte etc.) (@bloep)
    - `config:set`: Boolsche Werte können über `--type boolean true/false` gesetzt werden (@bloep, @staabm)
* Systemlog:
    - Dateipfade werden mit Editor-URL verlinkt (@gharlan)
    - Logeinträge vom Typ `success` (grün) werden unterstützt (@danspringer)
* Systembericht als Markdown: DB-Version wird auch in der Zusammenfassungszeile ausgegeben (@gharlan)
* Aktualisierung Backend-Übersetzungdateien: Schwedisch (@interweave-media), Spanisch (@nandes2062), Englisch (@ynamite)
* Performance-Optimierung bei Datei-Existenz-Checks (@staabm)
* Code-Stabilität durch statische Code-Analyse verbessert (@staabm, @gharlan)
* Kommentar-Texte erweitert (@staabm)
* Beispiel-`.gitignore` erweitert/optimiert (@alexplusde, @schuer)

### Bugfixes

* `rex_form`: Der Language-Support konnte nur genutzt werden, wenn die Tabelle die globalen Felder (updatedate etc.) enthielt (@Sysix)
* Darstellung der `dump()`-Ausgabe bei Nutzung von UIKit korrigiert (@skerbis)
* Console:
    - Änderungen an den YAML-Dateien wirkten sich erst nach Cache-Löschen oder Backendaufruf aus (@gharlan)
    - `setup:run`: Es kam zu einem Fehler, wenn das Backup-Addon deinstalliert wurde (@gharlan)
* Passwortregeln: Unnötige Regeln mit "min: 0" werden in der Regelbeschreibung in Fehlermeldungen nicht mehr mit ausgegeben (@gharlan)
* Editor-Basepath musste mit abschließendem Slash eingetragen werden (@gharlan)
* Setup: Pfad zur `config.yml` war teils falsch (und nicht dynamisch bei eigenen Path-Providern) in den Meldungen (@staabm)


Version 5.10.1 – 08.05.2020
---------------------------

### Neu

* Update der externen Bibliotheken (u.a. jQuery 3.5.1)

### Bugfixes

* Es kam zu einem Fehler, wenn ein Addon keine `package.yml` oder darin keine `version` enthielt (@gharlan)
* Logout im Chrome war teils sehr langsam (@staabm)
* Accesskeys funktionierten nicht mehr (@bloep)
* Systembericht: Bei fehlerhafter zweiter Datenbankverbindung kam es zu der Ooops-Fehlerseite (@gharlan)
* `rex_sql_table`:
    - Spaltenreihenfolge wurde teils nicht korrekt gesetzt (@gharlan)
    - Bei mehrfachem Aufruf von `ensure` für eine Tabelle ohne Änderungen kam es zu einem Fehler (@gharlan)
* `rex_sql`: Bei einer Exception in `setDBQuery` wurde die DB-ID nicht auf die Ursprungs-ID zurückgesetzt (@staabm)
* `rex_file`: bei `copy` kam es zu einer Warnung, wenn man nicht der Fileowner der Datei ist (@gharlan)
* Command `user:create`: Die angelegten User konnten sich nicht einloggen (@staabm, @bloep)


Version 5.10.0 – 10.03.2020
---------------------------

### Security

* Markdown-Ausgaben (Readmes, Installer etc.) waren nicht geschützt gegen XSS (@gharlan)

### Neu

* Update der externen Bibliotheken (@gharlan)
* Setup: Beim erneuten Ausführen wird das vorhandene DB-Passwort nicht mehr angezeigt (@staabm)
* EOL-Warnungen für PHP/MySQL/MariaDB:
    - Analog zu PHP wird bei MySQL/MariaDB-Version gewarnt, die vom Hersteller nicht mehr gepflegt wird (@staabm)
    - Die EOL-Warnungen werden auch in der Console und im Systembericht ausgegeben (@bloep, @staabm)
* Datenbank:
    - SSL-Connections können verwendet werden (in `config.yml` konfigurierbar) (@staabm)
    - Es wird nun einheitlich die Collation `utf8_unicode_ci`, bzw. `utf8mb4_unicode_ci` (nicht mehr teils `*_general_ci`) (@gharlan)
* Addons können in der `package.yml` unter `default_config` die Default-Werte für `rex_config` hinterlegen (@gharlan)
* Neue Klasse `rex_version`:
    - Methode `isUnstable` zum Prüfen, ob eine Version eine Entwicklungsversion ("beta" etc.) ist (@staabm)
    - Weitere Methoden wurden in die Klasse verschoben (und die bisherigen als deprecated markiert) (@gharlan):
        - `rex_string::versionSplit` -> `rex_version::split`
        - `rex_string::versionCompare` -> `rex_version::compare`
        - `rex::getVersionHash` -> `rex_version::gitHash`
* `rex_string`: Neue Methode `sanitizeHtml`, um HTML aus unsicherer Quelle gegen XSS zu schützen (@gharlan)
* `rex_response`: Neue Methode `sendJson` (@staabm)
* `rex_file`:
    - Neue Methode `mimeType()` um den Mime-Type einer Datei zu bestimmen (liefert bessere Resultate als `mime_content_type()`, zum Beispiel für SVGs) (@gharlan)
    - Neue Methode `move` (@staabm)
* `rex_package/addon/plugin`: Neue Methode `require`, die wie `get` das Package-Objekt liefert, aber eine Exception wirft, wenn das Package nicht vorhanden ist (@gharlan)
* Console:
    - Es wird eine Warnung ausgegeben, wenn die Console mit einem anderen User ausgeführt wird als dem File-Owner von `/redaxo` (@skerbis, @bloep)
    - Neuer Command `package:delete` (@bloep)
* Versionsnummern werden überall im System (Addonverwaltung, Systembericht etc.) mit einem Icon markiert, wenn es Entwicklungsversionen sind ("beta" etc.) (@staabm)
* Systembericht als Markdown: Neuer Button "In die Zwischenablage kopieren" (@staabm)
* Speichern/Übernehmen-Buttons haben ein `title`-Attribut mit Erläuterungstext (@staabm)
* Auf der Lizenz-Page der Packages wird ein Link zu einer Seite mit Erklärungen zu den Lizenzen ausgegeben (@staabm)
* Beim Cache löschen wird auch der Opcache geleert (@gharlan)
* `php.ini`-Einstellung `html_errors` wird immer deaktiviert, um HTML-Markup in Whoops und im Log zu vermeiden (@gharlan)
* Code-Stabilität durch Tests und statische Code-Analyse verbessert (@staabm, @bloep, @gharlan)

### Bugfixes

* Bei tiefer verschachtelten Abhängigkeiten der AddOns wurde die Ladereihenfolge nicht immer korrekt entsprechend der Abhängigkeiten generiert (@gharlan)
* `rex_sql`: In der Debug-Ausgabe wurden in `fullquery` nicht immer die Parameter ersetzt (@gharlan)
* Es kam zu Fehlern, wenn Addons eine eigene (ältere) Version von Parsedown mitlieferten (@gharlan)
* Bei den `package:*`-Commands waren Addons, die gerade erst in den Addonordner gelegt wurden, nicht direkt verfügbar (@bloep)
* Die Tabelle `rex_config` hat seit einigen Versionen keine `id`-Spalte mehr, bei manchen war diese aber trotzdem noch vorhanden und führte zu Problemen beim Update (@gharlan)


Version 5.9.0 – 02.02.2020
--------------------------

### Neu

* Update der externen Bibliotheken (u.a. jQuery v3 und pjax v2) (@skerbis, @schuer, @gharlan)
* MySQL 8 wird unterstützt (@staabm, @gharlan)
* utf8mb4-Unterstützung (vollständiger Unicode-Zeichensatz inkl. Emojis): Kann über das Setup aktiviert werden (@gharlan)
* Neuer zentraler Ordner für Logdateien: `redaxo/data/log` (`rex_path::log()`; Pfad kann über Pathprovider geändert werden) (@gharlan)
* Setup:
    - Kann über den neuen Command `setup:run` auch in der Console durchgeführt werden (@bloep)
    - Sprachen sind nun alphabetisch sortiert (@tbaddade)
    - Warnung wenn "session.auto_start" aktiviert ist (@bloep)
    - Warnung vorbereitet für End-Of-Live von PHP 7.x ab Ende November 2022 (@staabm)
    - HTTPS-Option kann nur noch bei Aufruf über HTTPS gesetzt werden, damit man sich nicht selbst aus dem Backend ausschließen kann (@bloep)
    - HSTS kann nicht mehr über das Setup (nur direkt über config.yml) gesetzt werden (@bloep)
    - Beim DB-Host kann der Port mit angegeben werden ("localhost:3306") (@staabm, @gharlan)
    - DB-Name kommt erst nach Host/Benutzer/Passwort (@gharlan)
    - Default-DB-Name nun "redaxo5" statt "redaxo_5_0" (@gharlan)
    - DB-Host/Benutzer/Name werden getrimmt (@aeberhard)
    - Es wird `rex_sql_table` verwendet für bessere Teilkorrekturen der DB (@tbaddade)
* In der config.yml kann über `editor_basepath` der Basispfad für die Editor-URLs geändert werden (nützlich für Docker) (@bloep)
* AddOn-Verwaltung: Suchfeld für AddOns (@danspringer)
* Systemlog:
    - 100 statt 30 Zeilen (@aeberhard)
    - Button zum Download der Datei (@aeberhard)
* Markdown-Pages (Readme): Die Sprungnavi ist nun rechts angeordnet (@schuer)
* Layout der Credits-Page optimiert und an Addonverwaltung angeglichen (@schuer)
* Whoops: REDAXO-Logo ist mit Startseite verlinkt (@gharlan)
* REX_VARs: Callbacks bekommen den Variablennamen und die zugehörige Klasse als Parameter `var` und `class` übergeben (@gharlan)
* `rex_sql`:
    - Über `getDbType()` kann der Type (MySQL oder MariaDB) abgefragt werden, über `getDbVersion()` die normalisierte Version (@gharlan)
    - Mit `escapeLikeWildcards()` können "%" und "_" escaped werden für `LIKE`-Ausdrücke (@gharlan)
* `rex_sql_table`:
    - DB-ID kann übergeben werden, somit auch nutzbar für die weiteren DBs (@thorol, @gharlan)
    - Bei `ensureGlobalColumns()` kann über den ersten Parameter die Position der Spalten festgelegt werden (@tbaddade)
* `rex_sql_schema_dumper`: Bei entsprechener Spalten-Kombi wird Shortcut `ensureGlobalColumns` genutzt (@gharlan)
* Fragment `core/page/section`: Attribute können übergeben werden (@tbaddade)
* Console-Commands:
    - Neuer Command `config:set` um Werte in der `config.yml` zu setzen (@bloep)
    - `db:set-connection` prüft nun, ob die neue Verbindung valide ist (kann per `--force` deaktiviert werden) (@bloep)
* Aktualisierung Backend-Übersetzungdateien: Schwedisch (@interweave-media), Spanisch (@nandes2062), Englisch (@ynamite)
* Englische Übersetzung der Readme des project-Addons (@skerbis)
* ETag-Header wird in Safari nicht mehr deaktiviert, da der Safari-Bug nicht mehr zu bestehen scheint (@gharlan)
* Der htaccess-Check-Cookie heißt nun `rex_htaccess_check` statt `htaccess_check` (@alexplusde)
* Code-Stabilität durch statische Code-Analyse verbessert (@staabm)

### Bugfixes

* `rex_sql`: Bei `->setWhere(['name' => 'a'])->setValue('name', 'b')` wurde fälschlich der Wert aus WHERE auch für SET verwendet (@gharlan)
* `rex_sql_table`: Beim Setzen von Primary Keys für Tabellen, die bisher keinen hatten, kam es zu einem Fehler (@gharlan)
* `rex_list` warf mit PHP 7.4 Notices "Trying to access array offset on value of type null" (@gharlan)
* `rex_socket`: Es kam teilweise zur Warnung "Undefined variable: errno" (@staabm)
* `rex_config`: Wenn während eines Requests `removeNamespace()` und danach `set()` für den selben Namespace aufgerufen wurde, kam es zu einem Fehler (@bloep)
* `rex_api_function`: Statische Methode `hasMessage` warf einen Fehler, wenn keine Api-Func aufgerufen wurde (@gharlan)
* `rex_log_file`: Pipe-Zeichen "|" konnte nicht in der Log-Message verwendet werden (@gharlan)
* Console-Commands:
    - `user:create` warf einen Fehler (@bloep)
    - `db:set-connection` konnte nur verwendet werden, wenn schon eine gültige DB-Verbindung hinterlegt war (@bloep)
    - `db:set-connection` hat fälschlich für nicht gesetzte Optionen deren Wert mit `null` gesetzt (@bloep)
    - `package:install`: Installation von Plugins von nicht aktivierten Addons wurde nicht unterbunden (@bloep)
* Im Setup bei "Aktualisierung der Datenbank" waren nicht alle Klassen dem Autoloader bekannt während der Re-Installation der Addons (@gharlan)
* Identität wechseln: Beim Zurückwechseln kommt es nicht mehr zu einem Fehler, wenn schon in einem anderen Tab zurückgewechselt wurde (@tbaddade)
* Addonverwaltung: Beim Öffnen der Hilfe/Lizenz eines Addons wird korrekt nach oben gesprungen (@gharlan)
* Bei Session-Start-Fehlern wurde der spezifische Grund unterschlagen (@gharlan)
* Datumsformat sprachspezifisch vereinheitlicht/korrigiert (@gharlan)


Version 5.8.1 – 01.11.2019
--------------------------

### Neu

* Update der externen Bibliotheken

### Bugfixes

* Whoops-Seite: Safemode-Button wieder sichtbar (@bloep)
* Benutzerwechsel: beim Zurückwechseln in zwei Browserfenstern kam es unnötigerweise zu einer Exception (@tbaddade)
* Unter System wurde an die REDAXO-Version teils fälschlich der Projekt-Git-Hash angehangen (@gharlan)
* `rex_form`: Beim Löschen von Datensätzen wurden die Prios nicht neu gesetzt (@dpf-dd)
* `rex_sql`: Aufruf `insertOrUpdate` ohne tatsächliche Änderungen führte fälschlich zu einer Exception (@pschuchmann)
* `rex_sql_table`: Fehlermeldung bei Kombi `setName` und `alter` für nicht-existente Tabelle korrigiert (@gharlan)


Version 5.8.0 – 20.08.2019
--------------------------

### Neu

* PHP-Mindestversion angehoben auf 7.1.3
* Update der externen Bibliotheken (u.a. Symfony components 4.3)
* Wenn Debug-Mode aktiv, wird das Frontend vor Crawlern versteckt (noindex) (@staabm)
* Vor Aktivierung des Debug-Modes kommt eine Bestätigungsbox (@skerbis)
* Session-Cookie:
    - `samesite` default auf `lax` statt `strict`, um unerwartete Backend-Logouts zu vermeiden (@staabm)
    - `samesite` kann neu auch auf `none` gesetzt werden (@staabm)
* `rex_form`: Statt der Konstante `REX_FORM_ERROR_VIOLATE_UNIQUE_KEY` (deprecated) ist nun `rex_form::ERROR_VIOLATE_UNIQUE_KEY` zu verwenden (@staabm)
* Beispiel-`.gitignore` wird mitgeliefert (@schuer)
* Aktualisierung Backend-Übersetzungdateien: Schwedisch (@interweave-media), Spanisch (@nandes2062), Englisch (@tyrant88)

### Bugfixes

* `rex_form`: Wenn ein Fieldsetname mit "?" endete, wurden die Werte nicht gespeichert (@gharlan)
* `rex_config_form`: Es konnten nicht zwei Formulare auf einer Seite verwendet werden (@gharlan)
* `rex_stream`: Warning in PHP 7.4 vermeiden (@gharlan)
* Command `config:get`: Ausgabe endete nicht mit einer Newline (@gharlan)
* Textkorrekturen und -vereinheitlichungen (@marcohanke, @sebastiannoell)
* Im Setup stand im Header unnötigerweise "Nicht angemeldet" (@gharlan)


Version 5.7.1 – 01.04.2019
--------------------------

### Bugfixes

* REDAXO 5.7.x kann nur ausgehend von >=5.6 aktualisiert werden, geprüft wurde aber nur auf >= 5.4 (@gharlan)
* Asset-Streaming (über `redaxo/index.php`):
    - `?asset=`-Parameter unterstützte keine absoluten Pfade (`/assets/...`), was zu Problemen in manchen AddOn-Konstellationen führen konnte (@staabm)
    - SourceMap-Dateien wurden teils versucht über falsche Pfade zu laden (werden nun gar nicht mehr geladen) (@gharlan)
* `rex_config`: Wenn ein Wert gesetzt, und der Key direkt wieder gelöscht wurde, kam es zu einem Fehler (@gharlan)
* Die Benutzerrechte wurden case-sensitive sortiert (@gharlan)
* Update der externen Bibliotheken (@gharlan)


Version 5.7.0 – 12.03.2019
--------------------------

### Wichtig

REDAXO 5.7.x ist die letzte Version die mit PHP 7.0 oder älter kompatibel ist.
Ab REDAXO 5.8.x wird PHP 7.1 oder neuer vorrausgesetzt.

### Neu

* System-Page:
    - Überarbeitung/Optimierung von System/Einstellungen (@tbaddade, @skerbis)
    - Zentrale Page für Logdateien, mit REDAXO-, PHP-, PHPMailer-Log und zukünftig ggf. weiteren (@staabm)
    - Packages können eigene Logfiles in der neuen zentralen System-Log-Seite einbinden (@staabm)
    - Systembericht mit Infos zu REDAXO, AddOns, PHP, Server (auch als Markdown zum Kopieren und Verwenden in GitHub-Issues etc.) (@gharlan)
* Fehlerbehandlung:
    - Whoops: Button "Copy as markdown" um Exception, Stacktrace und Systembericht zusammen als Markdown zu erhalten für Issues etc. (@gharlan)
    - Schönere Fehlerseite im Frontend und Backend (wenn nicht als Admin eingeloggt) (@elricco, @staabm, @tbaddade)
    - Die neuen Fehlerseiten können via Fragment angepasst werden (@tbaddade, @staabm)
* Editor-Integration:
    - Unter System kann ein Editor ausgewählt werden; Quellcode-Dateien werden dann (z.B. in Whoops) so verlinkt, dass man sie direkt in dem Editor öffnen kann (@staabm, @gharlan)
    - Mit der `rex_editor`-Klasse können an weiteren Stellen Editor-URLs erzeugt werden (@staabm)
    - Über den EP `EDITOR_URL` können die URLs manipuliert werden (@gharlan)
* Console:
    - Manche Core Commands können nun bereits vor dem Setup ausgeführt werden (@bloep)
    - Neuer Command `config:get` (@bloep)
    - Neuer Command `db:set-connection` (@bloep)
    - Neuer Command `user:create` (@staabm)
* `rex`: Neue Methode `isFrontend` (@staabm)
* `rex_list`/`rex_form`: Nutzbar mit den weiteren Datenbanken (@gharlan)
* `rex_i18n`: Neue Methode `msgInLocale` zum Übersetzen in andere Sprachen ohne die Default-Sprache zu ändern (@staabm)
* `rex_path`: Neue Methode `relative()` um aus einem absoluten Pfad einen relativ zum Projekt-Root zu bekommen (@gharlan)
* `rex_file`: Schreibvorgänge sind nun atomar (@staabm)
* `rex_sql`:
    - `addGlobal[Create/Update]Fields`: Umgebung (frontend/console) als Defaultwert für Benutzer (@staabm)
    - Debug-Ausgabe erweitert um aufgelöstes SQL-Statement inkl. Parametern (@aeberhard)
* `rex_clang`: Methode `count` hat optionalen Parameter `$ignoreOffline` (@tbaddade)
* `rex_response`: Unterstützung für HTTP-Range (@bloep)
* `rex_view`: Für JS-Dateien können Optionen gesetzt werden (defer/async/immutable) (@staabm)
* Es werden unterschiedliche Namespaces für Session-Variablen im Frontend und Backend verwendet, über `rex_request::clearSession` können diese getrennt voneinander gelöscht werden (@staabm)
* Neue Api-Function `rex_api_has_user_session` um den Status der Backend-Session abzufragen. Damit können u.a. Single-Sign-On Mechanismen realisiert werden. (@staabm)
* Setup-Hinweise bzgl. Sicherheit:
    - Warnung bei veralteter PHP-Version (@staabm)
    - Warnung bei XX7-Berechtigungen im Dateisystem (@staabm)
* README-Ausgabe, Markdown-Pages:
    - Sprachunterstützung (README.de.md etc.) (@staabm, @gharlan)
    - Sprungankernavi (@gharlan, @tbaddade)
* Readme für das Project-AddOn (@dtpop)
* Aktiver Debug-Modus wird durch Icon im Header angezeigt (@schuer)
* Verständlichere CSRF-Meldung (@alexplusde)
* Backend-Übersetzungdateien:
    - Neu: Niederländisch (noch ohne Core-AddOns) (@MaxKorlaar)
    - Aktualisierung: Englisch (@ynamite, @skerbis), Schwedisch (@interweave-media), Spanisch (@nandes2062)
* Default-Passwortregeln: Max. Länge von 4096 Zeichen (@staabm)
* bootstrap-select wird an weiteren Stellen verwendet (@skerbis, @schuer)
* REX-Vars: Generierter PHP-Code enthält am Anfang Original-Var-Code als Kommentar (@staabm, @gharlan)
* Verbesserung der Usability durch neue Beschreibungstexte, oder Präzisierung vorhandener (@schuer, @alexplusde)
* Datum aus Footer entfernt (@staabm)
* htaccess-Check: Bei den Direktaufrufeversuchen wird ein Parameter `?redaxo-security-self-test` an die Dateien gehangen (@staabm)
* Sicherheit:
    - Bei Logout aus dem Backend werden temporäre Daten auf dem Server sofort gelöscht (@staabm)
    - Im Backend wird eine rudimentäre HTTP Content-Security-Policy verwendet (@staabm)
* Performance:
    - Backend-Assets können optional über index.php geladen werden, um optimierte Cache-Header (immutable) setzen zu können (aktiv für Core-Assets) (@staabm)
    - Per Server Timing Api werden im Debug-Modus, oder bei authentifizierten Adminsessions, Metriken an den Client gesendet (@staabm)
    - Weniger Dateioperationen im Backend um Datei-basiertes Cachen zu beschleunigen (@staabm)
    - Übersetzungen können schneller verarbeitet/dargestellt werden (@staabm)
    - Viele kleinere und größere Performance-Optimierungen (@staabm)
* Update der externen Bibliotheken
* API-Dokumentation unter https://www.redaxo.org/api/master/ übersichtlicher durch neue subpackages (@staabm)

### Bugfixes

* Versionsbedingungen: Bei `^2.0` wurde fälschlich `3.0-beta` akzeptiert (@gharlan)
* Profil: Sprachen waren nicht sortiert und Änderungen wirkten sich nicht direkt nach Speichern aus, erst nach Reload (@skerbis, @bloep)
* Bei aktiviertem Safe-Mode blieb der Button unter "System" trotzdem bei "Safe mode aktivieren" (@skerbis)
* `rex_sql`: Insert ohne explizite Values warf Fehler (@gharlan)
* `rex_sql_table`: Für `timestamp`/`datetime`-Spalten konnte nicht der Default-Wert `CURRENT_TIMESTAMP` gesetzt werden (@gharlan)
* `rex_form`: Media-/Link-/Prio-Felder konnten nicht mit `rex_form_base` bzw. `rex_config_form` verwendet werden (@christophboecker)
* `rex::getVersionHash` funktionierte nicht auf Windows Servern (@staabm)
* Autoloader-Cache wird bei Fehlern nicht mehr geschrieben, um unvollständigen Cache zu vermeiden (@staabm)
* Nach Session-Ablauf wird bei erneutem Seitenaufruf der Browser-Cache gelöscht (wie bereits bei explizitem Logout) (@staabm)
* Besseres Escaping nutzen mittels `rex_escape` (@bloep, @gharlan)
* EP `PASSWORD_UPDATED`: User-ID wurde nicht korrekt übergeben (@staabm)
* Im Chrome kam es zu Warnungen bzgl. des Font-Preloadings (@bloep)
* Wenn der Client keinen `User-Agent`-Header schickt, kam es zu einer Warnung (@staabm)
* Bei frühen Fehlern in der Console konnte es passieren, dass die HTML-Fehlerseite ausgegeben wurde (@staabm)


Version 5.6.5 – 10.12.2018
--------------------------

### Security

* Update des phpmailers wg. Sicherheitslücken

### Bugfixes

* Update der externen Bibliotheken


Version 5.6.4 – 01.10.2018
--------------------------

### Security

* Sicherheitslücken (SQL-Injection) in der Benutzerverwaltung geschlossen (gemeldet von @Balis0ng, ADLab of VenusTech) (@staabm)
* XSS Sicherheitslücken (Cross-Site-Scripting) im Medienpool behoben (gemeldet von @Balis0ng, ADLab of VenusTech) (@bloep)
* XSS Sicherheitslücken (Cross-Site-Scripting) im Mediamanager behoben (gemeldet von @Balis0ng, ADLab of VenusTech) (@staabm)


Version 5.6.3 – 26.09.2018
--------------------------

### Security

* Kritische Sicherheitslücke (SQL-Injection) in der rex_list Klasse geschlossen (gemeldet von @Balis0ng, ADLab of VenusTech) (@staabm)


Version 5.6.2 – 10.07.2018
--------------------------

### Security

* Kritische Sicherheitslücke (Path Traversal) im Media-Manager-Addon geschlossen (gemeldet von Matthias Niedung, https://hackerwerkstatt.com) (@gharlan)

### Bugfixes

* `rex_sql`: BC-Break in `showTables` rückgängig gemacht, die Methode liefert nun auch wieder Views; Methode als deprecated markiert, stattdessen neue nicht-statische Methoden `getTables`, `getViews` und `getTablesAndViews` (@gharlan)
* Command `db:connection-options` konnte nicht mit `mysqldump` genutzt werden (@gharlan)
* `rex_http_exception`: Message der Orignal-Message nutzen statt leerer Message (@gharlan)


Version 5.6.1 – 21.06.2018
--------------------------

### Neu

* Erläuterung für Debug-Modus (@alexplusde; Übersetzung: @ynamite, @interweave-media, @nandes2062)
* Performance-Optimierungen (@staabm)

### Bugfixes

* Identität wechseln: Beim Versuch, in einen inaktiven Benutzer zu wechseln, kam es zu einem Fehler (@gharlan)
* `rex_delete_cache`: Wenn vor dem Aufruf `rex_config`-Werte gesetzt wurden, gingen diese verloren und wurden nicht gespeichert (@gharlan)
* `rex_list`: Bei Nutzung mehrere Listen auf einer Page griff die Paginierung immer synchron für beide Listen (@staabm)
* `rex_sql`: Bei Aufruf von `next()` und anschließend `getRow($fetch_type)` hatte der Parameter `$fetch_type` keine Auswirkung (@joachimdoerr)
* `rex_fragment::parse`: Nicht funktionierenden Parameter `$delete_whitespaces` entfernt (@staabm)


Version 5.6.0 – 05.06.2018
--------------------------

### Security

* Siehe mediapool-Changelog
* `rex_string::buildAttributes`: Die Attribute wurden nicht escaped, wodurch unter Umständen XSS möglich war (ggf. kontrollieren, ob man dort Attribute übergeben hat, die bereits escaped waren) (@gharlan)

### Neu

* MySQL-Mindestversion 5.5.3
* Update Symfony-Komponenten (3.4.11), Symfony-Polyfills (neu: ctype) (1.8.0), parsedown (1.7.1) (@gharlan)
* HTTPS kann für Frontend und/oder Backend erzwungen werden (Umleitung und optional HSTS-Header) über `config.yml` und Setup (@bloep)
* Admins können in die anderen Benutzer wechseln, ohne deren Passwort zu kennen (@gharlan)
* Im Debug-Mode kann Whoops optional auch für Warnings/Notices aktiviert werden (@gharlan)
* Safe-Mode kann aus System-Page heraus gestartet werden (@alexplusde, @tbaddade)
* Setup:
    - Webserver-Adresse wird automatisch eingetragen (@alexplusde, @tbaddade)
    - bootstrap-select wird verwendet (@skerbis)
* Packages-Page: Lizenz in Kurzform wird gelistet mit Link zu kompletter Lizenz (@staabm, @tbaddade, @gharlan)
* project-Addon wird per default spät geladen (`load: late` in package.yml, wird bei Update nicht gesetzt) (@gharlan)
* Beim Core-Update werden in der `config.yml` alle neuen Optionen ergänzt (@gharlan)
* Doku wird im Footer verlinkt (@olien)
* Backend-Übersetzungsdateien:
    - Englisch und Deutsch dienen als Fallback, wenn Übersetzungen fehlen (@gharlan)
    - Englisch aktualisiert (@ynamite)
    - Schwedisch aktualisiert (@interweave-media)
    - Spanisch aktualisiert (@nandes2062)
* Neue Consolen-Commands:
    - `user:set-password`: Neues Passwort für Benutzer setzen (@gharlan)
    - `setup:check`: Umgebung (Versionen, Dateirechte...) prüfen (@staabm)
    - `assets:sync`: Assets zwischen /assets und den assets-Ordnern in `src` synchronisieren (@staabm)
    - `db:connection-options`: Liefert die Optionen um sich mit dem `mysql` cli tool mit der DB zu verbinden (@gharlan)
* `rex_form`:
    - Neue abstrakte Basisklasse `rex_form_base` für alternative Speichermethoden, neue Klasse `rex_config_form` für Speicherung in `rex_config` (@gharlan)
    - Führende/nachfolgende Leerzeichen werden nach dem Senden entfernt (@staabm)
* `rex_sql`:
    - Für die Connection wird utf8mb4 genutzt (@gharlan)
    - Neue Methoden für die Nutzung von Transactions (@staabm)
    - Neue Methode `insertOrUpdate` für `INSERT .. ON DUPLICATE KEY UPDATE`-Queries (@gharlan)
    - Mehrere Datensätze können gleichzeitig eingefügt/aktualisiert/ersetzt werden (`addRecord`) (@gharlan)
* `rex_backend_login::hasSession`: Es wird nun keine Session mehr gestartet, wenn bereits der Session-Cookie nicht existiert (@VIEWSION)
* `rex_response`:
    - `sendFile`: Dateiname kann angegeben werden (@bloep)
    - `sendResource`: Content-Disposition und Dateiname können angegeben werden (@gharlan)
    - Neue Methode `sendCookie` (@staabm)
* `rex_file::copy`: Zugriffszeit wird auch übernommen (@staabm)

### Bugfixes

* Nach explizitem Logout funktioniert teils der direkte erneute Login nicht (CSRF-Token-Fehler) (@bloep)
* Cache löschen: Teils blieb der `rex_config` Cache erhalten (@gharlan, @tbaddade)
* Beim Deaktivieren/deinstallieren von Packages wurde dessen Cache nicht gelöscht (@bloep)
* `rex_sql`: `showTables` enthielt auch Views (@gharlan)
* `rex_sql_table`: Es konnten nicht mehrere Fulltext-Indexe gesetzt werden (@gharlan)


Version 5.5.1 – 05.01.2018
--------------------------

### Security

* Kritische Sicherheitslücke (Path Traversal) im Media-Manager-Addon geschlossen (gemeldet von @patrickhafner, KNOX-IT GmbH) (@gharlan)

### Bugfixes

- `rex_sql::hasNext` hat teilweise fälschlich `true` geliefert (@DanielWeitenauer)
- `rex_console_command::decodeMessage` hat Anführungszeichen nicht korrekt behandelt (@staabm)


Version 5.5.0 – 21.12.2017
--------------------------

### Security

* CSRF-Schutz (@gharlan):
    - für alle `rex_form` automatisch
    - für eigene Api-Function per Opt-in
    - für manuelle Formulare und Aktionen leicht integrierbar
    - Im Core bei allen relevanten Stellen integriert

### Neu

* Update Symfony-Komponenten (3.4.2), parsedown (1.6.4), whoops (2.1.14) (@gharlan)
* Backend-Übersetzungsdateien:
    - Aktualisierung Schwedisch (@interweave-media)
    - Aktualisierung Spanisch (@nandes2062)
    - Italienisch hinzugefügt (@Fanello, @lexplatt)
* Setup:
    - Warnung, wenn Setup nicht über HTTPS ausgeführt wird (@staabm)
    - Warnung, wenn Apache-Modul mod_security geladen ist (@staabm)
* Weniger strikte Default-Passwortregeln (nur min. 8 Zeichen) (@IngoWinter)
* Backend-Session wird duch regelmäßige Ajax-Calls erhalten (@IngoWinter)
* Bei Passwortfeldern kann in den Klartextmodus gewechselt werden (@pwechs83, @staabm, @tbaddade)
* Neue Consolen-Commands:
    - `cache:clear` (@bloep)
    - `package:install/uninstall/activate/deactivate` (@bloep)
    - `db:dump-schema` (@gharlan)
* In der Console ist die (Backend)Sprache fix auf englisch (@gharlan)
* In Log-Dateien werden Zeilenumbrüche erhalten (@VIEWSION)
* System-Log neu im Data- statt Cache-Ordner (@gharlan)
* bootstrap-select wird an mehr Stellen verwendet (statt normale Selects) (@skerbis)
* `rex_response`: Neue Methode `preload()` zum Setzen von preload-Headern (@bloep)
* `rex_request`: Neue Methode `isHttps()` (@staabm)
* `rex_socket`:
    - Neue Methode `followRedirects()` (@gharlan)
    - Warnung wenn Non-SSL-Verbindung aufgebaut wird (@staabm)
* `rex_fragment`: Method-Chaining ist möglich (@DanielWeitenauer)

### Bugfixing

* Setup:
    - Escaping fehlte an einigen Stellen (@staabm)
    - Teilweise kam es zu Fehlern während der Reinstallation der Addons (@gharlan)
* Profil: Beim EP `PROFILE_UPDATED` wurde die User-ID nicht korrekt übergeben (@gharlan)
* `rex_i18n`: Die Parameter wurden nicht escaped (@gharlan)
* `rex_form`: `setApplyUrl` hatte keine Auswirkung (@bloep)
* `rex_sql_table`: Bei noch nicht existenter Tabelle konnte es zu einer Exception kommen (@gharlan)


Version 5.4.0 – 04.10.2017
--------------------------

### Neu

* Updates: symfony/yaml (3.3.9), symfony/var-dumper (3.3.9), filp/whoops (2.1.10), erusev/parsedown (1.6.3)
* Neue Funktion `rex_escape`, diese kann und sollte statt `htmlspecialchars` für Ausgaben verwendet werden (@gharlan)
* Integration von symfony/console für die einfache Bereitstellung von Consolen-Kommandos in Addons (@gharlan)
* `rex_sql_table`:
    - Tabellen können auch neu erstellt, umbenannt und gelöscht werden (@gharlan)
    - Spaltennamen und Spaltenreihenfolge kann geändert werden (@gharlan)
    - Indexes und Fremdschlüssel können verwaltet werden (@gharlan)
    - Es kann eine komplette Tabellendefinition angegeben werden und dann mit `ensure()` eine Überprüfung und ggf. Korrektur erreicht werden (praktisch für install.php in Addons) (@gharlan)
* `rex_sql`: Debug-Ausgaben werden über `dump`-Funktion ausgegeben (@alexplusde)
* Neue Klasse `rex_password_policy`, für das Backend können in der config.yml Passwortregeln hinterlegt werden (Achtung: Default gelten nun die Regeln min. 8 Zeichen, und jeweils min. 1 Kleinbuchstabe, Großbuchtsabe und Ziffer)
* Neue Extension Points: PROFILE_UPDATED, PASSWORD_UPDATED
* Backend-Sprachen:
    - English ergänzt (@ynamite)
    - Portugiesisch ergänzt (Taina Soares)
    - Spanisch ergänzt (@nandes2062)
* Session-Cookie-Parameter können (für Frontend und Backend getrennt) in config.yml gesetzt werden (default mit httponly und SameSite=strict) (@staabm)
* Eingeloggt-bleiben-Cookie als httponly (@staabm)
* Beim Logout werden die Daten im Browser zu der Website gelöscht (Privatsphäre) (@staabm)
* Bereits in den index.php-Dateien kann ein alternativer `path_provider` gesetzt werden für tiefgreifendere Pfadänderungen (@gharlan)
* Debug-Modus kann an der Body-Klasse `rex-is-debugmode` erkannt werden (@schuer)
* In der Tabelle rex_config liegt der Primary Key nun direkt auf (namespace, key), Spalte id entfällt (@gharlan)
* Bei Installation über git wird unter System bei der Version der Commit-Hash mit ausgegeben (@staabm)
* Whoops: Links zu php.net (@staabm)

### Bugfixes

* Setup:
    - Nach Auswahl "Datenbank existiert bereits" und "Update aus vorheriger Version" waren anschließend fälschlich wieder nur die Standardaddons aktiviert (@gharlan)
    - Beim Import eines vorhandenen Backups wurden nicht die Addons aus dem Backup aktiviert (@gharlan)
* Sprachdateien:
    - Wenn ein Wert leer war, wurde die komplette folgende Zeile als Wert genommen (@gharlan)
    - Wenn ein Wert "=" enthielt, kam teilweise was falsches raus (@tyrant88)
    - Sprachkey für Schwedisch korrigiert (se_sv -> sv_se) (@gharlan)
* REX_VARs haben teilweise Warnungen geworfen in PHP 7.1 (@gharlan)
* `rex_list`: Funktionierte nicht mit MariaDB (@staabm)
* `rex_form`: Bei Container-Feldern wurden die Default-Werte ignoriert (@gharlan)
* `rex_select`: `countOptions()` lieferte teilweise falsches Ergebnis (@staabm)
* `rex_response`: Session locks in `sendFile()` werden vermieden (@staabm)
* `rex_clang`: Clang-ID wird einheitlich als `int` behandelt und zurückgegeben (@gharlan)
* `rex_sql`: Teilweise fehlte die Query in der Exception-Message (@gharlan)
* `rex_socket`: Die tatsächliche Ursache war bei Exceptions oft nicht ersichtlich (@gharlan)
* PJAX: Beim Absenden von Formularen wird nun nach oben gescrollt (@gharlan)
* Output Buffer wurden teilweise nicht korrekt beendet (@gharlan)
* System-Log: HTML in Log-Messages wurde nicht escaped (@gharlan)
* .htaccess in geschützten Ordnern: Anpassung für Apache 2.4 (@gharlan)
* Session-ID-Neugenerierung warf teilweise Warnungen (@gharlan)
* Im Chrome erschien beim Login nicht der Passwort-speichern-Dialog (@gharlan)


Version 5.3.0 – 14.02.2017
--------------------------

### Security

* „Security“-Abschnitte beachten in den Addons backup, structure und phpmailer

### Neu

* Updates: symfony/yaml (3.2.3), parsedown (1.6.1)
* Neue Funktion dump() zur Debug-Ausgabe von Variablen (macht nur Ausgaben für im Backend eingeloggte Admins)
* Schönere Fehlerseiten über Whoops
* Bessere Sichtbarkeit des Safe-Modes
* Neue Backendsprache Schwedisch (Jürgen Weiss)
* Paginierung: Anzahl der ausgegeben Seiten wird beschränkt
* Beim Löschen von Packages wird deren Data-Ordner nicht mehr gelöscht
* Bei mehr als 3 Sprachen wird die Sprachauswahl als Dropdown angezeigt
* rex_sql:
    - Bei Abfragen kann PDO::MYSQL_ATTR_USE_BUFFERED_QUERY deaktiviert werden
    - Neue Methode getMysqlErrnp() um MySQL-spezifischen Error-Code abzufragen
    - Bei Exceptions werden die PDO-Originalexception mit übergeben
* mbstring-Extension optional (durch Polyfill)
* Cache-Buster für Backend-Assets
* Performance-Verbesserungen

### Bugfixes

* Setup:
    - Funktionierte nicht mit PHP 7.1
    - Reine SQL-Exporte (ohne Dateiarchiv) konnten nicht zum Import ausgewählt werden
    - tokenizer-Extension wurde nicht überprüft
* Autoloader: Klassen konnten teilweise nicht gefunden werden, wenn sie sehr lange Strings enthielten
* rex_sql:
    - Tabellen-/Feldnamen werden korrekt escaped
    - getErrno und getError lieferten teilweise nicht das richtige Ergebnis
    - Debug-Infos wurden im Fehlerfall nicht ausgegeben
* rex_form: Errorcode-spezifische Fehlermeldungen wurden nicht getriggert
* rex_list: Bei der Query durften keine Leerzeichen vor dem Begin stehen (" SELECT ...")
* package.yml:
    - `null`-Werte führten zu Fehler
    - Bessere Fehlermeldung, wenn `requires` kein Array ist
* Beim Aktivieren von Packages wurden nur deren Konflikte geprüft, aber nicht ob andere Packages Konflikte zu diesem notiert haben
* EP RESPONSE_SHUTDOWN blockiert nicht mehr das Beenden der Antwort und die Session
* Profil: Durch überflüssiges `</div>` wurde Footer falsch angezeigt (@aeberhard)
* Beim Auslesen von `php.ini`-Werten kam es teilweise zu einer Notice
* Bei Seitenaufrufen über pjax wurde die Skriptzeit nicht aktualisiert
* Teilweise kam es zum JS-Fehler „Permission denied to access property winObjCounter“ (@ynamite)


Version 5.2.0 – 15.07.2016
--------------------------

### Neu

* PHP-Mindestversion 5.5.9
* Updates: jquery 2.2.4, symfony/yaml 3.1.2
* Markdown-Parser integriert:
    - README.md wird in Addonhilfe ausgegeben, falls help.php nicht vorhanden
    - Markdown-Dateien können in package.yml als Subpages definiert werden
* Neues leeres "project"-Addon, wo projektspezifische Dinge abgelegt werden können
* getSupportPage() bei Packages liefert die URL immer mit Protokoll (http://) (**BC-BREAK**)
* rex_sql: $pdo-Property als protected statt private
* rex_formatter: Auch DATETIME-Strings werden unterstützt

### Bugfixes

* Navigationspunkte werden nun ohne Beachtung Groß-/Kleinschreibung sortiert
* "Last-Modified"-Header wurden fälschlicherweise nicht als GMT geliefert (@rosserl)
* Nicht-installierte Addons mit leerer package.yml erzeugten eine Warnung (@aeberhard)
* Im Safemode hat der Autoloader unter bestimmten Bedingungen einen Fehler geworfen
* Login-Seite wurde mit 401-Header ausgeliefert, was mit machen Proxys inkompatibel ist
* pjax-Timeout verdoppelt, um doppelte Ausführungen von Aktionen zu vermeiden
* Fehlermeldung bei Reload nach (De)Aktivierung von Packages beseitigt
* rex_finder: Funktionierte auf manchen NAS nicht, da dort `fnmatch` nicht zur Verfügung steht
* rex_socket: Bei POST-Requests waren keine anderen Content-Types als "application/x-www-form-urlencoded" möglich
* rex_string: highlight() erzeugte teils doppelte Zeilenumbrüche
* rex_form: Felder innerhalb von Container-Fields konnten nicht gleich heißen wie vorhandene normale Felder


Version 5.1.0 – 24.03.2016
---------------------------

### Neu

* Core-Update wieder über Setup möglich, als Alternative zum Installer
* Status (online/offline) für Sprachen
* Neue REX_VAR: REX_CLANG[]
* Neue EPs: REX_FORM_GET und REX_LIST_GET
* Extension Points: Es können Extensions direkt für mehrere EPs (Array) registriert werden
* Im Backend wird der Header "X-Frame-Options: SAMEORIGIN" gesetzt
* Favicon im Backend
* Popups (Medienpool/Linkmap): Bereits offene Fenster wurden teilweise nicht korrekt weiter verwendet
* rex_context: Methoden fromGet() und fromPost() ergänzt
* rex_list: Methode getArrayValue() ergänzt
* rex_view: Bei doppelten CSS/JS-Dateien wird Exception geworfen
* Update jQuery (2.2.1), Bootstrap (3.3.6), Font-Awesome (4.5.0), symfony/yaml (3.0.3)

### Bugfixes

* rex_stream (Einbindung Module/Templates): Funktionierte auf manchen Servern (Strato) nicht
* Durch falsche Cache-Header wurden im IE teilweise veraltete Backend-Pages angezeigt
* ETag-Header wurden im Chrome nicht gesetzt
* Es gab Umlaut-Probleme, wenn die Default-Collation der DB nicht UTF-8 ist
* Autoloader: Es werden relative Pfade statt absolute gecacht, dadurch keine Probleme beim Verschieben des Projektordners
* PAGE_TITLE_SHOWN: Das Ergebnis des EPs wurde vor dem Titel angezeigt
* Subpages wurden nicht immer im richtigen Kontext (Addon/Plugin) geladen
* Hilfedateien: Sprachkeys der Packages waren nicht verfügbar, wenn das Package nicht aktiviert war
* Beim Reinstall/Update der Packages wurden die Ladereihenfolge nicht richtig aktualisert
* Packages konnten in package.yml nicht itemAttr und linkAttr setzen
* Der "Letzte Login" der Benutzer wurde nicht richtig erfasst
* rex_form: Feldnamen mit Sonderzeichen machten teilweise Probleme


Version 5.0.1 – 09.02.2016
---------------------------

### Security

* Im eingeloggten Bereich war eine SQL-Injection im Medienpool möglich
* Im eingeloggten Bereich war XSS im Medienpool möglich

### Bugfixes

* Fehlende Übersetzungen in Englisch ergänzt (@VIEWSION)
* Bei Überprüfung der DB-Verbindung wurde keine sinnvolle Fehlermeldung ausgegeben, wenn der Datenbankname fehlte
* Navigation: Teilweise erschienen die Addons über dem Hauptmenü bei Nicht-Admins
* Einbindung von Templates/Modulen (rex_stream) funktionierte auf manchen Servern nicht
* REX_VARs:
    - Verschachtelte REX_VARs funktionierten nicht bei Nutzung der globalen Args (ifempty, prefix etc.)
    - Teilweise verschwanden direkt auf die Vars folgende Zeilenumbrüche
* Der Error-Handler kam nicht mit den neuen Error-Klassen in PHP 7 klar
* rex_validator: Die verschiedenen Validierungstypen akzeptierten keine leeren Werte, dies muss nun explizit mit notEmpty geprüft werden
* rex_form: HTML5-Validierungen werden nun nicht mehr beim Löschen- und Abbrechen-Button ausgelöst
* Dropdowns als Dropups darstellen bei zuwenig Platz nach unten
