/**
 * Normalise one raw contract into the record the manifest stores.
 *
 * Defaults live here rather than in each module's `module.json` so that
 * contract files stay short and only carry what is genuinely specific to that
 * feature.
 */

import { readFileSync } from "node:fs";
import { basename, join } from "node:path";
import { hashModule } from "./hash.mjs";
import { assetPlan } from "./entries.mjs";
import { moduleNamespace, toKebab, toPascal } from "./namespace.mjs";

/**
 * The PHP file a module gets when its contract does not name one.
 *
 * A `category` has no `null` here on purpose: categories are pure data, so
 * requiring a PHP file for them would be a file that exists only to be required.
 */
const DEFAULT_ENTRY = {
  widget: "Widget.php",
  block: "Block.php",
  category: null,
};

/** `Widget.php` → `Widget`. `advanced-accordion.php` → `AdvancedAccordion`. */
export function classNameFor(entry) {
  return toPascal(toKebab(basename(entry, ".php")));
}

/**
 * @param {{raw: unknown, dir: string, relativeDir: string, contractPath: string, root: string}} found
 * @returns {Record<string, any>}
 */
export function normalise(found) {
  const raw = /** @type {Record<string, any>} */ (found.raw);
  const tier = raw.tier;
  const id = raw.id;

  const entry = raw.entry ?? DEFAULT_ENTRY[raw.type] ?? null;
  const hasEntry = typeof entry === "string" && entry.length > 0;

  /** Absolute paths of every file the module's hash covers. */
  const sources = {};
  for (const [kind, file] of Object.entries(raw.assets ?? {})) {
    if (typeof file === "string") sources[kind] = join(found.dir, file);
  }

  const hash = hashModule(found.contractPath, sources);
  const plan = assetPlan({ tier, id, assets: raw.assets }, found.dir, hash);

  return {
    id,
    type: raw.type,
    tier,
    title: raw.title,
    description: raw.description ?? "",
    keywords: Array.isArray(raw.keywords) ? raw.keywords : [],
    version: raw.version ?? "1.0.0",
    entry: hasEntry ? entry : null,
    className: hasEntry ? classNameFor(entry) : null,
    class: hasEntry
      ? `${moduleNamespace({ tier, type: raw.type, id })}\\${classNameFor(entry)}`
      : null,
    file: hasEntry ? `${found.relativeDir}/${entry}` : null,
    dir: found.relativeDir,
    category: raw.category ?? null,
    // Only a category module has a slug, and only `Categories::slug()` reads it.
    // Carried as null rather than omitted so the manifest's shape does not change
    // when a category is renamed.
    slug: raw.type === "category" ? (raw.slug ?? null) : null,
    icon: raw.icon ?? (raw.type === "category" ? "eicon-folder" : "eicon-default"),
    priority: Number.isInteger(raw.priority) ? raw.priority : 100,
    hash,
    assets: plan.assets,
  };
}

/**
 * Read and parse a contract. A malformed JSON file is a build error with the
 * path attached, not a stack trace from deep inside the extractor.
 *
 * @param {string} contractPath
 * @returns {unknown}
 */
export function readContract(contractPath) {
  const text = readFileSync(contractPath, "utf8");

  try {
    return JSON.parse(text);
  } catch (error) {
    throw new Error(`${contractPath}: invalid JSON — ${/** @type {Error} */ (error).message}`);
  }
}
