# Branding Page Design Implementation Plan (v3.5.0)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the approved Branding page mockup as the real page — a full-width "proof" (two chrome pulls with a legend under them), five flat cards, six switches and a sticky save bar — without changing one id, class, field name or line of the frozen uploader JavaScript the page already depends on.

**Architecture:** The page stays a stock ISPConfig tform module. This is markup, an inline `<style>` and additions to the inline `<script>` in `interface/web/customizer/templates/customizer_edit.htm`, plus one pure function in `lib/preview.inc.php` (the three status facts), the tmpl vars `customizer_edit.php` has to publish for them and for the fifteen `<details>` accessible names, and seventeen new wordbook keys in seven locales. The preview endpoint's contract grows by exactly one additive key, `summary`, so the facts stay live after an upload. Nothing in ISPConfig core is touched.

**Tech Stack:** PHP 8.x (ISPConfig 3.3.1p1 tform framework, vlibTemplate), vanilla ES5 JavaScript with `fetch`/`FormData`/`DOMParser`/`MutationObserver`, plain CSS with custom properties, container queries and `:has()`, `tests/brand/` (hand-rolled TAP-ish probes run by `php tests/brand/run.php`), `mockup/build.py` (Playwright) for the rendered check.

**Spec:** `mockup/branding/DESIGN-NOTES.md`, with `mockup/branding/branding.html`, `mockup/branding/branding.css` and `mockup/branding/shots/*.png` as the approved artefact. The plan argues from those four; read them before Task 4.

## Global Constraints

- **No ISPConfig core file is modified.** Everything ships under `interface/web/customizer/` and `mockup/`.
- **The frozen region is byte-identical.** `wireUpload()`, `wireRemove()`, their three call pairs, and the `#nz-msg-slot` relocation observer at the bottom of the template's `<script>` are carried over unchanged, character for character. The click-time CSRF mint inside `wireUpload()` exists because ISPConfig's session store has no locking; it must never be reimplemented, moved or extended. New behaviour goes in new functions above it.
- **Every id, class, name and data attribute the script binds survives.** `#nz-msg-slot`, `#nz-brandpage` and its three `data-*` attributes, the five preview slots (`used_logo`, `used_logo_more`, `used_logo_on_dark`, `used_logo_on_dark_more`, `used_favicon`), the three uploader buttons and their file inputs (`file`, `file_on_dark`, `file_favicon`), the three remove links, the four hex text/picker pairs, `.nz-prev-*`, `.nz-prevframe`, `.nz-brandpreview`, `.nz-actions`, `input[name=id]`, and the `data-submit-form` / `data-load-content` pair on the two buttons.
- **Every `.form-group` keeps its `label.col-sm-3` + `div.col-sm-9` shape.** `markField()` walks `input.closest('.form-group')`, and tform and the framework grid both assume that row.
- **DOM order is proof → legend.** `paintSurfacePane()` uses `querySelector` (first match only), so the first `.nz-prev-rail`, `.nz-prev-rail-light` and `.nz-prev-login` in the document must be the pulls and the light-rail sample — never a colour chip.
- **CSS class prefix is `nz-`.** Every selector is prefixed `#nz-brandpage` or `#pageContent`. Every colour is `var(--pz-…, var(--nz-…, <neutral literal>))` — phosphor's token, then clarity's, then a design-neutral fallback. No `!important`. `container-type` goes on `#nz-brandpage`, never on `#pageContent`.
- **One documented focus rule, and only one:** `#nz-brandpage .nz-drop:focus-within`. It exists because the file input it belongs to is deliberately clipped, so the design's own indicator would paint off-screen; it re-exposes an indicator rather than replacing one. No other rule on this page may name `:focus`, `:focus-visible`, `:focus-within` or `outline`.
- **Copy does not change except where this plan says so.** Every existing hint value is relocated verbatim into a `<details>`; the only value changes are the six switch labels, and the only new strings are the seventeen keys in Task 1.
- **Seven locales**: `de, en, es, fr, it, nl, pt`, in both `interface/web/customizer/lib/lang/<lang>_customizer.lng` (tform) and `interface/web/customizer/lib/lang/<lang>.lng` (module). Tooling parses `.lng` files as **text** and never `include()`s them. Values use the typographic apostrophe `’`, never an unescaped ASCII `'` inside a single-quoted PHP string, and never contain markup — wordbook values are emitted unescaped.
- **`preview.php` stays read-only and mints no token.** The only change to it is six more already-localised strings in the `$texts` array it already builds.
- **Local vs panel.** Local PHP is 8.3 CLI **without `mysqli`, `dom` or `mbstring`**. These run locally: `php -l`, `php tests/brand/run.php`, `php .github/scripts/lang_check.php`, `node --check` on the extracted script, and `python3 mockup/build.py --shoot` (Playwright is installed). These need CI or a panel: `php tests/svg/run.php` (needs `dom`), anything touching the database, and every in-panel behaviour check.
- **Commit at the end of every task. Do not push.**

---

## File Structure

**Modified**

- `interface/web/customizer/lib/lang/{de,en,es,fr,it,nl,pt}_customizer.lng` — eleven new tform keys; six switch labels reworded.
- `interface/web/customizer/lib/lang/{de,en,es,fr,it,nl,pt}.lng` — six new module keys (the status facts), which live here and not in the tform wordbook because `preview.php` has no tform and resolves them through `$app->lng()`.
- `.github/scripts/lang_check.php` — the attribute-bound key list grows from nine to twenty-three, because the fifteen `<details>` accessible names interpolate label values into `aria-label`.
- `interface/web/customizer/lib/preview.inc.php` — `customizer_brand_summary()`, and the additive `summary` block in `customizer_preview_payload()`.
- `interface/web/customizer/preview.php` — six more strings in the `$texts` array.
- `interface/web/customizer/customizer_edit.php` — publishes the three status facts and the fifteen hint labels.
- `interface/web/customizer/templates/customizer_edit.htm` — the page: new `<style>` block, new markup, six additions to the script.
- `tests/brand/run.php` — registers the new structural probe.
- `tests/brand/probe_module.php` — covers the summary builder and the payload's new block.
- `mockup/build.py` — renders the shipped template as two more pages so it can be shot beside the mockup.
- `.gitignore` — the generated `mockup/branding/shipped.html`.
- `README.md`, `CONTRIBUTING.md`, `SECURITY.md`, `UPGRADING.md`.

**Created**

- `tests/brand/probe_page.php` — the structural probe of `templates/customizer_edit.htm`: the JS contract, DOM order, the CSS house rules and the ES5 rule.
- `mockup/branding/sample_previews.php` — the sample brand run through the module's real renderers, as JSON.
- `mockup/branding/render_shipped.py` — turns the shipped template into a mockup fragment, and builds the canned preview response the page's own script is fed for the shot.

**Not touched:** `interface/web/customizer/logo_upload.php`, `logo_delete.php`, `form/customizer.tform.php`, `lib/svg_guard.inc.php`, `lib/dashlets.inc.php`, `bin/`, `install.sh`, `themes/`, `.github/workflows/ci.yml`, `mockup/branding/branding.html`, `mockup/branding/branding.css` (the design record stays exactly as approved).

---

## Task 1: The wordbook — seventeen new keys, six reworded switch labels, and the attribute guard

**Files:**
- Modify: `interface/web/customizer/lib/lang/{de,en,es,fr,it,nl,pt}_customizer.lng`
- Modify: `interface/web/customizer/lib/lang/{de,en,es,fr,it,nl,pt}.lng`
- Modify: `.github/scripts/lang_check.php` (the `$HTML_ATTR_WB_KEYS` array and the docblock above it)

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: eleven tform keys — `preview_panel_txt`, `hint_more_txt`, `drop_hint_txt`, `accent_short_txt`, `rail_short_txt`, `rail_light_short_txt`, `contrast_short_txt`, `also_used_on_txt`, `logo_variants_lead_txt`, `favicon_lead_txt`, `accent_hex_hint_txt`; six module keys — `summary_design_txt`, `summary_marks_none_txt`, `summary_marks_one_txt`, `summary_marks_txt`, `summary_favicon_txt`, `summary_favicon_none_txt`. Later tasks read all seventeen.

**Background the implementer needs:**

There are three wordbooks and three different core loaders (see `CONTRIBUTING.md`, "Translations"). The split that matters here: `customizer_edit.php` can reach the tform wordbook through `$app->tform->lng()`, but `preview.php` and `logo_upload.php` have no tform at all and can only reach `lib/lang/<lang>.lng` through `$app->lng()`. The six status-fact strings are needed by `preview.php`, so they go in the **module** wordbook; the eleven page-copy strings go in the **tform** wordbook beside every other label on this page.

`lang_check.php` enforces key parity per wordbook against its English source, and separately refuses `"` or `<` in any value that the template interpolates into a double-quoted HTML attribute. Task 4 puts fifteen existing label values inside an `aria-label`, so those fifteen keys join that list now — before the exposure exists, not after. All fifteen were checked across all seven locales while this plan was written and none contains either character today; the guard is what keeps that true.

`%s` and `%d` in these values are filled with `str_replace()`, never `sprintf()`: a translation carrying a stray `%` makes `sprintf()` raise a `ValueError` on PHP 8, which on an admin page is a fatal raised by a wordbook file. See Task 2.

**Nothing is retired.** Two keys stop being interpolated by the template — `rail_contrast_txt` ("Sidebar text contrast:", replaced on all four colour rows by `contrast_short_txt` after the ratio) and `preview_nav_txt` ("Navigation", replaced as the pull's caption by `preview_panel_txt`). Both stay in all seven files: `preview_nav_txt` still has the same value as the module wordbook's `surface_nav_txt` and reads as this page's vocabulary for the surface, and retiring either would be seven files of churn plus an `UPGRADING.md` entry to buy nothing. A translator seeing them unused is a smaller cost than a third-party translation breaking on a key that vanished.

- [ ] **Step 1: Run the guard before touching anything, so its output is a baseline**

Run: `php .github/scripts/lang_check.php`
Expected: three parity lines and `language files OK`. Note the tform key count in the first line (89 today) — it must read 100 when this task is done.

- [ ] **Step 2: Add the eleven new tform keys to `en_customizer.lng`**

Append these to `interface/web/customizer/lib/lang/en_customizer.lng`, grouped where they belong: the first eight after `$wb['preview_failed_txt']`, `logo_variants_lead_txt` immediately before `logo_variants_hint_txt`, `favicon_lead_txt` immediately before `favicon_intro_txt`, and `accent_hex_hint_txt` immediately after `accent_hex_txt`.

```php
// The page draws a browser tab strip, a sidebar and a content card in one
// frame now, so preview_nav_txt ("Navigation") no longer names what is in it.
// preview_nav_txt itself stays: the tab strip's visually-hidden label still
// uses preview_tab_txt, and preview_login_txt still captions the login pull.
$wb['preview_panel_txt'] = 'Panel';
// The accessible name of every "?" disclosure on the page, filled with the
// label of the thing it explains. str_replace fills it, not sprintf — see
// publish_hint_labels() in customizer_edit.php.
$wb['hint_more_txt'] = 'More about %s';
$wb['drop_hint_txt'] = 'Choose a file or drop it here.';
// Captions under a 56px colour chip. The full field labels do not fit.
$wb['accent_short_txt'] = 'Accent';
$wb['rail_short_txt'] = 'Sidebar';
$wb['rail_light_short_txt'] = 'Light sidebar';
// The noun after the ratio: "12.50:1 text contrast".
$wb['contrast_short_txt'] = 'text contrast';
// Labels the strip of every surface AFTER the first, under each mark block.
$wb['also_used_on_txt'] = 'Also used on';
```

```php
$wb['logo_variants_lead_txt'] = 'Two slots, because designs disagree about brightness. Fill the one that matches the design you use; the other borrows it.';
```

```php
$wb['favicon_lead_txt'] = 'One icon for the whole panel, login screen included.';
```

```php
$wb['accent_hex_hint_txt'] = 'Buttons, links, the active sidebar marker and the chart line. The text printed on a filled accent is chosen by measured contrast, so a pale accent gets dark text.';
```

- [ ] **Step 3: Reword the six switch labels in `en_customizer.lng`**

A switch already says "show", so the prefix is noise repeated six times. Replace the six existing assignments with these (same keys, new values):

```php
$wb['show_ispconfig_credit_txt'] = 'Footer credit: ISPConfig';
$wb['show_theme_credit_txt'] = 'Footer credit: the design';
$wb['show_version_txt'] = 'Software version';
$wb['show_news_feed_txt'] = 'News feed';
$wb['show_donation_dashlet_txt'] = 'Donation panel';
$wb['show_design_picker_txt'] = 'Design picker';
```

- [ ] **Step 4: Add the six new module keys to `en.lng`**

Append to `interface/web/customizer/lib/lang/en.lng`:

```php
// The three status facts in the Branding page's legend, under the proof. They
// live in THIS wordbook rather than the tform one for the same reason
// no_logo_set_txt does: customizer/preview.php rebuilds them after every
// keystroke and has no tform at all, so it resolves them through $app->lng().
// %s and %d are filled with str_replace(), never sprintf() — a translation
// carrying a stray '%' would make sprintf() raise a ValueError, which on an
// admin page is a fatal raised by a wordbook file.
$wb['summary_design_txt'] = '%s active';
$wb['summary_marks_none_txt'] = 'No marks';
$wb['summary_marks_one_txt'] = '%d mark';
$wb['summary_marks_txt'] = '%d marks';
$wb['summary_favicon_txt'] = 'Favicon set';
$wb['summary_favicon_none_txt'] = 'No favicon';
```

- [ ] **Step 5: Add the same seventeen keys to the six other locales**

Each file gets the eleven tform keys (in `<lang>_customizer.lng`) and the six module keys (in `<lang>.lng`), in the same positions as English, and the six switch labels are replaced in `<lang>_customizer.lng`. Comments are not translated — carry the English comment blocks from Steps 2 and 4 across unchanged, which is what every other key in these files already does.

German — `de_customizer.lng`:

```php
$wb['preview_panel_txt'] = 'Panel';
$wb['hint_more_txt'] = 'Mehr über %s';
$wb['drop_hint_txt'] = 'Datei auswählen oder hierher ziehen.';
$wb['accent_short_txt'] = 'Akzent';
$wb['rail_short_txt'] = 'Seitenleiste';
$wb['rail_light_short_txt'] = 'Helle Seitenleiste';
$wb['contrast_short_txt'] = 'Textkontrast';
$wb['also_used_on_txt'] = 'Auch verwendet auf';
$wb['logo_variants_lead_txt'] = 'Zwei Felder, weil Designs unterschiedlich hell sind. Füllen Sie das Feld, das zu Ihrem Design passt; das andere übernimmt es.';
$wb['favicon_lead_txt'] = 'Ein Symbol für das gesamte Panel, einschließlich Anmeldebildschirm.';
$wb['accent_hex_hint_txt'] = 'Schaltflächen, Links, die aktive Markierung in der Seitenleiste und die Diagrammlinie. Die Schrift auf einer gefüllten Akzentfläche wird nach gemessenem Kontrast gewählt, ein heller Akzent erhält also dunkle Schrift.';
$wb['show_ispconfig_credit_txt'] = 'Fußzeile: ISPConfig';
$wb['show_theme_credit_txt'] = 'Fußzeile: das Design';
$wb['show_version_txt'] = 'Software-Version';
$wb['show_news_feed_txt'] = 'News-Feed';
$wb['show_donation_dashlet_txt'] = 'Spendenfeld';
$wb['show_design_picker_txt'] = 'Design-Auswahl';
```

German — `de.lng`:

```php
$wb['summary_design_txt'] = '%s aktiv';
$wb['summary_marks_none_txt'] = 'Keine Logos';
$wb['summary_marks_one_txt'] = '%d Logo';
$wb['summary_marks_txt'] = '%d Logos';
$wb['summary_favicon_txt'] = 'Favicon gesetzt';
$wb['summary_favicon_none_txt'] = 'Kein Favicon';
```

Spanish — `es_customizer.lng`:

```php
$wb['preview_panel_txt'] = 'Panel';
$wb['hint_more_txt'] = 'Más sobre %s';
$wb['drop_hint_txt'] = 'Elija un archivo o arrástrelo aquí.';
$wb['accent_short_txt'] = 'Acento';
$wb['rail_short_txt'] = 'Barra lateral';
$wb['rail_light_short_txt'] = 'Barra lateral clara';
$wb['contrast_short_txt'] = 'contraste del texto';
$wb['also_used_on_txt'] = 'También se usa en';
$wb['logo_variants_lead_txt'] = 'Dos ranuras, porque los diseños no coinciden en luminosidad. Rellene la que corresponda al diseño que use; la otra la toma prestada.';
$wb['favicon_lead_txt'] = 'Un icono para todo el panel, incluida la pantalla de inicio de sesión.';
$wb['accent_hex_hint_txt'] = 'Botones, enlaces, el marcador activo de la barra lateral y la línea del gráfico. El texto impreso sobre un acento relleno se elige por contraste medido, así que un acento claro recibe texto oscuro.';
$wb['show_ispconfig_credit_txt'] = 'Crédito del pie: ISPConfig';
$wb['show_theme_credit_txt'] = 'Crédito del pie: el diseño';
$wb['show_version_txt'] = 'Versión del software';
$wb['show_news_feed_txt'] = 'Canal de noticias';
$wb['show_donation_dashlet_txt'] = 'Panel de donaciones';
$wb['show_design_picker_txt'] = 'Selector Design';
```

Spanish — `es.lng`:

```php
$wb['summary_design_txt'] = '%s activo';
$wb['summary_marks_none_txt'] = 'Sin logotipos';
$wb['summary_marks_one_txt'] = '%d logotipo';
$wb['summary_marks_txt'] = '%d logotipos';
$wb['summary_favicon_txt'] = 'Favicon definido';
$wb['summary_favicon_none_txt'] = 'Sin favicon';
```

French — `fr_customizer.lng`:

```php
$wb['preview_panel_txt'] = 'Panneau';
$wb['hint_more_txt'] = 'En savoir plus sur %s';
$wb['drop_hint_txt'] = 'Choisissez un fichier ou déposez-le ici.';
$wb['accent_short_txt'] = 'Accentuation';
$wb['rail_short_txt'] = 'Barre latérale';
$wb['rail_light_short_txt'] = 'Barre latérale claire';
$wb['contrast_short_txt'] = 'contraste du texte';
$wb['also_used_on_txt'] = 'Également utilisé sur';
$wb['logo_variants_lead_txt'] = 'Deux emplacements, car les designs n’ont pas la même luminosité. Remplissez celui qui correspond au design que vous utilisez ; l’autre l’emprunte.';
$wb['favicon_lead_txt'] = 'Une icône pour tout le panneau, écran de connexion compris.';
$wb['accent_hex_hint_txt'] = 'Boutons, liens, marqueur actif de la barre latérale et courbe des graphiques. Le texte posé sur un aplat d’accentuation est choisi par contraste mesuré : un accent clair reçoit donc un texte foncé.';
$wb['show_ispconfig_credit_txt'] = 'Crédit de pied de page : ISPConfig';
$wb['show_theme_credit_txt'] = 'Crédit de pied de page : le design';
$wb['show_version_txt'] = 'Version du logiciel';
$wb['show_news_feed_txt'] = 'Fil d’actualités';
$wb['show_donation_dashlet_txt'] = 'Panneau de dons';
$wb['show_design_picker_txt'] = 'Sélecteur Design';
```

French — `fr.lng`:

```php
$wb['summary_design_txt'] = '%s actif';
$wb['summary_marks_none_txt'] = 'Aucune marque';
$wb['summary_marks_one_txt'] = '%d marque';
$wb['summary_marks_txt'] = '%d marques';
$wb['summary_favicon_txt'] = 'Favicon défini';
$wb['summary_favicon_none_txt'] = 'Aucun favicon';
```

Italian — `it_customizer.lng`:

```php
$wb['preview_panel_txt'] = 'Pannello';
$wb['hint_more_txt'] = 'Altre informazioni su %s';
$wb['drop_hint_txt'] = 'Scegli un file o trascinalo qui.';
$wb['accent_short_txt'] = 'Accento';
$wb['rail_short_txt'] = 'Barra laterale';
$wb['rail_light_short_txt'] = 'Barra laterale chiara';
$wb['contrast_short_txt'] = 'contrasto del testo';
$wb['also_used_on_txt'] = 'Usato anche in';
$wb['logo_variants_lead_txt'] = 'Due caselle, perché i design non concordano sulla luminosità. Compila quella che corrisponde al design che usi; l’altra la prende in prestito.';
$wb['favicon_lead_txt'] = 'Un’icona per tutto il pannello, schermata di accesso inclusa.';
$wb['accent_hex_hint_txt'] = 'Pulsanti, link, il marcatore attivo della barra laterale e la linea del grafico. Il testo stampato su un accento pieno è scelto in base al contrasto misurato, quindi un accento chiaro riceve testo scuro.';
$wb['show_ispconfig_credit_txt'] = 'Credito nel piè di pagina: ISPConfig';
$wb['show_theme_credit_txt'] = 'Credito nel piè di pagina: il design';
$wb['show_version_txt'] = 'Versione del software';
$wb['show_news_feed_txt'] = 'Feed di notizie';
$wb['show_donation_dashlet_txt'] = 'Riquadro delle donazioni';
$wb['show_design_picker_txt'] = 'Selettore Design';
```

Italian — `it.lng`:

```php
$wb['summary_design_txt'] = '%s attivo';
$wb['summary_marks_none_txt'] = 'Nessun logo';
$wb['summary_marks_one_txt'] = '%d logo';
$wb['summary_marks_txt'] = '%d loghi';
$wb['summary_favicon_txt'] = 'Favicon impostata';
$wb['summary_favicon_none_txt'] = 'Nessuna favicon';
```

Dutch — `nl_customizer.lng`:

```php
$wb['preview_panel_txt'] = 'Paneel';
$wb['hint_more_txt'] = 'Meer over %s';
$wb['drop_hint_txt'] = 'Kies een bestand of sleep het hierheen.';
$wb['accent_short_txt'] = 'Accent';
$wb['rail_short_txt'] = 'Zijbalk';
$wb['rail_light_short_txt'] = 'Lichte zijbalk';
$wb['contrast_short_txt'] = 'tekstcontrast';
$wb['also_used_on_txt'] = 'Ook gebruikt op';
$wb['logo_variants_lead_txt'] = 'Twee vakken, omdat ontwerpen het oneens zijn over helderheid. Vul het vak dat bij uw ontwerp past; het andere leent het.';
$wb['favicon_lead_txt'] = 'Eén icoon voor het hele paneel, inclusief het aanmeldscherm.';
$wb['accent_hex_hint_txt'] = 'Knoppen, links, de actieve markering in de zijbalk en de grafieklijn. De tekst op een gevuld accent wordt op gemeten contrast gekozen, dus een licht accent krijgt donkere tekst.';
$wb['show_ispconfig_credit_txt'] = 'Voettekstvermelding: ISPConfig';
$wb['show_theme_credit_txt'] = 'Voettekstvermelding: het ontwerp';
$wb['show_version_txt'] = 'Softwareversie';
$wb['show_news_feed_txt'] = 'Nieuwsfeed';
$wb['show_donation_dashlet_txt'] = 'Donatievak';
$wb['show_design_picker_txt'] = 'Design-keuzelijst';
```

Dutch — `nl.lng`:

```php
$wb['summary_design_txt'] = '%s actief';
$wb['summary_marks_none_txt'] = 'Geen logo’s';
$wb['summary_marks_one_txt'] = '%d logo';
$wb['summary_marks_txt'] = '%d logo’s';
$wb['summary_favicon_txt'] = 'Favicon ingesteld';
$wb['summary_favicon_none_txt'] = 'Geen favicon';
```

Portuguese — `pt_customizer.lng`:

```php
$wb['preview_panel_txt'] = 'Painel';
$wb['hint_more_txt'] = 'Mais sobre %s';
$wb['drop_hint_txt'] = 'Escolha um ficheiro ou largue-o aqui.';
$wb['accent_short_txt'] = 'Destaque';
$wb['rail_short_txt'] = 'Barra lateral';
$wb['rail_light_short_txt'] = 'Barra lateral clara';
$wb['contrast_short_txt'] = 'contraste do texto';
$wb['also_used_on_txt'] = 'Também usado em';
$wb['logo_variants_lead_txt'] = 'Duas ranhuras, porque os designs não concordam quanto ao brilho. Preencha a que corresponde ao design que usa; a outra empresta-a.';
$wb['favicon_lead_txt'] = 'Um ícone para todo o painel, incluindo o ecrã de início de sessão.';
$wb['accent_hex_hint_txt'] = 'Botões, ligações, o marcador ativo da barra lateral e a linha do gráfico. O texto impresso sobre um destaque preenchido é escolhido por contraste medido, pelo que um destaque claro recebe texto escuro.';
$wb['show_ispconfig_credit_txt'] = 'Crédito do rodapé: ISPConfig';
$wb['show_theme_credit_txt'] = 'Crédito do rodapé: o design';
$wb['show_version_txt'] = 'Versão do software';
$wb['show_news_feed_txt'] = 'Feed de notícias';
$wb['show_donation_dashlet_txt'] = 'Painel de donativos';
$wb['show_design_picker_txt'] = 'Seletor Design';
```

Portuguese — `pt.lng`:

```php
$wb['summary_design_txt'] = '%s ativo';
$wb['summary_marks_none_txt'] = 'Sem logótipos';
$wb['summary_marks_one_txt'] = '%d logótipo';
$wb['summary_marks_txt'] = '%d logótipos';
$wb['summary_favicon_txt'] = 'Favicon definido';
$wb['summary_favicon_none_txt'] = 'Sem favicon';
```

- [ ] **Step 6: Widen `lang_check.php`'s attribute-bound key list**

In `.github/scripts/lang_check.php`, replace the docblock and array immediately above `check_no_html_hostile_chars()` (the block that begins "customizer_edit.htm interpolates exactly these nine tform-wordbook keys") with:

```php
/**
 * customizer_edit.htm interpolates exactly these tform-wordbook keys into
 * DOUBLE-QUOTED HTML attributes with no escaping at the call site. A value
 * containing '"' breaks out of the attribute; a value containing '<' opens a
 * tag inside it. Neither is stoppable once the string is in the template, so it
 * is enforced here, at the only point every translation passes through before
 * it ships.
 *
 * Three groups:
 *
 *   - data attributes on #nz-brandpage: preview_failed_txt,
 *     rail_hex_light_inherited_txt.
 *   - aria-label on a control: the three file inputs (logo_txt,
 *     logo_on_dark_txt, favicon_txt) and the four colour pickers
 *     (accent_hex_txt, rail_hex_txt, rail_hex_light_txt, login_bg_txt).
 *   - aria-label on a "?" disclosure. Each one is hint_more_txt with the label
 *     of the thing it explains substituted into it (publish_hint_labels() in
 *     customizer_edit.php), so BOTH halves land in the attribute and both are
 *     listed: hint_more_txt itself, and the fifteen labels it is filled with.
 *
 * Scoped to these keys only — other wordbook values (hint text, error messages)
 * legitimately quote UI labels, e.g. 'click "Upload logo"', and are never
 * placed inside an attribute.
 */
$HTML_ATTR_WB_KEYS = array(
    'preview_failed_txt', 'rail_hex_light_inherited_txt',
    'logo_txt', 'logo_on_dark_txt', 'favicon_txt',
    'accent_hex_txt', 'rail_hex_txt', 'rail_hex_light_txt', 'login_bg_txt',
    'hint_more_txt',
    'identity_head_txt', 'logo_on_light_head_txt', 'logo_url_txt',
    'logo_on_dark_head_txt', 'logo_url_on_dark_txt', 'placement_head_txt',
    'favicon_head_txt', 'favicon_url_txt',
    'show_design_picker_txt', 'show_version_txt', 'show_news_feed_txt',
    'show_donation_dashlet_txt', 'show_theme_credit_txt',
);
```

(`accent_hex_txt` and `rail_hex_light_txt` are already in the list from the picker group and are not repeated; they carry a disclosure as well.)

- [ ] **Step 7: Verify**

Run:
```bash
php .github/scripts/lang_check.php
find interface/web/customizer/lib -name '*.lng' -print0 | while IFS= read -r -d '' f; do php -l "$f" >/dev/null || echo "LINT FAIL $f"; done
grep -c "^\$wb\[" interface/web/customizer/lib/lang/en_customizer.lng
grep -rn "Show the" interface/web/customizer/lib/lang/*_customizer.lng | grep "show_.*_txt'\] =" || echo "no 'Show the' prefixes left on the six switch labels"
```
Expected: `tform wordbook: 7 file(s) match en_customizer.lng (100 keys)` (89 today plus eleven), `module wordbook: 7 file(s) match en.lng (16 keys)` (10 today plus six), `nav wordbook: …`, `language files OK`; no `LINT FAIL`; the `grep -c` prints 100; the last line prints the "no 'Show the' prefixes" message.

- [ ] **Step 8: Commit**

```bash
git add interface/web/customizer/lib/lang .github/scripts/lang_check.php
git commit -m "Wordbook for the Branding page redesign: the proof's legend, the disclosures, and shorter switch labels"
```

---

## Task 2: The status facts — one builder in the model, one additive key in the payload

**Files:**
- Modify: `interface/web/customizer/lib/preview.inc.php` (a new function after `customizer_preview_colour()`; `customizer_preview_payload()`'s docblock and body)
- Modify: `interface/web/customizer/preview.php` (the `$texts` array)
- Modify: `tests/brand/probe_module.php` (a new block before "one resolver signature across all three copies")

**Interfaces:**
- Consumes: the six module wordbook keys from Task 1.
- Produces: `customizer_brand_summary($design, $resolved, $favicon, $texts)` returning `array('design' => string, 'marks' => string, 'favicon' => string)`; `$texts` keys are `design`, `marks_none`, `marks_one`, `marks`, `favicon`, `favicon_none`. `customizer_preview_payload()` gains a fourth top-level key, `summary`, holding exactly that array, and six more `$texts` keys: `summary_design`, `summary_marks_none`, `summary_marks_one`, `summary_marks`, `summary_favicon`, `summary_favicon_none`. Task 3 calls the builder directly; Task 5 reads `data.summary` in the browser.

**Background the implementer needs:**

The legend under the proof prints three facts: which design is active, how many marks are supplied, and whether a favicon is set. Two of the three change when the operator uploads or removes an image, and an upload does **not** reload the page — the uploader replaces three slots in place. So the facts have to be reachable from the preview payload as well as from the page render, which is why the builder is a shared function and why the payload grows a key. The growth is additive and carries no new input: the endpoint reads nothing extra, writes nothing, and the three strings are wordbook text.

"A mark" means a variant with artwork of its **own**. `customizer_logo_resolve()` reports that as `from === want`; a slot that is borrowing the other variant already says so in the borrowed-variant note under its block, and counting it here would tell an operator with one logo that they have two.

- [ ] **Step 1: Write the failing test**

In `tests/brand/probe_module.php`, immediately before the comment block `/* ---- one resolver signature across all three copies ---- */`, insert:

```php
/* ---- the three status facts under the proof ------------------------------
 * The legend prints them on page load AND after every upload, and an upload
 * does not reload the page — so the same builder has to serve the page render
 * and the preview payload, or the two would word the same fact differently.
 */
t_ok('customizer_brand_summary() exists', function_exists('customizer_brand_summary'));
if (function_exists('customizer_brand_summary')) {
    $facts_txt = array('design' => '%s active', 'marks_none' => 'No marks',
                       'marks_one' => '%d mark', 'marks' => '%d marks',
                       'favicon' => 'Favicon set', 'favicon_none' => 'No favicon');
    $png  = 'data:image/png;base64,iVBORw0KGgo=';
    $png2 = 'data:image/png;base64,iVBORw0KGgoAAA=';

    $both = customizer_logo_resolve(array('custom_logo' => $png, 'logo_on_dark' => $png2));
    $one  = customizer_logo_resolve(array('custom_logo' => $png));
    $none = customizer_logo_resolve(array());
    $fav  = customizer_favicon_resolve(array('favicon_url' => '/themes/custom/favicon.svg'));
    $nofav = customizer_favicon_resolve(array());

    $s = customizer_brand_summary('clarity', $both, $fav, $facts_txt);
    t_eq('the active design is named, capitalised', $s['design'], 'Clarity active');
    t_eq('two supplied marks count as two', $s['marks'], '2 marks');
    t_eq('a favicon is reported set', $s['favicon'], 'Favicon set');

    //* A slot BORROWING the other variant is not a second mark: the borrowed
    //* note under the block already says so, and counting it would tell an
    //* operator with one logo that they have two.
    $s = customizer_brand_summary('classic', $one, $nofav, $facts_txt);
    t_eq('one supplied mark is singular', $s['marks'], '1 mark');
    t_eq('...and does not become two through the fallback', strpos($s['marks'], '2'), false);
    t_eq('no favicon is reported as such', $s['favicon'], 'No favicon');
    t_eq('the design name follows the design asked about', $s['design'], 'Classic active');

    $s = customizer_brand_summary('', $none, $nofav, $facts_txt);
    t_eq('nothing supplied says so in words, not as a zero', $s['marks'], 'No marks');
    t_eq('an unknown design says nothing at all', $s['design'], '');

    //* A wordbook is a file translators edit. A missing key must not fatal, and
    //* a stray '%' must not either — which is why this is str_replace and not
    //* sprintf, whose ValueError on PHP 8 would take the admin page with it.
    $s = customizer_brand_summary('clarity', $both, $fav, array());
    t_eq('a missing text says nothing rather than printing a key', $s['marks'], '');
    $s = customizer_brand_summary('clarity', $both, $fav,
        array_merge($facts_txt, array('marks' => '%d marks (50% of the pair)')));
    t_eq('a stray percent sign is printed, not fatal', $s['marks'], '2 marks (50% of the pair)');

    $s = customizer_brand_summary('clarity', null, null, $facts_txt);
    t_eq('a non-array resolve is "nothing supplied"', $s['marks'], 'No marks');
    t_eq('...and a non-array favicon is "not set"', $s['favicon'], 'No favicon');
}
```

and, inside the existing `if (function_exists('customizer_preview_payload'))` block, immediately before the closing `}` of that block, insert:

```php
    //* The facts ride the payload as well, because an upload refreshes the page
    //* through this endpoint and never reloads it. Additive: nothing else in
    //* the contract moved, which is what preview.php's shape probe assumes.
    $ptexts = array_merge($texts, array(
        'summary_design' => '%s active', 'summary_marks_none' => 'No marks',
        'summary_marks_one' => '%d mark', 'summary_marks' => '%d marks',
        'summary_favicon' => 'Favicon set', 'summary_favicon_none' => 'No favicon'));
    $ps = customizer_preview_payload(array('favicon_url' => '/themes/custom/favicon.svg'),
        $png, array(), array('clarity'), array(), $ptexts);
    t_ok('the payload carries the summary', isset($ps['summary']));
    t_eq('...naming the leading design', $ps['summary']['design'], 'Clarity active');
    t_eq('...counting the marks it resolved', $ps['summary']['marks'], '1 mark');
    t_eq('...and reporting the favicon', $ps['summary']['favicon'], 'Favicon set');

    //* The design comes from the LIST the caller passed, in its order, because
    //* customizer_installed_designs() puts the active design first and that is
    //* the one the operator is looking at.
    $ps2 = customizer_preview_payload(array(), '', array(), array('classic', 'clarity'),
        array(), $ptexts);
    t_eq('the leading design is the one named', $ps2['summary']['design'], 'Classic active');
    $ps3 = customizer_preview_payload(array(), '', array(), array(), array(), $ptexts);
    t_eq('no design installed names none', $ps3['summary']['design'], '');

    //* The four blocks that were there before are still there, unmoved.
    t_ok('the previous payload keys are unchanged',
        isset($ps['previews'], $ps['surfaces'], $ps['colours'])
        && isset($ps['previews']['used_logo'], $ps['previews']['used_logo_more'],
                 $ps['previews']['used_logo_on_dark'], $ps['previews']['used_logo_on_dark_more'],
                 $ps['previews']['used_favicon']));
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `php tests/brand/probe_module.php 2>&1 | grep -E '^FAIL|^# '`
Expected: `FAIL customizer_brand_summary() exists` and a non-zero failure count on the last line.

- [ ] **Step 3: Write the builder**

In `interface/web/customizer/lib/preview.inc.php`, immediately after `customizer_preview_colour()` and before the `customizer_preview_payload()` docblock, add:

```php
/**
 * The three status facts the Branding page prints in the legend under its
 * proof: which design is active, how many marks are supplied, and whether a
 * favicon is set.
 *
 * $design    the design whose chrome the page is describing — the first entry
 *            of customizer_installed_designs(), which is the active one. '' if
 *            nothing is known, in which case the fact is omitted rather than
 *            guessed.
 * $resolved  one customizer_logo_resolve() result (both variants)
 * $favicon   one customizer_favicon_resolve() result
 * $texts     already-localised, keyed 'design', 'marks_none', 'marks_one',
 *            'marks', 'favicon', 'favicon_none' — this file holds no wordbook
 *            of its own, exactly like every other renderer here
 *
 * Returns array('design' => …, 'marks' => …, 'favicon' => …), each a plain
 * string ready to print. An empty string means "there is nothing to say",
 * which the page renders as no fact at all (.nz-facts > span:empty).
 *
 * It lives here rather than in customizer_edit.php because it has two callers
 * that must not word the same fact differently: the page render, and
 * customizer_preview_payload(), which is what refreshes the legend after an
 * upload — an upload replaces three slots in place and never reloads the page.
 *
 * A mark counts when the variant has artwork of its OWN, which
 * customizer_logo_resolve() reports as 'from' equalling the variant name. A
 * slot that is BORROWING the other one already says so in the borrowed-variant
 * note under its block, and counting it here would tell an operator with one
 * logo that they have two.
 *
 * str_replace(), never sprintf(). These are values a translator edits, and one
 * carrying a stray '%' — '50% of the pair', a typo — makes sprintf() raise a
 * ValueError on PHP 8. That is a fatal on an admin page, caused by a wordbook
 * file, and there is nothing here worth that risk: a placeholder that is
 * missing simply leaves the sentence without its number.
 */
function customizer_brand_summary($design, $resolved, $favicon, $texts) {
    if(!is_array($texts))    $texts    = array();
    if(!is_array($resolved)) $resolved = array();
    $txt = function($key) use ($texts) {
        return (isset($texts[$key]) && is_string($texts[$key])) ? $texts[$key] : '';
    };

    $design_fact = '';
    if(is_string($design) && $design !== '' && $txt('design') !== '') {
        //* ucfirst, matching customizer_logo_surfaces_all()'s own captions, so
        //* the design is spelled the same way everywhere on this page.
        $design_fact = str_replace('%s', ucfirst($design), $txt('design'));
    }

    $marks = 0;
    foreach(array('on_light', 'on_dark') as $variant) {
        if(isset($resolved[$variant]['from']) && $resolved[$variant]['from'] === $variant) $marks++;
    }
    if($marks === 0) {
        $marks_fact = $txt('marks_none');
    } elseif($marks === 1) {
        $marks_fact = str_replace('%d', '1', $txt('marks_one'));
    } else {
        $marks_fact = str_replace('%d', (string)$marks, $txt('marks'));
    }

    $has_favicon = (is_array($favicon) && isset($favicon['src']) && $favicon['src'] !== '');

    return array(
        'design'  => $design_fact,
        'marks'   => $marks_fact,
        'favicon' => $has_favicon ? $txt('favicon') : $txt('favicon_none'),
    );
}
```

- [ ] **Step 4: Add the payload's `summary` block**

In the same file, in `customizer_preview_payload()`:

(a) extend the docblock's `$texts` line and its `Returns:` list. Replace

```php
 * $texts            the five already-localised preview strings, keyed
 *                   'no_logo', 'fallback_from_dark', 'fallback_from_light',
 *                   'no_favicon', 'favicon_url_wins'
```

with

```php
 * $texts            the already-localised preview strings, keyed 'no_logo',
 *                   'fallback_from_dark', 'fallback_from_light', 'no_favicon',
 *                   'favicon_url_wins', and the six the legend's status facts
 *                   need: 'summary_design', 'summary_marks_none',
 *                   'summary_marks_one', 'summary_marks', 'summary_favicon',
 *                   'summary_favicon_none'
```

and add, immediately after the `'colours'  => …` line of the `Returns:` list:

```php
 *   'summary'  => customizer_brand_summary()'s three facts, so the legend under
 *                 the proof can be refreshed after an upload. An upload
 *                 replaces three slots in place and never reloads the page, so
 *                 without this the mark count would go stale until the operator
 *                 navigated away and back.
```

(b) hoist the favicon resolve so the summary and the preview row describe one object rather than two. Replace the `'used_favicon' => customizer_favicon_preview_html(` block's inline `customizer_favicon_resolve(...)` argument with `$favicon`, and add this immediately after the `$surfaces = customizer_logo_surfaces_all($designs, $branding, $labels);` line:

```php
    //* Resolved once and used twice — the preview row and the status fact must
    //* not be able to disagree about whether an icon is set.
    $favicon = customizer_favicon_resolve(array(
        'favicon'     => isset($branding['favicon']) ? $branding['favicon'] : '',
        'favicon_url' => isset($branding['favicon_url']) ? $branding['favicon_url'] : '',
    ));
```

so the previews entry becomes:

```php
            'used_favicon' => customizer_favicon_preview_html($favicon,
                $txt('no_favicon'), $txt('favicon_url_wins')),
```

(c) add the fourth top-level key, immediately after the `'colours'  => array(…),` block and before the closing `);`:

```php
        //* The legend's three status facts. Additive: every key above kept its
        //* place, which is what preview.php's shape probe and the page's own
        //* reader assume. The active design leads $designs
        //* (customizer_installed_designs orders it), so it is the one named.
        'summary'  => customizer_brand_summary(
            //* is_array first: isset($x[0]) is TRUE for a non-empty STRING and
            //* would hand the summary a single character.
            (is_array($designs) && isset($designs[0]) && is_string($designs[0])) ? $designs[0] : '',
            $resolved, $favicon,
            array(
                'design'       => $txt('summary_design'),
                'marks_none'   => $txt('summary_marks_none'),
                'marks_one'    => $txt('summary_marks_one'),
                'marks'        => $txt('summary_marks'),
                'favicon'      => $txt('summary_favicon'),
                'favicon_none' => $txt('summary_favicon_none'),
            )
        ),
```

- [ ] **Step 5: Pass the six strings from the endpoint**

In `interface/web/customizer/preview.php`, extend the `$texts` array literal so it reads:

```php
    array(
        'no_logo'              => $app->lng('no_logo_set_txt'),
        'fallback_from_dark'   => $app->lng('logo_fallback_from_dark_txt'),
        'fallback_from_light'  => $app->lng('logo_fallback_from_light_txt'),
        'no_favicon'           => $app->lng('no_favicon_set_txt'),
        'favicon_url_wins'     => $app->lng('favicon_url_wins_txt'),
        //* The legend's status facts. They live in the MODULE wordbook rather
        //* than the tform one for the same reason the five above do: this
        //* endpoint has no tform and cannot address that wordbook by key.
        'summary_design'       => $app->lng('summary_design_txt'),
        'summary_marks_none'   => $app->lng('summary_marks_none_txt'),
        'summary_marks_one'    => $app->lng('summary_marks_one_txt'),
        'summary_marks'        => $app->lng('summary_marks_txt'),
        'summary_favicon'      => $app->lng('summary_favicon_txt'),
        'summary_favicon_none' => $app->lng('summary_favicon_none_txt'),
    )
```

- [ ] **Step 6: Run the suite**

Run:
```bash
php -l interface/web/customizer/lib/preview.inc.php
php -l interface/web/customizer/preview.php
php tests/brand/run.php
```
Expected: no syntax errors; `brand suite passed`, with the `== module ==` section showing the new `ok` lines and `== preview ==` still green (the endpoint gained no function, no SQL verb and no `$_SESSION` write, which is what that probe asserts).

- [ ] **Step 7: Commit**

```bash
git add interface/web/customizer/lib/preview.inc.php \
        interface/web/customizer/preview.php \
        tests/brand/probe_module.php
git commit -m "Build the Branding legend's three status facts once, and carry them in the preview payload"
```

---

## Task 3: The page publishes the facts and the fifteen disclosure names

**Files:**
- Modify: `interface/web/customizer/customizer_edit.php` (`onShowEnd()`, `render_image_previews()`, two new private methods)

**Interfaces:**
- Consumes: `customizer_brand_summary()` from Task 2; `hint_more_txt` and the six module keys from Task 1.
- Produces: the tmpl vars `summary_fact_design`, `summary_fact_marks`, `summary_fact_favicon`, and fifteen named `hint_<label key>` — `hint_identity_head_txt`, `hint_logo_on_light_head_txt`, `hint_logo_url_txt`, `hint_logo_on_dark_head_txt`, `hint_logo_url_on_dark_txt`, `hint_placement_head_txt`, `hint_favicon_head_txt`, `hint_favicon_url_txt`, `hint_accent_hex_txt`, `hint_rail_hex_light_txt`, `hint_show_design_picker_txt`, `hint_show_version_txt`, `hint_show_news_feed_txt`, `hint_show_donation_dashlet_txt`, `hint_show_theme_credit_txt`. Task 4's markup reads all eighteen.

**Background the implementer needs:**

`render_image_previews()` already resolves both logo variants and the favicon and already asks `customizer_installed_designs()` which designs to describe — but it does all three inline. Hoist the two it needs to name (`$designs` and the favicon resolve) into variables and hand them to the new method; do not recompute either, or the fact and the swatch beside it could disagree.

`logo_upload.php` renders this same template with no tform and none of these vars set. vlibTemplate removes an unknown `{tmpl_var}`, so that response carries empty `aria-label`s and three empty fact spans — harmless, because the uploader's driver scrapes only `#OKMsg`/`#errorMsg` and the three mark slots out of it, and the CSS collapses an empty fact.

- [ ] **Step 1: Write the failing test**

`customizer_edit.php` cannot be executed without a database, so it is asserted the way `probe_module.php` already asserts `onBeforeUpdate`'s guard: against the source. Add this to `tests/brand/probe_module.php`, immediately before the final `t_done();`:

```php
/* ---- the page publishes what its template asks for -----------------------
 * customizer_edit.php cannot be run here (no mysqli, no session), so the two
 * publishers are asserted against the source, the same way onBeforeUpdate's
 * guard is above. What is checked is that the page hands the template every
 * name the template interpolates — a missing one renders as an empty attribute
 * with nothing anywhere to say why.
 */
if ($edit_src !== false) {
    t_ok('the page publishes the three status facts',
        strpos($edit_src, "setVar('summary_fact_design'") !== false
        && strpos($edit_src, "setVar('summary_fact_marks'") !== false
        && strpos($edit_src, "setVar('summary_fact_favicon'") !== false);
    t_ok('...through the shared builder, not a second copy of the wording',
        strpos($edit_src, 'customizer_brand_summary(') !== false);
    t_ok('the disclosure names are built from hint_more_txt',
        strpos($edit_src, "lng('hint_more_txt')") !== false);
    t_ok('...with str_replace, because sprintf on a translated string can fatal',
        strpos($edit_src, "str_replace('%s'") !== false
        && strpos($edit_src, 'sprintf(') === false);
    foreach (array('identity_head_txt', 'logo_on_light_head_txt', 'logo_url_txt',
                   'logo_on_dark_head_txt', 'logo_url_on_dark_txt', 'placement_head_txt',
                   'favicon_head_txt', 'favicon_url_txt', 'accent_hex_txt',
                   'rail_hex_light_txt', 'show_design_picker_txt', 'show_version_txt',
                   'show_news_feed_txt', 'show_donation_dashlet_txt',
                   'show_theme_credit_txt') as $label) {
        t_ok("the '?' on $label is named", strpos($edit_src, "'" . $label . "'") !== false);
    }
}
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `php tests/brand/probe_module.php 2>&1 | grep -E '^FAIL'`
Expected: at least `FAIL the page publishes the three status facts` and `FAIL the disclosure names are built from hint_more_txt`.

- [ ] **Step 3: Publish the facts**

In `interface/web/customizer/customizer_edit.php`, inside `render_image_previews()`:

(a) hoist the design list. Replace

```php
        $surfaces = customizer_logo_surfaces_all(
            customizer_installed_designs(isset($_SESSION['s']['theme']) ? $_SESSION['s']['theme'] : ''),
            $branding,
            array('nav' => $app->lng('surface_nav_txt'), 'login' => $app->lng('surface_login_txt'))
        );
```

with

```php
        //* Hoisted because the legend's first status fact names the design the
        //* operator is looking at, and customizer_installed_designs() is what
        //* decides which that is — it puts the active design first.
        $designs  = customizer_installed_designs(isset($_SESSION['s']['theme']) ? $_SESSION['s']['theme'] : '');
        $surfaces = customizer_logo_surfaces_all(
            $designs,
            $branding,
            array('nav' => $app->lng('surface_nav_txt'), 'login' => $app->lng('surface_login_txt'))
        );
```

(b) hoist the favicon resolve and publish the facts. Replace the final `$app->tpl->setVar('used_favicon', …);` statement with

```php
        $favicon = customizer_favicon_resolve(array(
            'favicon'     => isset($branding['favicon']) ? $branding['favicon'] : '',
            'favicon_url' => isset($branding['favicon_url']) ? $branding['favicon_url'] : '',
        ));
        $app->tpl->setVar('used_favicon', customizer_favicon_preview_html(
            $favicon,
            $app->lng('no_favicon_set_txt'),
            $app->lng('favicon_url_wins_txt')
        ));

        $this->publish_brand_summary($designs, $resolved, $favicon);
```

(c) add the method, after `render_image_previews()` and before `publish_field_error_map()`:

```php
    /**
     * The three status facts in the legend under the proof.
     *
     * The wording is customizer_brand_summary()'s, not this page's, because the
     * legend is refreshed after an upload through customizer/preview.php — an
     * upload replaces three slots in place and never reloads the page — and two
     * copies of the sentence would eventually disagree. $app->lng(), not
     * $app->tform->lng(): these six live in the MODULE wordbook so the endpoint,
     * which has no tform at all, can reach them.
     *
     * Every value is already resolved by the caller and passed in rather than
     * re-read here: the fact and the swatch beside it describe one object, and
     * a second read is how they would come to describe two.
     */
    private function publish_brand_summary($designs, $resolved, $favicon) {
        global $app;
        $facts = customizer_brand_summary(
            //* is_array first, for the same reason the payload does it:
            //* isset($x[0]) is TRUE for a non-empty string.
            (is_array($designs) && isset($designs[0]) && is_string($designs[0])) ? $designs[0] : '',
            $resolved, $favicon,
            array(
                'design'       => $app->lng('summary_design_txt'),
                'marks_none'   => $app->lng('summary_marks_none_txt'),
                'marks_one'    => $app->lng('summary_marks_one_txt'),
                'marks'        => $app->lng('summary_marks_txt'),
                'favicon'      => $app->lng('summary_favicon_txt'),
                'favicon_none' => $app->lng('summary_favicon_none_txt'),
            )
        );
        $app->tpl->setVar('summary_fact_design',  $facts['design']);
        $app->tpl->setVar('summary_fact_marks',   $facts['marks']);
        $app->tpl->setVar('summary_fact_favicon', $facts['favicon']);
    }
```

- [ ] **Step 4: Publish the fifteen disclosure names**

Add this method immediately after `publish_brand_summary()`:

```php
    /**
     * The accessible name of every "?" disclosure on the page.
     *
     * Each "?" is a <summary>, which announces as a button; without a name it
     * announces as the character "?" and nothing else. The name is
     * hint_more_txt ("More about %s") with the label of the thing the
     * disclosure explains substituted in, so a screen-reader user hears "More
     * about Placement" rather than fifteen identical buttons.
     *
     * It is built here rather than in the template because vlibTemplate cannot
     * compose two strings, and it is str_replace() rather than sprintf()
     * because both halves are values a translator edits: one carrying a stray
     * '%' would make sprintf() raise a ValueError on PHP 8, which on this page
     * is a fatal caused by a wordbook file. A translation that drops the
     * placeholder simply loses the label and keeps the sentence.
     *
     * BOTH halves land inside a double-quoted aria-label with no escaping at
     * the call site, so hint_more_txt and all fifteen labels are on
     * lang_check.php's $HTML_ATTR_WB_KEYS list — see the docblock there.
     *
     * logo_upload.php renders this same template with no tform, so none of
     * these vars is set there and vlibTemplate removes them. That response
     * carries empty aria-labels; harmless, because the uploader's driver reads
     * only #OKMsg/#errorMsg and the three mark slots out of it.
     */
    private function publish_hint_labels() {
        global $app;
        $pattern = $app->tform->lng('hint_more_txt');
        $labels = array(
            'identity_head_txt', 'logo_on_light_head_txt', 'logo_url_txt',
            'logo_on_dark_head_txt', 'logo_url_on_dark_txt', 'placement_head_txt',
            'favicon_head_txt', 'favicon_url_txt', 'accent_hex_txt',
            'rail_hex_light_txt', 'show_design_picker_txt', 'show_version_txt',
            'show_news_feed_txt', 'show_donation_dashlet_txt', 'show_theme_credit_txt',
        );
        foreach($labels as $key) {
            $app->tpl->setVar('hint_' . $key, str_replace('%s', $app->tform->lng($key), $pattern));
        }
    }
```

and call it from `onShowEnd()`, so that method reads:

```php
    function onShowEnd() {
        global $app;
        $this->render_image_previews();
        $this->publish_field_error_map();
        $this->publish_hint_labels();
```

(the rest of `onShowEnd()` is unchanged).

- [ ] **Step 5: Run the checks**

Run:
```bash
php -l interface/web/customizer/customizer_edit.php
php tests/brand/run.php
```
Expected: no syntax error; `brand suite passed` with the fifteen new `ok the '?' on …` lines.

- [ ] **Step 6: Commit**

```bash
git add interface/web/customizer/customizer_edit.php tests/brand/probe_module.php
git commit -m "Publish the Branding legend's facts and every disclosure's accessible name"
```

---

## Task 4: The page — markup and the module stylesheet

**Files:**
- Create: `tests/brand/probe_page.php`
- Modify: `tests/brand/run.php` (register it)
- Modify: `interface/web/customizer/templates/customizer_edit.htm` (the `<style>` block and everything between `<div id="nz-brandpage" …>` and `</div><!-- /#nz-brandpage -->`)

**Interfaces:**
- Consumes: every tmpl var from Tasks 1 and 3.
- Produces: the markup Task 5's JavaScript binds — the ids `nz-save-flash`, `nz-fact-design`, `nz-fact-marks`, `nz-fact-favicon`, `nz-accent-ratio`, `nz-login-ratio`, `nz-drop-line-logo`, `nz-drop-line-logo-on-dark`, `nz-drop-line-favicon`; the classes `nz-prev-login-brand`, `nz-brandchip-hex` (each carrying `data-hex-field`), `nz-drop`, `nz-drop-line`; and `tests/brand/probe_page.php`, which Task 5 extends.

**Background the implementer needs:**

Read `mockup/branding/DESIGN-NOTES.md` first — it is the spec, and it explains every decision below that looks arbitrary. Then `mockup/branding/branding.html` (the approved markup) and `mockup/branding/branding.css` (the approved stylesheet, written under clarity's tokens only).

**Four things ship differently from the mockup, and each is deliberate:**

1. **The drop zone.** The mockup styled the file input itself and let the browser's stock "Choose file" button stand inside the zone. Here the zone is one control: a `<label class="nz-drop">` carrying one quiet line, with the real `<input type="file">` clipped inside it by `.nz-vh`. The input is never `display:none` and never `hidden` — it keeps its place in the tab order and its accessible name, and a label already opens the picker for the control it wraps, with no script. It has **no `for` attribute**: the input is nested, which associates them implicitly, and carrying both is the documented way to make some browsers open the picker twice. The drop half needs three lines of JavaScript (Task 5) because a label is not a native drop target the way a file input is. The one focus rule on this page exists for this control and is explained in the CSS.
2. **The mini panel's nav items stay blank bars, and the accent rule stays on the rail's left edge.** The mockup's items carry real labels ("Home", "Sites", "Email") and move the marker onto the active item. There is no source of *translated* nav labels on this page — they belong to core's own navigation, which this wordbook cannot reach — and inventing English literals inside a page that ships in seven locales is worse than a blank bar. With blank items the shipped comment's reasoning still holds exactly: a 2px stub beside one blank row reads as a text cursor. Same for the topbar and the content card: they are drawn as shapes, with the one real string the page already had (`btn_save_txt`) on the accent button.
3. **Copy is the shipped wording.** Every existing hint value moves into a `<details>` verbatim; the formats-and-size sentence stays inside the hint it is already part of rather than being duplicated into the zone. Three short lead strings and the accent hint are the only new copy (Task 1).
4. **"Also used on" is outside the slot it labels.** The mockup put it inside `#used_logo_more`, which would delete it on the first preview refresh (the slot's `innerHTML` is replaced wholesale) and would defeat the `:empty` rule that collapses the slot. It sits above the slot instead, hidden with `:has(+ .nz-upload-more:empty)`.

- [ ] **Step 1: Write the failing test**

Create `tests/brand/probe_page.php`:

```php
<?php
/**
 * interface/web/customizer/templates/customizer_edit.htm — the page's SHAPE.
 *
 * The template cannot be rendered here: vlibTemplate needs the framework, and
 * the values come from a database this environment has no driver for. What can
 * be asserted, and is worth more than a render, is the CONTRACT between the
 * three halves of the file — the markup, the style block and the inline script
 * — because every one of those is a promise the other two depend on and none of
 * them is checked by php -l.
 *
 * Parsed as TEXT and never executed, the same rule .github/scripts/lang_check.php
 * follows: this file is markup with PHP-less template tags in it, and there is
 * nothing here to run.
 *
 * Run through tests/brand/run.php.
 */

require_once __DIR__ . '/harness.php';

$path = __DIR__ . '/../../interface/web/customizer/templates/customizer_edit.htm';
$src  = @file_get_contents($path);
t_ok('the template exists and is readable', $src !== false);
if ($src === false) t_done();

/* ---- the JS contract: every hook the inline script binds ------------------
 * The script reaches for these by name. A rename in the markup is invisible to
 * every other check in this repository and shows up as one silently dead
 * feature on an admin page.
 */
$ids = array(
    // carried over, unchanged
    'nz-msg-slot', 'nz-brandpage', 'company_name',
    'accent_hex', 'accent_hex_pick', 'rail_hex', 'rail_hex_pick',
    'rail_hex_light', 'rail_hex_light_pick', 'login_bg', 'login_bg_pick',
    'logo_url', 'logo_url_on_dark', 'favicon_url',
    'logo_variant_nav', 'logo_variant_login',
    'used_logo', 'used_logo_more', 'used_logo_on_dark', 'used_logo_on_dark_more',
    'used_favicon', 'file', 'file_on_dark', 'file_favicon',
    'nz-logo-upload', 'nz-logo-upload-on-dark', 'nz-favicon-upload',
    'nz-logo-remove', 'nz-logo-remove-on-dark', 'nz-favicon-remove',
    'nz-rail-ratio', 'nz-rail-light-ratio', 'nz-rail-light-inherited',
    // new with the redesign
    'nz-accent-ratio', 'nz-login-ratio', 'nz-save-flash',
    'nz-fact-design', 'nz-fact-marks', 'nz-fact-favicon',
    'nz-drop-line-logo', 'nz-drop-line-logo-on-dark', 'nz-drop-line-favicon',
);
foreach ($ids as $id) {
    t_ok("id=\"$id\" is in the markup", strpos($src, 'id="' . $id . '"') !== false);
}

$classes = array(
    'nz-prev-rail', 'nz-prev-rail-light', 'nz-prev-login', 'nz-prev-accent',
    'nz-prev-accent-rule', 'nz-prev-name', 'nz-prev-nav-brand',
    'nz-prev-nav-brand-light', 'nz-prev-light-pane', 'nz-prevframe',
    'nz-brandpreview', 'nz-actions', 'nz-hexfield', 'nz-hexpick',
    // new with the redesign
    'nz-prev-login-brand', 'nz-brandchip-hex', 'nz-drop', 'nz-drop-line',
);
foreach ($classes as $cls) {
    t_ok("class $cls is in the markup", strpos($src, $cls) !== false);
}

//* The named fields. tform posts by NAME, so a rename here is a field that
//* silently stops being saved.
foreach (array('company_name', 'logo_url', 'logo_url_on_dark', 'favicon_url',
               'logo_variant_nav', 'logo_variant_login', 'accent_hex', 'rail_hex',
               'rail_hex_light', 'login_bg', 'custom_login_text', 'custom_login_link',
               'file', 'file_on_dark', 'file_favicon', 'id') as $name) {
    t_ok("name=\"$name\" is in the markup", strpos($src, 'name="' . $name . '"') !== false);
}

//* The tform button contract, and the three data attributes the script reads
//* off the page host.
t_ok('Save still submits pageForm to this module',
    strpos($src, 'data-submit-form="pageForm"') !== false
    && strpos($src, 'data-form-action="customizer/customizer_edit.php"') !== false);
t_ok('Cancel still loads the dashboard',
    strpos($src, 'data-load-content="dashboard/dashboard.php"') !== false);
foreach (array('data-field-errors', 'data-preview-failed', 'data-rail-light-inherited') as $attr) {
    t_ok("$attr is carried on the page host", strpos($src, $attr) !== false);
}

/* ---- every field row keeps the shape markField() walks ------------------- */
t_ok('the 3/9 row shape survives',
    substr_count($src, 'class="col-sm-3 control-label"') >= 12
    && substr_count($src, 'class="col-sm-9"') >= 12);

/* ---- DOM order: the pulls come before the chips --------------------------
 * paintSurfacePane() uses querySelector — first match only. If a colour chip
 * in the legend came first, the server's measured backdrop and ink would be
 * painted onto a 56px swatch and the pull would keep whatever it had.
 */
$first_rail  = strpos($src, 'nz-prevnav-rail nz-prev-rail"');
$chip_rail   = strpos($src, 'nz-brandchip-swatch nz-prev-rail"');
t_ok('the panel pull is the first .nz-prev-rail',
    $first_rail !== false && $chip_rail !== false && $first_rail < $chip_rail,
    "pull at $first_rail, chip at $chip_rail");
$first_light = strpos($src, 'nz-prevnav-rail nz-prev-rail-light"');
$chip_light  = strpos($src, 'nz-brandchip-swatch nz-prev-rail-light"');
t_ok('the light-rail sample is the first .nz-prev-rail-light',
    $first_light !== false && $chip_light !== false && $first_light < $chip_light,
    "sample at $first_light, chip at $chip_light");
t_ok('the login pull is the only .nz-prev-login', substr_count($src, 'nz-prev-login"') === 1);

/* ---- the collapsing slots ------------------------------------------------
 * :empty is the mechanism, so a slot must hold its tmpl_var and NOTHING else —
 * one space of whitespace defeats the selector and opens a blank row under the
 * block.
 */
foreach (array('used_logo_more', 'used_logo_on_dark_more') as $slot) {
    t_ok("#$slot holds its tmpl_var and nothing else",
        preg_match('#<div class="nz-upload-more" id="' . $slot . '">\{tmpl_var name=\'' . $slot . '\'\}</div>#', $src) === 1);
}
//* ...and the label above it is OUTSIDE the slot, or the first preview refresh
//* would replace the slot's innerHTML and delete it.
t_ok('the "Also used on" label is outside the slot it names',
    strpos($src, "<p class=\"nz-surfacelabel\">{tmpl_var name='also_used_on_txt'}</p>") !== false);

/* ---- the light pane ships hidden ----------------------------------------- */
t_ok('the light-rail pane ships hidden for the script to reveal',
    preg_match('/nz-prev-light-pane[^>]*\shidden/', $src) === 1);

/* ---- the style block, and the house rules it must obey ------------------- */
$a = strpos($src, '<style>');
$b = strpos($src, '</style>');
t_ok('there is exactly one style block', $a !== false && $b !== false
    && substr_count($src, '<style>') === 1);
$style = ($a !== false && $b !== false) ? substr($src, $a, $b - $a) : '';
//* Comments out, first and always. This block's own comments discuss the rules
//* below them — "rather than with !important", "a design that sets
//* `figure { display: block }`" — so a scan that read them would fail on the
//* documentation of the very rule it enforces, and the brace pair inside that
//* sentence would parse as a rule with an unprefixed selector.
$style = preg_replace('#/\*.*?\*/#s', '', $style);

t_ok('no rule uses !important', strpos($style, '!important') === false);
//* Exactly one focus rule, and it is the drop zone's — the control it belongs
//* to is deliberately clipped, so the design's own indicator would paint
//* off-screen. Every other control on this page keeps whatever indicator the
//* active design gives it, which is why any second occurrence is a failure.
t_ok('there is exactly one focus rule', substr_count($style, ':focus') === 1);
t_ok('...and it is the drop zone\'s', strpos($style, '.nz-drop:focus-within') !== false);
t_ok('nothing sets an outline', strpos($style, 'outline') === false);

//* Containment goes on OUR wrapper. #pageContent is core's element and giving
//* it a containment context changes the layout of every other module's page.
t_ok('the container is #nz-brandpage', strpos($style, '#nz-brandpage { container-type: inline-size') !== false);
t_ok('#pageContent is never given containment',
    preg_match('/#pageContent[^{]*\{[^}]*container-type/', $style) !== 1);
t_ok('there is a viewport fallback for @container',
    strpos($style, '@supports not (container-type: inline-size)') !== false);

//* Every selector is prefixed, so the page beats a design's own rules on
//* specificity rather than with !important.
preg_match_all('/([^{}@]+)\{([^{}]*)\}/', $style, $rules, PREG_SET_ORDER);
t_ok('the style block parses into rules', count($rules) > 60, count($rules) . ' rules');
$unprefixed = array();
foreach ($rules as $r) {
    foreach (explode(',', $r[1]) as $sel) {
        $sel = trim($sel);
        if ($sel === '' || $sel[0] === '@' || strpos($sel, 'from') === 0 || strpos($sel, 'to') === 0) continue;
        if (strpos($sel, '#nz-brandpage') === 0 || strpos($sel, '#pageContent') === 0) continue;
        $unprefixed[] = $sel;
    }
}
t_eq('every selector is prefixed #nz-brandpage or #pageContent', $unprefixed, array());

//* Every colour is a token chain: phosphor's, then clarity's, then a
//* design-neutral literal. A raw hex would look right under the design it was
//* written for and wrong under the other two. The single exception is the
//* light-mode rail sample's ground, which DEPICTS a light colour mode and
//* would otherwise be painted dark by a dark design's own page token.
$raw = array();
foreach ($rules as $r) {
    foreach (explode(';', $r[2]) as $decl) {
        if (!preg_match('/#[0-9A-Fa-f]{6}\b/', $decl)) continue;
        if (strpos($decl, 'var(--nz-') !== false) continue;      // a fallback argument
        if (strpos($r[1], 'nz-prev-lightbody') !== false) continue;
        $raw[] = trim($r[1]) . ' {' . trim($decl) . ' }';
    }
}
t_eq('no colour is named outside a token chain', $raw, array());
t_ok('...and every chain leads with phosphor\'s token',
    substr_count($style, 'var(--nz-') === substr_count($style, ', var(--nz-'),
    substr_count($style, 'var(--nz-') . ' clarity tokens, '
        . substr_count($style, ', var(--nz-') . ' of them inside a --pz- chain');

t_done();
```

Register it in `tests/brand/run.php` by extending the `$checks` array:

```php
$checks = array(
    'tform'   => __DIR__ . '/probe_tform.php',
    'preview' => __DIR__ . '/probe_preview.php',
    'page'    => __DIR__ . '/probe_page.php',
);
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `php tests/brand/probe_page.php 2>&1 | grep -cE '^FAIL'`
Expected: a count well above 20 — every new id, the DOM-order assertions, the "Also used on" label, the focus rule and the token-chain assertions all fail against today's template.

- [ ] **Step 3: Generate the new style block**

The approved stylesheet is `mockup/branding/branding.css`, written under clarity's tokens because that is what the mockup renders in. Converting it by hand is 103 token chains of opportunity to make a typo, so it is converted by script. Write this to `/tmp/nz2pz.py`:

```python
#!/usr/bin/env python3
"""Build the Branding page's <style> block from the approved mockup CSS.

Three transformations, in order:

  1. Drop the mockup's own banner comment (it talks about the mockup) and put
     the shipping header in its place.
  2. Cut the three rules that do not ship: the mockup's drop zone (the zone is
     one control here, not a styled file input), its ::file-selector-button
     (the input is clipped, so that pseudo never renders) and .nz-drop-note
     and .nz-prevcard-head (both name classes the shipped markup does not use).
  3. Prepend phosphor's token to every clarity chain, so every colour reads
     var(--pz-Y, var(--nz-X, <literal>)). A fallback can itself contain commas
     (rgba(...)), so the closing paren is found by scanning, never by a regex.

Then splice the result into the template between <style> and </style>.

Run from the repository root:  python3 /tmp/nz2pz.py
"""
import re
import sys
from pathlib import Path

CSS = Path("mockup/branding/branding.css")
TPL = Path("interface/web/customizer/templates/customizer_edit.htm")

# clarity token -> phosphor token. Every --nz- name that appears in branding.css
# is here; the converter exits rather than guess at one that is not.
MAP = {
    "accent": "accent", "action": "accent",
    "border": "edge", "border-soft": "edge-soft", "border-strong": "edge-input",
    "card": "glass", "ctl-h": "ctl-h",
    "danger": "danger", "danger-text": "danger",
    "ease": "ease", "font-mono": "mono", "fs-cardtitle": "fs-cardtitle",
    "page": "ground", "quick": "fast",
    "radius-card": "radius-card", "radius-ctl": "radius-ctl", "radius-pill": "radius-pill",
    "raised": "raised", "text": "ink-base", "text-disabled": "ink-faint",
    "text-heading": "ink-bright", "text-muted": "ink-muted",
    "text-on-action": "on-accent", "text-secondary": "ink-sub",
    "well": "raised",
}

HEADER = """
  /* This page has no stylesheet of its own, by design. Every colour below is
     var(--pz-…, var(--nz-…, <stock fallback>)) — phosphor's token, then
     clarity's, then a neutral literal — so the page inherits whichever design is
     active and looks deliberate under a design that has neither. No rule here
     may name a colour that belongs to one design.

     Everything is prefixed #nz-brandpage or #pageContent so it beats a design's
     own rules on specificity rather than with !important. Exactly one rule
     styles focus — the drop zone's, for the reason written beside it — and
     nothing else on this page may, because every design ships a focus indicator
     and this page must not replace it.

     Generated from mockup/branding/branding.css, the approved design, by the
     conversion described in docs/superpowers/plans/2026-09-07-branding-page-design.md.
     MATERIALS. Four, deliberately distinct, so the page does not read as one
     card kit repeated five times:
       1. the proof     — a recessed viewport on the page ground, hairline mount
       2. the cards     — flat card fill, hairline edge, no shadow
       3. the drop zone — a control: dashed edge on the input well, control radius
       4. the switch list and the save bar — no box at all, hairlines only */
"""

EXTRA = """
/* ============================================================
 * 6. WHAT SHIPS DIFFERENTLY FROM THE MOCKUP
 * ============================================================ */

/* The drop zone is ONE control here rather than a styled file input: a <label>
   carrying one quiet line, with the real <input type="file"> clipped inside it
   (.nz-vh). The input is never display:none and never hidden — it keeps its
   place in the tab order and its accessible name, and a label already opens the
   picker for the control it wraps, with no script at all. The drop half needs
   three lines of JS, beside the uploader, because a label is not a native drop
   target the way a file input is. */
#nz-brandpage .nz-drop {
  position: relative; display: block; box-sizing: border-box;
  width: 100%; padding: 14px;
  border: 1px dashed var(--pz-edge-input, var(--nz-border-strong, rgba(128, 128, 128, 0.45)));
  border-radius: var(--pz-radius-ctl, var(--nz-radius-ctl, 4px));
  background: var(--pz-raised, var(--nz-well, rgba(128, 128, 128, 0.06)));
  font-size: 12.5px; font-weight: 400; line-height: 1.6;
  color: var(--pz-ink-muted, var(--nz-text-muted, #6c757d));
  cursor: pointer;
}
/* A dropped file's name can be longer than the zone. */
#nz-brandpage .nz-drop-line { display: block; overflow-wrap: anywhere; }
#nz-brandpage .nz-drop:hover { border-color: var(--pz-accent, var(--nz-accent, #0065AB)); }
/* The ONE focus rule on this page, and the reason it is here rather than left
   to the design: the control that would carry the design's own indicator is
   deliberately clipped, so a keyboard user reaching the file input would have
   nothing on screen telling them where they are. This re-exposes an indicator
   on the element they can actually see. It replaces nothing — no other control
   on this page has a focus rule, and none may acquire one. */
#nz-brandpage .nz-drop:focus-within {
  border-style: solid;
  border-color: var(--pz-accent, var(--nz-accent, #0065AB));
}
/* Only the two buttons live in this row now: the chosen filename is shown in
   the zone itself, where the operator dropped it. */
#nz-brandpage .nz-drop-row { justify-content: flex-end; }

/* "Also used on" labels a slot that collapses when it has nothing to say, so
   the label has to go with it. It cannot live INSIDE the slot — the preview
   endpoint replaces that slot's innerHTML wholesale and would delete it on the
   first refresh, and its presence would defeat :empty. :has() is the same
   mechanism both designs' brand.php already use for the design-picker rule;
   where it is unsupported the label stands over an empty row, which is untidy
   rather than broken. */
#nz-brandpage .nz-surfacelabel:has(+ .nz-upload-more:empty) { display: none; }

/* A fact with nothing to say prints nothing — including the hairline that
   separates it from the next one. logo_upload.php renders this same template
   with no summary set at all, and three empty dividers would be the only thing
   its response showed. */
#nz-brandpage .nz-facts > span:empty { display: none; }

/* One disclosure carries two paragraphs: the placement hints, which say the
   same thing about two surfaces. */
#nz-brandpage .nz-hint > p + p { margin-top: 4px; }

/* previewNote() appends the refresh-failed line to .nz-brandpreview, which is
   the hero — give it the hero's own gutter instead of the mount's edge. */
#nz-brandpage .nz-hero > #nz-preview-note { margin: 0; padding: 0 18px 14px; }
"""

OPEN = re.compile(r"var\(--nz-([a-z0-9-]+)")


def cut(src, start, end, label):
    """Remove start..end inclusive, loudly."""
    try:
        a = src.index(start)
        b = src.index(end, a) + len(end)
    except ValueError:
        sys.exit("cannot find the %s block to cut — has branding.css changed?" % label)
    return src[:a] + src[b:]


def chains(src):
    out, i = [], 0
    while True:
        m = OPEN.search(src, i)
        if m is None:
            out.append(src[i:])
            return "".join(out)
        name = m.group(1)
        if name not in MAP:
            sys.exit("no phosphor token mapped for --nz-%s" % name)
        depth, j = 0, m.start() + 3          # at the '(' of var(
        while j < len(src):
            if src[j] == "(":
                depth += 1
            elif src[j] == ")":
                depth -= 1
                if depth == 0:
                    break
            j += 1
        else:
            sys.exit("unbalanced var() at offset %d" % m.start())
        out.append(src[i:m.start()])
        out.append("var(--pz-%s, %s)" % (MAP[name], src[m.start():j + 1]))
        i = j + 1


src = CSS.read_text(encoding="utf-8")
src = src[src.index("*/") + 2:].lstrip("\n")          # drop the mockup's banner
src = cut(src, "/* The drop zone IS the file input",
          "#nz-brandpage .nz-drop:hover { border-color: var(--nz-accent, #0065AB); }\n", "drop zone")
src = cut(src, "#nz-brandpage .nz-drop-note {", "}\n", ".nz-drop-note")
src = cut(src, "#nz-brandpage .nz-prevcard-head {", "}\n", ".nz-prevcard-head")
# EXTRA is APPENDED after the conversion, never put through it: it is already
# written as full --pz-/--nz- chains, and converting it again would wrap each
# inner var(--nz-…) in a second --pz- layer.
css = chains(src) + EXTRA

# Two spaces of indent, matching the block this replaces.
body = HEADER + "\n" + "\n".join(("  " + ln) if ln.strip() else "" for ln in css.split("\n"))

tpl = TPL.read_text(encoding="utf-8")
a = tpl.index("<style>")
b = tpl.index("</style>")
TPL.write_text(tpl[:a] + "<style>\n" + body.strip("\n") + "\n" + tpl[b:], encoding="utf-8")
print("style block rebuilt: %d token chains" % css.count("var(--pz-"))
```

Run: `python3 /tmp/nz2pz.py`
Expected: `style block rebuilt: 101 token chains` — 95 surviving the three cuts, plus the six in `EXTRA`. A different number means `mockup/branding/branding.css` has moved since this plan was written; read the diff before accepting it.

- [ ] **Step 4: Replace the page markup**

Replace everything in `interface/web/customizer/templates/customizer_edit.htm` from the line `<div class="nz-brandgrid">` down to and including the line `</div><!-- /.nz-brandgrid -->` with the block below. The `<div id="nz-brandpage" …>` line above it and the `</div><!-- /#nz-brandpage -->` line below it stay exactly as they are, as does everything above the page host (the page header, `#nz-msg-slot`, the style block and the two `upload_msg` / `upload_error` conditionals) and the whole `<script>` below it.

```html
<!-- ============================================================
     THE PROOF — the hero. This is the only page in the panel where the thing
     being edited and the thing being looked at are the same thing, so the
     picture leads and the controls follow: a press proof carries the pull at
     the top and its colour bar along the trailing edge.

     .nz-brandpreview stays on this element because previewNote() appends the
     refresh-failed line to it, and the aria-labelledby/h2 pair is what names
     the region. It is deliberately NOT an ARIA live region: it changes on
     every keystroke, and announcing that is worse than silence.
     ============================================================ -->
<section class="nz-hero nz-brandpreview" aria-labelledby="nz-preview-head">
  <div class="nz-hero-head">
    <h2 id="nz-preview-head">{tmpl_var name='preview_head_txt'}</h2>
    <p class="nz-hero-hint">{tmpl_var name='preview_hint_txt'}</p>
  </div>

  <div class="nz-proof">
    <!-- The panel pull. The nav items are blank bars and the chrome is drawn
         as shapes on purpose: the only strings available to this page in
         seven locales are its own wordbook's, and inventing English nav
         labels for a localised panel would be worse than a placeholder. That
         is also why the accent rule marks the RAIL's left edge rather than
         the active item — a 2px stub beside one blank row reads as a text
         cursor sitting in the pane. -->
    <figure class="nz-prevpane nz-pane-panel">
      <div class="nz-prevframe">
        <div class="nz-prevtab">
          <span class="nz-vh">{tmpl_var name='preview_tab_txt'}</span>
          <span class="nz-prevtab-chip"><span class="nz-prevtab-icon nz-prev-accent"></span><span class="nz-prev-name"></span></span>
        </div>
        <div class="nz-prevnav">
          <div class="nz-prevnav-rail nz-prev-rail">
            <span class="nz-prev-accent-rule" aria-hidden="true"></span>
            <!-- The brand slot: the resolved nav mark when the server says
                 there is one, the panel name when there is not. -->
            <div class="nz-prevnav-logo nz-prev-nav-brand"><span class="nz-prev-name"></span></div>
            <div class="nz-prevnav-item is-active">&nbsp;</div>
            <div class="nz-prevnav-item">&nbsp;</div>
            <div class="nz-prevnav-item">&nbsp;</div>
          </div>
          <div class="nz-prevnav-body">
            <div class="nz-prevtop">
              <span class="nz-prevsearch">&nbsp;</span>
              <span class="nz-prevuser">&nbsp;</span>
            </div>
            <div class="nz-prevbody">
              <div class="nz-prevcard">
                <div class="nz-prevbar w40"></div>
                <div class="nz-prevbar w60"></div>
                <div class="nz-prevbar w40"></div>
                <span class="nz-prevbtn nz-prev-accent">{tmpl_var name='btn_save_txt'}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
      <figcaption>{tmpl_var name='preview_panel_txt'}</figcaption>
    </figure>

    <figure class="nz-prevpane nz-pane-login">
      <div class="nz-prevframe">
        <div class="nz-prevlogin nz-prev-login">
          <div class="nz-prevlogin-name nz-prev-login-brand"><span class="nz-prev-name"></span></div>
          <div class="nz-prevlogin-card">
            <div class="nz-prevfield"></div>
            <div class="nz-prevfield"></div>
            <span class="nz-prevbtn nz-prev-accent">&nbsp;</span>
          </div>
        </div>
      </div>
      <figcaption>{tmpl_var name='preview_login_txt'}</figcaption>
    </figure>
  </div>

  <!-- The legend: the proof's colour bar, on the trailing edge where a press
       sheet carries one. It sits AFTER the proof in the DOM as well as under
       it, which is also what keeps paintSurfacePane()'s first-match painting
       on the pulls rather than on a 56px chip. -->
  <div class="nz-legend">
    <div class="nz-legend-cell nz-legend-marks">
      <div class="nz-marks-pair">
        <div class="nz-upload-mark" id="used_logo">{tmpl_var name='used_logo'}</div>
        <div class="nz-upload-mark" id="used_logo_on_dark">{tmpl_var name='used_logo_on_dark'}</div>
      </div>
    </div>

    <div class="nz-legend-cell nz-legend-name">
      <p class="nz-legend-brandname nz-prev-name"></p>
      <!-- Three independent facts, separated by hairlines rather than by
           middle dots, and each empty until there is something to say — see
           .nz-facts > span:empty. -->
      <p class="nz-facts"><span id="nz-fact-design">{tmpl_var name='summary_fact_design'}</span><span id="nz-fact-marks">{tmpl_var name='summary_fact_marks'}</span><span id="nz-fact-favicon">{tmpl_var name='summary_fact_favicon'}</span></p>
    </div>

    <!-- Shown only when rail_hex_light carries a value: an operator who leaves
         it blank has one sidebar, and a second sample would say otherwise. It
         ships hidden and paintSurfaces() reveals it. -->
    <figure class="nz-prevpane nz-prev-light-pane nz-legend-cell" hidden>
      <div class="nz-prevframe">
        <div class="nz-railsample">
          <div class="nz-prevnav-rail nz-prev-rail-light">
            <div class="nz-prevnav-logo nz-prev-nav-brand-light"><span class="nz-prev-name"></span></div>
            <div class="nz-prevnav-item is-active">&nbsp;</div>
            <div class="nz-prevnav-item">&nbsp;</div>
          </div>
          <div class="nz-prev-lightbody"></div>
        </div>
      </div>
      <figcaption>{tmpl_var name='rail_hex_light_txt'}</figcaption>
    </figure>

    <!-- The colour bar. The chip is pure colour and the value sits under it in
         page ink, so nothing here depends on measuring an ink for a swatch.
         data-hex-field names the field the caption mirrors. -->
    <div class="nz-legend-cell nz-legend-colours">
      <div class="nz-brandchips">
        <span class="nz-brandchip">
          <span class="nz-brandchip-swatch nz-prev-accent"></span>
          <span class="nz-brandchip-hex" data-hex-field="accent_hex">&mdash;</span>
          <span class="nz-brandchip-name">{tmpl_var name='accent_short_txt'}</span>
        </span>
        <span class="nz-brandchip">
          <span class="nz-brandchip-swatch nz-prev-rail"></span>
          <span class="nz-brandchip-hex" data-hex-field="rail_hex">&mdash;</span>
          <span class="nz-brandchip-name">{tmpl_var name='rail_short_txt'}</span>
        </span>
        <span class="nz-brandchip">
          <span class="nz-brandchip-swatch nz-prev-rail-light"></span>
          <span class="nz-brandchip-hex" data-hex-field="rail_hex_light">&mdash;</span>
          <span class="nz-brandchip-name">{tmpl_var name='rail_light_short_txt'}</span>
        </span>
      </div>
    </div>
  </div>
</section>

<!-- ============================================================
     THE SETTINGS
     ============================================================ -->
<div class="nz-cards">

<!-- IDENTITY — what the panel is called and what it is marked with. The two
     variants and the placement block are <h3> sub-blocks rather than nested
     fieldsets so a screen-reader user can jump between them by heading while
     the group stays one landmark. h3 and not h4: the proof's heading is the
     only h2 on this page, so an h4 here would skip a level. -->
<fieldset class="nz-card">
  <legend>{tmpl_var name='identity_head_txt'}</legend>

  <div class="form-group">
    <label for="company_name" class="col-sm-3 control-label">{tmpl_var name='company_name_txt'}</label>
    <div class="col-sm-9">
      <input type="text" name="company_name" id="company_name" value="{tmpl_var name='company_name'}" class="form-control" />
      <p class="help-block">{tmpl_var name='company_name_hint_txt'}</p>
    </div>
  </div>

  <!-- The pair intro. Every paragraph of inline help on this page is now
       either one muted line in the flow or a native <details> "?" beside the
       thing it explains; no help-block paragraph survives in the reading
       flow. The "?" is a <summary> with an aria-label, so it announces as a
       button with a purpose rather than as a question mark. -->
  <div class="nz-introrow">
    <p class="help-block">{tmpl_var name='logo_variants_lead_txt'}</p>
    <details class="nz-hint"><summary aria-label="{tmpl_var name='hint_identity_head_txt'}">?</summary>
      <p>{tmpl_var name='logo_variants_hint_txt'}</p>
    </details>
  </div>

  <!-- Named by the BACKGROUND each mark sits on, because that is what the
       operator can look at and check. Which artwork is "light enough" is a
       judgement; which of their designs has a dark header is a fact. -->
  <div class="nz-upload">
    <div class="nz-upload-head">
      <h3>{tmpl_var name='logo_on_light_head_txt'}</h3>
      <details class="nz-hint"><summary aria-label="{tmpl_var name='hint_logo_on_light_head_txt'}">?</summary>
        <p>{tmpl_var name='logo_hint_txt'}</p>
      </details>
    </div>
    <!-- The zone is the label; the input is clipped inside it, focusable and
         named. No for= attribute: the input is nested, which associates them,
         and carrying both makes some browsers open the picker twice.

         Not wired to the stock iframe uploader (no data-submit-form /
         data-form-upload, no formbutton-success): the page-render CSRF token
         it depends on can be silently lost to ISPConfig's lock-free session
         store, and the stock Enter-key handler must not trigger uploads. The
         fetch flow in the script below mints its own token at click time. -->
    <label class="nz-drop">
      <span class="nz-drop-line" id="nz-drop-line-logo">{tmpl_var name='drop_hint_txt'}</span>
      <input name="file" id="file" size="30" type="file" class="fileUpload nz-vh" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml,.svg" aria-label="{tmpl_var name='logo_txt'}" />
    </label>
    <div class="nz-drop-row">
      <button class="btn btn-default" type="button" id="nz-logo-upload">{tmpl_var name='upload_txt'}</button>
      <a href="javascript:void(0);" class="btn btn-default" id="nz-logo-remove">{tmpl_var name='logo_remove_txt'}</a>
    </div>
    <!-- Every surface AFTER the first. The label is OUTSIDE the slot: the
         preview endpoint replaces the slot's innerHTML wholesale and would
         delete a label inside it, and :empty — the rule that collapses the
         slot when there is nothing to say — is defeated by any content at
         all. Which is also why the slot is written on ONE line with nothing
         in it but the tmpl_var. -->
    <p class="nz-surfacelabel">{tmpl_var name='also_used_on_txt'}</p>
    <div class="nz-upload-more" id="used_logo_more">{tmpl_var name='used_logo_more'}</div>
    <div class="form-group">
      <label for="logo_url" class="col-sm-3 control-label">{tmpl_var name='logo_url_txt'}</label>
      <details class="nz-hint"><summary aria-label="{tmpl_var name='hint_logo_url_txt'}">?</summary>
        <p>{tmpl_var name='logo_url_hint_txt'}</p>
      </details>
      <div class="col-sm-9">
        <input type="text" name="logo_url" id="logo_url" value="{tmpl_var name='logo_url'}" class="form-control" placeholder="/themes/custom/logo.svg" />
      </div>
    </div>
  </div>

  <div class="nz-upload">
    <div class="nz-upload-head">
      <h3>{tmpl_var name='logo_on_dark_head_txt'}</h3>
      <details class="nz-hint"><summary aria-label="{tmpl_var name='hint_logo_on_dark_head_txt'}">?</summary>
        <p>{tmpl_var name='logo_on_dark_hint_txt'}</p>
      </details>
    </div>
    <!-- The name is deliberately NOT "file", even though logo_upload.php reads
         $_FILES['file'] for both slots: the driver below builds its own
         FormData and appends the chosen file under the name 'file' itself, so
         this attribute never reaches the server. Distinct names keep three
         file inputs unambiguous in a form that also carries a native Save. -->
    <label class="nz-drop">
      <span class="nz-drop-line" id="nz-drop-line-logo-on-dark">{tmpl_var name='drop_hint_txt'}</span>
      <input name="file_on_dark" id="file_on_dark" size="30" type="file" class="fileUpload nz-vh" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml,.svg" aria-label="{tmpl_var name='logo_on_dark_txt'}" />
    </label>
    <div class="nz-drop-row">
      <button class="btn btn-default" type="button" id="nz-logo-upload-on-dark">{tmpl_var name='upload_txt'}</button>
      <a href="javascript:void(0);" class="btn btn-default" id="nz-logo-remove-on-dark">{tmpl_var name='logo_remove_txt'}</a>
    </div>
    <p class="nz-surfacelabel">{tmpl_var name='also_used_on_txt'}</p>
    <div class="nz-upload-more" id="used_logo_on_dark_more">{tmpl_var name='used_logo_on_dark_more'}</div>
    <div class="form-group">
      <label for="logo_url_on_dark" class="col-sm-3 control-label">{tmpl_var name='logo_url_on_dark_txt'}</label>
      <details class="nz-hint"><summary aria-label="{tmpl_var name='hint_logo_url_on_dark_txt'}">?</summary>
        <p>{tmpl_var name='logo_url_on_dark_hint_txt'}</p>
      </details>
      <div class="col-sm-9">
        <input type="text" name="logo_url_on_dark" id="logo_url_on_dark" value="{tmpl_var name='logo_url_on_dark'}" class="form-control" placeholder="/themes/custom/logo-on-dark.svg" />
      </div>
    </div>
  </div>

  <!-- Placement, not artwork: which of the two marks above each surface uses.
       It stays a third block AFTER both variants rather than moving inside
       either, because it is scoped by SURFACE and is a statement about the
       pair — nesting it under one mark would read as a property of that mark.
       The two hints say the same thing about two surfaces, so one disclosure
       carries both.

       Only the <option> list comes from tform (tform_base.inc.php:510 assigns
       nothing but <option> tags to the field's own tmpl_var); the <select>
       shell is written here, which is core's idiom too. Keep
       class="form-control": clarity styles body.nz select.form-control with
       its own arrow SVG, and a bare select falls through to a different,
       list-header-sized rule. logo_upload.php renders this same template with
       no tform at all, so there both tmpl_vars are unknown and vlibTemplate
       removes them — that response carries two empty <select>s, which is
       harmless and which nothing here may assume otherwise about. -->
  <div class="nz-upload">
    <div class="nz-upload-head">
      <h3>{tmpl_var name='placement_head_txt'}</h3>
      <details class="nz-hint"><summary aria-label="{tmpl_var name='hint_placement_head_txt'}">?</summary>
        <p>{tmpl_var name='logo_variant_nav_hint_txt'}</p>
        <p>{tmpl_var name='logo_variant_login_hint_txt'}</p>
      </details>
    </div>
    <div class="form-group">
      <label for="logo_variant_nav" class="col-sm-3 control-label">{tmpl_var name='logo_variant_nav_txt'}</label>
      <div class="col-sm-9">
        <select name="logo_variant_nav" id="logo_variant_nav" class="form-control">{tmpl_var name='logo_variant_nav'}</select>
      </div>
    </div>
    <div class="form-group">
      <label for="logo_variant_login" class="col-sm-3 control-label">{tmpl_var name='logo_variant_login_txt'}</label>
      <div class="col-sm-9">
        <select name="logo_variant_login" id="logo_variant_login" class="form-control">{tmpl_var name='logo_variant_login'}</select>
      </div>
    </div>
  </div>
</fieldset>

<div class="nz-col">

<!-- COLOUR — the four values every brand-aware design reads. Chip, value and
     measurement on one line: the ratio is the answer to the question the
     colour raises, so it is not a paragraph somewhere below it. All four
     readouts are the SERVER's measurement; this module has exactly one
     contrast implementation and it is in PHP. -->
<fieldset class="nz-card">
  <legend>{tmpl_var name='colour_head_txt'}</legend>

  <div class="form-group">
    <label for="accent_hex" class="col-sm-3 control-label">{tmpl_var name='accent_hex_txt'}</label>
    <details class="nz-hint"><summary aria-label="{tmpl_var name='hint_accent_hex_txt'}">?</summary>
      <p>{tmpl_var name='accent_hex_hint_txt'}</p>
    </details>
    <div class="col-sm-9">
      <div class="nz-colourrow">
        <input type="color" id="accent_hex_pick" value="#0065AB" class="nz-hexpick" aria-label="{tmpl_var name='accent_hex_txt'}" />
        <input type="text" name="accent_hex" id="accent_hex" value="{tmpl_var name='accent_hex'}" class="form-control nz-hexfield" placeholder="#0065AB" />
        <p class="nz-measure"><span id="nz-accent-ratio" class="nz-ratio">&mdash;</span> {tmpl_var name='contrast_short_txt'}</p>
      </div>
    </div>
  </div>

  <div class="form-group">
    <label for="rail_hex" class="col-sm-3 control-label">{tmpl_var name='rail_hex_txt'}</label>
    <div class="col-sm-9">
      <div class="nz-colourrow">
        <input type="color" id="rail_hex_pick" value="#01243D" class="nz-hexpick" aria-label="{tmpl_var name='rail_hex_txt'}" />
        <input type="text" name="rail_hex" id="rail_hex" value="{tmpl_var name='rail_hex'}" class="form-control nz-hexfield" placeholder="#01243D" />
        <p class="nz-measure"><span id="nz-rail-ratio" class="nz-ratio">&mdash;</span> {tmpl_var name='contrast_short_txt'}</p>
      </div>
    </div>
  </div>

  <div class="form-group">
    <label for="rail_hex_light" class="col-sm-3 control-label">{tmpl_var name='rail_hex_light_txt'}</label>
    <details class="nz-hint"><summary aria-label="{tmpl_var name='hint_rail_hex_light_txt'}">?</summary>
      <p>{tmpl_var name='rail_hex_light_hint_txt'}</p>
    </details>
    <div class="col-sm-9">
      <div class="nz-colourrow">
        <input type="color" id="rail_hex_light_pick" value="#E7EBF0" class="nz-hexpick" aria-label="{tmpl_var name='rail_hex_light_txt'}" />
        <input type="text" name="rail_hex_light" id="rail_hex_light" value="{tmpl_var name='rail_hex_light'}" class="form-control nz-hexfield" placeholder="#E7EBF0" />
        <!-- When the light rail is showing the RAIL's value because this field
             is empty, a note says so — without it the two readouts print the
             same number with nothing to explain why. The text is carried as a
             data attribute on #nz-brandpage and written with textContent: it
             is wordbook prose and has no business being parsed as markup on
             the page that shows it. -->
        <p class="nz-measure"><span id="nz-rail-light-ratio" class="nz-ratio">&mdash;</span> {tmpl_var name='contrast_short_txt'} <span id="nz-rail-light-inherited" class="nz-inherited"></span></p>
      </div>
    </div>
  </div>

  <div class="form-group">
    <label for="login_bg" class="col-sm-3 control-label">{tmpl_var name='login_bg_txt'}</label>
    <div class="col-sm-9">
      <div class="nz-colourrow">
        <input type="color" id="login_bg_pick" value="#17252B" class="nz-hexpick" aria-label="{tmpl_var name='login_bg_txt'}" />
        <input type="text" name="login_bg" id="login_bg" value="{tmpl_var name='login_bg'}" class="form-control nz-hexfield" placeholder="#17252B" />
        <p class="nz-measure"><span id="nz-login-ratio" class="nz-ratio">&mdash;</span> {tmpl_var name='contrast_short_txt'}</p>
      </div>
      <p class="help-block">{tmpl_var name='colour_hint_txt'}</p>
    </div>
  </div>
</fieldset>

<!-- THE FAVICON — the third brand image, and the only one with a single slot:
     a tab strip is a tab strip whatever the design's header looks like. It is
     its own card rather than a fifth block inside Identity because it has one
     slot and no light/dark pair, and because splitting it is what makes the
     two columns end level. -->
<fieldset class="nz-card">
  <legend>{tmpl_var name='favicon_head_txt'}</legend>
  <div class="nz-upload">
    <div class="nz-introrow">
      <p class="help-block">{tmpl_var name='favicon_lead_txt'}</p>
      <details class="nz-hint"><summary aria-label="{tmpl_var name='hint_favicon_head_txt'}">?</summary>
        <p>{tmpl_var name='favicon_intro_txt'}</p>
        <p>{tmpl_var name='favicon_hint_txt'}</p>
      </details>
    </div>
    <div class="nz-upload-more" id="used_favicon">{tmpl_var name='used_favicon'}</div>
    <label class="nz-drop">
      <span class="nz-drop-line" id="nz-drop-line-favicon">{tmpl_var name='drop_hint_txt'}</span>
      <input name="file_favicon" id="file_favicon" size="30" type="file" class="fileUpload nz-vh" accept="image/svg+xml,.svg,image/png,image/x-icon,image/vnd.microsoft.icon,.ico" aria-label="{tmpl_var name='favicon_txt'}" />
    </label>
    <div class="nz-drop-row">
      <button class="btn btn-default" type="button" id="nz-favicon-upload">{tmpl_var name='favicon_upload_txt'}</button>
      <a href="javascript:void(0);" class="btn btn-default" id="nz-favicon-remove">{tmpl_var name='logo_remove_txt'}</a>
    </div>
    <div class="form-group">
      <label for="favicon_url" class="col-sm-3 control-label">{tmpl_var name='favicon_url_txt'}</label>
      <details class="nz-hint"><summary aria-label="{tmpl_var name='hint_favicon_url_txt'}">?</summary>
        <p>{tmpl_var name='favicon_url_hint_txt'}</p>
      </details>
      <div class="col-sm-9">
        <input type="text" name="favicon_url" id="favicon_url" value="{tmpl_var name='favicon_url'}" class="form-control" placeholder="/themes/custom/favicon.svg" />
      </div>
    </div>
  </div>
</fieldset>

<fieldset class="nz-card">
  <legend>{tmpl_var name='login_head_txt'}</legend>
  <div class="form-group">
    <label for="custom_login_text" class="col-sm-3 control-label">{tmpl_var name='custom_login_text_txt'}</label>
    <div class="col-sm-9"><input type="text" name="custom_login_text" id="custom_login_text" value="{tmpl_var name='custom_login_text'}" class="form-control" /></div>
  </div>
  <div class="form-group">
    <label for="custom_login_link" class="col-sm-3 control-label">{tmpl_var name='custom_login_link_txt'}</label>
    <div class="col-sm-9"><input type="text" name="custom_login_link" id="custom_login_link" value="{tmpl_var name='custom_login_link'}" class="form-control" placeholder="https://" /></div>
  </div>
</fieldset>

</div><!-- /.nz-col -->

<!-- PANEL VISIBILITY — six switches over what the panel shows. A switch, not a
     checkbox: every one of these is on or off for the whole panel, and a
     checkbox in the middle of a nine-column well reads as one more field to
     fill in rather than as a thing that is currently on.

     tform emits a bare <input type="checkbox"> with an id of its own
     (tform_base.inc.php:543-545), so the track is the input itself
     (appearance: none) and the thumb is a SIBLING span — a pseudo-element on
     an input is not rendered at all in some engines and this control must not
     depend on one. Nothing in the switch rules touches focus: the input keeps
     whatever indicator the active design gives it. -->
<fieldset class="nz-card nz-card-wide">
  <legend>{tmpl_var name='visibility_head_txt'}</legend>
  <div class="nz-switchgrid">
    <div class="form-group">
      <label for="show_design_picker" class="col-sm-3 control-label">{tmpl_var name='show_design_picker_txt'}</label>
      <details class="nz-hint"><summary aria-label="{tmpl_var name='hint_show_design_picker_txt'}">?</summary>
        <p>{tmpl_var name='show_design_picker_hint_txt'}</p>
      </details>
      <div class="col-sm-9"><span class="nz-switch">{tmpl_var name='show_design_picker'}<span class="nz-switch-thumb" aria-hidden="true"></span></span></div>
    </div>
    <div class="form-group">
      <label for="show_version" class="col-sm-3 control-label">{tmpl_var name='show_version_txt'}</label>
      <details class="nz-hint"><summary aria-label="{tmpl_var name='hint_show_version_txt'}">?</summary>
        <p>{tmpl_var name='show_version_hint_txt'}</p>
      </details>
      <div class="col-sm-9"><span class="nz-switch">{tmpl_var name='show_version'}<span class="nz-switch-thumb" aria-hidden="true"></span></span></div>
    </div>
    <div class="form-group">
      <label for="show_news_feed" class="col-sm-3 control-label">{tmpl_var name='show_news_feed_txt'}</label>
      <details class="nz-hint"><summary aria-label="{tmpl_var name='hint_show_news_feed_txt'}">?</summary>
        <p>{tmpl_var name='show_news_feed_hint_txt'}</p>
      </details>
      <div class="col-sm-9"><span class="nz-switch">{tmpl_var name='show_news_feed'}<span class="nz-switch-thumb" aria-hidden="true"></span></span></div>
    </div>
    <div class="form-group">
      <label for="show_donation_dashlet" class="col-sm-3 control-label">{tmpl_var name='show_donation_dashlet_txt'}</label>
      <details class="nz-hint"><summary aria-label="{tmpl_var name='hint_show_donation_dashlet_txt'}">?</summary>
        <p>{tmpl_var name='show_donation_dashlet_hint_txt'}</p>
      </details>
      <div class="col-sm-9"><span class="nz-switch">{tmpl_var name='show_donation_dashlet'}<span class="nz-switch-thumb" aria-hidden="true"></span></span></div>
    </div>
    <div class="form-group">
      <label for="show_ispconfig_credit" class="col-sm-3 control-label">{tmpl_var name='show_ispconfig_credit_txt'}</label>
      <div class="col-sm-9"><span class="nz-switch">{tmpl_var name='show_ispconfig_credit'}<span class="nz-switch-thumb" aria-hidden="true"></span></span></div>
    </div>
    <div class="form-group">
      <label for="show_theme_credit" class="col-sm-3 control-label">{tmpl_var name='show_theme_credit_txt'}</label>
      <details class="nz-hint"><summary aria-label="{tmpl_var name='hint_show_theme_credit_txt'}">?</summary>
        <p>{tmpl_var name='credits_hint_txt'}</p>
      </details>
      <div class="col-sm-9"><span class="nz-switch">{tmpl_var name='show_theme_credit'}<span class="nz-switch-thumb" aria-hidden="true"></span></span></div>
    </div>
  </div>
</fieldset>

</div><!-- /.nz-cards -->

<input type="hidden" name="id" value="{tmpl_var name='id'}" />

<!-- The save bar. Sticky, so the one control an operator is looking for is
     where they left it, and the confirmation lands beside the button that
     caused it rather than a screen away — the script moves core's own
     post-save banner into #nz-save-flash. Primary outermost, matching the
     login card and every other form footer in the panel. -->
<div class="nz-actions">
  <div class="nz-saveflash" id="nz-save-flash"></div>
  <div class="nz-actions-btns">
    <button class="btn btn-default formbutton-success" type="button" data-submit-form="pageForm" data-form-action="customizer/customizer_edit.php">{tmpl_var name='btn_save_txt'}</button>
    <button class="btn btn-default formbutton-default" type="button" data-load-content="dashboard/dashboard.php">{tmpl_var name='btn_cancel_txt'}</button>
  </div>
</div>
```

- [ ] **Step 5: Run the probe**

Run:
```bash
php tests/brand/probe_page.php
php tests/brand/run.php
```
Expected: `probe_page.php` prints only `ok` lines and `# N passed, 0 failed`; `brand suite passed`. If `every selector is prefixed` or `no colour is named outside a token chain` fails, the failure message names the offending selector and declaration — fix it in the template's style block, not in `mockup/branding/branding.css`, which is the design record and stays as approved.

- [ ] **Step 6: Commit**

```bash
git add interface/web/customizer/templates/customizer_edit.htm \
        tests/brand/probe_page.php tests/brand/run.php
git commit -m "The Branding page as a proof sheet: the hero, its legend, five cards and a sticky save bar"
```

---

## Task 5: The page script — the four live hooks and the drop zones

**Files:**
- Modify: `tests/brand/probe_page.php` (a new section)
- Modify: `interface/web/customizer/templates/customizer_edit.htm` (the inline `<script>`, above the frozen region only)

**Interfaces:**
- Consumes: the markup from Task 4; `data.summary` from Task 2.
- Produces: nothing later tasks read — this is the last code task.

**Background the implementer needs:**

Six additions, none of them inside the frozen region. `DESIGN-NOTES.md` lists four of them as optional hooks that each degrade to today's behaviour; they are all wired here, so all four contrast readouts are live, the hex captions follow the fields, the login pull shows the mark the server resolved for it, and the save confirmation lands beside the Save button.

The one place `DESIGN-NOTES.md` is not followed literally: it proposes adding a line to the existing `#nz-msg-slot` relocation observer to move `.alert-notification` into `#nz-save-flash`. That observer is inside the frozen region and stays byte-identical. It does not need changing anyway — the uploader's `#OKMsg`/`#errorMsg` arrive *later*, which is why they need an observer, while core's post-save banner is rendered with the page by `tabbed_form.tpl.htm` and is already in the document when the script runs. One move at init does it.

`input.files = event.dataTransfer.files` is the assignment that makes a dropped file the input's file; it is supported in every browser this panel runs in and is wrapped in a `try` regardless, because a refusal must leave the operator with an unchanged zone rather than a broken one.

ES5 only, matching the block it joins: `var`, `function`, no arrow functions, no template literals.

- [ ] **Step 1: Write the failing test**

Append to `tests/brand/probe_page.php`, immediately before the final `t_done();`:

```php
/* ---- the inline script --------------------------------------------------- */
$sa = strpos($src, '<script>');
$sb = strpos($src, '</script>');
t_ok('there is exactly one script block', $sa !== false && $sb !== false
    && substr_count($src, '<script>') === 1);
$js = ($sa !== false && $sb !== false) ? substr($src, $sa, $sb - $sa) : '';

/* The frozen region. The click-time CSRF mint inside wireUpload() exists
 * because ISPConfig's session store has no locking — a page-render token can be
 * silently erased by a concurrent request — and it is the one part of this page
 * that must never be reimplemented. wireRemove() and the #nz-msg-slot observer
 * are frozen with it: both self-disconnect or re-read state in ways that are
 * easy to break and impossible to see break. */
foreach (array('function wireUpload(', 'function wireRemove(',
               "fetch('customizer/logo_upload.php'", "fd.append('_csrf_id'",
               "fd.append('_csrf_key'", "fd.append('slot', slotName)",
               "wireUpload('nz-logo-upload', 'file', 'on_light')",
               "wireUpload('nz-logo-upload-on-dark', 'file_on_dark', 'on_dark')",
               "wireUpload('nz-favicon-upload', 'file_favicon', 'favicon')",
               "wireRemove('nz-logo-remove', 'on_light')",
               "wireRemove('nz-logo-remove-on-dark', 'on_dark')",
               "wireRemove('nz-favicon-remove', 'favicon')",
               "var slot = document.getElementById('nz-msg-slot')") as $needle) {
    t_ok("the frozen region still contains: $needle", strpos($js, $needle) !== false);
}
//* Exactly two references, both inside wireUpload(): the token mint and the
//* upload itself. A third would be a second uploader.
t_eq('logo_upload.php is reached from exactly two places',
    substr_count($js, 'logo_upload.php'), 2);

/* ES5, matching the block these additions join.
 *
 * Measured on the CODE, not the file: the frozen region's own comments say
 * "…exists to let them check" and quote jQuery's `data` in backticks, and a
 * scan that read those would report two syntax rules broken by prose it is not
 * allowed to edit. The '//' strip spares a '://' so a URL in a string survives.
 */
$code = preg_replace('#/\*.*?\*/#s', '', $js);
$code = preg_replace('#(^|[^:])//[^\n]*#', '$1', $code);
foreach (array('=>', 'const ', 'let ', '`') as $bad) {
    t_ok('the script stays ES5 (no ' . $bad . ')', strpos($code, $bad) === false);
}

/* The four live hooks and the drop forwarding. */
foreach (array("setRatio('nz-rail-ratio'", "setRatio('nz-rail-light-ratio'",
               "setRatio('nz-accent-ratio'", "setRatio('nz-login-ratio'",
               "paintNavBrand('.nz-prev-login-brand'",
               "getElementById('nz-save-flash')", "querySelector('.alert-notification')",
               '.nz-brandchip-hex', 'data-hex-field', 'data.summary',
               "setFact('nz-fact-design'", "setFact('nz-fact-marks'",
               "setFact('nz-fact-favicon'", 'function wireDrop(',
               "wireDrop('file', 'nz-drop-line-logo')",
               "wireDrop('file_on_dark', 'nz-drop-line-logo-on-dark')",
               "wireDrop('file_favicon', 'nz-drop-line-favicon')",
               'dataTransfer') as $needle) {
    t_ok("the script wires: $needle", strpos($js, $needle) !== false);
}
//* The login pull has to be cleared as well as painted, or a mark an earlier
//* response put there survives a change that resolves to none.
t_eq('every nav-brand slot is cleared when nothing resolves',
    substr_count($js, "paintNavBrand('.nz-prev-login-brand', null)"), 1);
```

- [ ] **Step 2: Run it to make sure it fails**

Run: `php tests/brand/probe_page.php 2>&1 | grep -E '^FAIL'`
Expected: the frozen-region and ES5 lines pass; every line from "the script wires:" onwards fails.

- [ ] **Step 3: Wire the two missing ratios and the summary facts**

In the template's `<script>`, add this helper immediately after `setRatio()`:

```js
  // One status fact in the legend. The strings are built server-side by
  // customizer_brand_summary(), so the page render and every refresh after an
  // upload word them identically; this only places them. textContent, like
  // every other string this page writes.
  function setFact(id, text) {
    var el = document.getElementById(id);
    if (!el) return;
    el.textContent = (typeof text === 'string') ? text : '';
  }
```

and in `refreshPreview()`'s success handler, replace

```js
        if (data.colours) {
          setRatio('nz-rail-ratio', data.colours.rail);
          setRatio('nz-rail-light-ratio', data.colours.rail_light);
```

with

```js
        // The legend's three facts. Two of them change when an image is
        // uploaded, and an upload does not reload the page — it replaces three
        // slots in place — so this is the only thing that keeps the mark count
        // and the favicon fact true between page loads.
        if (data.summary) {
          setFact('nz-fact-design', data.summary.design);
          setFact('nz-fact-marks', data.summary.marks);
          setFact('nz-fact-favicon', data.summary.favicon);
        }
        if (data.colours) {
          setRatio('nz-rail-ratio', data.colours.rail);
          setRatio('nz-rail-light-ratio', data.colours.rail_light);
          // All four colours carry a measured ratio and all four print one:
          // the readout is the answer to the question the colour raises, and a
          // page that answered it for two of the four would look like a bug.
          setRatio('nz-accent-ratio', data.colours.accent);
          setRatio('nz-login-ratio', data.colours.login);
```

- [ ] **Step 4: Mirror the hex captions**

In `paintPreview()`, immediately before the closing `paintName();` call, add:

```js
    // The caption under each colour chip says what the chip is painted with, so
    // it follows the FIELD and not the payload — a value the operator has typed
    // but not yet saved is the value the chip is showing. data-hex-field names
    // the field, so the markup decides which caption mirrors which control and
    // this loop stays one rule. The light rail's caption inherits the rail
    // exactly as its swatch does, or the two would disagree about the same
    // colour on the same line.
    var caps = document.querySelectorAll('.nz-brandchip-hex');
    for (var ci = 0; ci < caps.length; ci++) {
      var field = caps[ci].getAttribute('data-hex-field');
      var shown = field ? fieldValue(field) : '';
      if (shown === '' && field === 'rail_hex_light') shown = rail;
      caps[ci].textContent = (shown === '') ? '—' : shown;
    }
```

- [ ] **Step 5: Paint the login pull's mark**

In `paintSurfaces()`, in the "nothing to describe" branch, replace

```js
      paintNavBrand('.nz-prev-nav-brand', null);
      paintNavBrand('.nz-prev-nav-brand-light', null);
      return;
```

with

```js
      paintNavBrand('.nz-prev-nav-brand', null);
      paintNavBrand('.nz-prev-nav-brand-light', null);
      paintNavBrand('.nz-prev-login-brand', null);
      return;
```

and, after the two existing `paintNavBrand` calls further down, add:

```js
    // The login pull gets the mark its OWN surface resolved to. Without this it
    // showed the panel name whatever the operator had uploaded — which is
    // exactly the thing a login-screen preview exists to let them check.
    paintNavBrand('.nz-prev-login-brand', login[0]);
```

- [ ] **Step 6: Move the save confirmation beside the Save button**

In the wiring block, replace

```js
    if (formScope().querySelector('.alert-notification, #OKMsg')) {
```

with

```js
    // Core renders the post-save confirmation as '.alert-notification', where
    // tabbed_form.tpl.htm puts it: above this page's own markup, a screen away
    // from the button that caused it. Move it into the save bar. Done once, at
    // init, rather than in the observer below: that banner is SERVER-rendered
    // with the page and is already in the document, while the uploader's
    // #OKMsg/#errorMsg arrive later — which is what the observer is for, and
    // why its region is frozen.
    var saveFlash = document.getElementById('nz-save-flash');
    var savedMsg = formScope().querySelector('.alert-notification');
    if (saveFlash && savedMsg) saveFlash.appendChild(savedMsg);

    if (formScope().querySelector('.alert-notification, #OKMsg')) {
```

- [ ] **Step 7: Forward click and drop to the clipped file input**

Add this function immediately before the `// The upload driver below refreshes the three MARK slots…` comment that opens the frozen region, and its three calls immediately after it:

```js
  // The drop zone. The label already opens the picker for the input it wraps,
  // with no script — that half needs nothing. Dropping does: a label is not a
  // native drop target the way a file input is, so the file is taken off the
  // drag and assigned to the input, which is what the uploader then reads.
  //
  // The zone also SHOWS the chosen file, because the input that would have
  // shown it is clipped. That display is script-only, which costs nothing an
  // operator can reach: the Upload button is a fetch driver, so with JavaScript
  // off there is no upload to name a file for.
  function wireDrop(inputId, lineId) {
    var input = document.getElementById(inputId);
    var line  = document.getElementById(lineId);
    if (!input || !line) return;
    var zone = input.parentNode;
    // The instruction, kept so the zone can go back to it. Read from the DOM
    // rather than carried here, because it is wordbook text.
    var idle = line.textContent;

    function show() {
      var file = (input.files && input.files.length) ? input.files[0] : null;
      line.textContent = file ? file.name : idle;
    }
    input.addEventListener('change', show);
    show();

    if (!zone) return;
    // Both, and preventDefault on both: without it the browser navigates to the
    // dropped file and the operator loses the page they were editing.
    zone.addEventListener('dragenter', function (e) { e.preventDefault(); });
    zone.addEventListener('dragover', function (e) { e.preventDefault(); });
    zone.addEventListener('drop', function (e) {
      if (!e.dataTransfer || !e.dataTransfer.files || !e.dataTransfer.files.length) return;
      e.preventDefault();
      // Wrapped because a browser that refuses the assignment must leave the
      // zone exactly as it was rather than half-changed: the operator can still
      // click it and choose the same file.
      try { input.files = e.dataTransfer.files; } catch (err) { return; }
      show();
    });
  }
  wireDrop('file', 'nz-drop-line-logo');
  wireDrop('file_on_dark', 'nz-drop-line-logo-on-dark');
  wireDrop('file_favicon', 'nz-drop-line-favicon');
```

- [ ] **Step 8: Verify**

Run:
```bash
php tests/brand/run.php
node --check <(sed -n '/<script>/,/<\/script>/p' interface/web/customizer/templates/customizer_edit.htm | sed '1d;$d')
git diff --stat interface/web/customizer/templates/customizer_edit.htm
```
Expected: `brand suite passed` with `probe_page.php` fully green; `node --check` prints nothing (a syntax error is the only output it produces); the diff touches one file.

Then confirm the frozen region is untouched, byte for byte:
```bash
git show HEAD~1:interface/web/customizer/templates/customizer_edit.htm | sed -n '/function wireUpload(/,$p' > /tmp/frozen.before
sed -n '/function wireUpload(/,$p' interface/web/customizer/templates/customizer_edit.htm > /tmp/frozen.after
diff /tmp/frozen.before /tmp/frozen.after && echo "frozen region byte-identical"
```
Expected: `frozen region byte-identical`.

- [ ] **Step 9: Commit**

```bash
git add interface/web/customizer/templates/customizer_edit.htm tests/brand/probe_page.php
git commit -m "Wire the Branding page's live hooks: four ratios, the hex captions, the login mark, the save flash and the drop zones"
```

---

## Task 6: Documentation

**Files:**
- Modify: `README.md` (the Branding page description)
- Modify: `CONTRIBUTING.md` (the `preview.php` row; what to check when this page changes)
- Modify: `SECURITY.md` (the `preview.php` bullet)
- Modify: `UPGRADING.md` (a new subsection under "Upgrading this extension")

**Interfaces:** none — nothing consumes this task.

- [ ] **Step 1: README — what the page looks like now**

In `README.md`, under `### The Branding page`, replace the paragraph beginning "The page itself is two columns on a wide screen" with:

```markdown
The page leads with the picture. A full-width proof at the top shows two pulls
side by side — the panel (tab strip, sidebar, topbar, a content card) and the
login screen — and under them, on the same mount, a legend: the two supplied
marks on the backgrounds they are for, the panel name, three status facts, the
light-mode sidebar sample when you have set one, and the three colours as
measured values. The settings follow in five cards, and the save bar is sticky,
so the one control you are looking for is where you left it. Below about 900px
of content width the page collapses to one column.

Colours, the panel name and the contrast readouts update as you type; which logo
each surface ends up with is worked out by the server, using the same code the
panel itself uses, so the preview cannot promise a mark the panel will not
render. Every paragraph of inline help is a "?" beside the thing it explains.
```

- [ ] **Step 2: CONTRIBUTING — the payload row and the checking routine**

In `CONTRIBUTING.md`, in the file table, replace the `preview.php` row's description with:

```markdown
| `interface/web/customizer/preview.php` | The Branding page's live preview: admin-only and **read-only**. Takes the form's unsaved values as POST and returns JSON built by `lib/preview.inc.php` — the resolved logo variants per surface, the five preview slots (one swatch per mark column, the remaining surfaces per strip, and the favicon), the measured rail ink, and the legend's three status facts (`summary`, so the mark count stays true after an upload, which replaces three slots in place and never reloads the page). It exists so the logo-variant resolver stays in PHP: a JavaScript copy would sit outside the three-copies-agree guarantee CI enforces. It declares no function of its own, and `tests/brand/probe_preview.php` proves it writes nothing. |
```

and replace the paragraph beginning "The Branding page is the one page in this repository with a layout of its own" with:

```markdown
The Branding page is the one page in this repository with a layout of its own, so
it needs checking at three widths — 1440, 1280 and 1024 — under **each installed
design**, and in both colour modes on clarity. It has no stylesheet: every colour
in its inline `<style>` is `var(--pz-…, var(--nz-…, <stock fallback>))`, so a rule
that names a colour directly will look correct under the design you wrote it for
and wrong under the other two. `tests/brand/probe_page.php` asserts that, the
`nz-`/`#nz-brandpage` prefixing, the single permitted focus rule and every id the
inline script binds — run it before you look at anything. Four things need a
human: the drop zones by **keyboard** (Tab must reach each one and show where it
is, Space must open the picker) and by **drag and drop**; the `<details>` hints,
which must open in place without moving the control beside them; the sticky save
bar, which must not cover the last field; and JavaScript **off**, where the form
must still save — that is the check that proves the preview stayed an
enhancement. The live preview needs `customizer/preview.php` reachable.
```

- [ ] **Step 3: SECURITY — the payload's new block**

In `SECURITY.md`, in the `preview.php` bullet, replace the sentence beginning "It is the Branding page's live preview: admin-only, the same three checks in the same order, and **read-only**" with:

```markdown
  page's live preview: admin-only, the same three checks in the same order,
  and **read-only** — one `SELECT` against `sys_ini` row 1, and no write of
  its own: nothing to `sys_ini`, nothing to `sys_config`, nothing to disk. Its
  response carries the rendered preview rows, the surfaces list, the colour
  blocks and three short status sentences (`summary`) built from the module's
  own wordbook — no value a request supplied is echoed back, and the operator's
  panel name never enters the payload at all.
```

(keep the rest of the bullet, from "It declares no function of its own either" onwards, exactly as it is).

- [ ] **Step 4: UPGRADING — what this release changes for an existing panel**

In `UPGRADING.md`, immediately after the `### Retired wordbook keys` section and before `The full usage line:`, insert:

```markdown
### The Branding page's new layout (v3.5.0)

The page was rebuilt around its own preview: the picture is now the full-width
thing at the top, with the two supplied marks, the panel name, three status
facts and the colour chips in a legend beneath it, and the settings in five
cards under that. Nothing about **what** it stores changed — same keys, same
`sys_ini` row, same `sys_config` row for the donation switch, no migration, no
new column. An upgrade is `git checkout <tag>` and `./install.sh`, exactly as
before.

Three things are worth knowing before you upgrade:

- **The six visibility switches were relabelled.** "Show the software version"
  is now "Software version", and so on for the other five: a switch already
  says "show". The settings and their keys are unchanged — only the labels are
  shorter. All seven shipped locales were updated in the same release.
- **Seventeen wordbook keys were added**, eleven to the form wordbook
  (`lib/lang/<lang>_customizer.lng`) and six to the module wordbook
  (`lib/lang/<lang>.lng`, where the endpoint that refreshes the preview can
  reach them). If you maintain a translation of your own, add all seventeen:
  ISPConfig *substitutes* wordbooks rather than merging them, so a file that
  exists but omits a key renders the raw key name in the UI.
  `.github/scripts/lang_check.php` lists exactly what is missing.
- **No design has to change.** The page's own stylesheet is inline and reads
  design tokens through the same `var(--pz-…, var(--nz-…, …))` chains it always
  did, and the brand-token contract is untouched.

Downgrading is uneventful in the same way: the older page reads the same stored
values and simply does not know about the new wordbook keys, which are inert
where nothing asks for them.
```

- [ ] **Step 5: Verify**

Run:
```bash
grep -n 'summary' CONTRIBUTING.md SECURITY.md | head
grep -c 'proof' README.md
php .github/scripts/lang_check.php
php tests/brand/run.php
```
Expected: the `summary` block is described in both files; README mentions the proof; `language files OK`; `brand suite passed`.

- [ ] **Step 6: Commit**

```bash
git add README.md CONTRIBUTING.md SECURITY.md UPGRADING.md
git commit -m "Document the Branding page's new layout, the payload's summary block and what to check by hand"
```

---

## Task 7: Render the shipped page through the mockup harness and check it against the approved shots

**Files:**
- Create: `mockup/branding/sample_previews.php`
- Create: `mockup/branding/render_shipped.py`
- Modify: `mockup/build.py` (two page entries, two shot rows, the generated fragment)
- Modify: `.gitignore` (the generated fragment)

**Interfaces:** none — this is the last task.

**Background the implementer needs:**

`mockup/build.py` renders clarity's real shell offline and screenshots it. Until now it rendered the *mockup* of this page. This task makes it render the page that actually ships, from the real template, with the real preview markup produced by the module's real renderers — and with the page's own inline script running against a canned response from `customizer_preview_payload()`. That last part is what makes the shot worth comparing: it exercises the JS contract end to end with no panel, no database and no network.

`build.py` strips every `<script>` out of the shell to avoid 404s from missing core JS, then re-injects a controlled bootstrap per page (`PAGE_SCRIPTS`, which is how the dashboard's charts are drawn). The Branding page's bootstrap is a `fetch` stub plus the page's own script.

- [ ] **Step 1: The sample brand, through the real renderers**

Create `mockup/branding/sample_previews.php`:

```php
<?php
/**
 * The Branding mockup's sample brand, run through the module's REAL renderers.
 *
 * mockup/branding/render_shipped.py needs two things it must not invent: the
 * markup the five preview slots really carry, and the response
 * customizer/preview.php really returns. Both come from
 * lib/preview.inc.php here, so the rendered page is the page the panel serves
 * rather than a hand-written imitation of it — which is the whole point of
 * shooting the shipped template instead of the mockup.
 *
 * Pure: no database, no session, no HTTP. Run it with
 *   php mockup/branding/sample_previews.php
 * and it prints one JSON object on stdout.
 *
 * The brand is invented (Karoo Hosting) and its colours are deliberately NOT
 * clarity's: a preview painted in the design's own palette proves nothing.
 */

require __DIR__ . '/../../interface/web/customizer/lib/preview.inc.php';

//* The three marks, written as readable SVG rather than pasted as base64 so a
//* reader can see what is being previewed. Byte-for-byte the artwork in
//* mockup/branding/branding.html.
$mark_on_light = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 178 40" width="178" height="40">
<rect x="0" y="4" width="32" height="32" rx="8" fill="#2E7D6B"/>
<path d="M7 27.5 L13.5 17 L18 22.5 L21.5 18.5 L26 27.5 Z" fill="#FFFFFF"/>
<circle cx="22.5" cy="12.5" r="2.6" fill="#FFFFFF" opacity="0.65"/>
<text x="42" y="26" font-family="Inter,\'Segoe UI\',Helvetica,Arial,sans-serif" font-size="18" font-weight="700" letter-spacing="-0.3" fill="#123A34">Karoo</text>
<text x="99" y="26" font-family="Inter,\'Segoe UI\',Helvetica,Arial,sans-serif" font-size="18" font-weight="400" letter-spacing="-0.3" fill="#4E6B64">Hosting</text>
</svg>';

$mark_on_dark = str_replace(array('fill="#123A34">Karoo', 'fill="#4E6B64">Hosting'),
                            array('fill="#FFFFFF">Karoo', 'fill="#A9C7BF">Hosting'),
                            $mark_on_light);

$icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="32" height="32">
<rect width="32" height="32" rx="7" fill="#2E7D6B"/>
<path d="M6 24 L13 11 L18 17.5 L21.5 13 L26 24 Z" fill="#FFFFFF"/>
</svg>';

$uri = function($svg) { return 'data:image/svg+xml;base64,' . base64_encode($svg); };

$custom_logo = $uri($mark_on_light);          // core's own column = the light-background slot
$branding = array(
    'logo_on_dark'     => $uri($mark_on_dark),
    'logo_url'         => '',
    'logo_url_on_dark' => '',
    'favicon'          => $uri($icon),
    'favicon_url'      => '',
    'accent_hex'       => '#2E7D6B',
    'rail_hex'         => '#123A34',
    'rail_hex_light'   => '#E6EEEC',
    'login_bg'         => '#0E2723',
);

//* Both designs, so the "Also used on" strip has something in it — which is the
//* state the mockup was drawn in.
$designs = array('clarity', 'classic');
$labels  = array('nav' => 'Navigation', 'login' => 'Login screen');
$texts   = array(
    'no_logo'              => 'No custom logo set — the theme shows its own default.',
    'fallback_from_dark'   => 'No light-background logo set, so the dark-background one is shown here.',
    'fallback_from_light'  => 'No dark-background logo set, so the light-background one is shown here.',
    'no_favicon'           => 'No custom favicon set — the design shows its own.',
    'favicon_url_wins'     => 'This is the favicon path set below, which wins over the uploaded file.',
    'summary_design'       => '%s active',
    'summary_marks_none'   => 'No marks',
    'summary_marks_one'    => '%d mark',
    'summary_marks'        => '%d marks',
    'summary_favicon'      => 'Favicon set',
    'summary_favicon_none' => 'No favicon',
);

$payload = customizer_preview_payload($branding, $custom_logo, array(), $designs, $labels, $texts);

echo json_encode(array(
    //* What customizer/preview.php would answer, for the page's own script.
    'payload' => $payload,
    //* What customizer_edit.php would put in the template, for the render. The
    //* five slots come from the payload rather than being rendered twice —
    //* they are the same call, which is the guarantee the module makes.
    'tpl' => array_merge($payload['previews'], array(
        'summary_fact_design'  => $payload['summary']['design'],
        'summary_fact_marks'   => $payload['summary']['marks'],
        'summary_fact_favicon' => $payload['summary']['favicon'],
        'company_name'         => 'Karoo Hosting',
        'accent_hex'           => $branding['accent_hex'],
        'rail_hex'             => $branding['rail_hex'],
        'rail_hex_light'       => $branding['rail_hex_light'],
        'login_bg'             => $branding['login_bg'],
        'logo_url'             => '',
        'logo_url_on_dark'     => '',
        'favicon_url'          => '',
        'custom_login_text'    => 'Support: help@karoo.example',
        'custom_login_link'    => 'https://karoo.example/support',
        'id'                   => '1',
        'field_errors_json'    => '{}',
    )),
), JSON_UNESCAPED_SLASHES);
echo "\n";
```

Run: `php mockup/branding/sample_previews.php | head -c 200`
Expected: a JSON object beginning `{"payload":{"previews":{"used_logo":"<span class=\"nz-marks nz-mark-primary\"…`.

- [ ] **Step 2: The renderer**

Create `mockup/branding/render_shipped.py`:

```python
#!/usr/bin/env python3
"""Turn the SHIPPED Branding template into a mockup fragment.

mockup/build.py renders clarity's real shell around a fragment. Pointing it at
interface/web/customizer/templates/customizer_edit.htm needs three things this
module supplies:

  * every {tmpl_var} the page interpolates, with the values customizer_edit.php
    would have set — the wordbook read as TEXT (never executed, the same rule
    .github/scripts/lang_check.php follows), the sample brand's field values,
    the two <select> option lists and the six checkboxes emitted the way
    tform_base.inc.php emits them, and the fifteen disclosure names composed the
    way publish_hint_labels() composes them;
  * the five preview slots and the three status facts, which come from the
    module's own renderers through sample_previews.php;
  * a bootstrap that lets the page's own inline script run with no panel behind
    it, by answering its one fetch() with the payload those same renderers
    produced.

Nothing here reimplements a decision. Anything this file had to compute itself
would be a second copy of something the panel already decides, and the shot
would stop being evidence about the shipped page.
"""
import json
import re
import subprocess
from pathlib import Path

HERE = Path(__file__).resolve().parent            # mockup/branding
REPO = HERE.parent.parent
TPL = REPO / "interface/web/customizer/templates/customizer_edit.htm"
TFORM_LNG = REPO / "interface/web/customizer/lib/lang/en_customizer.lng"
SAMPLE = HERE / "sample_previews.php"

# Which switches are on in the sample, matching mockup/branding/branding.html.
SWITCHES = {
    "show_design_picker": True, "show_version": False, "show_news_feed": True,
    "show_donation_dashlet": False, "show_ispconfig_credit": True,
    "show_theme_credit": True,
}

# The fifteen "?" disclosures, by the label key each one is named after — the
# same list publish_hint_labels() walks in customizer_edit.php.
HINT_LABELS = [
    "identity_head_txt", "logo_on_light_head_txt", "logo_url_txt",
    "logo_on_dark_head_txt", "logo_url_on_dark_txt", "placement_head_txt",
    "favicon_head_txt", "favicon_url_txt", "accent_hex_txt",
    "rail_hex_light_txt", "show_design_picker_txt", "show_version_txt",
    "show_news_feed_txt", "show_donation_dashlet_txt", "show_theme_credit_txt",
]

_WB = re.compile(r"\$wb\[\s*'([^']+)'\s*\]\s*=\s*'((?:[^'\\]|\\.)*)'\s*;")


def wordbook(path: Path) -> dict:
    """Every $wb key in a .lng file, parsed as TEXT and never executed."""
    out = {}
    for m in _WB.finditer(path.read_text(encoding="utf-8")):
        out[m.group(1)] = re.sub(r"\\(['\\])", r"\1", m.group(2))
    return out


def sample() -> dict:
    """The module's own renderers, through sample_previews.php."""
    try:
        out = subprocess.run(["php", str(SAMPLE)],
                             capture_output=True, text=True, check=True)
    except (FileNotFoundError, subprocess.CalledProcessError) as exc:
        raise SystemExit("mockup/branding: php is needed to render the shipped "
                         "Branding page (it runs the module's own preview "
                         "renderers): %s" % exc)
    return json.loads(out.stdout)


def _options(wb: dict, selected: str) -> str:
    """tform_base.inc.php:504-512 emits <option> tags and nothing else."""
    rows = [("", "logo_variant_auto_txt"),
            ("on_light", "logo_variant_on_light_txt"),
            ("on_dark", "logo_variant_on_dark_txt")]
    return "".join(
        "<option value='%s'%s>%s</option>"
        % (value, " selected='selected'" if value == selected else "", wb[key])
        for value, key in rows)


def _checkbox(key: str, on: bool) -> str:
    """tform_base.inc.php:543-545, verbatim."""
    return ('<input name="%s" id="%s" value="y" type="checkbox"%s />'
            % (key, key, " CHECKED" if on else ""))


def variables() -> dict:
    wb = wordbook(TFORM_LNG)
    data = sample()

    v = dict(wb)                      # every *_txt the template interpolates
    v.update(data["tpl"])             # the slots, the facts and the field values
    v["logo_variant_nav"] = _options(wb, "")
    v["logo_variant_login"] = _options(wb, "")
    for key, on in SWITCHES.items():
        v[key] = _checkbox(key, on)
    # publish_hint_labels(), in Python: str_replace('%s', <label>, hint_more_txt)
    for key in HINT_LABELS:
        v["hint_" + key] = wb["hint_more_txt"].replace("%s", wb[key])
    # Two vars only the uploader's response sets, and the page's own hint text.
    v["upload_msg"] = ""
    v["upload_error"] = ""
    return v


def bootstrap() -> str:
    """The page's own inline script, with its one fetch() answered locally.

    The script is taken OUT OF THE TEMPLATE rather than copied here, so what
    runs in the shot is the code that ships. fetch is stubbed because
    customizer/preview.php needs a panel; everything the stub returns was built
    by the module's real payload function.
    """
    src = TPL.read_text(encoding="utf-8")
    js = src.split("<script>", 1)[1].rsplit("</script>", 1)[0]
    payload = json.dumps(sample()["payload"])
    return ("<script>\nwindow.fetch = function () {\n"
            "  return Promise.resolve({ ok: true, status: 200,\n"
            "    json: function () { return Promise.resolve(%s); } });\n"
            "};\n</script>\n<script>%s</script>\n" % (payload, js))
```

- [ ] **Step 3: Teach `build.py` about the shipped page**

In `mockup/build.py`:

(a) add `import importlib.util` to the imports at the top;

(b) extend `DARK_PAGES` with one entry, after the existing `"dark-branding"` line:

```python
    # The same page as it SHIPS: interface/web/customizer/templates/
    # customizer_edit.htm rendered with the mockup's own sample brand, so the
    # design and the build can be compared side by side. Written by
    # branding/render_shipped.py at build time — see build().
    "dark-branding-live": ("tools", "../branding/sidenav-tools.html", "../branding/shipped.html"),
```

(c) in `build()`, immediately after the `(WEBROOT / "branding").symlink_to(HERE / "branding")` line, add:

```python
    # Render the shipped Branding template into a fragment, and hand its own
    # inline script a canned preview response so the page paints itself exactly
    # as it does on a panel. Everything both need comes from the module's real
    # renderers; see branding/render_shipped.py.
    spec = importlib.util.spec_from_file_location(
        "render_shipped", HERE / "branding/render_shipped.py")
    render_shipped = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(render_shipped)
    shipped_tpl = (REPO / "interface/web/customizer/templates/customizer_edit.htm").read_text(encoding="utf-8")
    (HERE / "branding/shipped.html").write_text(
        render_tpl(shipped_tpl, render_shipped.variables()), encoding="utf-8")
    PAGE_SCRIPTS["dark-branding-live"] = render_shipped.bootstrap()
```

(d) extend the light-mode pair list in `build()` so it reads:

```python
    for src, dst in (("dark-dashboard", "light-dashboard"), ("dark-login", "light-login"),
                     ("dark-branding", "light-branding"),
                     ("dark-branding-live", "light-branding-live")):
```

(e) extend `SHOT_MATRIX`, after the two existing branding rows:

```python
    ("dark-branding-live", ("desktop", "fold", "narrow")),
    ("light-branding-live", ("desktop", "fold")),
```

(f) extend `SHOT_DIRS`:

```python
SHOT_DIRS = {"dark-branding": HERE / "branding/shots",
             "light-branding": HERE / "branding/shots",
             "dark-branding-live": HERE / "branding/shots",
             "light-branding-live": HERE / "branding/shots"}
```

(g) add the generated fragment to `.gitignore`, under the "Build / scratch" block:

```gitignore
# The shipped Branding template, rendered into a mockup fragment by
# mockup/branding/render_shipped.py on every build. Generated, never edited.
mockup/branding/shipped.html
```

- [ ] **Step 4: Render and shoot**

Run:
```bash
cd mockup && python3 build.py --shoot --only=branding
```
Expected: the stylesheet report lists `dark-branding-live.html` and `light-branding-live.html` with `0 missing`, and five new files appear in `mockup/branding/shots/` — `dark-branding-live-{desktop,fold,narrow}.png`, `light-branding-live-{desktop,fold}.png` — each reporting `(0 failed requests)`.

- [ ] **Step 5: Check the render against the JS contract, mechanically**

Run, from the repository root:
```bash
python3 - <<'PY'
import re
from pathlib import Path
html = Path("mockup/branding/shipped.html").read_text()

# Nothing may be left unresolved: a surviving tag is a var the page asks for
# and customizer_edit.php does not publish.
left = re.findall(r"\{tmpl_var name='([^']+)'\}", html) + re.findall(r"<tmpl_[a-z]+", html)
print("unresolved template tags:", sorted(set(left)) or "none")

# The first-match hooks, in the order paintSurfacePane() needs them.
for cls in ("nz-prev-rail", "nz-prev-rail-light", "nz-prev-login"):
    first = html.find(cls)
    chip = html.find("nz-brandchip-swatch " + cls)
    print(f"{cls}: first at {first}, chip at {chip}",
          "OK" if chip == -1 or first < chip else "OUT OF ORDER")

# The five slots really carry rendered markup, not a placeholder.
for slot in ("used_logo", "used_logo_more", "used_logo_on_dark",
             "used_logo_on_dark_more", "used_favicon"):
    i = html.find('id="%s"' % slot)
    body = html[i:i + 400] if i != -1 else ""
    print(f"{slot}:", "rendered" if "<img" in body or "<em" in body else "EMPTY")

# The legend's three facts.
for fact in ("nz-fact-design", "nz-fact-marks", "nz-fact-favicon"):
    i = html.find('id="%s"' % fact)
    print(f"{fact}:", html[i:i+120].split(">", 1)[1].split("<", 1)[0] if i != -1 else "MISSING")
PY
```
Expected: `unresolved template tags: none`; all three hooks `OK`; all five slots `rendered`; the three facts read `Clarity active`, `2 marks`, `Favicon set`.

- [ ] **Step 6: Check the render against the approved shots, by eye**

Open the mockup shot and the live shot side by side for each pair —
`mockup/branding/shots/dark-branding-desktop.png` against `dark-branding-live-desktop.png`, and the same for `-fold`, `-narrow` and the two `light-` shots. The two are **not** pixel-identical by design (see Task 4's four deliberate differences), so this is a checklist, not a diff:

| Must match the mockup | Where to look |
|---|---|
| The proof is full width, two pulls side by side, panel wider than login | top of the page |
| The tab strip sits on top of the panel pull, one tab, hairline under it | panel pull |
| The legend is one row under the proof, inside the same mount, hairline-separated cells | under the proof |
| Both supplied marks, each on its own ground, side by side | legend, first cell |
| The panel name in 17px, three facts under it separated by hairlines | legend, second cell |
| The light-mode sidebar sample, present because `rail_hex_light` is set | legend, third cell |
| Three colour chips, hex in mono under each, caption under that | legend, right, flush to the edge |
| Identity on the left; Colour, Favicon and Login screen stacked on the right; the two columns end level | the cards |
| Card titles in the card-title voice, not tracked caps, with a hairline under each | every card |
| Four colour rows: picker, field, ratio, all on one line | Colour card |
| Panel visibility spans both columns, six switches in three columns | below the cards |
| The switches are tracks with thumbs, three on and three off | Panel visibility |
| The save bar is pinned to the bottom of the viewport in the `-fold` shots and static in the full-page ones | bottom |
| At 1000px: one column, proof stacked, switches at two columns | `-narrow` |

| Expected to differ, and why |
|---|
| The nav items, topbar and content card are blank bars rather than "Home / Sites / Email", "Search / admin" and "Websites" — there is no source of translated nav labels on this page (Task 4, note 2) |
| The accent rule is the rail's full-height left edge rather than a marker on the active item — the consequence of blank items, and the shipped page's own documented reason |
| Each drop zone is one quiet line, with no stock "Choose file" button — the owner's fold-in (Task 4, note 1) |
| The formats-and-size sentence is behind the "?" rather than under the zone — copy is the shipped wording (Task 4, note 3) |
| Hint and label text is the shipped wordbook's, so several lines are longer than the mockup's trimmed copy |

If anything in the first table does not match, fix the template — not the mockup, which is the approved design record and stays as it is.

- [ ] **Step 7: Full local verification**

Run:
```bash
find themes interface bin .github -name '*.php' -print0 | while IFS= read -r -d '' f; do php -l "$f" >/dev/null || echo "LINT FAIL $f"; done
find interface -name '*.lng' -print0 | while IFS= read -r -d '' f; do php -l "$f" >/dev/null || echo "LINT FAIL $f"; done
php -l mockup/branding/sample_previews.php
php .github/scripts/lang_check.php
php tests/brand/run.php
node --check <(sed -n '/<script>/,/<\/script>/p' interface/web/customizer/templates/customizer_edit.htm | sed '1d;$d')
bash -n install.sh && bash -n uninstall.sh
git status --porcelain
```
Expected: no `LINT FAIL`; `language files OK`; `brand suite passed`; `node --check` silent; the shell check silent; `git status` shows only the intended new/modified files and **not** `mockup/branding/shipped.html`.

- [ ] **Step 8: What the operator has to see on a real panel**

None of the above touches a database, so this list is what still has to be done on a test panel — `./install.sh --module` (or `--all`), then Tools > Branding, hard-refreshed:

1. **It renders under each installed design.** clarity dark, clarity light (the topbar toggle), and classic. Check 1440, 1280 and 1024 — the collapse is measured on the content column, so the one-column layout arrives before the window looks narrow.
2. **The proof describes the panel.** The sidebar pull is the rail colour, the login pull is the login background, and the marks in the two rails are the ones the *server* resolved — not whichever image was uploaded last.
3. **Typing repaints.** Type in each of the four colour fields: the pulls and the chips recolour immediately, the chip caption follows the field, and the ratio beside the field lands within about a third of a second. All four ratios must move; none may sit at `—` once a valid colour is in its field.
4. **The light sample appears and disappears.** Set `rail_hex_light` to something different from `rail_hex` and the light sidebar sample appears in the legend; clear it and the sample goes, and the light ratio reads `(inherited from Sidebar colour)`.
5. **The three facts are true and stay true.** They should read the active design, the number of marks *supplied* (a borrowed variant is not a second mark) and whether a favicon is set. Upload a dark-background logo: the count goes to 2 without a page reload. Remove it: it goes back to 1.
6. **The drop zones work three ways.** Click one — the picker opens and the chosen filename replaces the quiet line. **Tab** to it — the zone shows where the focus is; **Space** opens the picker. **Drag** a file onto it — the filename appears and the page does not navigate away. Then press Upload and confirm the banner lands under the page header, in `#nz-msg-slot`, and that all three preview rows refresh.
7. **The save bar does its job.** Scroll: it stays at the bottom and never covers the last field. Press Save changes: the page comes back with the confirmation *inside the save bar*, beside the button, and the proof flashes once.
8. **A bad value is still reported twice.** Type `nonsense` into a colour field and save: the banner appears at the top **and** the field itself is outlined with the message beneath it.
9. **The hints open in place.** Every "?" opens under its own row and pushes the rest of the card down; none of them moves the control beside it. With a screen reader, each announces "More about …" rather than "question mark".
10. **JavaScript off, the form still saves.** The preview is unpainted and the zones cannot upload — both are enhancements — but every field and the Save button work.
11. **A second design installed.** With both clarity and classic present, each mark block's "Also used on" strip lists the other surfaces, and a variant with nothing more to say shows no label and no empty row.

- [ ] **Step 9: Commit**

```bash
git add mockup/build.py mockup/branding/render_shipped.py \
        mockup/branding/sample_previews.php mockup/branding/shots .gitignore
git commit -m "Shoot the shipped Branding page in clarity's shell, beside the design it was built from"
```

---

## Verification summary

| Check | Command | Where it runs |
|---|---|---|
| PHP syntax, all sources and language files | `find … -name '*.php' \| … php -l` | local + CI |
| Wordbook key parity, nav-title budget, attribute-hostile characters | `php .github/scripts/lang_check.php` | local + CI |
| Brand readers, the summary builder, the payload, the form validators, the preview endpoint's shape, **and the page's own shape** | `php tests/brand/run.php` | local + CI |
| The inline script parses | `node --check <(sed -n '/<script>/,/<\/script>/p' … )` | local |
| The shipped page renders, paints and resolves every var | `cd mockup && python3 build.py --shoot --only=branding` plus Step 5's script | local |
| SVG upload screen corpus | `php tests/svg/run.php` | **CI only** — needs `ext/dom` |
| Cache-buster, dashlet overrides, favicon contract, brand-token contract, classic asset ban, installer flags | the `ci.yml` steps | **CI only** |
| Everything in Task 7 Step 8 | by hand | **test panel only** |

Nothing in this plan can be proven by running the panel locally: the local PHP has no `mysqli`, so every endpoint that reads `sys_ini` is untestable here by design. That is why every decision this page makes lives in `lib/preview.inc.php`, where it is tested without a database, and why the template itself is asserted structurally by `tests/brand/probe_page.php`.
