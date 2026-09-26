/**
 * Find every module contract under `modules/`.
 *
 * The walk is recursive rather than a fixed three-level readdir so a module can
 * carry its own sub-folders (`parts/`, `presets/`, `variants/`) without the
 * extractor needing to know. It stops descending only at dot-directories and
 * `node_modules`.
 */

import { readdirSync, existsSync, statSync } from "node:fs";
import { join, relative, sep } from "node:path";

const CONTRACT = "module.json";
const IGNORED = new Set(["node_modules", "vendor", "dist", "__tests__"]);

/**
 * @param {string} root Absolute path to the `modules/` directory.
 * @returns {{dir: string, relativeDir: string, contractPath: string}[]} Sorted by path.
 */
export function discover(root) {
  if (!existsSync(root)) return [];

  /** @type {{dir: string, relativeDir: string, contractPath: string}[]} */
  const found = [];

  /** @param {string} dir */
  const walk = (dir) => {
    let entries;
    try {
      entries = readdirSync(dir, { withFileTypes: true });
    } catch {
      return;
    }

    // Sort so the manifest is byte-identical across machines and checkouts.
    entries.sort((a, b) => (a.name < b.name ? -1 : a.name > b.name ? 1 : 0));

    for (const entry of entries) {
      if (!entry.isDirectory()) continue;
      if (entry.name.startsWith(".") || IGNORED.has(entry.name)) continue;

      const child = join(dir, entry.name);
      const contract = join(child, CONTRACT);

      if (existsSync(contract) && statSync(contract).isFile()) {
        found.push({
          dir: child,
          relativeDir: relative(root, child).split(sep).join("/"),
          contractPath: contract,
        });
        // A folder holding a contract is a module: keep walking anyway, because a
        // module is allowed to nest sub-modules (e.g. a bundle of variants).
        continue;
      }

      walk(child);
    }
  };

  walk(root);
  return found;
}

/**
 * Every PHP file inside a module folder, recursively.
 *
 * @param {string} dir
 * @returns {string[]} Absolute paths, sorted.
 */
export function phpFilesIn(dir) {
  /** @type {string[]} */
  const out = [];

  const walk = (current) => {
    let entries;
    try {
      entries = readdirSync(current, { withFileTypes: true });
    } catch {
      return;
    }
    entries.sort((a, b) => (a.name < b.name ? -1 : a.name > b.name ? 1 : 0));

    for (const entry of entries) {
      const path = join(current, entry.name);
      if (entry.isDirectory()) {
        if (entry.name.startsWith(".") || IGNORED.has(entry.name)) continue;
        walk(path);
      } else if (entry.name.endsWith(".php")) {
        out.push(path);
      }
    }
  };

  walk(dir);
  return out;
}
