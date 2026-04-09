Changelog
=========

Version 3.8.0 – 09.04.2026
--------------------------

* Login-Seite: Neues Hintergrundbild (@tbaddade, @gharlan)
* Multiple-Selects: Häkchen für ausgewählte Optionen erscheint links vom Text statt ganz rechts (@tbaddade, @gharlan)
* Moduleingabe: Sticky footer (Übernehmen-/Speichern-Button) (@ynamite)


Version 3.7.0 – 05.09.2025
--------------------------

* Login-Seite: Neues Hintergrundbild (@schuer, @gharlan)


Version 3.6.0 – 17.07.2025
--------------------------

* Login-Seite: Neues Hintergrundbild (@gharlan)


Version 3.5.1 – 10.02.2025
--------------------------

* Update Font Awesome auf 6.7.2 (@alxndr-w)


Version 3.5.0 – 22.10.2024
--------------------------

* Login-Seite: Neues Hintergrundbild (@dergel)
* Update Font Awesome auf 6.5.2 (@madiko)
* Neues Icon für Cronjob-Menüpunkt (@alxndr-w)


Version 3.4.1 – 07.04.2024
--------------------------

* Alte Login-Hintergründe wurden beim Update nicht aus dem Root-Assets-Ordner entfernt (@gharlan)


Version 3.4.0 – 12.03.2024
--------------------------

* Login-Seite: Neues Hintergrundbild (@gharlan)


Version 3.3.1 – 24.02.2024
--------------------------

* Customizer: Beim Header-Link zum Frontend wurde das Icon teilweise nicht angezeigt (@danspringer)


Version 3.3.0 – 09.02.2024
--------------------------

* Font Awesome: Major-Update auf v6.5.1 (FA4-icons deprecated; Changelog FA: https://fontawesome.com/docs/changelog/) (@madiko, @tbaddade)
* Login-Seite: Neues Hintergrundbild (@tbaddade, @gharlan)


Version 3.2.0 – 28.02.2023
--------------------------

* Login-Seite: Neues Hintergrundbild (@schuer)
* Markierungsfarbe in AddOn-Tabelle nach Installer-Download optimiert (@pwechs)
* CodeMirror: LineWrapping per Default deaktiviert (@aeberhard)
* Customizer: Frontend-Link anhand tatsächlicher Domain, nicht über `rex::getServer()` (@christophboecker)


Version 3.1.2 – 20.02.2023
--------------------------

* Dropdowns wurden teils von der Headerleiste überdeckt (@TobiasKrais)
* Darstellung HTML5-Datefelder korrigiert (@tbaddade)
* customizer: Fehlende Config-Werte werden besser abgefangen (@gharlan)


Version 3.1.1 – 13.12.2022
--------------------------

* Suchfeld: Bei eigener Nutzung war je nach Wrapper teils der Clear-Button nicht sichtbar (@eaCe)
* scss-compiler: Bei Exceptions kam trotzdem Exit-Code 0 (@staabm)


Version 3.1.0 – 25.07.2022
--------------------------

* Login-Seite: Neues Hintergrundbild, dazu Styleanpassungen (@schuer)
* JS-Darkmode-Detektor (`rex:theme.change`) (@christophboecker)
* CodeMirror-Aktualisierung (@aeberhard)
    - Unterstützung des Darkmodes (Default-Theme `dracula`)
    - Eigene globale Optionen können gesetzt werden


Version 3.0.1 – 10.01.2022
--------------------------

* Sichtbarkeit der Elemente in der mobilen Navbar korrigiert (@schuer)
* Im Dark-Mode waren aufgeklappte Selectboxen teilweise hell (@schuer)
* Bilder innerhalb von Slices wurden im Backend teilweise verzerrt dargestellt (@alxndr-w)
* `boostrap-select`: Statt "Bitte wählen" wird "—" angezeigt (weniger Verwirrung bei optionalen Feldern und bei leeren Selectboxen) (@gharlan)


Version 3.0.0 – 17.11.2021
--------------------------

* Dark-Mode (siehe Core-Changelog) (@schuer)
* Navigation grundlegend überarbeitet und für kleinere Bildschirme optimiert (@schuer)
* Weitere UI- und UX-Verbesserungen (@schuer)
* Login-Seite: Neues Hintergrundbild, dazu Styleanpassungen (@schuer)
* Neuer Loading-Spinner (@schuer)
* Optimierung der Darstellung von markdown-Dateien (@tbaddade)
* Bootstrap-Tooltips werden global aktiviert (@skerbis)
* `theme-color`-Metatag wird nicht mehr gesetzt (@gharlan)


Version 2.12.1 – 21.06.2021
---------------------------

* Login-Seite: 
    - Hintergrundbild und deren Credits werden nun auch auf kleinen Screens angezeigt (@schuer)
    - Bei geringer Höhe schob sich der Footer über die Login-Box (@schuer)
    - Darstellungen Eingabefelder im iOS-Safari korrigiert (@schuer)
* `structure/version`: Die Leiste für den Wechsel zwischen Arbeits-/Liveversion wurde mobil nicht korrekt dargestellt (@schuer)
* `mediapool`: Seit Chrome 91 stimmten die Spaltenbreiten teilweise nicht mehr (@schuer)


Version 2.12.0 – 03.03.2021
---------------------------

* Login-Seite modernisiert, u.a. mit vollflächigem Hintergrundbild (kann über Fragment geändert werden) (@schuer)
* Es werden die Systemschriften statt Lucida Grande verwendet, mit etwas größerer Schriftgröße (@schuer)
* Navigation: Die Menüpunkte und Trennlinien nehmen gesamte Breite der Sidebar ein und die Icons stehen zentriert untereinander (@schuer)
* Navigation im Setup mit optimierter Darstellung bzgl. aktiver/disabled Setupschritte (@schuer)
* Grüneres Grün im Backend (@schuer)
* Tab-Darstellung optimiert/modernisiert (@schuer)
* Das Herzsymbol neben dem Logo bei aktivem Debug-Modus pulsiert für bessere Sichtbarkeit (@staabm)
* Klickbare Fläche um Links herum an vielen Stellen vergrößert (@schuer)
* Alert-Meldungen innerhalb von Tabellen werden nahtlos ohne Abstand in die Zeilen eingefasst (@schuer)
* Bei Sprüngen in der AddOn-Liste wird das Zieladdon kurz farblich hervorgehoben (@skerbis)
* Wortumbrüche bei langen Zeichenketten an vielen Stellen optimiert (u.a. Systemlog) (@schuer)
* Readonly-Inputfelder reagieren nicht mehr auf focus/hover (@skerbis)
* Buttons in Input-Groups haben dieselbe Höhe wie die Inputs (@schuer)
* Höhe der Breadcrumbs und Sprachauswahl optimiert (@schuer)
* bootstrap-select: Es wurden ausschließlich die mitgelieferten deutschen Texte verwendet (@gharlan)
* Während Update wurden Vendor-Files von bootstrap-select und fontawesome nicht korrekt aktualisiert (@gharlan)
* Weitere Layoutoptimierungen (@schuer)
* Customizer: Das Farbeingabefeld unterstützt zusätzlich den Standard-Colorpicker (@staabm)
* Customizer: Codemirror-Update auf Version 5.58.3 (@aeberhard)


Version 2.11.1 – 11.11.2020
---------------------------

* Selectboxen: Text der aktuellen Auswahl überlappte teilweise mit dem Pfeil am rechten Rand (@tbaddade)


Version 2.11.0 – 01.07.2020
---------------------------

* Anpassungen für Änderungen in den Core-Addons
* Farben für Systemlogzeilen korrigiert (@gharlan)
* Font-Awesome wird nicht mehr per Preloading geladen (@staabm)


Version 2.10.1 – 08.05.2020
---------------------------

* Markdown-Pages: Bei schmalem Inhalt war die Sprungnavi nicht am rechten Rand (@bloep)


Version 2.10.0 – 10.03.2020
---------------------------

* Customizer: Abhängigkeit zur PHP-Extension "zip" explizit hinterlegt (@staabm)
* Customizer: Beim Entpacken wurde ein relativer Pfad verwendet, wodurch bei manchen das Entpacken nicht funktionierte (@gharlan)
* Die `bootstrap-select.min.js.map` fehlte (@gharlan)


Version 2.9.0 – 02.02.2020
--------------------------

* Der Ajax-Loader-Layer erscheint erst mit Verzögerung um Flackern bei sehr schnellen Seitenladungen zu vermeiden (@gharlan)
* Tabellenlayout optimiert (@schuer)
* Submodule entfernt (@schuer, @gharlan)
* In Markdown-Ausgaben hatten Listen ab zweiter Ebene keine Listenpunkte (@gharlan)
* Customizer-Layout korrigiert (@schuer)
* Update CodeMirror (5.51) mit neuen Optionen (@aeberhard)
    - addon autorefresh.js hinzugefügt wg. hidden Textarea bei cronjobs
    - CSS Standardhöhe CodeMirror von 330px auf 490px angepasst, border hinzugefügt
    - neue Option AutoResize, codemirror-autoresize.css hinzugefügt
    - ESC-Taste für fullscreen (mac), Hinweis auf Fullscreen-Modus bei den Optionen
    - comdemirror.css -> codemirror.min.css
    - comdemirror-compressed.js -> codemirror.min.js
* Beim Update wurden die CodeMirror-Assets nicht aktualisiert (@gharlan)


Version 2.8.1 – 01.11.2019
--------------------------

* Favicon und zugehörige Dateien wurden mit falschem Pfad eingebunden (@gharlan)


Version 2.8.0 – 20.08.2019
--------------------------

* Customizer-Farbe wird für `theme-color`-Metatag verwendet (@tbaddade)
* Assets nutzen immutable cache (@staabm)
* ID- und Prio-Spalten breiter (für größere Zahlen) (@tbaddade)
* Abstand nach Paginierung korrigiert (@tbaddade)
* SCSS-Compiler: Methode `setStripComments` entfernt, da diese sowieso noch nie funktioniert hat (@staabm)


Version 2.7.1 – 01.04.2019
--------------------------

* Markdown-Ausgabe: Layout nicht mehr in der Breite zerschießen (@ansichtsache)


Version 2.7.0 – 12.03.2019
--------------------------

* Layout für neue Core-Komponenten und diverse kleine Optimierungen (@tbaddade)
* Hauptnavi: Weniger Padding (top/bottom) (@schuer)
* Neue Favicons (@schuer)
* Consolen-Command `be_style:compile` (@bloep)
* Codemirror-Integration verbessert (@aeberhard)
    - Sourcen verkleinert (@aeberhard, @staabm)
    - Ergänzt um Suche (@aeberhard)
    - Vereinfachte Einbindung systemweit (@aeberhard)
    - CodeMirror wird nur geladen wenn er auch benötigt wird (@aeberhard, @staabm)
* Customizer:
    - Bessere Default-Erkennungsfarbe (@skerbis)
    - Bessere Darstellung des Links zur Website im Header (@schuer)


Version 2.6.1 – 10.07.2018
--------------------------

* Keine fixe Breite für die Aktionsspalten in Tabellen (@gharlan)
* Normale Schriftgröße für `<blockquote>` (@gharlan)


Version 2.6.0 – 05.06.2018
--------------------------

* Update bootstrap-select (1.12.4), scssphp (0.7.6) (@gharlan)
* Update CodeMirror (5.38) mit neuen Optionen (@aeberhard)
* Korrektur Suchfeld in bootstrap-select (@skerbis)


Version 2.4.0 – 21.12.2017
--------------------------

* Font-Awesome wird per preload-Header vorgeladen (@bloep)


Version 2.3.0 – 04.10.2017
--------------------------

* `max-width` für iframe, img, svg, video, object und embed in Slices im Backend (@skerbis)
* customizer: Bei der Erkennungsfarbe können nun auch Farbangaben wie `rgba(...)` genutzt werden (@gharlan)


Version 2.2.1 – 17.02.2017
--------------------------

* In Version 2.2.0 wurden die Styles aus be_style und be_style/redaxo nicht mehr als erstes geladen


Version 2.2.0 – 14.02.2017
--------------------------

* Update bootstrap 3.3.7
* Update fontawesome 4.7.0
* Update bootstrap-select 1.12.1
* REDAXO-Logo auf Loginseite sprang teilweise
* Optimierung Header auf Mobilgeräten
* Optimierung Bootstrap-Modals
* SCSS-Compiler: Ordner werden angelegt, wenn noch nicht vorhanden
* Customizer: Neues Theme material


Version 2.1.1 – 15.07.2016
--------------------------

* Update fontawesome 4.6.3
* Update scssphp 0.6.5
* Update bootstrap-select 1.10.0
* Diverse optische Korrekturen
* Customizer: Icon von "Link zur Website" auch klickbar (@alexplusde), Codemirror-Fullscreen-Modus gefixt (@alexplusde)
