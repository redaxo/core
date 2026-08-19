Changelog
=========

Version 2.18.3 – 31.07.2026
---------------------------

### Security

* Fehlende CSRF-Prüfungen beim Bearbeiten von Typen und Effekten, beim Leeren des Cache und beim Speichern der Einstellungen ergänzt (@gharlan)


Version 2.18.2 – 29.06.2026
---------------------------

### Security

* Stored XSS via Type-Name in `mediaIsInUse` (gemeldet von @de3erve) (@gharlan)


Version 2.18.1 – 01.06.2026
---------------------------

### Bugfixes

* Deprecated `imagedestroy` wird nicht mehr aufgerufen (@gharlan)


Version 2.17.2 – 05.09.2025
---------------------------

### Bugfixes

* Escaping des Mediatypnamens korrigiert (@gharlan)


Version 2.17.1 – 04.03.2025
---------------------------

### Bugfixes

* Beim Nutzen der Mediapool-Services im Frontend wurde der Media-Manager-Cache nicht gelöscht und der Verwendungscheck hat nicht gegriffen (@marcohanke)


Version 2.17.0 – 22.10.2024
---------------------------

### Neu

* Neuer EP `MEDIA_MANAGER_INIT`, der für jeden Aufruf getriggert wird (noch vor der Cache-Prüfung) (@gharlan)


Version 2.16.0 – 12.03.2024
---------------------------

### Neu

* Paginierung auf 100 Elemente pro Seite erhöht (@gharlan)


Version 2.15.0 – 09.02.2024
---------------------------

### Neu

* Effekt `convert2image`: Unterstützung für Videos (erfordert `ffmpeg`) (@skerbis)

### Bugfixes

* Auf Windows wurden Bilder teils neu generiert, obwohl sie bereits im Cache waren (@AndiLeni)
* Effekt `resize`: Fehler vermeiden, wenn ein Werte (Breite/Höhe) leer gelassen wurden (@gharlan)
* Effekt `header`: Fehler vermeiden bei Updates von älteren Versionen (@gharlan)


Version 2.14.0 – 28.02.2023
---------------------------

### Neu

* AVIF-Unterstützung: Verarbeitung von AVIF-Bildern und Umwandlung in AVIF (@gharlan)
* Lossless WEBP wird unterstützt (Qualität mit Wert 101) (@gharlan)
* Effekt `workspace`:
    - Optionaler Abstand zum Rand (@frood)
    - Optionales Hintergrundbild (@frood)
* Effekt `header`: `noindex` kann aktiviert werden (@bitshiftersgmbh)


Version 2.13.3 – 20.02.2023
---------------------------

### Bugfixes

* Effekt `resize`: Werte mit `px`-Suffix wurden nicht akzeptiert (@gharlan)


Version 2.13.2 – 16.12.2022
---------------------------

### Bugfixes

* Seit 2.13.1 kam es bei Nutzung von `rex_media_manager::create` teils zur Auslieferung der Bilder in Originalgröße (@gharlan)


Version 2.13.1 – 13.12.2022
---------------------------

### Bugfixes

* Wenn die Datei aus dem Cache kommt, war im Objekt der `media_path` nicht korrekt gesetzt (relevant für EPs etc.) (@dergel, @gharlan)


Version 2.13.0 – 25.07.2022
---------------------------

### Neu

* Cache-Order für generierte Bilder kann per `rex_media_manager::setCacheDirectory` geändert werden (@gharlan)

### Bugfixes

* `rex_managed_media::getMediaFilename` lieferte uneinheitliche Ergebnisse, nun einheitlich den neuen Dateinamen (@gharlan)


Version 2.12.3 – 09.05.2022
---------------------------

### Bugfixes

* Effekt `image_format`: Bei `.jpg`-Dateien (nicht `.jpeg`) war die neue Extension im Header nicht korrekt (@gharlan)


Version 2.12.2 – 03.05.2022
---------------------------

### Bugfixes

* Effekt `image_format`: Bei `.jpeg`-Dateien (nicht `.jpg`) war die neue Extension im Header nicht korrekt (@gharlan)
* Beim Löschen von Medien wurde deren Verwendung im Media Manager (z.B. als Wasserzeichen) nicht geprüft (@TobiasKrais)


Version 2.12.1 – 29.11.2021
---------------------------

### Bugfixes

* Klickfläche für den Hinzufügen-Link vergrößert (@schuer)


Version 2.12.0 – 17.11.2021
---------------------------

### Neu

* System-Mediatypen reduziert auf `rex_media_small`, `rex_media_medium`, `rex_media_large` (@schuer)
* System-Mediatypen sind nicht mehr bearbeitbar (es war sowieso nicht updatesicher möglich) (@gharlan, @bloep)


Version 2.11.1 – 21.06.2021
---------------------------

### Bugfixes

* Effekt `resize`: Warning vermeiden, wenn nur Höhe oder nur Breite gesetzt wurde (@Hirbod)


Version 2.11.0 – 03.03.2021
---------------------------

### Neu

* Neuer Effekt `image_format`, über den das Bildformat geändert werden kann (JPG, PNG, GIF, WEBP) (@aeberhard)

### Bugfixes

* System-Mediatypen konnten umbenannt werden (@staabm)
* Effekt `image_properties`: Der Effekt wirkte sich nur aus, wenn noch weitere Bildeffekte aktiv waren (@gharlan)


Version 2.10.1 – 11.11.2020
---------------------------

### Bugfixes

* Effekt `insert_image`: Bei right und bottom hatte der Abstand zum Rand das falsche Vorzeichen (@gharlan)


Version 2.10.0 – 01.07.2020
---------------------------

### Neu

* Effekt `mirror`: Opazität der Spiegelung kann gesetzt werden (@lexplatt)

### Bugfixes

* Auslesen von Medien über `rex_media_manager::create()` funktionierte nicht korrekt, wenn der Mediatype den Effekt `mediapath` beinhaltete (@gharlan)
* Table-Hover-Effekt fehlte (@tbaddade)


Version 2.9.1 – 08.05.2020
--------------------------

### Bugfixes

* Effekt `convert2img`: Wenn die PHP-Extension `imagick` installiert ist, dann wurde die Density nicht berücksichtigt und CMYK-PDFs wurden nicht korrekt in RGB umgewandelt (@lexplatt, @gharlan)
* Bedingte Felder wurden teils nicht ausgeblendet (@gharlan)


Version 2.9.0 – 10.03.2020
--------------------------

### Neu

* Effekt `convert2img`:
    - Wandelt auch SVGs in JPG/PNG um (@dergel)
    - Unterstützt Transparenzen (Farbe kann angegeben werden) (@dergel)

### Bugfixes

* SVGs wurden teils mit falschem Content-Type ausgeliefert (@gharlan)
* `rex_media_manager::getUrl` hat im Backend eine URL mit der Backend-`index.php` geliefert, was teils zu langsamen Backend-Seitenaufrufen führte (Session-Locks) (@gharlan)


Version 2.8.0 – 02.02.2020
--------------------------

### Neu

* Statt des Error-Bildes wird nun der 404-Statuscode gesendet (@gharlan)
* Effekt `convert2img`: Funktioniert nun auch ohne `exec()`-Rechte, wenn die PHP-Extension `imagick` installiert ist (@iceman-fx, @gharlan)
* Umbenennung "Mediatyp" in "Medientyp" (@alexplusde)

### Bugfixes

* Effekt `rotate`: Transparenz wurde nicht erhalten (@gharlan)


Version 2.7.0 – 20.08.2019
--------------------------

### Neu

* Effekt `header`: Optional kann der Medien-Orginalname als Dateiname im Header gesetzt werden (@alexplusde, @gharlan)
* Überarbeitete Hilfe, nun auch in englisch (@skerbis)
* Effekt `convert2img`: Prüfung, ob imagemagick verfügbar ist (@skerbis)
* Erläuterungen zu den Effekten `convert2img` und `mediapath` (@alexplusde)


Version 2.6.0 – 12.03.2019
--------------------------

### Neu

* Neue Methode `rex_media_manager::getUrl` zum Erzeugen der Media-Manager-URLs, inkl. EP `MEDIA_MANAGER_URL` (@gharlan)
* Unterstützung für HTTP-Range um Videos besser zu unterstützen (@bloep)
* Neue EPs: `MEDIA_MANAGER_BEFORE_SEND` und `MEDIA_MANAGER_AFTER_SEND` (@tbaddade)
* Recht "media_manager[]" entfernt, nur Admins dürfen Media Manager verwalten (@staabm)
* Wenn Cache-Buster-Param verwendet, werden immutable-Cache-Header gesetzt (@staabm)
* Bei (Re)Installation/Update wird `rex_sql_table` verwendet (@gharlan)

### Bugfixes

* Beim Löschen eines Media-Typs blieben die Effekte in der DB erhalten (@gharlan)
* Besserer Umgang mit großen Dateien (@bloep)
* Effekt `image_properties`: Nach Aktivierung des Interlace-Modus konnte es zu Warnings kommen, die eine korrekte Auslieferung der Bilder verhindern konnte (@gharlan)
* Effekt `flip`: Transparenz wurde nicht erhalten (@staabm)
* CSS/JS-Dateien werden nun als `text/css`/`application/javascript` statt `text/plain` ausgeliefert (@TobiasKrais)


Version 2.5.7 – 01.10.2018
--------------------------

### Security

* XSS Sicherheitslücken (Cross-Site-Scripting) behoben (gemeldet von @Balis0ng, ADLab of VenusTech) (@staabm)


Version 2.5.6 – 10.07.2018
--------------------------

### Security

* Kritische Sicherheitslücke (Path Traversal) geschlossen (gemeldet von Matthias Niedung, https://hackerwerkstatt.com) (@gharlan)

### Bugfixes

* Es wurden unnötig Cache-Dateien erstellt, auch wenn keine Effekte angewandt wurden (@gharlan)


Version 2.5.5 – 21.06.2018
--------------------------

### Bugfixes

* Effekt `convert2img`: Der Cli-`convert`-Befehl wurde teils nicht gefunden, obwohl vorhanden (@staabm)


Version 2.5.4 – 05.06.2018
--------------------------

* Sprachdateien aktualisiert


Version 2.5.3 – 03.01.2018
--------------------------

### Security

* Kritische Sicherheitslücke (Path Traversal) geschlossen (gemeldet von @patrickhafner, KNOX-IT GmbH) (@gharlan)

### Bugfixes

* Bei nicht existenten Bildern kam nicht das Error-Bild (@gharlan)


Version 2.5.2 – 22.12.2017
--------------------------

### Bugfixes

* `rex_media_manager::create()` hat einen falschen Cache-Pfad genutzt (@gharlan)


Version 2.5.1 – 21.12.2017
--------------------------

### Bugfixes

* Bei Nutzung von `setMediaPath` in Effekten griff das Caching teilweise nicht mehr richtig (@gharlan)
* Es kam teilweise zu Warnungen, da die Exif-Daten nicht eingelesen werden konnten (@IngoWinter)
* Nach Effektlöschung enthielten die Prios eine Lücke (@gharlan)


Version 2.5.0 – 07.11.2017
--------------------------

### Neu

* Bilder werden automatisch gemäß Exif-Orientation-Wert gedreht (@gharlan)
* header-Effekt: max-age bzw. immutable kann gesetzt werden (@gharlan)
* Pro Type ein eigener Cache-Ordner (@gharlan)

### Bugfixes

* Bei Typen mit Effekten, die den Pfad anpassen (mediapath), wurden die Bilder bei jedem Aufruf neu erzeugt (@gharlan)
* workspace: Es kam zu Fehlern, wenn nur ein Zielwert gesetzt wurde (@gharlan)
* workspace/resize: Bedingte Eingabefelder wurden nie ausgeblendet (@gharlan)


Version 2.4.0 – 04.10.2017
--------------------------

### Neu

* Unterstützung webp (@Hirbod)
* Neue globale Einstellung zu Webp-Qualität, PNG-Kompression und Interlace/Progressive-Modus (@Hirbod, @gharlan)
* JPG- und Webp-Qualität, PNG-Kompression, Interlace/Progressive-Modus können über Effekte gesetzt werden (@gharlan)
* Neue Effekte:
    - image_properties (JPG- und Webp-Qualität, PNG-Kompression, Interlace/Progressive-Modus) (@Hirbod, @gharlan)
    - brightness (@Hirbod)
    - contrast (@Hirbod)
* Angepasste Effekte:
    - flip: Spiegelung an X- und Y-Achse gleichzeitig möglich (@Hirbod)
* Sprechende (übersetzte) Namen für Effekte (@gharlan)

### Bugfixes

* `setSourcePath()` konnte nicht in Effekten richtig genutzt werden (@gharlan)
* Bildtyperkennung schlug teilweise fehl (@gharlan)
* Effekte:
    - flip: Native gd-Methode, 1px-Versatz, Transparenz-Erhaltung (@Hirbod)
    - greyscale: Native gd-Methode, Transparenz-Erhaltung (@Hirbod)
    - sepia: Native gd-Methode, Transparenz-Erhaltung (@Hirbod)
    - sharpen: Warf teilweise Warnings (@gharlan)
    - mediapath: Teilweise kam bei eigentlich existierenden Bildern trotzdem das Error-Bild (@gharlan)
    - header: Korrektur no_cache-Header (@gharlan)

Version 2.3.0 – 21.02.2017
--------------------------

### Neu

* Einfache Methode um an die generierte Cache-Datei zu kommen und so die Bildmaße etc. auszulesen (`rex_media_manager::create($type, $file)->getMedia()`)

### Bugfixes

* Mit PHP 5.5 wurden die Medien nicht mehr ausgeliefert


Version 2.2.0 – 14.02.2017
--------------------------

### Neu

* Neuer Effekt rotate zum Drehen der Bilder (@alexplusde)
* Medientypen können dupliziert werden (@phoebusryan)
* Ggf. geöffnete Sessions werden frühzeitig abgebrochen, um Session Locking zu mindern

### Bugfixes

* „Cache löschen“ führte auf Anleitung statt auf Typen-Page (@ynamite)


Version 2.1.0 – 15.07.2016
--------------------------

### Neu

* Neue Effekte: colorize (@phoebusryan), convert2image

### Bugfixes

* Effekte insert_image, filter_blur und rounded_corners repariert
* crop-Filter schneidet nun auch zu, wenn nur eine Seite größer als Zielgröße ist
* fileinfo-Extension war nicht als Abhängigkeit hinterlegt
* Bei gecachten Bildern wurde bei der Auslieferung nicht der Output Buffer geleert


Version 2.0.2 – 24.03.2016
--------------------------

### Bugfixes

* Fehler im png/alpha Handling beseitigt
