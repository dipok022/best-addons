# Best Addons — Complete Folder Structure & Extractor Description

> The single document that defines where every byte of this plugin lives, how a
> folder becomes a running feature, and how to add a new surface (widget, block,
> theme-builder location, popup, header/footer) without touching framework code.

---

## 1. The one-paragraph version

Features live in **self-contained folders** under `modules/`. Each folder is
described by a `module.json` contract. The **folder extractor** (`build/extract.mjs`)
reads every contract at build time and emits one file — `manifest.json` — containing
the module list, a class→file classmap, every Vite entry point, the built asset
paths, and content hashes. At runtime WordPress **never scans a directory**: it reads
`manifest.json` once, filters by tier and by the admin's enable/disable toggles, and
loads only the PHP classes and assets that survive. Adding a feature means adding a
folder. Nothing else changes.

---

## 2. The performance thesis (why this shape)

The previous architecture did three expensive things on every request. This one
removes all three.

| Cost in the old code | Where it was | What replaces it |
| --- | --- | --- |
| `glob( $widgets_dir . '*.php' )` on every Elementor registration | `widget-registry.php` | Zero filesystem work. Module list is a decoded array from `manifest.json`. |
| `filemtime()` per widget, per request, for cache-busting (2 `stat()` calls × N widgets) | `register_production_assets()` | Build-time content hash baked into the manifest. Versions are strings, no I/O. |
| `require_once` on every widget PHP file just to check `class_exists()` | `widget-registry.php` | Classmap autoloader. Only a class that is actually instantiated gets parsed. |
| Hand-maintained `$widgets = [...]` array that drifts from the folder contents | `best-addons.php` | Vite inputs are generated *from* the manifest, so an unbuilt module is impossible. |

The result, on a page using 1 of 14 widgets: the visitor downloads that widget's
CSS/JS and **0 bytes** for the other 13, PHP parses 1 widget class, and the
front-end controller does no filesystem stats.

**Three rules preserve this. Breaking any one of them costs performance:**

1. **No `glob()`, `scandir()`, `RecursiveDirectoryIterator` or `file_exists()` on a
   request path.** If PHP needs to know something at runtime, the extractor must have
   already put it in the manifest.
2. **No eager `require_once` of module PHP.** Register a class name; let the
   classmap autoloader parse the file when the class is first touched.
3. **Assets are registered, never enqueued, by the spine.** Enqueueing happens
   through Elementor's `get_style_depends()` / `get_script_depends()` contract, so
   WordPress prints a handle only when a widget that needs it is on the page.

---

## 3. Directory tree

```text
best-addons/
│
├── best-addons.php              # Bootstrap. The ONLY PHP file WordPress loads directly.
├── uninstall.php                # Teardown: options, post meta, transients.
├── manifest.json                # ◀◀ GENERATED. The runtime's only source of truth.
├── FOLDER-STRUCTURE.md          # This document.
├── README.md                    # Install, build, and authoring guide.
├── package.json                 # npm scripts. `build` is the extractor's entry point.
├── vite.config.js               # Multi-entry build. Reads build/vite-inputs.json + spine.json.
├── tsconfig.json
│
│
├── build/                       # ◀◀ THE FOLDER EXTRACTOR
│   ├── extract.mjs              # Orchestrator. `node build/extract.mjs`
│   ├── manifest.schema.json     # The module.json contract, machine-readable.
│   ├── spine.json               # Hand-listed build entries: admin + widget builder.
│   ├── vite-inputs.json         # ◀◀ GENERATED. Module Vite inputs, from the manifest.
│   └── lib/
│       ├── discover.mjs         # Walk modules/**/module.json, sorted, deterministic.
│       ├── module.mjs           # Normalise a raw contract into a module record.
│       ├── validate.mjs         # Contract violations. Any error fails the build.
│       ├── php-classmap.mjs     # Parse PHP for namespace + class → classmap.
│       ├── entries.mjs          # Derive Vite inputs and their dist filenames.
│       ├── hash.mjs             # Content hashing for cache-busting.
│       ├── namespace.mjs        # folder path → PascalCase → PHP namespace.
│       └── report.mjs           # Human-readable build report.
│
│
├── tests/                       # Runtime harness. Stubs WP + Elementor, then asserts.
│   ├── wp-stubs.php             # WordPress function shims and the option store.
│   ├── elementor-stubs.php      # Minimal Elementor base classes.
│   └── runtime.php              # `npm run test:php`. 46 assertions over the spine.
│
├── includes/                    # ◀◀ THE SPINE. Framework only. No features live here.
│   ├── Plugin.php               # Composition root. Wires the spine together.
│   │
│   ├── Support/
│   │   ├── Paths.php            # Absolute paths + URLs. Single source for both.
│   │   ├── Manifest.php         # Reads and caches manifest.json. No I/O after boot.
│   │   └── Autoloader.php       # Manifest classmap for Feature\*, path map for the spine.
│   │
│   ├── Licensing/
│   │   ├── LicenseManager.php   # Key, status, expiry, activation, deactivation.
│   │   └── ProGate.php          # Single question the registry asks: "is Pro open?"
│   │
│   ├── Modules/
│   │   ├── Registry.php         # The runtime module registry. Built from manifest.
│   │   ├── ModuleDefinition.php # Immutable value object for one manifest entry.
│   │   ├── Enablement.php       # Per-site enable/disable state, with migrations.
│   │   ├── Module.php           # Interface every module type implements.
│   │   ├── Categories.php       # Category slugs + id→slug translation.
│   │   ├── ProGuard.php         # Trait: refuses to construct without a licence.
│   │   ├── BaseElementorWidget.php  # Abstract Elementor widget with auto-assets.
│   │   └── BaseBlock.php        # Abstract Gutenberg block with auto-assets.
│   │
│   ├── Assets/
│   │   ├── AssetPipeline.php    # Registers module assets from the manifest.
│   │   ├── SpineAssets.php      # The admin bundles, off the manifest. Builder ones are dormant.
│   │   └── AdminApp.php         # Mounts the React admin SPA, enqueues it lazily.
│   │
│   ├── Admin/
│   │   ├── AdminMenu.php        # Menu + submenu registration.
│   │   ├── OptionsPage.php      # Prints the React mount point. No markup of its own.
│   │   └── Rest/
│   │       ├── Controller.php   # Shared REST plumbing (permission + response).
│   │       ├── ModulesController.php   # GET the manifest as UI-shaped JSON.
│   │       ├── SettingsController.php  # GET/POST enablement + preferences.
│   │       └── LicenseController.php   # GET status, POST activate/deactivate.
│   │
│   ├── Integrations/
│   │   ├── Elementor/
│   │   │   ├── ElementorBridge.php    # Hooks Elementor, decides if we are active.
│   │   │   ├── CategoryRegistrar.php  # Registers panel categories from manifest.
│   │   │   └── WidgetRegistrar.php    # Instantiates `type: widget` modules.
│   │   └── Blocks/
│   │       └── BlockRegistrar.php     # Instantiates `type: block` modules.
│   │
│   └── WidgetBuilder/           # The visual widget builder (pre-existing, migrated).
│       ├── Cpt.php               # `best_widget` post type.
│       ├── DynamicWidget.php     # Builds an Elementor widget from CPT data.
│       ├── Loader.php            # Registers published builder widgets.
│       ├── AdminUi.php           # Menus, AJAX handlers, asset enqueue.
│       ├── ListPage.php          # Widget list screen.
│       └── EditorPage.php        # Full-screen React editor page (prints + exits).
│
│
├── modules/                     # ◀◀ EVERY FEATURE. Nothing outside this tree ships features.
│   ├── free/
│   │   ├── widgets/
│   │   │   ├── advanced-accordion/
│   │   │   │   ├── module.json  # The contract.
│   │   │   │   ├── Widget.php   # The class. Extends BaseElementorWidget.
│   │   │   │   ├── style.scss   # Front-end styles.
│   │   │   │   ├── view.ts      # Front-end behaviour. Optional.
│   │   │   │   └── editor.ts    # Elementor-editor behaviour. Optional.
│   │   │   └── best-accordion/
│   │   │       └── …
│   │   └── blocks/
│   │       └── sample-box/
│   │           └── …
│   └── pro/
│       └── widgets/
│           └── advanced-tabs/
│               └── …
│
│
├── assets/
│   ├── admin/                   # ◀◀ The React admin SPA (options panel).
│   │   ├── index.html           # Vite entry.
│   │   └── src/
│   │       ├── main.tsx         # Mounts #ba-admin-root.
│   │       ├── app/
│   │       │   ├── App.tsx      # Shell + hash router.
│   │       │   ├── Shell.tsx    # Sidebar chrome.
│   │       │   └── routes/
│   │       │       ├── ModulesRoute.tsx
│   │       │       ├── LicenseRoute.tsx
│   │       │       └── SettingsRoute.tsx
│   │       ├── components/      # Button, Card, Toggle, Badge, Field, Notice…
│   │       ├── hooks/           # useModules, useSettings, useLicense, useHashRoute
│   │       ├── lib/             # api client, types, format helpers
│   │       └── styles/          # _tokens.scss, _base.scss, components/
│   │
│   ├── editor/                  # React app for the visual widget builder.
│   ├── frontend/                # React renderer for builder-made widgets.
│   ├── src/js|scss              # Legacy sources, retained for the retiring bundles.
│   └── dist/                    # ◀◀ GENERATED, git-tracked. Never hand-edited.
│       ├── css/
│       └── js/
│
└── languages/                   # Translations for spine + module strings.
```

---

## 4. The module contract — `module.json`

This is the only thing the extractor reads. Everything else about a module
(classes, assets, panels) is *derived*, not declared twice.

```jsonc
{
  "$schema": "../../../../build/manifest.schema.json",

  "id": "advanced-accordion",          // kebab-case, unique plugin-wide. Required.
  "type": "widget",                    // widget | block | category. Required.
  "tier": "free",                      // free | pro. Required. Must match the folder.
  "title": "Advanced Accordion",       // Shown in the panel. Required.
  "description": "…",                  // Shown in the options panel + search.
  "keywords": ["accordion", "faq"],    // Powers the panel's search box.
  "version": "1.0.0",

  "entry": "Widget.php",               // The PHP class. Defaults to Widget.php.
  "category": "free",                  // The *id* of a category module, not a slug.
  "icon": "eicon-accordion",           // Elementor icon class.
  "priority": 10,                      // Panel ordering. Lower comes first.

  "assets": {
    "style":  "style.scss",            // Front-end CSS.  Optional.
    "script": "view.ts",               // Front-end JS.   Optional.
    "editor": "editor.ts"              // Editor-only JS. Optional. Never on the front end.
  }
}
```

A `type: category` module is pure data — it has no `entry` and no class — and it
declares the one thing a widget cannot derive for itself:

```jsonc
{
  "id": "free",                        // The id widgets point at:  "category": "free".
  "slug": "best-addons-free",          // The slug Elementor registers under.
  "type": "category",
  "tier": "free",
  "title": "Best Addons — Free",
  "priority": 10
}
```

`id` and `slug` are separate on purpose. A widget names a category by `id`,
because that is a reference the extractor can check against the folders actually
on disk; Elementor needs the `slug`. `Categories::slug()` is the single place the
two are joined, so renaming a category's slug never touches the widgets in it.
The extractor rejects a category whose slug is missing or equal to its id — that
combination registers a category no widget can find, which fails as a silently
invisible widget rather than an error.

**What the module author does *not* write, and never should:**

- Asset URLs. Derived from the id and the Vite output convention.
- Asset handles. Generated: `ba-{tier}-{id}-style`, `ba-{tier}-{id}-script`, …
- Cache-busting versions. Derived from source content hashes.
- `get_style_depends()` / `get_script_depends()`. Injected by `BaseElementorWidget`.
- Namespace declarations. Derived from the folder path.
- A `register()` call. Dispatched by type through the Registry.

This is the entire point of the extractor: **one source of truth per module,
declared once.** A second place to update is a second place to forget.

---

## 5. Derivation rules

The extractor applies these mechanically. They are the contract; the code is a
direct transcription of this table.

### 5.1 Folder → namespace

```text
modules/{tier}/{type}s/{id}/x.php
      │         │       │   └── namespace  BestAddons\Feature\{Tier}\{Types}\{Pascal}\{X}
      │         │       └────── Pascal(id)  advanced-accordion → AdvancedAccordion
      │         └────────────── Title(type) widget → Widget,  block → Block
      └──────────────────────── Title(tier) free → Free,      pro → Pro
```

The type folder is always **plural** on disk (`widgets/`, `blocks/`,
`categories/`) and plural-PascalCase in the namespace, so the namespace reads as a
collection. The extractor derives both from `type`; there is no override, which
means a module cannot claim a namespace that disagrees with its folder.

A `type: category` module is the exception that proves the rule: it has no PHP at
all, so it contributes no namespace segment and no classmap entry.

The **classmap** the extractor emits is authoritative — the namespace is a naming
convention for humans, not something PHP re-derives. That is deliberate: it means a
renamed folder can never break a class reference at runtime.

### 5.2 Module → Vite entry

Each declared asset becomes one Rollup entry. The output filename is
predictable, because PHP has to enqueue by path and a content hash would force
WordPress to guess on every deploy.

| Declared       | Entry name                    | Emitted file                     |
| -------------- | ----------------------------- | -------------------------------- |
| `style`        | `{tier}-{id}-style`           | `css/{tier}-{id}-style.min.css`  |
| `script`       | `{tier}-{id}-script`          | `js/{tier}-{id}-script.min.js`   |
| `editor`       | `{tier}-{id}-editor`          | `js/{tier}-{id}-editor.min.js`   |

Including the tier in the filename means a Free and a Pro module can never collide
on an output path, and a Pro bundle is trivially separable when you build a
free-only zip.

### 5.3 Module → WordPress handles

```text
style  → ba-{tier}-{id}-style
script → ba-{tier}-{id}-script
editor → ba-{tier}-{id}-editor
```

### 5.4 Module → content hash

`sha256` over the module's own `module.json` + every declared source file, truncated
to 10 hex chars. This is the `ver` argument on every enqueue. Edit one SCSS line →
one new version string → that widget's CSS busts cache, nothing else does.

---

## 6. Runtime data flow

```text
        BUILD TIME                                    REQUEST TIME
 ─────────────────────────────────      ──────────────────────────────────────────
 build/extract.mjs                                best-addons.php
   │                                               │  require once
   ├── discover  modules/**/module.json            ▼
   │      │                                    includes/Plugin.php
   ├── validate  contract + FS reality           │  register autoloader (classmap)
   │      │                                     │  Manifest::boot()  ← one JSON read
   ├── classmap  scan PHP → FQCN ⇒ path          │      │
   │      │                                     │      ▼
   ├── entries   → build/vite-inputs.json        │  Registry::boot()
   │      │                                     │      ├─ ProGate: drop tier=pro if locked
   │      │                                     │      ├─ Enablement: drop disabled ids
   │      │                                     │      └─ keep type=widget only if Elementor up
   ▼                                             │
 manifest.json                                   ▼
   ▲                                    ElementorBridge → WidgetRegistrar
   │                                             │  new $fqcn()  ← autoloader parses one file
 vite.config.js                                  ▼
   │  reads manifest for inputs              AssetPipeline registers handles
   ▼                                             │
 assets/dist/**                                 ▼
                                            Elementor prints only the
                                            handles a used widget asked for
```

### The three filters, in order

1. **Tier.** `tier: "pro"` modules are dropped unless `ProGate::allows()`. A locked
   Pro module's PHP is never parsed, and its assets are never registered.
2. **Enablement.** Dropped if the site owner toggled it off in the options panel.
   The default for a newly discovered module is *enabled*, so shipping an update
   that adds a widget never requires a settings migration.
3. **Platform.** `type: "widget"` modules only load when Elementor is active;
   `type: "block"` only when the block editor is. Neither loads on a page that
   does not need it.

A module must survive all three before a single line of its PHP is parsed.

---

## 7. `manifest.json` shape

```jsonc
{
  "version": 2,                 // Bumped when the shape changes. PHP refuses unknown majors.
  "generated": 1758768000,      // Build timestamp. Diagnostics only; never a version.
  "plugin": "1.0.0",
  "modules": [
    {
      "id": "advanced-accordion",
      "type": "widget",
      "tier": "free",
      "title": "Advanced Accordion",
      "class": "BestAddons\\Feature\\Free\\Widgets\\AdvancedAccordion\\Widget",
      "file": "modules/free/widgets/advanced-accordion/Widget.php",
      "category": "free",       // A category module *id*; see §4.
      "slug": null,             // Only a type:category module carries a slug.
      "icon": "eicon-accordion",
      "priority": 10,
      "hash": "3f9a1c02be",
      "assets": {
        "style":  { "handle": "ba-free-advanced-accordion-style",  "file": "css/free-advanced-accordion-style.min.css" },
        "script": { "handle": "ba-free-advanced-accordion-script", "file": "js/free-advanced-accordion-script.min.js" }
      }
    }
  ],
  "classmap": {
    "BestAddons\\Feature\\Free\\Widgets\\AdvancedAccordion\\Widget":
      "modules/free/widgets/advanced-accordion/Widget.php"
  },
  "entries": { "free-advanced-accordion-style": "…/style.scss" },
  "stats": { "modules": 3, "free": 2, "pro": 1, "classes": 3 }
}
```

---

## 8. How to add a feature

### 8.1 A new Elementor widget (Free)

```bash
mkdir -p modules/free/widgets/my-widget
cp modules/free/widgets/advanced-accordion/module.json modules/free/widgets/my-widget/
```

Edit the contract (`id`, `title`, `icon`, `entry`, `assets`). Write
`Widget.php` extending `BaseElementorWidget` — you get asset injection, the
namespace, and the `best_addons_{id}` widget name for free. Then:

```bash
npm run extract   # regenerate manifest.json — this is the only wiring step
npm run dev
```

The widget is in the panel. No framework file was edited.

### 8.2 A Pro module

Identical, except `"tier": "pro"` and it uses `ProGuard` in its constructor. It is
invisible and unparsed until a license is active, at which point the options panel
unlocks it — same code path, no re-registration.

### 8.3 The surfaces named in the brief

All three are **new module `type`s**, not new framework subsystems. Each needs:

1. A `type` in the schema enum.
2. A `*Registrar` in `includes/Integrations/` implementing one method, dispatched
   by `Registry` for that type.
3. Its own folder tree under `modules/`.

| Surface                | `type`             | Registrar            | Notes |
| ---------------------- | ------------------ | -------------------- | ----- |
| Elementor widget       | `widget` ✅ shipped | `WidgetRegistrar`    | Reference implementation. |
| Gutenberg block        | `block` ✅ shipped  | `BlockRegistrar`     | Reference implementation. |
| Panel category         | `category` ✅ shipped | `CategoryRegistrar` | Pure data, no class. Joins the same Pro gate and Enablement filter as everything else. |
| Theme Builder location | `theme-location`   | `ThemeLocationRegistrar` | Renders a React preview + a PHP `render_location`. The manifest already carries `title`/`icon`, which is all an Elementor location needs. |
| Popup                  | `popup`            | `PopupRegistrar`     | A CPT-backed module. Reuses the `WidgetBuilder` storage pattern; the popup's inner layout is a `type: "popup"` module or a builder widget. |
| Header / footer        | `theme-location` with `"location": "header" \| "footer"` | same | Locations are data, not code. A header is a `theme-location` module pinned to the `header` location. |

The brief's remaining surfaces are deliberately **not** pre-built here: the spine
does not know they exist yet, and adding them is the exact operation this document
describes. The widget and block registrars are shipped as worked examples — copy
their shape.

---

## 9. Invariants the extractor enforces

The build **fails**, loudly, on any of these. A broken manifest is worse than no
manifest, so it never ships.

1. Duplicate module `id` anywhere in the tree.
2. Missing or malformed `module.json`; unknown `type` or `tier`.
3. A declared asset file that does not exist on disk.
4. A declared `entry` PHP file that is missing, or declares no class, or declares a
   class whose name does not match the folder-derived namespace.
5. Two modules whose derived Vite entry names collide.
6. An `id` that is not kebab-case, or contains characters unsafe for a filename.
7. A `category` that is not also declared as a category module.
8. A `tier` that disagrees with the folder it sits in (`modules/free/**` may not
   declare `"tier": "pro"`). A module that lies about its tier would load for an
   unlicensed site.
9. A `type: category` module with no `slug`, a non-kebab-case `slug`, or a `slug`
   equal to its own `id`.
10. A `slug` on anything that is not a category module, or an `entry` on a category
    module (a category is data, and an entry there is a copy-paste slip).
11. An unknown key in `module.json`. The contract is closed: a typo like `"titel"`
    is an error, not a silently ignored line.

Rule 7 is what makes the panel's category list self-maintaining: categories are
themselves modules, so a new category is a folder, not a hardcoded `add_category()`
call. Rules 8–10 keep that mechanism honest — a category only reaches the panel by
passing the same Pro gate and Enablement filter as every other module, which is
why `Categories::all()` reads the *registry* and not the manifest.

---

## 10. Extending the extractor

`build/lib/` is split so a new concern is a new file, not a bigger one:

| File                  | Owns                                          |
| --------------------- | --------------------------------------------- |
| `discover.mjs`        | Finding candidate folders.                    |
| `validate.mjs`        | Failing the build. Add rules here.            |
| `module.mjs`          | Normalising one contract → one record.        |
| `php-classmap.mjs`    | The PHP parser. The only file that reads PHP. |
| `entries.mjs`         | Naming Vite inputs and outputs.               |
| `namespace.mjs`       | folder path → PHP namespace.                  |
| `hash.mjs`            | Content hashing.                              |
| `report.mjs`          | The console table.                            |

`extract.mjs` orchestrates and owns no rules of its own. If you need a new rule,
it belongs in one of the eight files above — never inline in the orchestrator.

---

## 11. Legacy → module migration map

The pre-extractor layout is fully absorbed. Nothing was left behind.

| Old path                          | New path                                        |
| --------------------------------- | ----------------------------------------------- |
| `widgets/*.php`                   | `modules/free/widgets/{id}/Widget.php`          |
| `widget-registry.php`             | `includes/Modules/Registry.php`                 |
| `admin-settings.php`              | `includes/Admin/OptionsPage.php` + React panel  |
| `includes/widget-builder/class-*.php` | `includes/WidgetBuilder/*.php` (namespaced) |
| `assets/src/js/*.js` (widget code)| `modules/{tier}/{type}s/{id}/view.ts`            |
| `assets/src/sass/*.scss` (widget) | `modules/{tier}/{type}s/{id}/style.scss`         |
| `assets/src/editor/`              | `assets/src/widget-builder/editor/` — since moved out |
| `assets/src/frontend/`            | `assets/src/widget-builder/frontend/` — since moved out |
| `assets/src/js/widget-builder-list.js` | `assets/src/widget-builder/admin/list.js` — since moved out |
| `assets/src/js/widget-builder-admin.js` | *deleted* — see below                     |

The legacy jQuery builder UI (`widget-builder-admin.js` plus its 2,167-line
stylesheet) was referenced by nothing: the live list entry was
`widget-builder/admin/list.js`, a separate and much smaller file. Both were
removed rather than relocated, so the source tree no longer implies a second
editor that cannot run.

`assets/src/` is organised **by module, not by file type** — one folder per
module, with shared product-agnostic code kept out of it entirely. That shared
engine used to live in top-level `dev/`, beside `includes/` and `modules/`,
under a one-way rule: a module could import `dev/`, never the reverse, enforced
by `assets/src/layout.test.mjs`. Both of those are gone now — see below.

## The widget builder has moved out

`dev/` and `assets/src/widget-builder/` were copied to
`master-addons/dev/js/admin/widget-builder/widget-builder-react/` and deleted
here. The copy is not wired into that plugin either; it sits alongside that
plugin's own legacy builder so the two can be compared before anyone commits.
The nesting in the copy is deliberate — it preserves the relative import depth
between the engine and the module, so all 73 cross-references still resolve.

What that leaves behind, and why:

| Left behind                     | Why it stayed                                                                                   |
| ------------------------------- | ---------------------------------------------------------------------------------------------- |
| `includes/WidgetBuilder/*.php`  | The CPT, the `_ba_controls` meta contract and the AJAX surface — the expensive half to rebuild.   |
| `SpineAssets::BUILDER_*`        | Referenced only from those classes, and nothing enqueues them while the flag below is `false`.   |
| `assets/src/types/global.d.ts`  | The `Window.elementorFrontend` augmentation. Module views compile against it, so it is not the builder's. |
| `assets/src/popup-builder/`     | A README-only placeholder, now paused — the engine it described left with the builder.            |

Those four `BUILDER_*` constants point at bundles this plugin no longer builds,
which would 404 — `DynamicWidget` is the one that matters, because it is the
front-end renderer, so a site already using a saved builder widget would lose
its output. So the server half is gated rather than deleted:

```php
Plugin::BUILDER_AVAILABLE   // includes/Plugin.php
```

`false` skips `Cpt::init()`, `Loader::init()`, `AdminUi::init()` and the admin
submenu link — the last one because a link with no screen behind it 404s too.
Nothing is deleted, and flipping the flag back to `true` re-enables all of it.
`tests/runtime.php` asserts the flag and the bundles agree, so restoring the
flag without restoring the bundles fails the suite rather than shipping a
half-wired builder.

