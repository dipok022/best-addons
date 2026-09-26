/**
 * folder path → PHP namespace.
 *
 * The single place that knows the naming convention:
 *
 *   modules/{tier}/{type}/{id}/x.php
 *     → BestAddons\Feature\{Tier}\{Type}\{PascalId}\{X}
 *
 * The extractor emits a classmap, so PHP never re-derives this at runtime. It
 * exists so the convention is written down once and enforced at build time
 * rather than being folklore repeated across module folders.
 */

const UPPER = /[-_\s]+(.)?/g;

/**
 * Irregular plurals. The folder segment is the plural form, so these have to be
 * stated rather than derived by appending "s".
 */
const PLURALS = {
  widget: "widgets",
  block: "blocks",
  category: "categories",
};

const SINGULARS = Object.fromEntries(
  Object.entries(PLURALS).map(([singular, plural]) => [plural, singular]),
);

/** `widget` → `widgets`, `category` → `categories`. */
export function toPlural(type) {
  return PLURALS[type] ?? `${type}s`;
}

/** `categories` → `category`. Null when the folder segment is not a known type. */
export function toSingular(folder) {
  return SINGULARS[folder] ?? null;
}

/** `advanced-accordion` → `AdvancedAccordion`. */
export function toPascal(input) {
  return String(input)
    .replace(UPPER, (_, c) => (c ? c.toUpperCase() : ""))
    .replace(/^(.)/, (c) => c.toUpperCase());
}

/** `AdvancedAccordion` → `advanced-accordion`. Inverse of toPascal. */
export function toKebab(input) {
  return String(input)
    .replace(/([a-z0-9])([A-Z])/g, "$1-$2")
    .replace(/[\s_]+/g, "-")
    .toLowerCase();
}

/** `best-addons-free` → `BestAddonsFree`. */
export function toPascalHandle(input) {
  return toPascal(String(input).replace(/-/g, "-"));
}

/**
 * Derive the namespace a module's PHP must declare.
 *
 * @param {{tier: string, type: string, id: string}} module
 * @returns {string} e.g. `BestAddons\Feature\Free\Widgets\AdvancedAccordion`
 */
export function moduleNamespace(module) {
  return [
    "BestAddons",
    "Feature",
    toPascal(module.tier),
    toPascal(toPlural(module.type)),
    toPascal(module.id),
  ].join("\\");
}

/** True when `id` is safe for a filename, a handle, and an Elementor widget name. */
export function isKebabCase(id) {
  return typeof id === "string" && /^[a-z0-9]+(-[a-z0-9]+)*$/.test(id);
}
