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
- [ ] **API nach drei Regeln** (überarbeitet, ersetzt den ersten Entwurf mit `Responsive(lg: …)`):
  1. **Breakpoints werden immer als Enum notiert.** Es gibt keine benannten Argumente `sm:`/`lg:` und keine Strings. `Breakpoint` hat die Fälle `Xs` (ab 0, die Basis), `Sm`, `Md`, `Lg`, `Xl`. Eine Sonderrolle „base“ gibt es nicht: Jede Angabe gilt „ab Breakpoint X“ (mobile first), auch die kleinste.
  2. **Was responsiv ist und was fest, ergibt sich aus der Struktur.** Responsive Werte stehen ausschließlich in `ColumnLayout`-Objekten, von denen jedes an genau einen Breakpoint gebunden ist. Feste Werte sind direkte Parameter von `Grid` bzw. `GridColumn`. Ein Parameter ist also nie mal fest und mal responsiv.
     - responsiv (`ColumnLayout`): `width`, `offset`, `order`, `visibility`
     - fest (`Grid`): `layout`, `gap`, `tileSize`, `subgrid`, bei `GridLayout::Equal` der Breakpoint `from`
     - fest (`GridColumn`): `alignX`, `alignY`
  3. **Kein `null` als Wert.** Ein weggelassener Parameter in `ColumnLayout` heißt immer „wie beim kleineren Breakpoint“. Zurücksetzen geht nur über ausdrückliche Enum-Fälle (`Order::Source`, `Visibility::Visible`) bzw. ausdrückliche Zahlen (`offset: 0`, `width: 12`). Im ersten Entwurf hätte `lg: null` „ab `lg` wieder die normale Reihenfolge“ bedeuten sollen, das war aber nicht erkennbar. Jetzt heißt das `Order::Source`.
- [ ] **Enums**, alle ohne Bezug zu 12 Tracks oder Klassennamen:
  - `Breakpoint`: `Xs`, `Sm`, `Md`, `Lg`, `Xl`
  - `ColumnWidth`: `Auto` (so breit wie der Inhalt), `Fill` (der Rest der Zeile). Das sind die beiden Breiten, die sich nicht als Spaltenzahl ausdrücken lassen.
  - `Order`: `Source` (DOM-Reihenfolge), `First`, `Last`
  - `Visibility`: `Visible`, `Hidden`
  - `GridLayout`: `Columns` (Standard), `Equal` (gleich breite Spalten), `Tiles` (bisher `data-layout="auto"`)
  - `GridGap`: `None`, `Small`, `Default`, `Large`
  - `TileSize`: `Small`, `Medium`, `Large`
  - `Align`: `Start`, `Center`, `End`, `Stretch`

  **Breiten und Offsets sind Spaltenzahlen** von 12 (`width: 8`, `offset: 3`), denn Zahlen lesen sich am einfachsten. Psalm prüft `int<1,12>` bzw. `int<0,11>`, zur Laufzeit gibt es eine `InvalidArgumentException`. Die 12 steht als `Grid::COLUMNS` an einer Stelle. Ein anderes CSS-System bildet die Zahl in seiner View selbst ab, z. B. als `8 / 12` = 66,67 %.
- [ ] **Signaturen.** Spalten und Layouts werden als Liste übergeben, nicht variadisch. Variadische Parameter müssen in PHP am Ende stehen, und Positionsargumente lassen sich nicht mit benannten Optionen davor mischen.

  ```php
  final class Grid implements Renderable
  {
      /** @param list<GridColumn> $columns */
      public function __construct(
          public array $columns,
          public GridLayout $layout = GridLayout::Columns,
          public GridGap $gap = GridGap::Default,
          public TileSize $tileSize = TileSize::Medium,   // nur bei GridLayout::Tiles
          public Breakpoint $from = Breakpoint::Xs,       // nur bei GridLayout::Equal: darunter untereinander
          public bool $subgrid = false,
      ) {}
  }

  final class GridColumn implements Renderable
  {
      /** @param list<ColumnLayout> $layouts je Breakpoint höchstens eines, Reihenfolge egal */
      public function __construct(
          public string|Renderable $content,
          public array $layouts = [],
          public Align $alignX = Align::Stretch,
          public Align $alignY = Align::Stretch,
      ) {}
  }

  final class ColumnLayout
  {
      public function __construct(
          public Breakpoint $breakpoint,
          /** @var int<1,12>|ColumnWidth|null */
          public int|ColumnWidth|null $width = null,   // weggelassen = wie beim kleineren Breakpoint
          /** @var int<0,11>|null */
          public ?int $offset = null,
          public ?Order $order = null,
          public ?Visibility $visibility = null,
      ) {}
  }
  ```

  Die Parameter von `ColumnLayout` sind technisch nullable, damit sie weggelassen werden können. In der Doku steht aber nur „weglassen = übernehmen“, nie `null` als Wert. Ein `ColumnLayout` ganz ohne Werte und doppelte Breakpoints in einer Spalte → `InvalidArgumentException`.
- [ ] **Beispiele:**

  ```php
  // Hauptinhalt + Sidebar, ab Lg nebeneinander; die Sidebar steht mobil oben
  echo new Grid([
      new GridColumn($main, [new ColumnLayout(Breakpoint::Lg, width: 8)]),
      new GridColumn($aside, [
          new ColumnLayout(Breakpoint::Xs, order: Order::First),
          new ColumnLayout(Breakpoint::Lg, width: 4, order: Order::Source),
      ]),
  ], gap: GridGap::Large)->render();

  // zentriertes Formular
  echo new Grid([
      new GridColumn($form, [new ColumnLayout(Breakpoint::Md, width: 6, offset: 3)]),
  ])->render();

  // Feld + Buttons nach Inhaltsbreite, immer nebeneinander
  echo new Grid([
      new GridColumn($field, [new ColumnLayout(Breakpoint::Xs, width: ColumnWidth::Fill)]),
      new GridColumn($buttons, [new ColumnLayout(Breakpoint::Xs, width: ColumnWidth::Auto)], alignY: Align::End),
  ])->render();

  // Hilfetext erst ab Md sichtbar
  new GridColumn($help, [
      new ColumnLayout(Breakpoint::Xs, visibility: Visibility::Hidden),
      new ColumnLayout(Breakpoint::Md, width: 4, visibility: Visibility::Visible),
  ]);

  // feste Layouts ohne responsive Angaben
  echo new Grid($cards, layout: GridLayout::Tiles, tileSize: TileSize::Small)->render();
  echo new Grid($columns, layout: GridLayout::Equal, from: Breakpoint::Md)->render();
  ```

  Für `Grid($cards, …)` müssen `$cards` bereits `GridColumn` sein; ein `GridColumn::wrap(list<string|Renderable>)` als Helfer wäre denkbar.
- [ ] **Vollständiges Beispiel mit Grid, Spalten und Layouts**, angelehnt an `pages/mediapool/media.detail.php`. Mobil stehen die Spalten untereinander, die Vorschau oben. Ab `Md` steht links das Formular (8 Spalten), rechts die Vorschau (4). Ab `Xl` wird die Vorschau breiter (7 + 5). Ein Hinweistext ist erst ab `Md` sichtbar.

  ```php
  echo new Grid(
      [
          new GridColumn($form, [
              new ColumnLayout(Breakpoint::Md, width: 8),
              new ColumnLayout(Breakpoint::Xl, width: 7),
          ]),
          new GridColumn($preview, [
              new ColumnLayout(Breakpoint::Xs, order: Order::First),
              new ColumnLayout(Breakpoint::Md, width: 4, order: Order::Source),
              new ColumnLayout(Breakpoint::Xl, width: 5),
          ], alignY: Align::Start),
          new GridColumn($hint, [
              new ColumnLayout(Breakpoint::Xs, visibility: Visibility::Hidden),
              new ColumnLayout(Breakpoint::Md, visibility: Visibility::Visible, width: 8, offset: 4),
          ]),
      ],
      gap: GridGap::Large,
  )->render();
  ```

  Lesart: `Grid` legt fest, *wie* das Raster als Ganzes funktioniert (`layout`, `gap` …, alles fest). Jede `GridColumn` trägt ihren Inhalt, ihre feste Ausrichtung und eine Liste von `ColumnLayout`s. Jedes `ColumnLayout` sagt: „ab diesem Breakpoint gilt …“. Was dort nicht steht, bleibt wie beim kleineren Breakpoint.

  Ausgabe der heutigen View (nur zur Orientierung, der Entwickler sieht sie nicht; ein anderes CSS-System erzeugt hier anderes Markup):

  ```html
  <div class="rex-grid" data-gap="lg">
      <div data-span="md:8 xl:7">…</div>
      <div data-span="md:4 xl:5" data-order="first md:source" data-align-y="start">…</div>
      <div data-span="md:8" data-start="md:5" data-visibility="hidden md:visible">…</div>
  </div>
  ```

  `offset: 4` wird zu `data-start="md:5"`, weil eine CSS-Grid-Spalte nach 4 freien Spalten bei Linie 5 beginnt. Diese Umrechnung steckt in der View und nicht beim Aufrufer.

  Ein Grid **mit festem Layout** hat dagegen keine `ColumnLayout`s, die Spalten ordnen sich selbst an:

  ```php
  echo new Grid(GridColumn::wrap($cards), layout: GridLayout::Tiles, tileSize: TileSize::Small)->render();
  echo new Grid(GridColumn::wrap([$a, $b, $c, $d, $e]), layout: GridLayout::Equal, from: Breakpoint::Md)->render();
  ```

  Werden bei `Tiles`/`Equal` trotzdem `width`/`offset` angegeben, sollte das eine `InvalidArgumentException` werfen. `order` und `visibility` bleiben dort erlaubt.
- [ ] **Ungültige Werte früh abweisen.** `int` außerhalb von 1–12 → `InvalidArgumentException`, Psalm-Typ `int<1,12>`. `Grid` nimmt nur `GridColumn` an (variadisch), der Nutzer schreibt kein Wrapper-HTML.
- [ ] **Fragment `core/page/grid.php` als Adapter** auf die Komponente. Das alte Var `classes` mit Deprecation abfangen statt still ignorieren, `spans` gegen das Vokabular prüfen. Den Standardwert ohne `spans` auf `GridLayout::Equal` umstellen, damit `intdiv(12, $count)` bei 5 Spalten keine Lücke und ab 13 Spalten kein `md:0` mehr erzeugt.
- [ ] **Alle sieben Core-Stellen auf die Komponente umstellen.** Sie dienen dann zugleich als Beispiele für Addons.
- [ ] **Das Markup bleibt dokumentierter Vertrag** für Addons, die HTML direkt schreiben (Twig, JS-gerendert). Die PHP-Komponente ist aber der empfohlene Weg.

### Fehlendes Vokabular im CSS (Grundlage für die Enums)

- [ ] **Startposition / Offset**, z. B. `data-start="md:4"`. Ein zentriertes Formular oder eine rechts stehende Spalte lässt sich heute nicht ausdrücken.
- [ ] **Spalte nach Inhalt / Rest der Zeile** (`auto`, `fill`). Typisches Muster im Backend: Feld plus Buttons.
- [ ] **Gleich breite Spalten** ohne Rechnen (`grid-auto-flow: column`). Fünf Spalten gehen mit 12 Tracks nicht.
- [ ] **Sichtbarkeit pro Breakpoint**, als Ersatz für `hidden-*`/`visible-*`.
- [ ] **Reihenfolge pro Breakpoint**, als Ersatz für push/pull. Dokumentieren, dass die visuelle Reihenfolge von der DOM-Reihenfolge abweicht (WCAG 1.3.2 / 2.4.3).
- [ ] **Zeilenabstand:** `gap: 0 var(--rex-gutter)` lässt umbrechende Spalten aneinanderkleben, `Tiles` hat dagegen einen Zeilenabstand. Das vereinheitlichen und Gap-Varianten anbieten.
- [ ] **Ausrichtung:** `data-align="end"` ist heute `text-align: right`, also weder RTL-fähig noch eine Ausrichtung der Spalte selbst. Durch `align-self`/`justify-self` mit logischen Werten ersetzen.
- [ ] **Tiles:** Die Mindestbreite ist fest `16rem`, Varianten fehlen. Spans werden zurückgesetzt, eine doppelt breite Kachel ist nicht möglich.
- [ ] **Subgrid:** Die Voraussetzung (die Spalte ist selbst `.rex-grid` und hat einen Span) ist nicht dokumentiert. In der Komponente: `new GridColumn(new Grid([...], subgrid: true), [new ColumnLayout(Breakpoint::Md, width: 6)])`; die View setzt die Voraussetzungen selbst.

### Breakpoints

- [ ] **Container Queries statt Viewport.** Ein Grid in der Content-Sidebar (`lg:4`), in einem Overlay oder einem Dialog reagiert heute auf den Viewport: `sm:6` ergibt dann zwei Spalten in rund 300px. Addons können das nicht beeinflussen. Die Lösung ist `container-type: inline-size` auf `.rex-grid` bzw. auf den Hauptbereichen, das liegt in der Baseline (Chrome 105 / Firefox 110 / Safari 16). Der Vertrag (`data-span`, Enums) bleibt dabei gleich.
- [ ] **Eine einzige Quelle für die Werte.** 768/992/1200/1400 und die `max-width`-Gegenstücke stehen als Literale in rund 50 Media Queries. esbuild übersetzt `@custom-media` nicht, also die Werte wenigstens in `tokens.css` dokumentieren und beim Build bzw. im Lint prüfen. In PHP hält das Enum `Breakpoint` die Werte fest.

### Absicherung

- [ ] **Visual-Test-Fixture** mit allen Grid-Varianten (Spans, Start, auto/fill, equal, tiles, subgrid, hidden, order, gap, Ausrichtung) in mehreren Breiten. Heute decken die Visual Tests das Grid nur zufällig über zwei Core-Seiten ab, und von den Varianten für Addons ist keine getestet.
- [ ] **PHPUnit-Tests** für `Grid`/`GridColumn`: Markup pro Enum-Wert, ungültige Werte, Fragment-Adapter.
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
