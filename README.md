# ⚡ Best Addons for Elementor

A high-performance, production-optimized collection of custom elements and extensions engineered cleanly for the Elementor Page Builder ecosystem.

## 🚀 Key Architectural Advantages

- **Multi-Entry Asset Splitting:** Powered by Vite. Every widget compiles down to its own isolated CSS/JS chunks.
- **On-Demand Performance Delivery:** Leverages Elementor's native dependency hooks (`get_style_depends` & `get_script_depends`). Visitors download **0 bytes** of unused asset code.
- **Granular Optimization Panel:** Built-in WordPress admin dashboard allows site owners to turn off individual elements. Deactivated features are completely skipped during PHP directory scanning.
- **Scalable Autoloader Registry:** Built with an open-closed architectural pattern. Drop a new class file inside the `widgets/` directory, map it to the array, and it boots up instantly.

---

## 📂 Directory Layout Blueprint

```text
best-addons/
├── admin-settings.php     # Admin dashboard toggle control layer
├── assets/
│   ├── dist/              # Fully minified production files (Git-tracked)
│   │   ├── css/           # Extracted standalone widget style maps
│   │   └── js/            # Isolated component functional layers
│   └── src/               # Raw development source code
│       ├── js/            # Modular ES6 JS modules
│       └── sass/          # Component-scoped SASS stylesheets
├── best-addons.php        # Core plugin bootstrap loader
├── widget-registry.php    # Dynamic layout array parsing registry
└── widgets/               # Modular PHP widget block files
```

---

## 🛠️ Local Development Setup Guide

### 1. Requirements

Ensure you have the following installed on your local environment machine:

- **Node.js** (v18.0.0 or higher recommended)
- **PHP** (v7.4 or higher)
- **Elementor** (Active on your target WordPress site)

### 2. Dependency Installation

Navigate to your plugin directory inside your local terminal panel and run:

```bash
npm install
```

### 3. Continuous File Monitoring Setup (Watch Mode)

To stay active while writing code, run the Vite watch engine block. Hitting `Ctrl + S` will auto-compile your SASS/JS modules instantly in real-time:

```bash
npm run dev
```

### 4. Compiling a Production Build

Right before zipping your plugin configurations up for commercial deployment or final delivery, run the optimization compilation extractor script:

```bash
npm run prod
```

---

## 🧩 Included Custom Components

1. **Sample Content Box:** A lightweight text wrapper component built to demonstrate clean DOM injection pipelines.
2. **Advanced Accordion:** A feature-rich accordion grid system featuring 5 modular aesthetic design presets and automated multi-open tracking behavior toggles.

---

## 🧑‍💻 Author and Licensing Credits

- **Lead Software Engineer:** Dipok Roy
- **License:** GPL v2.0 or later
- **Text Domain:** `best-addons`

---

_Engineered cleanly with peak performance and low server cost footprints in mind._
