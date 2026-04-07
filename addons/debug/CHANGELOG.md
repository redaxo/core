Changelog
=========

Version 1.3.2 – 07.04.2024
--------------------------

### Bugfixes

* Als erster Request erschien immer der Abruf der `manifest.json` (@gharlan)


Version 1.3.1 – 24.02.2024
--------------------------

### Bugfixes

* Bei Update wurde das Clockwork-Frontend nicht aktualisiert (@gharlan)


Version 1.2.2 – 25.07.2022
--------------------------

### Bugfixes

* REDAXO-Installationen in einem Unterordner funktionierten nicht (@staabm)


Version 1.2.1 – 03.05.2022
--------------------------

### Bugfixes

* Console-Commands wurden immer aufgenommen, auch bei deaktiviertem Debug-Modus (@bloep)


Version 1.2.0 – 17.11.2021
--------------------------

### Neu

* Update auf Clockwork 5.1 (@bloep)
* Light/Dark-Mode wird entsprechend der Einstellung in REDAXO gesetzt (@bloep)


Version 1.1.1 – 21.06.2021
--------------------------

### Bugfixes

* `rex_socket`-Einträge in Timeline enthielten doppelten Slash in URL (@gharlan)


Version 1.1.0 – 03.03.2021
--------------------------

### Neu

* Clockwork-Update auf Version 5 (@bloep)
* Die Boot-Zeiten der Packages werden einzeln erfasst (@bloep)
* Wenn XDebug mit Profiler-Modus aktiviert ist, können die Ergebnisse in Clockwork eingesehen werden (@bloep)
* Der Erklärungstext zum Debug-Modus wird auch auf der AddOn-Page (wenn Debug-Modus inaktiv) angezeigt (@staabm)
* Das Clockwork-Frontend wird als ZIP mitgeliefert und bei Installation entpackt (@bloep)


Version 1.0.1 – 11.11.2020
--------------------------

### Bugfixes

* Daten werden komprimiert und kürzer vorgehalten (@bloep)


Version 1.0.0 – 01.07.2020
--------------------------

### Neu

* Neues Addon um Frontend-/Backend-/Console-Aufrufe besser analysieren zu können (Performance, Datenbankabfragen, Extension Points...), basierend auf [Clockwork](https://github.com/itsgoingd/clockwork) (@bloep, @staabm, @gharlan)
