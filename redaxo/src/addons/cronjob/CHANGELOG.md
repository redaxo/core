Changelog
=========

Version 2.12.1 – 31.07.2026
---------------------------

### Security

* Fehlende CSRF-Prüfung beim Löschen des Logs ergänzt (@gharlan)


Version 2.11.1 – 04.03.2025
---------------------------

### Bugfixes

* Bei Cronjob-Klassen mit Namespaces bzw. Großbuchstaben konnten die Params in der Klasse nicht korrekt abgefragt werden (@gharlan)


Version 2.10.2 – 24.02.2024
---------------------------

### Bugfixes

* Cronjob-Typen wurden nicht alphabetisch sortiert in der Auswahl (@dergel)
* Cronjob-Klassen konnten keine Namespaces enthalten (@dergel)


Version 2.10.1 – 09.02.2024
---------------------------

### Bugfixes

* Bei Aufruf über Console wird `nexttime` nicht mehr beachtet (da überflüssig und problematisch) (@gharlan)


Version 2.9.0 – 25.07.2022
--------------------------

### Neu

* Skript-Umgebung: Auflistung der ausgeführten Jobs mit Status und Meldung (@gharlan)
* Im Cronjob-Formular werden teils Checkboxen/Radios statt Selects verwendet (@gharlan)
* Anzeige Logeinträge auf 100 erhöht (@staabm)


Version 2.8.2 – 10.01.2022
--------------------------

### Bugfixes

* Validierung für die Intervall-Felder korrigiert (@gharlan)


Version 2.8.0 – 03.03.2021
--------------------------

### Neu

* Der Hinweis für die Skript-Umgebung enthält den absoluten Pfad zur REDAXO-Console (@skerbis)
* PlugIn `article_status`: Neu können die Artikel-Datumsfelder nach Statusänderung automatisch geleert werden (optional) (@tyrant88)

### Bugfixes

* Fehlendes Escaping ergänzt (@gharlan)


Version 2.7.0 – 10.03.2020
--------------------------

### Neu

* Die Umgebung, über die ein Job ausgeführt wurde, wird mit im Log gespeichert/ausgegeben (@alexplusde)

### Bugfixes

* Im Log war der Button "im Editor öffnen" nie sichtbar (@staabm)


Version 2.6.0 – 02.02.2020
--------------------------

### Neu

* Bei (Re)Installation/Update wird `rex_sql_table` verwendet (@tbaddade)
* Button zum Löschen des Logs (@skerbis)

### Bugfixes

* Die Log-Message wurde nicht escaped ausgegeben (@gharlan)
* Plugin `optimize_tables`:
    - Tabellennamen wurden nicht escaped (@alexplusde)
    - Fehlermeldungen wurden nicht ins Log geschrieben (@alexplusde)


Version 2.5.0 – 20.08.2019
--------------------------

### Neu

* Das Log wird auch unter System/Log eingebunden (@staabm)


Version 2.4.1 – 01.04.2019
--------------------------

### Bugfixes

* Typ "PHP-Code": Bei Fehlern wurde das Backend teils nur halb, ohne Layout dargestellt (@gharlan)


Version 2.4.0 – 12.03.2019
--------------------------

### Neu

* Typabhängige Felder erscheinen direkt unterhalb der Typauswahl (@skerbis)

### Bugfixes

* Bei Uhrzeit/Zeitzonen-Differenzen zwischen PHP und DB wurden die Cronjobs zu einem falschen Zeitpunkt ausgeführt (@dergel)
* optimize_tables: Views wurden fälschlich versucht auch zu optimieren (@gharlan)
* Plugins: Bei vorinstallierten Cronjobs wurde das Intervall nicht richtig gesetzt (@gharlan)


Version 2.3.0 – 05.06.2018
--------------------------

### Neu

* Command `cronjob:run`: Es kann ein einzelner Job direkt ausgeführt werden (`--job`) (@gharlan)

### Bugfixes

* Status-Toggle-Link war nicht nutzbar (CSRF-Token fehlte) (@gharlan)


Version 2.2.0 – 21.12.2017
--------------------------

### Neu

* CSRF-Schutz (@gharlan)
* Consolen-Command `cronjob:run` für die Ausführung der Jobs der script-Umgebung (das alte Skript unter `redaxo/src/addons/cronjob/bin/run` ist deprecated) (@staabm)

### Bugfixes

* Fehler werden besser abgefangen, vor allem um die Ausführung weiterer Jobs in der cli nicht zu behindern (@staabm)


Version 2.1.2 – 04.10.2017
--------------------------

### Bugfixes

* Bedingte typspezifische Parameter wurden nicht getoggelt (@gharlan)


Version 2.1.1 – 14.02.2017
--------------------------

### Bugfixes

* Langlaufende Jobs (>2h) oder bei Abbrüchen wurden die Jobs nach 2 Stunden erneut gestartet
* Wenn keine Umgebung auswählt wurde, kam es zu einem Fehler ohne sinnvolle Meldung
* Wenn die Skript-Umgebung mit einer zu niedrigen PHP-Version genutzt wurde, wurde mit unverständlicher Meldung abgebrochen


Version 2.1.0 – 30.09.2016
--------------------------

### Neu

* Neue Cronjob-Umgebung "Skript", insbesondere um die Redaxo-Cronjobs über
  einen echten Cronjob laufen zu lassen
* Flexiblere Intervallauswahl

### Bugfixes

* Cronjob-Typ "phpcallback" hat teilweise Notices geworfen


Version 2.0.3 – 15.07.2016
--------------------------

### Bugfixes

* Manuelles Ausführen von Cronjobs nicht über pjax, da es länger dauern kann


Version 2.0.2 – 24.03.2016
--------------------------

### Bugfixes

* Bei Nutzung von MariaDB wurden die Cronjobs teilweise nicht ausgeführt
