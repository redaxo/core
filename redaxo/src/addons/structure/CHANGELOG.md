Changelog
=========

Version 2.20.3 – 31.07.2026
---------------------------

### Security

* Fehlende CSRF-Prüfungen ergänzt:
  - Content: Slices anlegen, bearbeiten und löschen (@gharlan)
  - History: Version wiederherstellen und komplette Historie löschen (gemeldet von @VietTinNguyen) (@gharlan)
  - Version: Arbeitsversion übernehmen, aus Live-Version erzeugen und leeren (@gharlan)


Version 2.20.1 – 01.06.2026
---------------------------

### Security

* In den API-Functions wurden die Kategorie- und Sprach-Berechtigungen des Users nicht überall korrekt berücksichtigt (@gharlan)


Version 2.20.0 – 09.04.2026
---------------------------

### Neu

* Actions: Optional können eindeutige Schlüssel vergeben werden (ähnlich wie bei Templates/Modulen) (@skerbis)
* Linkmap: Visuelles Feedback bei Linkübernahme in Linklist (@Hasan-Alherek)
* EP `SLICE_BE_PREVIEW`: Slice-Revision wird mit als Param übergeben (@ynamite)
* Markup für die Slice-Ausgabe im Backend in Fragmente ausgelagert (@marcohanke)

### Bugfixes

* Block hinzufügen: `$this->getCurrentSlice()` liefert korrektes (weitgehend leeres) Slice-Objekt (@gharlan)


Version 2.19.0 – 05.09.2025
---------------------------

### Neu

* Neue Berechtigung, um in der Linkmap alle Kategorien auswählen zu können (auch die, für die man ansonsten keine Rechte hat) (@skerbis)

### Bugfixes

* Umwandlung Kategorie/Artikel (beide Richtungen): Priorität bei mehr als 100 Kategorien/Artikeln korrigiert (@gharlan)
* Version-Plugin: Nach Kopieren zwischen Live-/Arbeitsversion wurden die Slices der Quellversion ausgegeben (@gharlan)


Version 2.18.0 – 17.07.2025
---------------------------

### Neu

* Inhalte zwischen Sprachen kopieren: Optional können vorhandene Inhalte überschrieben werden (@omphteliba)

### Bugfixes

* History-Plugin: History-Datenbankstruktur an Struktur der Originaltabelle `rex_article_slice` angepasst (@dergel)


Version 2.17.4 – 04.03.2025
---------------------------

### Bugfixes

* Wenn eine neue Sprache mit Prio=1 angelegt wird, wurden die Kategorien/Artikel nicht in die neue Sprache dupliziert (@gharlan)
* Beim Löschen von Sprachen aus dem Frontend heraus wurde die Artikel-Slice-Tabelle nicht aufgeräumt (@marcohanke)
* History-Plugin: Es wurden nur Snapshots erstellt, wenn Benutzer mit Rollback-Recht die Artikel bearbeitet haben, und nur im Backend (@marcohanke)
* Article/Category-Service: Es wurde nicht abgefangen, wenn die Zielkategorie nicht existiert (@dergel, @gharlan)


Version 2.17.3 – 10.02.2025
---------------------------

### Security

* Content-Page: Der Artikelname wurde in in der Überschrift nicht escaped (Stored XSS; gemeldet von @geo-chen) (@gharlan)


Version 2.17.2 – 22.10.2024
---------------------------

### Bugfixes

* `rex_article_not_found_exception`: Die aktuelle Artikel-ID wurde nicht auf den Fehlerartikel umgeschwenkt (@tyrant88)
* Artikelbearbeitung: Fehler vermeiden bei Benutzern mit limitierten Rechten (@gharlan)


Version 2.17.1 – 07.04.2024
---------------------------

### Bugfixes

* Modulverwaltung: "Eingabe"-Feld wurde ohne Codemirror angezeigt (@skerbis)


Version 2.17.0 – 12.03.2024
---------------------------

### Bugfixes

* Der Templatename wurde in der Struktur ohne Escaping ausgegeben (@gharlan)
* Create/Update-Felder (Datum/Benutzer) wurden teils nicht korrekt gesetzt (@gharlan)
* `rex_module::forKey`: Im Objekt war der Key anschließend nicht gesetzt (@gharlan)


Version 2.16.0 – 09.02.2024
---------------------------

### Neu

* Neuer EP `PAGE_STRUCTURE_ARTICLE_ORDER_BY` um die Artikelsortierung zu ändern (@ynamite)
* Neuer EP `SLICE_BE_PREVIEW` um die Slice-Vorschau im Backend ändern zu können (@michael-kreatif)

### Bugfixes

* Bei Kategorie-Statuswechsel wurde Createdate statt Updatedate gesetzt (@tbaddade)
* Kategorie-Löschen funktionierte nicht, wenn es keine Clang mit ID 1 gibt (@alxndr-w)
* Slice löschen: Bei anschließendem Reload Whoops vermeiden (@gharlan)
* `rex_template::forKey()` korrigiert (@dgrothaus-mc)


Version 2.15.0 – 28.02.2023
---------------------------

### Neu

* Struktur: Template-Spalte optimiert (@pwechs)
* Artikel-Editieransicht: Artikel-Status kann in der Metadaten-Box rechts geändert werden (@pwechs)
* Templates: Bei Inaktiv-Setzung Prüfung, ob Template noch aktiv genutzt wird (@pwechs, @gharlan)
* `rex_template`: Neue Methode `exists` (@staabm)
* Datenbank: Überflüssige Indexe entfernt (@gharlan)


Version 2.14.3 – 20.02.2023
---------------------------

### Bugfixes

* Template-Liste: Templatename wurde nicht übersetzt bei Nutzung des `translate:`-Präfixes (@gharlan)
* Linklist-Vars: Deprecated-Meldung entfernt (@gharlan)


Version 2.14.2 – 13.12.2022
---------------------------

### Bugfixes

* version-Plugin: Über EP `ART_CONTENT_UPDATED` kann nun bei `work_to_live`-Action gesteuert werden, in welcher Version man nach der Aktion im Backend landet (@gharlan)


Version 2.14.1 – 02.08.2022
---------------------------

### Bugfixes

* version-Plugin: Fehler beim Speichern der jeweiligen aktuellen Artikelversionsansicht (Live/Arbeitsversion) in der Session (@gharlan) 


Version 2.14.0 – 25.07.2022
---------------------------

### Neu

* `rex_template`: Neue Methode `getCtypes` die ein Array von neuen `rex_ctype`-Objekten liefert (@staabm)
* Beim Löschen von Kategorien/Artikeln wird im confirm-Dialog darauf hingewiesen, dass in allen Sprachen gelöscht wird (@gharlan)
* version-Plugin: 
    - Voransicht Arbeitsversion: Bei fehlender Backend-Session kommt die Oops-Page mit Erläuterung (statt hartem Fehler mit Logmeldung) (@gharlan)
    - Nach Kopieren zwischen Live/Arbeitsversion wird in die Zielversion gesprungen (@gharlan)

### Bugfixes

* history-Plugin: Session-Übernahme bei Multidomain korrigiert (@gharlan)
* Templates-Cache war fälschlich in `cache/addons/templates` statt im `structure`-Cacheordner (@gharlan)


Version 2.13.3 – 03.05.2022
---------------------------

### Bugfixes

* Artikel in Kategorie umwandeln: Der neue Startartikel hatte eine falsche Priorität (@gharlan)
* `rex_article_slice`: Methode `getMediaListArray` lieferte fälschlich Linklist-Werte (@rhetzer)
* `REX_VALUE[]`: Mit PHP 8.1 kam es teils zu Deprecation-Notices (@nfission)


Version 2.13.2 – 10.01.2022
---------------------------

### Bugfixes

* Kategorie in Artikel umwandeln: Felder `catname` und `catpriority` wurden nicht korrekt aktualisiert (@gharlan)
* Inhalt von/zu Sprache kopieren: Es werden auch die Inhalte der Arbeitsversion kopiert (@gharlan)
* Die Version-Toolbar wird nicht in der Artikel-Funktionen-Page angezeigt, da dort nicht relevant (@gharlan)
* Modul-Aktionen: Speicherung korrigiert bei Auswahl der "Alle"-Checkboxen (@gharlan)
* Fehlermeldung im Fronted optimiert, wenn noch kein Artikel existiert (@gharlan)
* Bei Installation wird die Default-Config für Start-/Fehler-Artikel in `rex_config` gespeichert (@gharlan)


Version 2.13.1 – 29.11.2021
---------------------------

### Bugfixes

* Templates: bei Modulzuweisung zu CTypes wurde teils fälschlich nach Speichern wieder "Alle" aktiviert (@gharlan)


Version 2.13.0 – 17.11.2021
---------------------------

### Neu

* Im Modul wird der aktuelle Slice gecacht als `rex_article_slice`-Objekt zur Verfügung gestellt über `$this->getCurrentSlice()`, so kann über PHP ohne REX_VARs auf die Values zugegriffen werden (@gharlan)
* `rex_article_slice`:
    - Neue Methoden `getValueArray`, `getLinkListArray`, `getMediaListArray`, die den Feldinhalt direkt als Array liefern (@gharlan)
* Strukur-Page: Tabellenzeilen erhalten `data-status="x"`-Attribut, so können die Zeilen je nach Status gestylet werden (@danspringer, @schuer)
* Kategorie-Selectfelder mit Suchfeld (@skerbis)
* Bezeichner optimiert (@alxndr-w)

### Bugfixes

* Sliceänderungen wirkten sich teils erst verzögert aus (wegen Opcache) (@gharlan)
* `rex_article_slice`: bei `getLinkUrl` bekam man die aktuelle URL statt `null`, wenn das Feld nicht gesetzt ist (`getMediaUrl` entsprechend) (@gharlan)
* `REX_LINK[id=X output=url]` hat teilweise die URL in falscher Sprache geliefert (@gharlan)
* Bei der Modulzuweisung zu den CTypes wurde bei Abwahl aller Module wieder die Checkbox "Alle" gesetzt (@gharlan)


Version 2.12.1 – 21.06.2021
---------------------------

### Bugfixes

* `rex_var_link(list)::getWidget`: ID-Parameter mit zusätzlichem Namespace-Anteil (nicht nur integer) wurden nur teilweise unterstützt (@gharlan)


Version 2.12.0 – 03.03.2021
---------------------------

### Neu

* In der Strukturübersicht werden leere Kategorien von solchen mit Kindelementen durch Iconvarianten unterschieden (@schuer)
* Die Paginierung der Kategorien/Artikel kann über die AddOn-Property `rows_per_page` angepasst werden; der Default-Wert wurde auf 50 erhöht (@tyrant88)
* Überschrift auf content-Page enthält Artikelnamen (@schuer)
* Neuer EP `SLICE_MENU` (mit eigener Klasse `rex_extension_point_slice_menu`), als Weiterentwicklung von `STRUCTURE_CONTENT_SLICE_MENU` mit mehr Möglickeiten, die vorhandenen Buttons zu ändern/entfernen (@staabm)
* Aus Templates/Modulen heraus kann neue Exception `rex_article_not_found_exception` geworfen werden, wodurch auf den Fehlerartikel gewechselt wird (@gharlan)
* Wenn eine Sprache mit ID=0 (R4-Import) existiert, wird im Backend eine gesonderte Meldung ausgegeben (@staabm)

### Bugfixes

* Bei Exceptions in Modulen war anschließend ein zusätzlicher Output-Buffer aktiv (@staabm)


Version 2.11.2 – 25.01.2021
---------------------------

### Security

* Fehlendes Escaping ergänzt (@gharlan)

### Bugfixes

* `rex_module::forKey()` korrigiert (@DanielWeitenauer)


Version 2.11.1 – 11.11.2020
---------------------------

### Bugfixes

* `rex_article_slice`: `getPreviousSlice`/`getNextSlice` lieferten mit `$ignoreOfflines` teilweise fälschlich `null` (@gharlan)


Version 2.11.0 – 01.07.2020
---------------------------

### Neu

* Neues Recht `publishSlice[]` für den Slice-Status (@tbaddade)
* `rex_category`/`rex_article`: Neue Methoden `getClosest` und `getClosestValue` für Abfragen vom Element ausgehend den ParentTree aufwärts, sowie `isOnlineIncludingParents` (@gharlan)
* `rex_article_slice`: neue `isOnline`-Methode, und `$ignoreOfflines`-Parameter bei einigen Methoden (@DanielWeitenauer)
* `rex_template`/`rex_module`: Abfrage der Keys wird gecacht (@gharlan)
* Fragment `module_select.php`: Module-Key wird mit übergeben (@skerbis)
* Darstellung in Artikelbearbeitung bei fehlenden Slice-Rechten verbessert (@tbaddade)
* Modulbearbeitung: Hinweis auf Nutzungsmöglichkeit der Aktionen (@staabm)

### Bugfixes

* Beim Versuch das Default-Template zu löschen, kam es teilweise zu einer Exception statt zu der angedachten Fehlermeldung (@gharlan)
* history-Plugin: Beim Aufruf der alten Artikelversionen kam es zu einer Warning bzgl. Module-Keys (@gharlan)
* Mobilansicht der Struktur: Bei leerer Artikelliste erschien der Hinzufügen-Button ohne Untergrund (@tbaddade)


Version 2.10.1 – 08.05.2020
---------------------------

### Bugfixes

* Bei Fehlern während der Artikelcache-Generierung wurde im Frontend eine Fehlermeldung ausgegeben, die den vollen Cachepfad enthielt (@gharlan)
* Beim Backendaufruf von nicht existenten Artikeln erschien keine Fehlermeldung (@tbaddade)
* Es wurden teilweise falsche Übersetzungsschlüssel verwendet (@bloep)


Version 2.10.0 – 10.03.2020
---------------------------

### Neu

* Slice-Status (online/offline) kann gesetzt werden (Übernahme von bloecks/status) (@gharlan, @schuer)
* `REX_TEMPLATE_KEY`-Platzhalter für Templates/Module hinzugefügt (@staabm)
* Modulen können (analog zu den Templates) eindeutige Keys vergeben werden (inkl. `REX_MODULE_KEY`-Platzhalter) (@alexplusde, @staabm)
* Der Status-Schalter nutzt ein Dropdown, wenn weitere Status hinzugefügt wurden (Bsp. accessdenied) (@alexplusde)
* Template/Module löschen: Auflistung der Artikel verschönert, in denen es noch verwendet wird, und es werden die Artikel in allen betroffenen Sprachversionen aufgelistet (@gharlan)
* Spalten in `rex_article_slice`-Tabelle umsortiert (`article_id` und `module_id` weiter nach vorne) (@gharlan)

### Bugfixes:

* Einfache Rex-Vars wie `REX_MODULE_ID`/`REX_SLICE_ID` wurden erst nach den richtigen Rex-Vars wie `REX_VALUE[X]` ersetzt, dadurch konnten sie nicht nicht als Argumente innerhalb der Vars genutzt werden und wurden auch im eigentlichen Inhalt der Values ersetzt (@gharlan)
* Nach dem Speichern von Blöcken erschien die Erfolgsmeldung nicht mehr im Block (@gharlan)
* `rex_category`/`rex_article`: Methoden wie `getId`, `getParentId` etc. lieferten die Zahl als String statt als Integer (@gharlan)


Version 2.9.0 – 02.02.2020
--------------------------

### Neu

* Neue Rechte `addCategory[]`, `editCategory[]`, `deleteCategory[]`, `addArticle[]`, `editArticle[]`, `deleteArticle[]` (@gharlan)
* Templates können eindeutige Keys vergeben werden und dann darüber (statt über die ID) eingebunden werden (`REX_TEMPLATE[key=my_key]`) (@tbaddade)
* Toggle-Status der Panels in der Sidebar (Metainfos etc.) wird per Localstorage gespeichert (@IngoWinter)
* `rex_navigation`:
    - Die Callbacks erhalten als weiteren Referenzparameter den Linktext und können ihn darüber ändern (@alexplusde)
    - Markup kann über Klassenerweiterung und Überschreiben der neu dafür vorgesehenen Methoden angepasst werden (@DanielWeitenauer, @gharlan)
* Neue Klasse `rex_template_select` für die Template-Auswahl (@DanielWeitenauer)
* Neue Methode `rex_content_service::addSlice` (@omphteliba, @gharlan)
* Neuer EP `ART_CONTENT_UPDATED` bei jeglichen Content-Änderungen (@gharlan)
* In der Struktur wird nicht mehr die Kategorie-Zeile ".." für die Oberkategorie ausgegeben (@schuer)
* Die Artikel-Tabellenzeilen haben ein neues Attribut `data-article-id="X"` für Artikelspezifische Anpassungen (@skerbis)
* Module-Auswahl über separates Fragment `module_select.php` für einfachere Anpassung (@tbaddade)
* Code besser strukturiert mittels neuer Klasse `rex_structure_context` (@DanielWeitenauer)
* Zusammenspiel der Plugins history und version optimiert (@dergel)
* Plugin history: Cronjob-Typ für das Löschen alter History-Datensätze (@dergel)
* Plugin version: Toolbar besser platziert nur über dem Bereich, auf den sie sich bezieht (@gharlan)

### Bugfixes

* Mountpoints wurden in Linkmap und `rex_category_select` unsortiert ausgegeben (@gharlan)
* `rex_category`: Wenn bei `getChildren`/`getArticles` ein leere Liste herauskam, wurde unnötig der Cache erneuert (@gharlan)
* `rex_article_content`: Bei `hasValue` konnte im Gegensatz zu `getValue` nicht der `art_`-Präfix für die Metainfos weggelassen werden (@bloep)
* Beim Ändern von Kategorien/Artikeln wurde das Änderungsdatum immer in allen Sprachen neu gesetzt (@gharlan)


Version 2.8.1 – 01.11.2019
--------------------------

### Security

* XSS Sicherheitslücken behoben (Michel Pass und Mathias Niedung von Althammer & Kill, @gharlan)


Version 2.8.0 – 20.08.2019
--------------------------

### Neu

* Assets nutzen immutable cache (@staabm)
* `rex_navigation`: Methode `addCallback` gibt `$this` zurück (@alexplusde)
* EP `CAT_MOVED`: "clang"-Parameter wird übergeben analog zu anderen EPs ("clang_id" ist deprecated) (@gharlan)
* Bei Template-/Modul-Namen wird Hinweis ausgeben, dass `translate:i18n_key`-Syntax verwendet werden kann (@tbaddade)
* Slice-Ansicht: "Bearbeiten"/"Löschen" ausgeschrieben, statt Icons (@alexplusde)
* Linkmap: IDs hinter Namen optisch zurückgenommen (@tbaddade)

### Bugfixes:

* EP `SLICE_ADDED`: `slice_id`-Parameter war immer `0` (@staabm)
* PlugIn `version`: In Tablet-Ansicht wurden die Buttons nicht angezeigt (@tbaddade)
* `$this->getValue('createdate')` lieferte im Backend einen Datetime-String, statt des Unix-Timestamps wie im Frontend (@gharlan)
* `rex_template::getTemplatesForCategory`: Bei `$ignore_inacttive=false` wurden nur inaktive Templates geliefert, statt alle (@gharlan)
* Nach Prio-Setzung wurde nicht der Cache aller betroffenen Kategorien/Artikel zurückgesetzt (@gharlan)


Version 2.7.0 – 12.03.2019
--------------------------

### Neu

* Neuer EP: `CAT_MOVED` (@bloep)
* Linkmap öffnet default in aktueller Kategorie (@schuer)
* version-PlugIn: Arbeitsversion kann geleert werden (@dpf-dd)
* Slice-Value-Felder als `MEDIUMTEXT` statt `TEXT` damit mehr Inhalte gespeichert werden können (@bloep)
* Leere CTypes werden in der Backend-Navi grau dargestellt (@schuer)
* Sliceausgabe mit Scrollbar bei zu breiten Inhalten (@schuer)
* In Moduleverwaltung wird angezeigt, ob die Module jeweils in Verwendung sind (@tbaddade)
* Bei (Re)Installation/Update wird `rex_sql_table` verwendet (@bloep)
* "Kein Startartikel selektiert"-Fehler nutzt Frontend-Ooops-Seite (@tbaddade)

### Bugfixes:

* `rex_category::get()` lieferte auch für Nicht-Startartikel ein Kategorie-Objekt (@gharlan)
* `rex_category::getCurrent()` lieferte Fehler, wenn es keinen aktuellen Artikel gibt (@gharlan)
* Der Funktionen-Tab wurde nicht ausgeblendet, wenn ein Benutzer nur die Berechtigung für `copyContent[]` und nur für eine Sprache hat (@TobiasKrais)
* Template-Verwaltung: An einer Stelle wurde der Table-Prefix `rex_` fix genommen, statt `rex::getTablePrefix()` (@staabm)
* In Modulen enthielt im Backend die Variable `$content` den Modul-PHP-Code, was zu verwirrenden Ausgaben führen konnte (@gharlan)


Version 2.6.1 – 21.06.2018
--------------------------

### Bugfixes

* Bei der Status-Änderung von Kategorien wurde fälschlich das `publishArticle[]`-Recht statt `publishCategory[]` geprüft (@gharlan)


Version 2.6.0 – 05.06.2018
--------------------------

### Neu

* Mountpoints werden nach Prio sortiert, wenn alle in gleicher Oberkategorie (@gharlan)
* Neue EPs `ART_MOVED` und `ART_COPIED` (@alexwenz)
* Template löschen: Hinweis welche Artikel es benutzen (@bloep)
* Linklist: Artikel-IDs werden auch ausgegeben (@tbaddade)
* Umbenennung "Homepage" in "Hauptebene" in Breadcrumb + passenderes Icon (@tbaddade)
* Umbenennung "Spalten" in "Bereiche" (@alexplusde)
* `rex_category`, `rex_article`, `rex_article_base`: Neue Methode `getClangId`, `getClang` als deprecated markiert (@staabm)
* Rechtschreibprüfung in Codeeingabefeldern deaktiviert (@staabm)

### Bugfixes

* Kompatibilität zu PHP 7.2 (@IngoWinter)
* Bei der Ausgabe des Modulnamens fehlte teils das Escaping (@staabm)


Version 2.5.0 – 21.12.2017
--------------------------

### Neu

* CSRF-Schutz für Api-Functions, Templates und Module (@gharlan)
* Neue EPs `MODULE_ADDED/UPDATED/DELETED` und `TEMPLATE_ADDED/UPDATED/DELETED` (@bloep)
* Bessere Code-Strukturierung (Api-Functions) (@DanielWeitenauer)


Version 2.4.0 – 04.10.2017
--------------------------

### Security

* XSS-Möglichkeit in Linkmap beseitigt (@staabm)

### Neu

* history-Plugin: Slider mit Visualisierung, zu welchen Zeitpunkten Snapshots gemacht wurden (@schuer)
* Funktionen-Subpage wird nur angezeigt, wenn die Rolle für mindestens eine der Funktionen die Berechtigung hat (@DanielWeitenauer)
* Modul-/Template-Liste: 100 pro Seite (@gharlan)
* EP `SLICE_SHOW` enthält nun Parameter `sql` für direkten Zugriff auf alle Values (@dergel)
* `rex_redirect`: Verständliche Exception wenn fälschlich eine URL statt einer Artikel-ID übergeben wird (@joachimdoerr)

### Bugfixes

* history-Plugin:
    - Wiederherstellung funktionierte nicht (@skerbis)
    - Besserer Spaltenabgleich zwischen History- und Haupttabelle (@dergel)
* `rex_template` hat teilweise Notices geworfen (@DavidBruchmann)
* Teilweise kam die unübersetzte Meldung "translate:article_doesnt_exist" (@TobiasKrais)
* Modul-/Template-Liste: Beim Speichern landete man immer auf Seite 1 (@gharlan)
* Benutzer mit `article2category[]`-Recht konnten fälschlich keine Kategorien in Artikel umwandeln (@gharlan)


Version 2.3.1 – 19.03.2017
--------------------------

### Bugfixes

* Bei Nutzung der Linkmap über Editoren (Redactor etc.) wurde der Link teilweise mehrfach eingefügt
* In den Service-Klassen wurden teilweise Int-Parameter ungeprüft in Queries genutzt
* Es kam zu einem Fehler beim Updaten, wenn das History-Plugin installiert, aber nicht aktiviert ist
* In der Modulliste wurde der Name nicht übersetzt


Version 2.3.0 – 14.02.2017
--------------------------

### Security

* Bei Backend-Benutzern war über die Linkmap Cross-Site-Scripting (XSS) möglich

### Neu

* Beim Block-Übernehmen wird die Meldung auch im Block angezeigt
* Nach Durchführung von Artikelfunktionen bleibt man auf der Funktionsseite
* History: Benutzer wird mit protokolliert
* Neue Methode rex_article_slice::getPriority() (@phoebusryan)
* MEDIUMTEXT-Spalten für Template- und Module-Code

### Bugfixes

* Wenn Frontend mit nicht vorhandener ID als clang-Parameter aufgerufen wurde, kam es zu einem harten Fehler, statt Umleitung auf NotFound-Artikel
* Kategorie/Artikel verschieben: Breadcrumb wurde nicht aktualisiert
* Inhalte kopieren:
    - Wenn im Zielartikel bereits Slices vorhanden waren, wurden die neuen nicht korrekt ans Ende gesetzt
    - Wenn Ursprung keine Slices enthielt, kam es zu einer falschen Fehlermeldung
* Bei Reload nach Block-Übernehmen blieb der Block nicht offen
* Beim Anlegen neuer Slices funktionierten REX_MODULE_ID und REX_CTYPE_ID nicht
* History:
    - Teilweise wurden Versionen in Dropdown doppelt angezeigt
    - Bei Multidomain-Lösungen konnten die Artikel der anderen Domains nicht angezeigt werden
* rex_navigation::getBreadcrumb(): Die Start-Kategorie-ID wurden nicht berücksichtigt
* rex_navigation::showBreadcrumb(): Die Parameter waren falsch benannt (versetzt)


Version 2.2.0 – 15.07.2016
--------------------------

### Neu

* Neues Plugin "history": Änderungen an Artikelinhalten werden protokolliert, mit Vergleichs- und Wiederherstellungsmöglichkeit
* Das Default-Template wird vorinstalliert
* Neue EPs: SLICE_CREATE, SLICE_EDIT, SLICE_DELETE, SLICE_MOVE, ART_SLICES_QUERY, ART_SLICES_COPY
* Umbenennung EPs (alte funktionieren noch): STRUCTURE_CONTENT_CREATED/UPDATED/DELETED -> SLICE_CREATED/UPDATED/DELETED
* Service-Klassen können auch aus Frontend heraus genutzt werden
* "Block hinzufügen" wird auch beim Bearbeiten/Erstellen eines Slices angezeigt

### Bugfixes

* Artikel kopieren: Inhalten wurden nicht kopiert, wenn Ctype 1 leer ist
* Content-Page: Es wurde nicht immer korrekt zum Slice gesprungen
* Benutzer mit Recht "Artikel veröffentlichen" konnten trotzdem den Status der Artikel nicht ändern
* Bei Artikeln ohne Template wurde "KEIN TEMPLATE" nicht mehr angezeigt, sobald es Templates gab
* Sobald eine 2. Seite vorhanden war, bekamen alle neuen Artikel/Kategorien die Prio 31
* $art/$cat->getValue('parent_id') liefert immer das selbe wie getParentId()
* Aktionen: "Aktion übernehmen" sprang zurück in Übersicht


Version 2.1.0 – 24.03.2016
--------------------------

### Neu

* Slice-Values werden automatisch zurückgesetzt, wenn nicht gesendet (dadurch Checkboxes ohne Tricks möglich)
* JS-Event rex:selectLink bei Auswahl eines Artikels in Linkmap
* Templates-Page: Aufteilung in Tabs
* Content-Subpages können sich auch links dranheften und href überschreiben

### Bugfixes

* CAT_UPDATED: Es wurden teilweise die alten Daten geliefert
* REX_LINK[]: Wenn Feld leer, wurde fälschlicherweise die aktuelle URL geliefert
* Linkmap: Bei Benutzern mit eingeschränkten Kategorierechten kam es zu Fehlern
* POSTSAVE-Actions hatten keinen Zugriff auf Values


Version 2.0.1 – 09.02.2016
--------------------------

### Bugfixes

* Die CommonVars (REX_ARTICLE_ID etc.) konnten nicht innerhalb der ObjectVars (REX_ARTICLE[] etc.) verwendet werden (@schuer)
