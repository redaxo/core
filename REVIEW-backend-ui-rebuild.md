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
