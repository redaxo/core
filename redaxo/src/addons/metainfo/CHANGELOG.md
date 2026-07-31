Changelog
=========

Version 2.12.3 – 31.07.2026
---------------------------

### Security

* Fehlende CSRF-Prüfung beim Löschen von Feldern ergänzt (@gharlan)


Version 2.12.2 – 29.06.2026
---------------------------

### Security

* Stored XSS via `isMediaInUse` (@gharlan)


Version 2.12.1 – 01.06.2026
---------------------------

### Bugfixes

* Status-Toggle in Metainfo-Sidebar funktionierte nicht bei Usern mit mehreren Mountpoints (@isospin)


Version 2.11.3 – 17.07.2025
---------------------------

### Bugfixes

* Nach Löschung eines Feldes wurden die Prioritäten nicht neu gesetzt (@skerbis)
* Escaping des Meta-Präfixes korrigiert (@gharlan)


Version 2.11.2 – 04.03.2025
---------------------------

### Bugfixes

* Wenn im Core-Setup die vorhandene Datenbank überschrieben wurde, kam es während des MetaInfo-Cleanups zum Fehler (@gharlan)


Version 2.11.0 – 12.03.2024
---------------------------

### Bugfixes

* Metainfo-Sidebar: Beim Status-Wechsel-Link wurde die aktuelle Sprache nicht mitgegeben (@gharlan)
* Nach DB-Import wurde die Cleanup-Routine nicht korrekt ausgeführt (@gharlan)


Version 2.10.1 – 09.02.2024
---------------------------

### Bugfixes

* Löschen von Medien: Verwendungsprüfung und Verlinkung korrigiert (@TobiasKrais)


Version 2.9.1 – 20.02.2023
--------------------------

### Bugfixes

* Bei Feldnotizen (`note="..."`) wurde der `translate:`-Präfix nicht berücksichtigt (@gharlan)


Version 2.9.0 – 25.07.2022
--------------------------

### Neu

* Es können Notizen/Hinweise zu Feldern hinzugefügt werden (@gharlan)


Version 2.8.5 – 03.05.2022
--------------------------

### Bugfixes

* Prepared Statements nutzen (@staabm)


Version 2.8.4 – 10.01.2022
--------------------------

### Bugfixes

* Bei Datumsfeldern funktionierte die Checkbox mit PHP 8 nicht mehr (@gharlan)


Version 2.8.3 – 29.11.2021
--------------------------

### Bugfixes

* Klickfläche für den Hinzufügen-Link vergrößert (@schuer)


Version 2.8.2 – 17.11.2021
--------------------------

### Bugfixes

* Metainfos wurden teils doppelt gespeichert, teils erneut nach den entsprechenden `*_UPDATED` EPs (@gharlan)


Version 2.8.1 – 21.06.2021
--------------------------

### Bugfixes

* Media(list)- und Link(list)-Widgets hatten teils gleiche IDs wie entsprechende Widgets in den Slices, wodurch es zu Konflikten kam (@gharlan)


Version 2.8.0 – 03.03.2021
--------------------------

### Neu

* Die CSS-Datei wird nur noch auf der Content-Page eingebunden, da sie nur dort benötigt wird (@aeberhard)


Version 2.7.3 – 25.01.2021
--------------------------

### Security

* SQL-Injection in der Feldverwaltung (Adminbereich) verhindert (@gharlan)


Version 2.7.2 – 01.07.2020
--------------------------

### Bugfixes

* Hinweistexte verbessert (@alexplusde)
* Table-Hover-Effekt fehlte (@tbaddade)


Version 2.7.1 – 08.05.2020
--------------------------

### Bugfixes

* Attribute ohne Wert (`data-foo`) wurden teils ignoriert (@gharlan)


Version 2.7.0 – 10.03.2020
--------------------------

### Neu

* Artikel-Metainfos werden immer in der Seitenleite rechts angezeigt/geändert, nicht mehr in einer eigenen Page (@dergel)

### Bugfixes

* Template-Filter: Templates mit Kategoriebeschränkung standen fälschlich nicht zur Auswahl (@gharlan)
* Der Default-Wert für die Standard-Metafelder unterschied sich zwischen MySQL und MariaDB (@gharlan)


Version 2.6.0 – 02.02.2020
--------------------------

### Neu

* Artikel-Metainfos können auf Templates beschränkt werden (@felixheidecke)
* Bei (Re)Installation/Update wird `rex_sql_table` verwendet (@tbaddade)

### Bugfixes

* Manche Queries wurden unnötigt doppelt ausgeführt (@tbaddade)


Version 2.5.1 – 01.11.2019
--------------------------

### Bugfixes

* Date/Time-Felder wurden im Medienpool nicht disabled entsprechend der zugehörigen Checkbox (@gharlan)


Version 2.5.0 – 20.08.2019
--------------------------

### Neu

* Assets nutzen immutable cache (@staabm)
* Konstanten `REX_METAINFO_FIELD_...` sind deprecated, stattdessen die Konstanten `rex_metainfo_table_manager::FIELD_...` verwenden (@staabm)

### Bugfixes

* Die Default-Werte wurden nicht so mit umschließenden Pipes versehen, wie die Werte auch nach dem Speichern abgelegt werden (@gharlan)
* Date/Time-Felder wurden nicht mehr disabled entsprechend der zugehörigen Checkbox (@gharlan)
* Die Attribute wurden nicht escaped (@staabm)


Version 2.4.0 – 12.03.2019
--------------------------

### Neu

* Bei Date(time)-Feldern kann Start- und Endjahr für Jahr-Selecbox festgelegt werden (@gharlan)

### Bugfixes

* Metadaten in Struktur-Sidebar werden nun vom Struktur-AddOn selbst geliefert (@DanielWeitenauer)


Version 2.3.1 – 05.06.2018
--------------------------

### Bugfixes

* Beim Bearbeiten der Artikel-Metainfos wurden updatedate und updateuser nicht aktualisiert (@gharlan)
* Date/Time/Datetime-Felder: Tag/Monat/Stunde/Minute nun einheitlich zweistellig, aktueller Wert war vorher teils nicht selektiert (@gharlan)


Version 2.3.0 – 21.12.2017
--------------------------

### Neu

* CSRF-Schutz (bei Api-Functions) (@gharlan)

Version 2.2.0 – 04.10.2017
--------------------------

### Neu

* `rex_metainfo_add_field`: `callback` kann gesetzt werden (@DanielWeitenauer)

### Bugfixes

* Bei Checkboxen mit Value 0 wurde der `checked`-Status nicht richig gesetzt (@gharlan)


Version 2.1.1 – 14.02.2017
--------------------------

### Bugfixes

* Medienfelder in Sprachmetainfos wurden nicht beim Media-isInUse-Check berüchsichtigt
* Feld-id-Attribute waren zu unspezifisch, konnten daher doppelt vorkommen
* Bei Einzelcheckboxes stimmten id-Attribut und for-Attribut in Label nicht überein


Version 2.1.0 – 24.03.2016
--------------------------

### Neu

* Metainfos für Sprachen
* Es werden keine Felder inital angelegt, aber die Standardfelder können über einen Button nachgerüstet werden

### Bugfixes

* Bei Multiple-Selects wurde das size-Attribut falsch gesetzt
* Bei Artikel-Metainfos wurde keine Meldung nach Speichern angezeigt


Version 2.0.1 – 09.02.2016
--------------------------

### Bugfixes

* Bei Datumsfeldern war es nicht möglich, den Monat zu ändern (Gort)
