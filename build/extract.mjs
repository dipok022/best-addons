/**
 * The folder extractor.
 *
 *   node build/extract.mjs           # write manifest.json + build/vite-inputs.json
 *   node build/extract.mjs --check   # verify the manifest is current, write nothing
 *
 * Reads every `module.json` under `modules/`, validates each one against the
 * contract, derives everything the runtime needs, and writes it to one file.
 * Owns no rules of its own — orchestration only.
 */

import { writeFileSync, readFileSync, existsSync } from "node:fs";
import { dirname, join, relative, resolve, sep } from "node:path";
import { fileURLToPath } from "node:url";

import { discover, phpFilesIn } from "./lib/discover.mjs";
import { readContract, normalise } from "./lib/module.mjs";
import { validate, ContractError } from "./lib/validate.mjs";
import { classmapForFiles } from "./lib/php-classmap.mjs";
import { distDirFor, ASSET_KINDS } from "./lib/entries.mjs";
import { report } from "./lib/report.mjs";

const HERE = dirname(fileURLToPath(import.meta.url));
export const ROOT = resolve(HERE, "..");
const MODULES_DIR = join(ROOT, "modules");
const MANIFEST_PATH = join(ROOT, "manifest.json");
const INPUTS_PATH = join(ROOT, "build", "vite-inputs.json");

/** Bump when the manifest's shape changes. `includes/Support/Manifest.php` refuses an unknown major. */
const MANIFEST_VERSION = 2;

const toPosix = (p) => p.split(sep).join("/");

/**
 * Run the extraction.
 *
 * @param {{check?: boolean, pluginVersion?: string}} options
 * @returns {{modules: Array<Record<string,any>>, categories: string[], stats: Record<string, number>, manifestPath: string, inputsPath: string, dropped: string[]}}
 */
export function extract(options = {}) {
  const check = options.check === true;
  const pluginVersion = options.pluginVersion ?? readPluginVersion();

  /** @type {string[]} */
  const dropped = [];

  const found = discover(MODULES_DIR);

  // ── Read, parse and normalise ─────────────────────────────────────────────
  const discovered = found.map((item) => {
    const raw = readContract(item.contractPath);
    const module = normalise({ ...item, raw, root: ROOT });
    return { ...item, raw, module };
  });

  // ── Classmap: every PHP file in every module folder ───────────────────────
  /** @type {Record<string, string>} */
  const classmap = {};
  for (const item of found) {
    Object.assign(classmap, classmapForFiles(phpFilesIn(item.dir), ROOT));
  }

  // ── Validate (throws ContractError listing every problem) ─────────────────
  const categories = validate(
    discovered.map((item) => ({ ...item, classmap })),
  );

  // ── Derive the manifest ───────────────────────────────────────────────────
  const modules = discovered
    .map((item) => item.module)
    .sort((a, b) => a.priority - b.priority || a.id.localeCompare(b.id));

  // A module that declares no assets and no editor behaviour is almost always an
  // unfinished contract rather than a deliberate choice.
  for (const module of modules) {
    if (module.type !== "category" && Object.keys(module.assets).length === 0) {
      dropped.push(`${module.dir}: declares no assets. If that is intentional, ignore this.`);
    }
  }

  const manifest = {
    version: MANIFEST_VERSION,
    generated: Math.floor(Date.now() / 1000),
    plugin: pluginVersion,
    modules,
    classmap,
    categories,
    stats: {
      modules: modules.length,
      free: modules.filter((m) => m.tier === "free").length,
      pro: modules.filter((m) => m.tier === "pro").length,
      widgets: modules.filter((m) => m.type === "widget").length,
      blocks: modules.filter((m) => m.type === "block").length,
      classes: Object.keys(classmap).length,
      assets: modules.reduce((n, m) => n + Object.keys(m.assets).length, 0),
    },
  };

  // ── Vite inputs, derived from the same records ────────────────────────────
  /** @type {Record<string, string>} */
  const inputs = {};
  /** @type {Record<string, string>} */
  const entryKinds = {};
  /** @type {Record<string, string>} */
  const entryHandles = {};

  for (const module of modules) {
    for (const [kind, asset] of Object.entries(module.assets)) {
      // `module.dir` is relative to `modules/`, so it has to be joined to
      // MODULES_DIR rather than to the plugin root.
      inputs[entryNameOf(module, kind)] = toPosix(
        join(MODULES_DIR, module.dir, asset.source),
      );
      entryKinds[entryNameOf(module, kind)] = kind;
      entryHandles[entryNameOf(module, kind)] = asset.handle;
    }
  }

  const inputsPayload = {
    version: MANIFEST_VERSION,
    inputs,
    kinds: entryKinds,
    handles: entryHandles,
    distDirs: Object.fromEntries(ASSET_KINDS.map((k) => [k, distDirFor(k)])),
  };

  // ── Write, or verify ──────────────────────────────────────────────────────
  if (check) {
    verifyUnchanged(manifest, inputsPayload);
    return manifestResult(manifest, categories, dropped, true);
  }

  writeFileSync(MANIFEST_PATH, `${JSON.stringify(manifest, null, 2)}\n`);
  writeFileSync(INPUTS_PATH, `${JSON.stringify(inputsPayload, null, 2)}\n`);

  return manifestResult(manifest, categories, dropped);
}

function entryNameOf(module, kind) {
  return `${module.tier}-${module.id}-${kind}`;
}

function manifestResult(manifest, categories, dropped, checked = false) {
  return {
    modules: manifest.modules,
    categories,
    stats: manifest.stats,
    manifestPath: toPosix(relative(ROOT, MANIFEST_PATH)),
    inputsPath: toPosix(relative(ROOT, INPUTS_PATH)),
    dropped,
    checked,
  };
}

/**
 * `--check` compares module structure, not the timestamp. `generated` changes on
 * every run, so comparing raw bytes would report drift that does not exist.
 */
function verifyUnchanged(manifest, inputsPayload) {
  if (!existsSync(MANIFEST_PATH)) {
    throw new Error("manifest.json is missing. Run `npm run extract`.");
  }

  const current = JSON.parse(readFileSync(MANIFEST_PATH, "utf8"));
  const strip = (m) => ({ ...m, generated: 0 });

  if (JSON.stringify(strip(current)) !== JSON.stringify(strip(manifest))) {
    throw new Error("manifest.json is out of date. Run `npm run extract`.");
  }

  if (existsSync(INPUTS_PATH)) {
    const currentInputs = JSON.parse(readFileSync(INPUTS_PATH, "utf8"));
    if (JSON.stringify(currentInputs) !== JSON.stringify(inputsPayload)) {
      throw new Error("build/vite-inputs.json is out of date. Run `npm run extract`.");
    }
  }
}

/** Read the version from the plugin header so the manifest never disagrees with it. */
function readPluginVersion() {
  const bootstrap = join(ROOT, "best-addons.php");
  if (!existsSync(bootstrap)) return "0.0.0";
  const match = readFileSync(bootstrap, "utf8").match(/^\s*\*\s*Version:\s*(.+)$/m);
  return match ? match[1].trim() : "0.0.0";
}

// ── CLI ─────────────────────────────────────────────────────────────────────
const invokedDirectly = process.argv[1] && resolve(process.argv[1]) === resolve(fileURLToPath(import.meta.url));

if (invokedDirectly) {
  try {
    const result = extract({ check: process.argv.includes("--check") });
    report(result);
  } catch (error) {
    console.error("");
    console.error("  ✖ Folder extraction failed.");
    console.error("");
    if (error instanceof ContractError) {
      console.error(error.problems.map((p) => `    • ${p}`).join("\n"));
    } else {
      console.error(`    ${/** @type {Error} */ (error).message}`);
    }
    console.error("");
    process.exitCode = 1;
  }
}

export { MANIFEST_PATH, INPUTS_PATH, MODULES_DIR, MANIFEST_VERSION };
