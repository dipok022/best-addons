/**
 * Contract enforcement.
 *
 * A broken manifest is worse than no manifest: the runtime would register assets
 * that do not exist and autoload classes that are not there, and the failure
 * would surface on a visitor's page instead of on the build machine. So every
 * rule here is a hard build failure.
 *
 * Problems are collected rather than thrown one at a time — a single run should
 * report everything that needs fixing.
 */

import { existsSync, statSync } from "node:fs";
import { isKebabCase, moduleNamespace, toPlural } from "./namespace.mjs";
import { acceptsExtension, entryName, ASSET_KINDS } from "./entries.mjs";

const TYPES = new Set(["widget", "block", "category"]);
const TIERS = new Set(["free", "pro"]);

/** Thrown when one or more contract rules are violated. */
export class ContractError extends Error {
  /** @param {string[]} problems */
  constructor(problems) {
    super(
      `Module contract validation failed with ${problems.length} problem(s):\n` +
        problems.map((p) => `  • ${p}`).join("\n"),
    );
    this.name = "ContractError";
    this.problems = problems;
  }
}

const isNonEmptyString = (v) => typeof v === "string" && v.trim().length > 0;

/**
 * @param {unknown} raw Parsed module.json.
 * @param {string} relativeDir e.g. `free/widgets/advanced-accordion`
 * @param {string[]} problems Accumulator.
 */
function validateShape(raw, relativeDir, problems) {
  const where = relativeDir;

  if (raw === null || typeof raw !== "object" || Array.isArray(raw)) {
    problems.push(`${where}: module.json must be a JSON object.`);
    return false;
  }

  const module = /** @type {Record<string, any>} */ (raw);
  const known = new Set([
    "$schema",
    "id",
    "slug",
    "type",
    "tier",
    "title",
    "description",
    "keywords",
    "version",
    "entry",
    "category",
    "icon",
    "priority",
    "assets",
  ]);

  for (const key of Object.keys(module)) {
    if (!known.has(key)) {
      problems.push(
        `${where}: unknown key "${key}". Add it to build/manifest.schema.json if it is intentional.`,
      );
    }
  }

  for (const key of ["id", "type", "tier", "title"]) {
    if (!isNonEmptyString(module[key])) {
      problems.push(`${where}: "${key}" is required and must be a non-empty string.`);
    }
  }

  if (isNonEmptyString(module.id) && !isKebabCase(module.id)) {
    problems.push(
      `${where}: id "${module.id}" must be kebab-case (lowercase words joined by single hyphens).`,
    );
  }

  if (isNonEmptyString(module.type) && !TYPES.has(module.type)) {
    problems.push(
      `${where}: unknown type "${module.type}". Expected one of: ${[...TYPES].join(", ")}.`,
    );
  }

  if (isNonEmptyString(module.tier) && !TIERS.has(module.tier)) {
    problems.push(`${where}: unknown tier "${module.tier}". Expected free or pro.`);
  }

  if (module.keywords !== undefined && !Array.isArray(module.keywords)) {
    problems.push(`${where}: "keywords" must be an array of strings.`);
  }

  if (module.priority !== undefined && !Number.isInteger(module.priority)) {
    problems.push(`${where}: "priority" must be an integer.`);
  }

  if (module.assets !== undefined) {
    if (module.assets === null || typeof module.assets !== "object" || Array.isArray(module.assets)) {
      problems.push(`${where}: "assets" must be an object.`);
    } else {
      for (const [kind, value] of Object.entries(module.assets)) {
        if (!ASSET_KINDS.includes(kind)) {
          problems.push(
            `${where}: unknown asset kind "${kind}". Expected one of: ${ASSET_KINDS.join(", ")}.`,
          );
        } else if (!isNonEmptyString(value)) {
          problems.push(`${where}: assets.${kind} must be a non-empty string.`);
        } else if (!acceptsExtension(kind, value)) {
          problems.push(
            `${where}: assets.${kind} "${value}" has an extension this kind does not accept.`,
          );
        }
      }
    }
  }

  if (isNonEmptyString(module.type) && module.type === "widget" && !isNonEmptyString(module.category)) {
    problems.push(`${where}: a type=widget module must declare a "category".`);
  }

  return true;
}

/**
 * Rules 3–5: the contract's promises must match the disk.
 *
 * Takes both the raw contract and the normalised module on purpose. The raw
 * contract is what the author wrote — the source file names, the declared entry
 * — and that is what must be checked against the disk. The normalised module has
 * already had its assets replaced by the resolved build plan, so reading
 * `assets` from it would compare a filename against a description of a file.
 *
 * @param {Record<string, any>} raw Parsed module.json.
 * @param {Record<string, any>} module Normalised module.
 * @param {{dir: string, relativeDir: string}} found
 * @param {Set<string>} seenIds
 * @param {Set<string>} seenEntries
 * @param {Record<string,string>} classmap
 * @param {string[]} problems
 */
function validateReality(raw, module, found, seenIds, seenEntries, classmap, problems) {
  const where = found.relativeDir;

  if (seenIds.has(module.id)) {
    problems.push(`${where}: duplicate id "${module.id}". Ids must be unique plugin-wide.`);
  }
  seenIds.add(module.id);

  // The folder path must agree with the contract, or the derived namespace lies.
  const segments = found.relativeDir.split("/");
  if (segments.length < 3) {
    problems.push(
      `${where}: modules must sit at modules/{tier}/{type}/{id}/ so the namespace can be derived.`,
    );
  } else {
    const [folderTier, folderType, folderId] = segments;
    if (folderTier !== module.tier) {
      // Without this, a module could live in modules/free/ while declaring
      // "tier": "pro" — the gate would follow the contract, so the folder a
      // developer is looking at would tell them the opposite of the truth.
      problems.push(
        `${where}: folder is under tier "${folderTier}" but module.json says "${module.tier}".`,
      );
    }
    if (folderType !== toPlural(module.type)) {
      problems.push(
        `${where}: folder says type "${folderType}" but module.json says "${module.type}" (expected folder "${toPlural(module.type)}").`,
      );
    }
    if (folderId !== module.id) {
      problems.push(`${where}: folder is named "${folderId}" but module.json id is "${module.id}".`);
    }
  }

  if (module.type !== "category" && !raw.entry && !module.entry) {
    problems.push(
      `${where}: a type=${module.type} module must declare an "entry" PHP file, or inherit the default.`,
    );
  }

  // A category module has to say which slug it registers under, because that slug
  // is what a widget's `get_categories()` returns. Without this rule a category
  // could be registered under its module id and no widget would ever find it —
  // a failure that shows up as an invisible widget in the panel.
  if (module.type === "category") {
    if (!isNonEmptyString(raw.slug)) {
      problems.push(
        `${where}: a type=category module must declare a "slug" — the category id widgets will point at.`,
      );
    } else if (!isKebabCase(raw.slug)) {
      problems.push(`${where}: slug "${raw.slug}" must be kebab-case.`);
    } else if (raw.slug === module.id) {
      problems.push(
        `${where}: slug "${raw.slug}" is the same as the module id. Prefix it, e.g. "best-addons-${raw.slug}".`,
      );
    }
  } else if (raw.slug !== undefined) {
    problems.push(`${where}: "slug" only applies to a type=category module.`);
  }

  // Rule 3: declared sources must exist.
  for (const kind of ASSET_KINDS) {
    const declared = raw.assets?.[kind];
    if (!isNonEmptyString(declared)) {
      if (declared !== undefined) {
        problems.push(`${where}: assets.${kind} must be a file name, not ${typeof declared}.`);
      }
      continue;
    }

    const path = `${found.dir}/${declared}`;
    if (!existsSync(path) || !statSync(path).isFile()) {
      problems.push(`${where}: assets.${kind} points at "${declared}", which does not exist.`);
    }
  }

  // Rule 5: derived entry names must not collide.
  for (const kind of ASSET_KINDS) {
    if (!raw.assets?.[kind]) continue;
    const name = entryName(module.tier, module.id, kind);
    if (seenEntries.has(name)) {
      problems.push(`${where}: derived entry "${name}" collides with another module.`);
    }
    seenEntries.add(name);
  }

  // Rule 4: the entry class must exist and match the derived namespace. A module
  // with no entry is pure data and legitimately has no class — but a *category*
  // that declares one is almost always a copy-paste slip, and left alone it
  // produces a confusing "class not found" about a category.
  if (module.type === "category" && raw.entry) {
    problems.push(
      `${where}: a type=category module is pure data and must not declare an "entry".`,
    );
  }

  if (!module.entry) return;

  if (!existsSync(`${found.dir}/${module.entry}`)) {
    problems.push(`${where}: entry "${module.entry}" does not exist.`);
  }

  const expectedNamespace = moduleNamespace(module);
  const expectedClass = `${expectedNamespace}\\${module.className}`;

  if (!classmap[expectedClass]) {
    problems.push(
      `${where}: expected class ${expectedClass} was not found. ` +
        `Check that ${module.entry} declares \`namespace ${expectedNamespace};\` and \`class ${module.className}\`.`,
    );
  }

  const declared = classmap[expectedClass];
  if (declared && !declared.endsWith(`/${module.entry}`) && declared !== module.entry) {
    problems.push(
      `${where}: class ${expectedClass} is declared in "${declared}" but module.json entry is "${module.entry}".`,
    );
  }
}

/**
 * Rule 7: every category a widget points at must exist as a `type: category`
 * module, so the panel's category list is maintained by the same extractor
 * instead of a hardcoded `add_category()` call.
 *
 * @param {Array<Record<string, any>>} modules
 * @param {string[]} problems
 */
function validateCategories(modules, problems) {
  const declared = new Set(
    modules.filter((m) => m.type === "category").map((m) => m.id),
  );

  for (const module of modules) {
    if (module.type !== "widget" || !module.category) continue;
    if (!declared.has(module.category)) {
      problems.push(
        `${module.relativeDir}: category "${module.category}" is not declared by any type=category module. ` +
          `Add modules/{tier}/${toPlural("category")}/${module.category}/module.json.`,
      );
    }
  }
}

/**
 * Run every rule.
 *
 * @param {Array<{module: Record<string,any>, dir: string, relativeDir: string, classmap: Record<string,string>}>} discovered
 * @returns {string[]} The declared categories, for the manifest.
 */
export function validate(discovered) {
  /** @type {string[]} */
  const problems = [];
  const seenIds = new Set();
  const seenEntries = new Set();

  const modules = [];

  for (const item of discovered) {
    if (!validateShape(item.raw, item.relativeDir, problems)) continue;

    const module = item.module;
    modules.push(module);

    validateReality(item.raw, item.module, item, seenIds, seenEntries, item.classmap, problems);
  }

  validateCategories(modules, problems);

  if (problems.length > 0) throw new ContractError(problems);

  return [...new Set(modules.filter((m) => m.type === "widget").map((m) => m.category))].sort();
}
