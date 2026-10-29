# Review: `feat/backend-ui-rebuild` (gegen `6.x`)

> ⚠️ **Vor dem Merge löschen** – diese Datei ist nur eine Arbeitsnotiz für den Branch und gehört nicht nach `6.x`.

✅ = vom Reviewer selbst nachgeprüft, ❔ = Befund aus der Analyse, vor dem Fix bitte verifizieren.

---

## Re-Review (Stand f58df83a2, 24 neue Commits seit c26f5c3ee)

**Checks:**
- **PHPUnit:** Ohne `DATABASE_URL` liefen nur die Tests ohne DB, und die sind grün. Die beiden Fehler in `FileTest::testMimeType` (css/js) kommen nur lokal vor, weil `project/public/assets/core/` dort fehlt. Mit dem Branch haben sie nichts zu tun.
- **Lint und Build:** `npm run lint` ist grün. `npm run build` erzeugt die committeten Assets byte-identisch.
- **Statische Analyse:** PHPStan und Psalm melden keine Fehler.

Fast alle Punkte aus dem ersten Review sind sauber umgesetzt. Abgehakte Punkte stehen unten in der ursprünglichen Liste.

### Offen / neu

- [ ] ✅ **Neue Einträge in der Psalm-Baseline** – `.tools/psalm/baseline.xml`, Datei `fragments/core/form/form.php`: 6 neue Einträge (`MixedArgument` `$field`/`$label`, zusätzliche `MixedArrayAccess`/`MixedAssignment`/`MixedOperand`). Insgesamt schrumpft die Baseline stark, aber laut AGENTS.md darf neuer Code nichts hinzufügen. Entweder die Fragment-Vars typisieren (`@var`/`Type::…`) oder den Punkt im PR ausdrücklich nennen.
- [ ] ✅ **Ein doppeltes Escaping ist übrig** – `src/View/View.php:185`: `prefix: I18n::msg('language')`, aber `Dropdown.view.php:10` escaped `prefix` über `Html::from()` noch einmal. Auf `I18n::rawMsg()` umstellen.
- [ ] ✅ **`'unsafe-inline'` verdrängt jetzt Nonces** (`ContentSecurityPolicy::add()`, ca8da8634). Ruft ein Addon `add('script-src', "'unsafe-inline'")` auf, verliert das ganze Backend für diesen Request den Nonce-Schutz für Scripts. Vorher hat der Browser das `'unsafe-inline'` neben dem Nonce einfach ignoriert. Für den Fall aus dem debug-Addon (`style-src-elem`) ist das gewollt. Vorschlag: auf `style-src*` beschränken oder für `script-src*` bewusst entscheiden und im Docblock warnen.
- [ ] ❔ **`data-confirm` mit Modifier-Tasten** – `assets/js/src/dialog.js:121-126` dispatcht ein synthetisches `MouseEvent` mit `ctrlKey`/`metaKey`. Ob Browser für ein untrusted Event wirklich einen neuen Tab öffnen, ist nicht getestet; Chrome behandelt das ggf. als Popup. Bitte in Chrome, Firefox und Safari ausprobieren.
- [ ] ❔ **Overlay schluckt weiterhin Submit-Events** – `assets/js/src/overlay.js:185` (`stopPropagation`). Der Core-Fall ist durch die Capture-Phase in `forms.js` gelöst. Submit-Listener von Addons in der Bubble-Phase auf `document` laufen im Overlay aber weiterhin nicht. Mindestens in den PR-/Upgrade-Notes nennen.
- [ ] ❔ **Doppelklick auf Submit im Overlay** – `load()` bricht jetzt die ältere Anfrage ab, aber beim Server kommen trotzdem beide POSTs an. Gegenmaßnahme: Submit-Buttons während des Ladens sperren.
- [ ] ❔ **`Asset::$cssFilesRead` bleibt gesetzt** – `src/View/Asset.php`: ein statisches Flag ohne Reset (anders als `Accesskey::reset()`). In Tests oder langlaufenden Prozessen wirft ein späteres `requireBootstrapCompat()` dann eine Exception.
- [ ] **Hinweise für die PR-Beschreibung** (das Repo hat keine Upgrade-Datei), alles Breaking Changes für Addons:
  - `DataList::getRows()` heißt jetzt `getPageRows()`.
  - Entfallen sind `REX_LIST_GET`, `factory()`, `addFormAttribute`, `setColumn*` und `removeColumn`.
  - Der Konstruktor von `Toolbar` nimmt statt `cssClass` jetzt `attributes`, und `inverse` ist auf Position 3 gerückt.
  - `requireBootstrapCompat()` funktioniert nur noch in `boot()`.
  - Das Overlay übernimmt nur `#rex-js-page-main`.
  - Die globalen Funktionen (`openREXMedia`, `selectMedia`, …) sind entfallen.
- [ ] **Entscheidung offen:** Die Komponenten sind jetzt bewusst öffentliche API. `Tabs` nimmt aber weiterhin untypisierte Arrays mit rohem HTML im `title` entgegen. Entweder `Tabs` `@internal` markieren oder typisieren.

---

## Grid-System (`.rex-grid` / `data-span`)

**Ziel:** Ein stabiles Grid, das Addon-Entwickler einfach nutzen können, ohne das HTML zu kennen, das dabei ausgegeben wird. Das CSS selbst ist solide: Mobile first, die Reihenfolge der Tokens ist egal, die Begründung über die CSP ist dokumentiert. Es fehlen aber eine PHP-API, Teile des Vokabulars und Teile der Compat-Schicht.

### Rückschritte gegenüber 6.x (✅)

- [ ] **Compat-Grid unvollständig** – `assets/css/src/compat/grid.css` enthält nur `col-{xs,sm,md,lg}-{1..12}`. 6.x hatte zusätzlich `col-*-offset-*`, `col-*-push-*`/`col-*-pull-*` und die Sichtbarkeitsklassen `hidden-*`/`visible-*` (geprüft in `6.x:assets/css/styles.css`). Addons mit `requireBootstrapCompat()` brechen dort ohne Fehlermeldung. Die fehlenden Klassen gehören in `compat/` zurück.
- [ ] **`xl` fehlt** – 6.x kannte `$screen-xl-min: 1400px`, und das neue CSS nutzt 1400px an drei Stellen. Im Grid gibt es aber kein `xl:`.

### PHP-API: Komponente mit Enums statt Strings

Heute wird das Grid auf drei Arten geschrieben:
- über das Fragment mit `spans` als String-Array (`media.detail.php`, `settings.php`, `linkmap.php`, `credits.php`)
- als roh geechotes HTML (`log.mailer_archive.php`, `profile/index.php`, `fragments/core/page/main_content.php`)
- als String-Verschachtelung in einer Schleife (`report.html.php:19-23`)

Tippfehler wie `'md-6'`, `'md:13'` oder `'xl:4'` bleiben ohne Fehlermeldung. Wer das CSS-System austauscht, muss jede dieser Stellen anfassen.

- [ ] **Komponenten `Grid` und `GridColumn`** in `src/View/Component/`, nach dem Muster der übrigen Komponenten (`*.view.php`). Die View ist die **einzige** Stelle, die das Markup (`.rex-grid`, `data-*`) kennt. Wer das CSS oder Framework austauscht, ändert nur `Grid.view.php` und `grid.css`.
- [ ] **API nach drei Regeln:**
  1. **Breakpoints werden immer als Enum notiert.** Es gibt keine benannten Argumente `sm:`/`lg:` und keine Strings. `Breakpoint` hat die Fälle `Xs` (ab 0, die Basis), `Sm`, `Md`, `Lg`, `Xl`. Jede Angabe gilt „ab Breakpoint X“ (mobile first), auch die kleinste.
  2. **Was responsiv ist und was fest, ergibt sich aus der Struktur.** Responsive Werte stehen ausschließlich in `GridColumnRule`-Objekten, jedes an genau einen Breakpoint gebunden. Feste Werte sind Parameter von `Grid`, `GridColumn` oder dem Layout-Objekt.
     - responsiv (`GridColumnRule`): `width`, `start`, `order`, `visibility`
     - fest (`Grid`): `layout`, `gap`, `subgrid`
     - fest (Layout-Objekt): `GridLayoutSplit::$from`, `GridLayoutTiles::$size`
     - fest (`GridColumn`): `alignX`, `alignY`
  3. **Kein `null` als Wert.** Ein weggelassener Parameter in `GridColumnRule` heißt immer „wie beim kleineren Breakpoint“. Zurückgesetzt wird über ausdrückliche Enum-Fälle (`GridOrder::Source`, `GridVisibility::Visible`, `GridColumnStart::Auto`) bzw. eine ausdrückliche Breite (`width: 12` bei `Columns`, `width: GridColumnWidth::Fill` bei `Split`).
- [ ] **Namen:** Der Namespace `Redaxo\Core\View\Component` ist flach, und die bestehenden Klassen tragen den Namen ihrer Komponente als Präfix (`TableColumn`, `ButtonVariant`, `SectionVariant`, `AlertType`). Deshalb bekommt alles, was nur zum Grid gehört, das Präfix `Grid`. Unbestimmte Namen wie `Split`, `Columns`, `Order` oder `ColumnRule` wären im flachen Namespace mehrdeutig; `ColumnRule` würde z. B. direkt neben dem `ColumnType` der Tabelle stehen. Die Layouts heißen `GridLayout…` und nicht `Grid…Layout`, damit die Autovervollständigung nach `GridLayout` alle zeigt. Ohne Präfix bleibt nur `Breakpoint`, weil das ein Konzept über Komponenten hinweg ist (z. B. später Toolbar oder Tabs).
  - Nebenbefund: Das bestehende `ColumnType` gehört zur Tabelle und verstößt gegen dieses Muster. Umbenennen in `TableColumnType`.
- [ ] **Typen:**
  - Klassen: `Grid`, `GridColumn`, `GridColumnRule`
  - Layouts (Interface `GridLayout`): `GridLayoutColumns` (Standard, 12 Spalten), `GridLayoutSplit(from: Breakpoint)`, `GridLayoutTiles(size: GridTileSize)`. Das Layout ist ein Objekt und kein Enum, weil es seine eigenen Einstellungen trägt. Ein `tileSize` bei einem Spalten-Grid lässt sich so gar nicht erst schreiben.
  - Enums:
    - `Breakpoint`: `Xs`, `Sm`, `Md`, `Lg`, `Xl`
    - `GridColumnWidth`: `Auto` (so breit wie der Inhalt), `Fill` (teilt sich den restlichen Platz mit den anderen `Fill`-Spalten), nur bei `GridLayoutSplit`
    - `GridColumnStart`: `Auto` (direkt nach der vorherigen Spalte, der Normalfall)
    - `GridOrder`: `Source` (DOM-Reihenfolge), `First`, `Last`
    - `GridVisibility`: `Visible`, `Hidden`
    - `GridGap`: `None`, `Small`, `Default`, `Large` (gilt für Spalten- und Zeilenabstand)
    - `GridTileSize`: `Small` (12rem), `Medium` (16rem, der heutige Wert), `Large` (24rem) als Mindestbreite einer Kachel
    - `GridAlign`: `Start`, `Center`, `End`, `Stretch`. Ein Enum für beide Achsen reicht, weil `justify-self` und `align-self` im Grid dieselben Werte mit derselben Bedeutung haben. `alignX` wird zu `justify-self`, `alignY` zu `align-self`, beide mit logischen Werten (RTL-fähig), **nicht** zu `text-align`.

  **Breite und Start sind Spaltenzahlen** von 12 (`width: 8`, `start: 4`), denn Zahlen lesen sich am einfachsten. Psalm prüft `int<1,12>`, zur Laufzeit gibt es eine `InvalidArgumentException`. Die 12 steht als `GridLayoutColumns::COLUMNS` an einer Stelle; View, Validierung und Tests verwenden die Konstante, ein PHPUnit-Test prüft, dass `grid.css` Regeln bis genau `COLUMNS` enthält. Ein anderes CSS-System bildet die Zahl in seiner View selbst ab, z. B. als `8 / 12` = 66,67 %.

  **Entschieden: `start` statt `offset`.** Ein Offset ist aus Bootstrap als *Abstand zur vorherigen Spalte* bekannt. CSS Grid kann nur eine *absolute* Startspalte setzen. Unter dem Namen `offset` würde `[width: 4] [offset: 2, width: 4]` in die nächste Zeile springen statt bei Spalte 7 zu beginnen, ohne Fehlermeldung. `start: 7` sagt dagegen genau, was passiert, und braucht keine Umrechnung in der View.

  **`Auto` und `Fill` nur in `GridLayoutSplit`.** In einem Raster aus 12 festen Spuren (`Columns`) belegt eine Spalte immer ganze Spuren; „so breit wie der Inhalt“ gibt es dort nicht, und „Rest der Zeile“ ginge nur mit bekannter Startspalte. Ein Wechsel des ganzen Rasters auf Flexbox scheidet aus, weil Subgrid CSS Grid braucht und `start` dann wieder relativ wäre. Deshalb bekommen `Auto` und `Fill` ein eigenes Layout, `GridLayoutSplit` (intern Flexbox, das bleibt in der View). Der Name sagt: Die Breite wird auf die Spalten aufgeteilt. „Row“ wurde verworfen, weil es nach einer von mehreren Reihen klingt.
- [ ] **Signaturen.** Spalten und Regeln werden als Liste übergeben, nicht variadisch. Variadische Parameter müssen in PHP am Ende stehen, und Positionsargumente lassen sich nicht mit benannten Optionen davor mischen.

  ```php
  final class Grid implements Renderable
  {
      /** @param list<GridColumn|string|Renderable> $columns plain content becomes a column without rules */
      public function __construct(
          public array $columns,
          public GridLayout $layout = new GridLayoutColumns(),
          public GridGap $gap = GridGap::Default,
          public bool $subgrid = false,
      ) {}
  }

  interface GridLayout {}

  final class GridLayoutColumns implements GridLayout
  {
      public const int COLUMNS = 12;
  }

  final class GridLayoutSplit implements GridLayout
  {
      /** @param Breakpoint $from below it the columns stack, from it on they share exactly one row */
      public function __construct(
          public Breakpoint $from = Breakpoint::Xs,
      ) {}
  }

  final class GridLayoutTiles implements GridLayout
  {
      public function __construct(
          public GridTileSize $size = GridTileSize::Medium,
      ) {}
  }

  final class GridColumn implements Renderable
  {
      /** @param list<GridColumnRule> $rules at most one per breakpoint, in any order */
      public function __construct(
          public string|Renderable $content,
          public array $rules = [],
          public GridAlign $alignX = GridAlign::Stretch,
          public GridAlign $alignY = GridAlign::Stretch,
      ) {}
  }

  final class GridColumnRule
  {
      public function __construct(
          public Breakpoint $breakpoint,
          /** @var int<1,12>|GridColumnWidth|null */
          public int|GridColumnWidth|null $width = null,
          /** @var int<1,12>|GridColumnStart|null */
          public int|GridColumnStart|null $start = null,
          public ?GridOrder $order = null,
          public ?GridVisibility $visibility = null,
      ) {}
  }
  ```

  Die Parameter von `GridColumnRule` sind technisch nullable, damit sie weggelassen werden können. In der Doku steht aber nur „weglassen = übernehmen“, nie `null` als Wert.

- [ ] **Was in welchem Layout erlaubt ist** (in PHPUnit 1:1 als Data Provider abbilden):

  | Eigenschaft | `Columns` | `Split` | `Tiles` |
  |---|---|---|---|
  | `width` als Zahl | ja | ja, ab `from` | Exception |
  | `width` als `Auto`/`Fill` | Exception | ja, ab `from` | Exception |
  | `start` | ja | Exception | Exception |
  | `order` | ja | ja | ja |
  | `visibility` | ja | ja | ja |
  | `alignX` / `alignY` | ja | ja | ja |

  Weitere Fälle mit `InvalidArgumentException`:
  - eine `GridColumnRule` ganz ohne Werte
  - doppelte Breakpoints in einer Spalte
  - Start + Breite ragen über 12 hinaus (`start + width - 1 > 12`). Geprüft wird pro Breakpoint mit den **geerbten** Werten: `width: 8` ab `Md` und `start: 6` ab `Lg` ist ab `Lg` ungültig. Ohne Prüfung legt CSS Grid stillschweigend zusätzliche Spuren an und die Seite läuft rechts über.
  - bei `Split`: eine `width`-Regel unterhalb von `from` (dort stehen die Spalten untereinander)
  - bei `Split`: die Zahlen-Breiten der sichtbaren Spalten ergeben an einem Breakpoint mehr als 12, oder genau 12, obwohl noch eine `Auto`- oder `Fill`-Spalte daneben steht (für sie bliebe kein Platz)

- [ ] **Bedeutung der Werte:**
  - `width` ohne Regel ist bei `Columns` 12 (volle Breite), bei `Split` `Fill` (alle gleich breit). Sie gilt, bis eine Regel eines größeren Breakpoints sie ersetzt.
  - Bei `Split` heißt `width: 4` „4/12 der Zeile“, der Abstand ist dabei richtig verrechnet. `Auto` darf schrumpfen, damit langer Inhalt umbricht statt überzulaufen; `Fill` darf bis 0 schrumpfen.
  - `alignX` muss in allen Layouts gleich wirken: Der Inhalt wird so breit wie nötig und steht am Anfang, in der Mitte oder am Ende seiner Spalte. `justify-self` greift in Flexbox nicht, deshalb braucht die View bei `Split` mit `alignX` ≠ `Stretch` einen eigenen Weg (z. B. ein inneres Element). `alignY` wird auch dort zu `align-self`.
  - `GridColumnStart::Auto` setzt die Spalte direkt hinter die vorherige; eine Zahl setzt sie fest in diese Spalte, notfalls in einer neuen Zeile.
  - `GridOrder::Source` stellt die Position im DOM wieder her (`order: 0`); `First`/`Last` ziehen die Spalte vor bzw. hinter alle `Source`-Spalten. Tastatur- und Vorleserreihenfolge bleiben immer die des DOM. Regel für die Doku: die visuelle Reihenfolge darf nur abweichen, wenn Inhalt und Tastaturreihenfolge verständlich bleiben (WCAG 1.3.2 / 2.4.3).
  - `GridVisibility::Hidden` ist `display: none`, also auch für Screenreader weg. `Visible` stellt die eigene Darstellung der Spalte wieder her, bei einer verschachtelten `.rex-grid` also `display: grid`.

- [ ] **Leere und ungewöhnliche Grids:**
  - Ohne Spalten gibt `Grid` nichts aus (leerer String, kein Wrapper). Listen kommen oft dynamisch aus der Datenbank, eine Exception würde jeden Aufrufer zu einer eigenen Prüfung zwingen.
  - Eine einzige Spalte bekommt trotzdem den Wrapper, denn auch sie kann Regeln haben. Nur der Fragment-Adapter behält die bisherige Rohausgabe bei einer Spalte.
  - Mehr als 12 Spalten: Bei `Columns` brechen sie um (ohne `width` stehen ohnehin alle untereinander). Bei `Split` stehen sie ab `from` alle in einer Zeile, auch 13 oder mehr. Bei `Tiles` gibt es keine Obergrenze.
  - `subgrid: true` ohne umgebendes Grid fällt auf ein normales 12er-Raster zurück (`subgrid` nur über `.rex-grid > .rex-grid[data-subgrid]`; die View rendert eine `GridColumn` mit Subgrid-Inhalt als ein Element). Die Spaltenangaben der inneren Spalten beziehen sich auf die Spuren der äußeren Spalte; reicht deren Breite nicht, brechen die inneren Spalten um. PHP prüft das nicht, weil das innere Grid sein äußeres nicht kennt.
  - Kachelbreiten sind fest pro `GridTileSize`; eine doppelt breite Kachel gibt es nicht (`width` ist bei `Tiles` verboten).
- [ ] **Reiner Inhalt als Spalte:** `Grid` nimmt neben `GridColumn` auch reinen Inhalt (`string|Renderable`) an und verpackt ihn intern als Spalte ohne Regeln. `GridColumn` braucht man nur, wenn eine Spalte Regeln oder eine Ausrichtung hat. Einen Helfer wie `GridColumn::wrap()` gibt es nicht; der Entwickler muss nichts zusätzlich lernen.
- [ ] **Wie `layout` und die Spalten zusammenspielen:**
  - **`GridLayoutColumns`** (Standard): ein Raster aus 12 Spalten. Jede Spalte gibt in ihren Regeln selbst `width`/`start` an. Ohne `width` ist sie 12 Spalten breit, und ergibt die Summe mehr als 12, bricht die Zeile um.
  - **`GridLayoutSplit`**: Unterhalb von `from` stehen die Spalten untereinander. Ab `from` stehen sie in genau einer Zeile ohne Umbruch und teilen sich die Breite: ohne Angabe gleich breit (auch bei 5 Spalten), sonst nach Zahl, `Auto` oder `Fill`.
  - **`GridLayoutTiles`**: Kacheln mit einer Mindestbreite nach `size`. Pro Zeile passen so viele hinein, wie Platz ist, ohne Breakpoints.
- [ ] **Beispiele:**

  ```php
  // Hauptinhalt + Sidebar, ab Lg nebeneinander; die Sidebar steht mobil oben
  echo new Grid([
      new GridColumn($main, [new GridColumnRule(Breakpoint::Lg, width: 8)]),
      new GridColumn($aside, [
          new GridColumnRule(Breakpoint::Xs, order: GridOrder::First),
          new GridColumnRule(Breakpoint::Lg, width: 4, order: GridOrder::Source),
      ]),
  ], gap: GridGap::Large)->render();

  // zentriertes Formular
  echo new Grid([
      new GridColumn($form, [new GridColumnRule(Breakpoint::Md, width: 6, start: 4)]),
  ])->render();

  // Kacheln: keine width/start in den Spalten
  echo new Grid($cards, layout: new GridLayoutTiles(GridTileSize::Small))->render();

  // fünf gleich breite Spalten ab Md
  echo new Grid([$a, $b, $c, $d, $e], layout: new GridLayoutSplit(from: Breakpoint::Md))->render();

  // Feld + Buttons, immer nebeneinander
  echo new Grid([
      $field, // Fill
      new GridColumn($buttons, [new GridColumnRule(Breakpoint::Xs, width: GridColumnWidth::Auto)], alignY: GridAlign::End),
  ], layout: new GridLayoutSplit())->render();

  // Navigation (3/12) + Inhalt (Rest), ab Md nebeneinander
  echo new Grid([
      new GridColumn($nav, [new GridColumnRule(Breakpoint::Md, width: 3)]),
      $main, // Fill
  ], layout: new GridLayoutSplit(from: Breakpoint::Md))->render();

  // gemischt: eine Spalte mit Regeln, eine als reiner Inhalt
  echo new Grid([
      new GridColumn($main, [new GridColumnRule(Breakpoint::Lg, width: 8)]),
      $aside,
  ])->render();

  // verschachtelt, die inneren Spalten liegen auf dem Raster des äußeren Grids
  new GridColumn(new Grid([...], subgrid: true), [new GridColumnRule(Breakpoint::Md, width: 6)]);
  ```
- [ ] **Vollständiges Beispiel**, angelehnt an `pages/mediapool/media.detail.php`. Mobil stehen die Spalten untereinander, die Vorschau oben. Ab `Md` steht links das Formular (8), rechts die Vorschau (4). Ab `Xl` sind es 7 + 5. Ein Hinweistext ist erst ab `Md` sichtbar.

  ```php
  echo new Grid(
      [
          new GridColumn($form, [
              new GridColumnRule(Breakpoint::Md, width: 8),
              new GridColumnRule(Breakpoint::Xl, width: 7),
          ]),
          new GridColumn($preview, [
              new GridColumnRule(Breakpoint::Xs, order: GridOrder::First),
              new GridColumnRule(Breakpoint::Md, width: 4, order: GridOrder::Source),
              new GridColumnRule(Breakpoint::Xl, width: 5),
          ], alignY: GridAlign::Start),
          new GridColumn($hint, [
              new GridColumnRule(Breakpoint::Xs, visibility: GridVisibility::Hidden),
              new GridColumnRule(Breakpoint::Md, visibility: GridVisibility::Visible, width: 8, start: 5),
          ]),
      ],
      gap: GridGap::Large,
  )->render();
  ```

  Lesart: `Grid` legt mit `layout` und `gap` fest, *wie* das Raster als Ganzes funktioniert. Jede `GridColumn` trägt ihren Inhalt, ihre feste Ausrichtung und ihre Regeln. Jede `GridColumnRule` heißt: „ab diesem Breakpoint gilt …“.

  Ausgabe der heutigen View (nur zur Orientierung, der Entwickler sieht sie nicht; ein anderes CSS-System erzeugt hier anderes Markup):

  ```html
  <div class="rex-grid" data-gap="lg">
      <div data-span="md:8 xl:7">…</div>
      <div data-span="md:4 xl:5" data-order="first md:source" data-align-y="start">…</div>
      <div data-span="md:8" data-start="md:5" data-visibility="hidden md:visible">…</div>
  </div>
  ```

- [ ] **Fragment `core/page/grid.php` als Adapter** auf die Komponente. Das alte Var `classes` mit Deprecation abfangen statt still ignorieren, `spans` gegen das Vokabular prüfen. Den Standardwert ohne `spans` auf `GridLayoutSplit(from: Breakpoint::Md)` umstellen: bei 2, 3, 4, 6 und 12 Spalten sieht das aus wie heute, bei 5 Spalten entsteht keine Lücke mehr und ab 13 Spalten kein `md:0`. Die übrige Semantik bleibt, auch die Rohausgabe bei nur einem Inhalt.
- [ ] **Alle sieben Core-Stellen auf die Komponente umstellen.** Sie dienen dann zugleich als Beispiele für Addons.
- [ ] **Das Markup bleibt dokumentierter Vertrag** für Addons, die HTML direkt schreiben (Twig, JS-gerendert). Die PHP-Komponente ist aber der empfohlene Weg. Festlegen und dokumentieren:
  - **Öffentlich** sind nur die Klasse `.rex-grid` und die Attribute, die die View ausgibt (`data-span`, `data-start`, `data-order`, `data-visibility`, `data-align-x`, `data-align-y` an Spalten; `data-layout`, `data-gap`, `data-subgrid` und die Layout-Einstellungen am Grid) sowie das bestehende `data-align="end"`. Alles andere ist intern.
  - **Grammatik:** Tokens durch Leerzeichen getrennt, Form `[bp:]wert`, `bp` ∈ `sm md lg xl`, ein Wert ohne Präfix gilt ab `Xs`. Die Reihenfolge der Tokens ist egal.
  - **Zwei Tokens für denselben Breakpoint** in einem Attribut (`data-span="md:6 md:8"`) sind ungültig, das Ergebnis ist nicht festgelegt. Die View erzeugt das nie.
  - `data-align="end"` behält seine Bedeutung (Text an das Ende), aber mit `text-align: end` statt `right`, damit es RTL-fähig ist. Die Komponente gibt es nicht aus, sie nutzt `data-align-x`/`data-align-y`.

### Fehlendes Vokabular im CSS (Grundlage für die Enums)

- [ ] **Startspalte**, z. B. `data-start="md:4"` und `data-start="lg:auto"`. Ein zentriertes Formular oder eine rechts stehende Spalte lässt sich heute nicht ausdrücken.
- [ ] **Split-Layout** (`data-layout="split"` o. ä.): Flexbox ohne Umbruch ab `from`, Spalten standardmäßig gleich breit (`flex: 1 1 0`, `min-inline-size: 0`), dazu `auto` (`flex: 0 1 auto`) und Zahlen als `flex: 0 0 calc(…)` mit verrechnetem Abstand. Fünf gleich breite Spalten gehen mit 12 Tracks nicht, Feld + Buttons auch nicht.
- [ ] **Sichtbarkeit pro Breakpoint**, als Ersatz für `hidden-*`/`visible-*`.
- [ ] **Reihenfolge pro Breakpoint**, als Ersatz für push/pull. Dokumentieren, dass die visuelle Reihenfolge von der DOM-Reihenfolge abweicht (WCAG 1.3.2 / 2.4.3).
- [ ] **Zeilenabstand:** `gap: 0 var(--rex-gutter)` lässt umbrechende Spalten aneinanderkleben, `Tiles` hat dagegen einen Zeilenabstand. Entschieden: `GridGap` gilt für beide Achsen, `Default` ist `var(--rex-gutter)` in beiden Richtungen. Die Visual-Test-Baselines der betroffenen Core-Seiten ändern sich dadurch bewusst.
- [ ] **Ausrichtung:** `data-align="end"` ist heute `text-align: right`, also weder RTL-fähig noch eine Ausrichtung der Spalte selbst. Neu `data-align-x`/`data-align-y` mit `justify-self`/`align-self` und logischen Werten; `data-align="end"` bleibt für Addons (siehe Markup-Vertrag).
- [ ] **Tiles:** Die Mindestbreite ist fest `16rem`. Varianten nach `GridTileSize` ergänzen (12/16/24rem).
- [ ] **Subgrid:** Die Voraussetzung (die Spalte ist selbst `.rex-grid` und hat einen Span) ist nicht dokumentiert. In der Komponente: `new GridColumn(new Grid([...], subgrid: true), [new GridColumnRule(Breakpoint::Md, width: 6)])`; die View setzt die Voraussetzungen selbst.

### Breakpoints

- [ ] **Entschieden: Grid-Breakpoints beziehen sich auf die Breite des Grids, nicht auf den Viewport.** Ein Grid in der Content-Sidebar (`lg:4`), in einem Overlay oder einem Dialog reagiert heute auf den Viewport: `sm:6` ergibt dann zwei Spalten in rund 300px. Addons können das nicht beeinflussen. Container Queries liegen in der Baseline (Chrome 105 / Firefox 110 / Safari 16). Das ist keine CSS-Optimierung, sondern ändert, was `Breakpoint::Lg` bedeutet. Deshalb gilt verbindlich:
  - **Container ist jedes `.rex-grid` selbst** (`container-type: inline-size`), keine Hauptbereiche. Die Spalten fragen ihr eigenes Grid ab. Ein verschachteltes Grid reagiert auf seine eigene Breite, egal was ein Addon außen herum legt.
  - **Ausnahme Subgrid:** `.rex-grid[data-subgrid]` ist kein Container. Seine Spalten liegen auf dem äußeren Raster und reagieren deshalb auch auf die Breite des äußeren Grids.
  - **Gleiche Namen, neue Werte.** `Sm`–`Xl` bleiben, die Zahlen werden neu festgelegt: jeweils die Breite von `#rex-js-page-main` bei geöffneter Navigation und den alten Viewport-Werten 768/992/1200/1400. Abnahmekriterium: Die Visual-Test-Baselines der Core-Seiten ändern sich durch die Umstellung nicht (abgesehen vom bewusst geänderten Zeilenabstand, siehe oben).
  - **Einschränkung, dokumentieren:** `container-type: inline-size` macht die Breite des Grids unabhängig von seinem Inhalt. In einer Umgebung, die sich nach dem Inhalt richtet (`inline-block`, Float, Flex-Item ohne `flex-grow`/feste Basis, Tabellenzelle mit automatischer Breite), schrumpft das Grid auf 0. Ein `.rex-grid` braucht seine Breite vom umgebenden Block.
  - **Compat bleibt beim Viewport.** `col-*`, `hidden-*` usw. bilden Bootstrap 3 nach und behalten Media Queries mit 768/992/1200 (plus `xl` 1400).
- [ ] **Eine einzige Quelle für die Werte.** Media und Container Queries können keine `var()` verwenden, und `@custom-media` unterstützt keiner der Zielbrowser. Quelle ist deshalb das Enum `Breakpoint` (string-backed: `'sm'` usw. als Token, die Pixelbreite über eine Methode). Ein PHPUnit-Test liest `assets/css/src/components/grid.css` und prüft, dass jede `@container`-Schwelle einem Enum-Wert entspricht und jeder Enum-Wert vorkommt. Die rund 50 übrigen Media Queries des Backend-Rahmens (768/992/1200/1400 als Literale) sind Viewport-Werte und unabhängig davon; sie in `tokens.css` dokumentieren und per Lint auf diese vier Werte beschränken.

### Absicherung

- [ ] **Visual-Test-Fixture** mit allen Grid-Varianten (Spans, Start, Split mit Zahl/auto/fill, Tiles, subgrid, hidden, order, gap, Ausrichtung) in mehreren Breiten, jeweils auch in Sidebar, Overlay und Dialog. Für die Compat-Schicht zusätzlich ein paar typische alte Bootstrap-Layouts (Offset, Push/Pull, `hidden-*`). Heute decken die Visual Tests das Grid nur zufällig über zwei Core-Seiten ab, und von den Varianten für Addons ist keine getestet.
- [ ] **PHPUnit-Tests** für `Grid`/`GridColumn`: Markup pro Enum-Wert, die Matrix der erlaubten Eigenschaften, alle Exception-Fälle (auch Start + Breite über geerbte Werte), leeres Grid, Fragment-Adapter, Breakpoint-Werte gegen `grid.css`.
- [ ] **Doku für Addon-Entwickler:** PHP-API mit Beispielen und der Markup-Vertrag.

> Korrektur zum ersten Grid-Befund: Die Varianten `data-subgrid`, `data-layout="auto"` und `data-align="end"` nicht entfernen, sie sind als API für Addons gedacht. Sie brauchen aber Doku und Tests (siehe oben).

---

## Erstes Review (Stand c26f5c3ee)

### Blocker

- [x] ✅ **Form-Tests rot** – Erwartungen an das neue Markup angepasst (09d864f3d).
- [x] ✅ **Doppeltes Escaping** – `I18n::rawMsg()` in DataList, ArticleHistory, users.php und den Spaltenlabels (7ed56b552, 03bc7673e); ein Rest ist oben unter „Offen“ aufgeführt.

### Sollte behoben werden

- [x] ✅ **`requireBootstrapCompat()` nur in `boot()`** – dokumentiert, wirft jetzt eine `LogicException` (5161e1a3c).
- [x] ✅ **`ContentSecurityPolicy::add()` verliert `'self'`** – neue Direktiven übernehmen ihren Fallback, Test ergänzt (eda16d02a).
- [x] ✅ **History-Vorschau bei Mehr-Domain-Setups** – `pages/structure/content.php` erlaubt die Frontend-Origin in `frame-src` (7aa3038d7).
- [x] ✅ **CSP-Header vor `OUTPUT_FILTER`** – der Header wird jetzt in einem `OUTPUT_FILTER` auf Stufe `Late` gesetzt; früh endende Antworten bekommen die volle Basis-Policy (ee23a7027).
- [x] ✅ **Live-Region im Overlay stumm** – Region liegt jetzt im obersten modalen Dialog (e42e50674).
- [x] ✅ **`data-rex-submit-params` im Overlay** – Listener läuft jetzt in der Capture-Phase (b806b38c0).
- [x] ✅ **`page/section.php` `before`/`after`** – werden wieder ausgegeben (1de03d62d).
- [x] ✅ **`button_group.php` Submit-Buttons** – ohne `url` wieder `type="submit"`, `type` aus `attributes` wird beachtet (1de03d62d).

### Robustheit / kleiner

- [x] Overlay-Loads: neuere Anfrage bricht ältere ab (`AbortController`).
- [x] Mehrfaches `rex.ready()`: Guards in `history.js`, `webauthn.js`, `content-sidebar.js`.
- [x] Select-Panel: entfernt seine Listener, wenn es nicht mehr im DOM ist.
- [x] Ctrl/Cmd+Enter: `preventDefault`.
- [x] `data-confirm` übernimmt die Modifier-Tasten (Browserverhalten oben unter „Offen“).
- [x] Dropdown-Pfeiltasten erreichen jetzt `a[href]` und `button`, Formularfelder behalten ihre Pfeiltasten.
- [x] Collapse: `data-target` nimmt beliebige Selektoren.
- [x] `Accesskey::reset()`.
- [x] History-Restore: Ergebnis und Fehler erscheinen als Meldung auf der Content-Seite (fd3d4e6a5).
- [x] Range-Slider: transparente Border für Forced Colors.
- [x] Komponenten-Links: `a:not(.rex-button, .btn)` in alert.css/table.css.
- [x] Stylelint: Outline- und Farbfunktions-Regeln verschärft.
- [ ] Browser-Baseline weiterhin nicht automatisch geprüft (manuell sauber). `light-dark()` per `var()` in `color-mix()` in Firefox 128 / Safari 18 noch testen.
- [x] Utilities ohne `!important`: Addon-CSS gewinnt jetzt, wie AGENTS.md es beschreibt.
- [x] `a11y.log` wird beim Start geleert, eigene Datei pro Scope.
- [x] Psalm-Baselines um die gelöschten Fragments bereinigt (neue Einträge siehe oben).
- [x] Ungestylte `rex-table-action-*` entfernt.

### API / Design

- [x] CSP: Konstruktor, `get()` und `toString()` sind jetzt `@internal`.
- [x] `Toolbar` nimmt `HtmlAttributes` statt `cssClass`.
- [ ] `Tabs` (siehe oben unter „Offen“).
- [x] `getRows()` heißt jetzt `getPageRows()`.
- [ ] Upgrade-Hinweise in die PR-Beschreibung (siehe oben).

### Vorbestehend

- [x] `session-timeout.js`: nach Esc oder ✕ läuft die Session weiter, ohne still abzulaufen.
- [x] `webauthn.js`: Passkey-Bestätigung funktioniert auch ohne Platform-Authenticator.
- [x] `pages/system/settings.php`: DB-Name und Host werden escaped.
