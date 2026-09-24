# Best Addons Widget Builder

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

assets/src/js/
├── widget-builder-admin.js    # Editor JS (692 lines)
└── widget-builder-list.js     # List page JS (176 lines)

assets/src/sass/
└── widget-builder-admin.scss  # Dark theme styles (884 lines)
```

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

Vite compiles:
- `widget-builder-admin.js` → `assets/dist/js/widget-builder-admin.min.js`
- `widget-builder-list.js` → `assets/dist/js/widget-builder-list.min.js`
- `widget-builder-admin.scss` → `assets/dist/css/widget-builder-admin.min.css`

## 🎨 Dark Theme

Matching Master Addons dark UI with improvements:
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
