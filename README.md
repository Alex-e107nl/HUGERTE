# e107 HugeRTE WYSIWYG Editor Plugin

**An installable e107 2.x plugin that replaces the outdated, insecure `tinymce4` plugin with [HugeRTE](https://hugerte.org) — the free, MIT-licensed fork of TinyMCE (TinyMCE 6/7 API).**

[![e107](https://img.shields.io/badge/e107-2.x-7b4f9e)](https://e107.org)
[![Plugin version](https://img.shields.io/badge/plugin-1.0-blue)](plugin.xml)
[![HugeRTE](https://img.shields.io/badge/HugeRTE-1.0.15-orange)](https://github.com/hugerte/hugerte)
[![License: GPL](https://img.shields.io/badge/plugin-license-GPLv3-green.svg)](https://www.gnu.org/licenses/gpl-3.0.html)
[![Editor license: MIT](https://img.shields.io/badge/editor-license-MIT-brightgreen.svg)](https://github.com/hugerte/hugerte)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb3)](https://www.php.net)
[![JavaScript](https://img.shields.io/badge/JS-ES5%20%2B%20ES6-yellow)](https://developer.mozilla.org/en-US/docs/Web/JavaScript)

---
## Warning / Disclaimer

Please note! Although we have made every effort and conducted extensive testing, 
you must ALWAYS create a system backup before applying our code. We are under no 
circumstances responsible for any damage or data loss resulting from the use of 
our code. It is impossible to test every possible combination of systems, CMS 
versions, servers, and PHP versions currently in use.

## Why this plugin?

The old `tinymce4` plugin loaded **TinyMCE 4.9.11 from a public CDN** — an end-of-life
branch with known security issues and no local control. This plugin ships **HugeRTE
1.0.15 locally** (no third-party CDN dependency) and provides the same feature set as
the old plugin, adapted to the TinyMCE 6/7 API and e107 2.x core:

- **HugeRTE** is a 100% MIT fork of TinyMCE based on TinyMCE 6.8.4 + TinyMCE 7 code up
  to the license-change commit. Its API is identical to TinyMCE 6/7 with the global
  object renamed: `tinymce` → `hugerte`.
- All content conversion (BBCode ↔ HTML) is done **server-side in PHP**, the clean way —
  no blocking synchronous AJAX from the browser like the old plugin.
- The editor is fully **offline-capable** and **upgradable in seconds** (see *Upgrading*).

## Key Features

- **Drop-in e107 WYSIWYG editor** — auto-discovered via `plugin.xml` keywords
  (`default` / `editor` / `wysiwyg`); select it in *Admin → Site Settings*
- **Role-based toolbars** — `mainadmin`, `admin`, `member`, `public` templates
  (theme overridable, back-compatible with `THEME/templates/tinymce/*.xml`)
- **e107 Media Manager integration** — toolbar buttons for **image / video / glyph**
  open a sized, scrollbar-free, responsive dialog and insert at the caret via `postMessage`
- **BBCode toggle** — one button switches between the WYSIWYG editor and a plain
  BBCode editing panel with quick-insert buttons (bold, italic, link, image, quote,
  glyph, code, spoiler, table, …)
- **Dark mode** — automatically uses the `oxide-dark` skin when the site is in dark
  mode (`data-bs-theme="dark"`) or the OS prefers dark
- **Safe content round-trip** — content survives e107's `cleanHtml()` sanitization:
  - *Strikethrough* is stored as `<span style="text-decoration:line-through">`
    (e107 strips `<s>` **and its text** — the span form is identical visually)
  - *Italic* is stored as `<i>` (TinyMCE produces `<em>`; converted on save, server-side)
  - *Glyphs* (Font Awesome) survive the editor's parser/serializer (`<span>` + zero-width space)
  - Empty `<p>&nbsp;</p>` tags that accumulate on every save/reopen cycle are stripped
- **e107 emoticons** — emote-pack images served to the editor's emoticons plugin
- **Autosave, pagebreak (`[newpage]`), code highlighting, templates/snippets**
- **Graceful fallback** — users without `post_html` permission keep the core BBCode editor
- **Per-file cache-busting** — external plugin JS is versioned by `filemtime()`, so
  edits to `plugin.js` are picked up without manual cache clearing

## Tech Stack

| Layer | Technology |
|---|---|
| CMS | e107 2.x (PHP 7.4+), plugin API (`plugin.xml`, `e_footer`, `e_parse`) |
| Editor | HugeRTE 1.0.15 (MIT fork of TinyMCE, TinyMCE 6.8.4/7 API, global `hugerte`) |
| Editor engine | Vanilla JS UMD build + DOM model (`contenteditable`), DOMPurify-sanitized |
| UI | Bootstrap 5 (e107 core), oxide / oxide-dark skins |
| Content bridge | Server-side PHP (e_parse hooks + `parser.php` BBCode↔HTML endpoint) |
| Icons | Font Awesome (glyphs) — loaded into the editor iframe via `content_css` |

## Prerequisites

- **e107 CMS 2.x** (compatibility flag: `2.0`)
- **PHP 7.4+** with standard e107 configuration
- A user role with **`post_html`** permission to see the WYSIWYG editor
  (all other users automatically get the BBCode fallback editor)
- No external dependencies — the editor is bundled locally in the `hugerte/` subfolder

## Installation

1. **Copy** this folder to your e107 plugins directory:

   ```
   e107_plugins/
   └── hugerte/          ← this entire folder
   ```

2. **Install** the plugin:
   - *Admin → Plugins* → find **Hugerte** → click **Install** (or *Install/Uninstall* tab)
   - The setup class (`hugerte_setup.php`) registers the plugin in the core
     `e_parse_list` preference automatically.

3. **Select the WYSIWYG editor**:
   - *Admin → Preferences* → content editor * → *Enable WYSIWYG textareas** → save
   - (Uninstall all other editors to use the hugerte editor plugin)

4. **Configure** (optional):
   - *Admin → Plugins → Hugerte → Configure* — set the 5 plugin prefs and use the
     live preview page to test the editor.

5. **Verify** with the test matrix:

   | Test | Expected result |
   |---|---|
   | Edit a news post | Editor renders with the correct role toolbar |
   | Save | DB contains clean HTML, no empty `<p>&nbsp;</p>`, `<i>` for italic, span-strikethrough |
   | Frontend display | Content displays correctly |
   | Image / Video / Glyph buttons | Media Manager dialog opens, selection inserts at caret |
   | BBCode button | Switches to BBCode panel; **B/I/U** buttons work; switch back restores editor |
   | User without `post_html` | Core BBCode editor (unchanged) |
   | Dark mode site | Editor uses the dark skin |

6. **Uninstall the old plugin** (after sign-off):
   - *Admin → Plugins* → **tinymce4** → **Uninstall**
   - Remove the `trumbowyg` hook from the `e_parse_list` pref if it was left behind.

### Upgrading the editor

The bundled editor is a self-contained distribution. To upgrade:

1. Download the new [HugeRTE release](https://github.com/hugerte/hugerte/releases)
2. **Replace the entire `hugerte/` subfolder** with the new dist
3. Done — no other plugin file changes needed (all integration code lives outside `hugerte/`)

> ⚠️ Never edit files inside `hugerte/` — they will be overwritten on upgrade.
> All custom CSS/JS belongs in `e_header.php`, `e_footer.php` or `plugins/e107/plugin.js`.

## Configuration

### Plugin preferences (*Admin → Plugins → Hugerte → Configure*)

| Pref | Default | Description |
|---|---|---|
| `paste_as_text` | `0` | Strip formatting on paste (`paste_as_text: true`) |
| `browser_spellcheck` | `1` | Enable native browser spellcheck in the editor |
| `visualblocks` | `1` | Show Visual Blocks outlines (formatting boundaries) |
| `use_theme_style` | `0` | Also load the active theme's WYSIWYG CSS library in the editor |
| `code_highlight_class` | `prettyprint linenums` | CSS class applied to highlighted code blocks |

### PHP constants

| Constant | Default | Description |
|---|---|---|
| `e_HUGERTE_DEBUG` | *not set* | Define in `e107_config.php` to reveal the hidden `<textarea>` behind the editor when saving misbehaves |
| `e_TINYMCE_TEMPLATE` | *not set* | Force a specific toolbar template (`mainadmin` / `admin` / `member` / `public`) for debugging |

### Toolbar templates

Per-role configs live in `templates/*.xml` (HugeRTE v7 XML format). The plugin resolves
the template in this order:

1. `?config=` query override (`e_TINYMCE_TEMPLATE`)
2. Theme override: `THEME/templates/tinymce/<role>.xml` (back-compat with tinymce4 themes)
3. Plugin default: `templates/<role>.xml`

Example (`templates/member.xml`):

```xml
<hugerte name="Member" version="7">
  <plugins>advlist autolink autosave charmap code directionality link lists media table visualchars wordcount</plugins>
  <menubar>edit view insert format tools table help</menubar>
  <toolbar>undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | link image media table | code fullscreen</toolbar>
  <toolbar_mode>sliding</toolbar_mode>
  <external_plugins>e107</external_plugins>
</hugerte>
```

## Usage

### For editors (site users)

The plugin works automatically on any e107 form with a WYSIWYG area (news posts,
custom pages, mailout):

- **e107 buttons** (image / video / glyph icons) open the Media Manager; select an item
  and click **Save** — it is inserted at the caret
- **BBCode button** toggles between WYSIWYG and raw BBCode editing (with quick-insert
  buttons for all common tags); the form saves correctly even while the BBCode panel
  is open
- **Source Code** button shows the editor's HTML; on save, content is normalized
  (`<em>` → `<i>`, empty paragraphs removed) before it reaches the database

### For developers — programmatic access

The editor instance is the standard HugeRTE/TinyMCE 6/7 API:

```js
// Get the active editor
var ed = hugerte.activeEditor;

// Read / write content
var html = ed.getContent();      // serialized HTML (em already converted to i)
ed.setContent('<p>Hello</p>');
ed.insertContent('<a href="https://e107.org">link</a>');

// Sync to the bound textarea (e.g. before form submit)
hugerte.EditorManager.save();
```

### Registering your own external plugin

```js
hugerte.PluginManager.add('myplugin', function (editor, url) {
  editor.ui.registry.addButton('mybutton', {
    text: 'Mine',
    onAction: function () { editor.insertContent('<p>Hi</p>'); }
  });
});
```

Add `"myplugin"` to the `external_plugins` element of the relevant `templates/*.xml`
and point its URL via the same mechanism the `e107` plugin uses
(`wysiwyg_class.php → getExternalPlugins()`, cache-busted by file mtime).

## API Reference

### Endpoints

| Endpoint | Method | Purpose |
|---|---|---|
| `wysiwyg.php` | GET | Emits the `hugerte.init({...})` configuration (gzip/minified; readable when `perm 0`) |
| `plugins/e107/parser.php` | POST | BBCode↔HTML bridge. Params: `mode=tohtml\|tobbcode`, `content=<html/bbcode>`. Auth: `wysiwyg::isAllowed()` |
| `plugins/e107/mediamanager.php` | GET | Auth gate (`USER` + `post_html`), redirects to `e_ADMIN_ABS image.php?mode=main&action=dialog&for=<category>&iframe=1&bbcode=img\|video\|glyph` |
| `plugins/e107/emotes.php` | GET | Serves `hugerte.Resource.add('hugerte.plugins.emoticons', ...)` with the active e107 emote pack |
| `admin_config.php` | GET | Admin dispatcher: prefs UI + live editor preview |

### e_parse hooks (`e_parse.php` → class `hugerte_parse`)

| Method | Direction | What it does |
|---|---|---|
| `toWYSIWYG($text, $param)` | DB → editor | Decodes `asElementContent()` encoding first, strips `[html]…[/html]` wrappers (legacy), passes pure HTML straight through, converts BBCode via the full pipeline otherwise. Returns textarea-safe encoded HTML |
| `toDB($text, $param)` | editor → DB | Strips accumulated empty `<p>&nbsp;</p>`/`<br>` variants and converts `<em>` → `<i>` (guaranteed server-side). Runs after core `cleanHtml()` |

Both hooks are no-ops unless HugeRTE is the active editor **and** the user may post HTML.

### postMessage protocol (Media Manager iframe → parent)

| `type` | Payload | Effect |
|---|---|---|
| `hugerte-media-insert` | `{html: '<...>'}` | Parent inserts the HTML into the editor that opened the dialog (`window.__hugerte_media_editor`), normalizes glyphs, verifies via `getContent()` with a direct-DOM fallback |
| `hugerte-media-close` | — | Closes the dialog only |

### CSS hooks

| Class | Purpose |
|---|---|
| `.hugerte-media-dialog` | Tagged (at runtime) on the media-manager dialog; sized `min(70vw,1000px) × min(55vh,650px)`, full flex chain, no scrollbars, full-viewport on mobile |
| `.hugerte-media-host` | Flex host div inside the dialog body; the iframe flexes to fill it |
| `.e107-bbcode-panel` | The BBCode toggle panel (plain DOM, not a TinyMCE dialog) |

Native HugeRTE dialogs are capped at `max-width: min(500px, 95vw); max-height: 90vh`
(`e_header.php`, mirrored as a safety net in `e_footer.php`).

## File Layout

```
e107_plugins/hugerte/
├── plugin.xml                 # manifest: keywords default/editor/wysiwyg, prefs, adminLinks
├── e_header.php               # inline CSS: editor + dialog sizing (native & media), responsive
├── e_footer.php               # loads hugerte.min.js + wysiwyg.php; glue JS (dialog CSS safety
│                              #   net, italic getContent patch, form-submit safety net,
│                              #   media-dialog eDialogSave override via postMessage,
│                              #   iframe scrollbar kill, mailout/tab helpers)
├── wysiwyg.php                # emits hugerte.init({...})
├── wysiwyg_class.php          # config builder: role templates, dark-mode skin, strikethrough
│                              #   format override, external_plugins with filemtime cache-buster
├── e_parse.php                # server-side content conversion (toWYSIWYG / toDB)
├── editor.css                 # editor iframe content CSS (fa-ul, bbcode image floats, …)
├── admin_config.php           # prefs UI + live preview
├── hugerte_setup.php          # install/upgrade (e_parse_list registration)
├── templates/                 # mainadmin / admin / member / public toolbars (XML)
├── snippets/                  # bootstrap_table.htm, bootstrap_row48.htm (Template plugin)
├── langs/                     # TinyMCE 6 language packs (en_GB, nl) — global renamed to hugerte
├── languages/                 # e107 admin language files (English, Dutch)
├── images/                    # plugin icons (16/32 px)
├── plugins/e107/
│   ├── plugin.js              # external editor plugin: media buttons, BBCode toggle,
│   │                          #   strikethrough/glyph/italic fixes, SaveContent handling
│   ├── parser.php             # BBCode↔HTML bridge endpoint
│   ├── mediamanager.php       # auth + redirect to e107 Media Manager dialog
│   └── emotes.php             # e107 emote pack → hugerte.Resource
└── hugerte/                   # ★ HugeRTE 1.0.15 dist — REPLACEABLE on upgrade, never edit
    ├── hugerte.min.js
    ├── themes/silver/  icons/default/  models/dom/
    ├── plugins/… (29)  skins/ui/{oxide,oxide-dark,…}  skins/content/…
```

## Troubleshooting

| Symptom | Fix |
|---|---|
| Editor not rendering | Check *Admin → Site Settings* → WYSIWYG Editor = HugeRTE; user needs `post_html` perm; check browser console |
| Old `plugin.js` behaviour | It is cache-busted by file mtime — if in doubt, hard-refresh (Ctrl+Shift+R) |
| Saving misbehaves | Define `e_HUGERTE_DEBUG` in `e107_config.php` to reveal the underlying `<textarea>` |
| Strikethrough text disappears | Must use the span format (this plugin overrides it) — do not re-enable `<s>` output |
| Italic stored as `<em>` | `toDB()` in `e_parse.php` converts it server-side on every save |
| Media dialog scrollbar | Handled: `overflow:hidden` is injected into the iframe's html/body by `e_footer.php` |
| Wrong toolbar | Verify `templates/<role>.xml` and any `THEME/templates/tinymce/` override |

## Contributing

1. **Only modify plugin files.** All integration code lives in the plugin folder;
   never edit e107 core files — adapt to core, not the other way around.
2. **Never edit `hugerte/`** — it is a replaceable distribution. All custom CSS goes in
   `e_header.php`/`e_footer.php`, all custom JS in `plugins/e107/plugin.js` (or
   `e_footer.php` inline when a guaranteed-fresh copy is required).
3. **Keep both dialog-CSS copies in sync** — `e_header.php` and the inline safety net in
   `e_footer.php` must carry identical values (footer loads later and would override).
4. **Watch for PHP string pitfalls** — inline JS in `e_footer.php` lives in PHP
   double-quoted strings: escape apostrophes carefully and remember `$` interpolation
   (use `\$` for literal JS regex back-references where needed).
5. **Test the full round-trip** — edit → save → reload → frontend display, for:
   italic, strikethrough, glyph, media insert, BBCode toggle, dark mode, empty-paragraph
   accumulation (save/reopen several times).
6. **TinyMCE 6/7 gotchas to respect** — schema aliases `<i>`→`<em>`; `getContent()`
   strips U+FEFF-only elements (use U+200B); `windowManager.open()` ignores
   `width`/`height`/`classes` options (size via CSS); dialog body items must be
   `{type:'panel', items:[…]}`; the `bar` component's buttons need DOM-level click
   listeners (no `onAction`).

## Changelog

- **1.0** (2026-11-21 → 2026-11-24) — Initial release: HugeRTE 1.0.15 integration,
  media-manager dialogs, BBCode toggle, strikethrough/italic/glyph save fixes,
  empty-paragraph cleanup, dark mode, role-based toolbars, server-side content
  conversion, admin config + preview.

## License

- **Plugin code:** [GNU GPL v3](https://www.gnu.org/licenses/gpl-3.0.html) (e107 plugin convention)
- **Bundled editor** (`hugerte/` subfolder): [MIT](https://github.com/hugerte/hugerte) —
  HugeRTE 1.0.15, free fork of TinyMCE (Tiny Technologies, Inc.)

```
Copyright (c) 2026 e107 contributors          — plugin integration code (GPLv3)
Copyright (c) 2022 Ephox Corporation DBA Tiny Technologies, Inc.
Copyright (c) 2026 HugeRTE contributors       — bundled editor (MIT)
```
