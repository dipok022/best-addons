# Best Addons Widget Builder

> **Dormant.** The JavaScript half of this system has moved to
> `master-addons/dev/js/admin/widget-builder/widget-builder-react/`, and
> `Plugin::BUILDER_AVAILABLE` is `false`, so none of this is booted. The rest of
> this file documents the system as built — useful if you are porting it back,
> and misleading if you assume any of it currently runs. See
> "The JavaScript half has moved" below and `FOLDER-STRUCTURE.md`.

## 🚀 Overview

A **complete custom Elementor widget builder** system with a full-screen admin UI, better than Master Addons, ElementsKit, and WDesignKit combined.

## ✨ Features

### Core Functionality
- ✅ **Full-screen custom editor** with 5-column layout
- ✅ **30+ control types** (text, color, media, repeater, typography, dimensions, etc.)
- ✅ **Drag-drop control palette** with searchable grid
- ✅ **Live token documentation** sidebar with copy buttons
- ✅ **CodeMirror integration** for HTML/CSS/JS editing
- ✅ **Auto-save** with unsaved indicator
- ✅ **Export/Import JSON** for widget sharing
- ✅ **CDN library management** (CSS/JS includes)
- ✅ **Dynamic token parsing** `{{control_id}}`
- ✅ **Scoped CSS** per widget
- ✅ **Custom widget categories**

### Better Than Competitors
**Master Addons:** ✅ All features + improved UX  
**ElementsKit:** ✅ More control types + better UI  
**WDesignKit:** ✅ Easier setup + better performance  

**Unique to Best Addons:**
- Real-time control settings drawer
- Improved drag-drop experience
- Better token documentation
- Cleaner, modern dark UI
- More organized control palette

## 📁 File Structure

```
includes/widget-builder/
├── class-cpt.php              # CPT registration (no WP UI)
├── class-list-page.php        # Custom widget list page
├── class-editor-page.php      # Full-screen editor
├── class-admin-ui.php         # Menu + AJAX handlers
├── class-dynamic-widget.php   # Dynamic Elementor widget
└── class-loader.php           # Widget registration
```

### The JavaScript half has moved

The editor, the front-end renderer, the list screen and the shared editing engine
were copied to
`master-addons/dev/js/admin/widget-builder/widget-builder-react/` and deleted
from this plugin. That copy is not wired into the other plugin either — it sits
beside that plugin's own legacy builder, unwired, until the comparison is done.

So everything below describes where that code *used to* live here, and where it
is now:

```text
master-addons/dev/js/admin/widget-builder/widget-builder-react/
├── dev/                       # Shared editing engine. See its dev/README.md
│   ├── components/            # Palette, columns, sections, settings, toaster
│   ├── state/                 # EditorProvider, editorReducer, useToasts
│   ├── lib/                   # ids, tree
│   ├── styles/                # Design tokens + one partial per shared component
│   └── types/                 # model.ts (domain model), global.d.ts
└── assets/src/widget-builder/ # This module. See its own README
    ├── editor/                # Editor SPA         → ba-builder-editor
    ├── frontend/              # Front-end renderer → ba-builder-frontend
    └── admin/list.js          # List screen        → ba-builder-list
```

The `dev/` nesting inside that copy is deliberate: it preserves the relative
import depth between the engine and the module, so the 73 cross-references
between them still resolve exactly as they did here.

**This directory is dormant.** `Plugin::BUILDER_AVAILABLE` is `false`, so
`Cpt::init()`, `Loader::init()`, `AdminUi::init()` and the admin submenu link are
all skipped — otherwise these classes would register a `best_widget` post type
and an Elementor widget whose editor and renderer both 404, and would expose
four AJAX endpoints that save markup nothing can display. The files are kept
because the CPT, the `_ba_controls` meta contract and the AJAX surface are the
expensive half to rebuild, and they are exactly what a port back would need.
See `FOLDER-STRUCTURE.md` and `includes/Plugin.php`.

The editor and the front-end renderer are **separate entry trees** on purpose:
the split is what keeps CodeMirror and dnd-kit out of the bundle a visitor
downloads.

## 🎯 Usage

### Access Widget Builder
1. Go to **WP Admin → Best Addons → Widget Builder**
2. Click **"Add New Widget"**
3. Enter widget name and category

### Create a Widget
1. **Drag controls** from left palette into Content/Style/Advanced tabs
2. Click a control row to **edit settings** (ID, label, default, options)
3. Write **HTML template** using `{{token}}` placeholders
4. Add **CSS** (auto-scoped to your widget)
5. Add **JS** (auto-scoped with `widgetEl` variable)
6. Click **Save Settings**

### Use Your Widget
1. Set status to **"Active (Published)"**
2. Open **Elementor editor** on any page
3. Find your widget in the selected category
4. Drag it onto the page
5. Customize via the controls you created

### Export/Import
- **Export:** Click gear icon → Export Widget → Downloads JSON
- **Import:** List page → Import Widget → Drop JSON file

## 🎨 Control Types

| Type | Description |
|------|-------------|
| **Layout** | Heading, Divider, Hidden, Tabs |
| **Text** | Text, Textarea, WYSIWYG, Code |
| **Number** | Number, Slider, Dimensions |
| **Toggle** | Switcher, Choose, Visual Choice |
| **Select** | Select, Select2 |
| **Style** | Color, Typography, Font, Background, Border, Box Shadow, Text Shadow |
| **Media** | URL, Media, Gallery, Icons |
| **Advanced** | Repeater, Popover Toggle, Date Time |

## 🔧 Token System

In your HTML/CSS/JS, use `{{control_id}}` to output control values:

```html
<!-- HTML Template -->
<div class="my-widget">
  <h2 style="color: {{title_color}};">{{title}}</h2>
  <p>{{description}}</p>
  {{icon}}
</div>
```

```css
/* CSS (auto-scoped) */
.my-widget {
  padding: {{spacing}}px;
  background: {{bg_color}};
}
```

```js
// JS (widgetEl is pre-defined)
const title = widgetEl.querySelector('h2');
title.addEventListener('click', () => {
  alert('{{title}}');
});
```

## 🏗️ Architecture

### CPT-Based System
- Widgets stored as `best_widget` custom post type
- No standard WP edit UI — fully custom pages
- Meta fields: `_ba_controls`, `_ba_html`, `_ba_css`, `_ba_js`, `_ba_includes`

### Dynamic Widget Generation
- `Best_Addons_Dynamic_Widget` extends `\Elementor\Widget_Base`
- Controls registered from CPT meta on-the-fly
- Template parsing replaces tokens with setting values
- CSS scoped via selector prepending
- JS scoped via IIFE with `widgetEl` reference

### AJAX Operations
- `ba_create_widget` — Create new widget
- `ba_save_widget` — Save widget data
- `ba_delete_widget` — Delete widget
- `ba_export_widget` — Export as JSON

## 🎯 Build Process

```bash
# Development
npm run dev

# Production
npm run build
```

Vite compiles (entries are declared in `build/spine.json`):
- `widget-builder/admin/list.js` → `assets/dist/js/ba-builder-list.min.js`
- `widget-builder/editor/main.tsx` → `assets/dist/js/ba-builder-editor.min.js` + `.css`
- `widget-builder/frontend/main.tsx` → `assets/dist/js/ba-builder-frontend.min.js`

## 🎨 Dark Theme

Now defined by the design tokens in `dev/styles/_tokens.scss`, which
every partial in both folders `@use`s — so a value is declared once. Matching
Master Addons dark UI with improvements:
- Color palette: `#1a1d2e`, `#242739`, `#2d3047`, `#e11d48`
- Modern gradients and shadows
- Smooth transitions throughout
- Better contrast and readability

## 📝 Code Standards

All code follows Best Addons standards:
- ✅ Proper escaping (`esc_html()`, `esc_attr()`, `esc_url()`)
- ✅ Nonce verification on all AJAX
- ✅ Capability checks (`manage_options`)
- ✅ Input sanitization
- ✅ Detailed inline comments
- ✅ Consistent formatting

## 🚦 Next Steps

1. Run `npm run build` to compile assets
2. Go to WP Admin → Best Addons → Widget Builder
3. Create your first widget
4. Use it in Elementor!

---

**Built with ❤️ for Best Addons by Dipok Roy**
