# `popup-builder/` — not built, and not started here

This folder is a placeholder. It documents what a **second module** would look
like, kept from the period when `assets/src/` held the widget builder alongside
it.

## Status: paused

The shared editing engine this folder was written against — the React store,
palette, columns, settings drawer and design tokens — **has left this plugin**.
It was copied to:

```text
master-addons/dev/js/admin/widget-builder/widget-builder-react/
```

along with the widget builder that consumed it. Nothing in this plugin imports
either, and the React engine is not loaded by `master-addons` yet either; that
copy exists so both implementations can be compared before a decision is made.

So the pattern this folder describes is no longer available locally. A popup
builder here would mean either:

1. **Waiting** on the `master-addons` decision — if the React engine is adopted
   there, the popup builder belongs beside it, not here. This is the likely path.
2. **Rebuilding locally** — writing a new engine in this plugin, duplicating work
   that already exists elsewhere. Only worth it if the two plugins are genuinely
   independent products.

## If you pursue (1)

Do not copy anything in here. Start from the engine's own README, which explains
what belongs in the shared half and what must stay module-specific:

- `widget-builder-react/dev/README.md` — the engine, and the rule that it must
  never import from a module
- `widget-builder-react/assets/src/widget-builder/README.md` — the module's
  layout, and why its editor and front-end renderer are separate entries

The short version: a popup builder needs its own `main.tsx`, its own persistence
and API client (popups are a post type, not `_ba_controls` meta), and its own
chrome — and it reuses the columns, the store and the design tokens. That split
is what keeps a second module cheap to add.
