/**
 * The PHP parser.
 *
 * The only file in `build/lib/` that reads PHP source. It exists so the
 * runtime gets a classmap and never has to probe the filesystem: when the
 * autoloader is asked for a class it does one array lookup, not a
 * `file_exists()` per path segment.
 *
 * A regex is correct here and not in a general-purpose tool because the input is
 * this repository's own code under a naming convention the validator enforces.
 * A module that parses into an unexpected shape fails the build (see
 * validate.mjs), so a false negative is a build error rather than a silent
 * missing class.
 */

import { readFileSync } from "node:fs";
import { relative, sep } from "node:path";

const NAMESPACE = /^\s*namespace\s+([A-Za-z_\\][A-Za-z0-9_\\]*)\s*[;{]/m;
const DECLARATION =
  /^\s*(?:(?:abstract|final|readonly)\s+)*(class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)/gm;

/**
 * Map every fully-qualified class a PHP file declares to its plugin-relative path.
 *
 * @param {string} file Absolute path.
 * @param {string} root Absolute path the returned keys are relative to.
 * @returns {Record<string, string>} FQCN → relative POSIX path.
 */
export function classmapForFile(file, root) {
  const source = readFileSync(file, "utf8");
  const relativePath = relative(root, file).split(sep).join("/");

  // A file may open a braced namespace: `namespace X { … }`.
  const namespaceMatch = source.match(NAMESPACE);
  if (!namespaceMatch) return {};

  const namespace = namespaceMatch[1].replace(/\\+$/, "");

  /** @type {Record<string, string>} */
  const map = {};

  for (const match of source.matchAll(DECLARATION)) {
    map[`${namespace}\\${match[2]}`] = relativePath;
  }

  return map;
}

/**
 * Merge the classmaps of every PHP file in a module.
 *
 * @param {string[]} files Absolute paths.
 * @param {string} root Absolute path for relative keys.
 * @returns {Record<string, string>}
 */
export function classmapForFiles(files, root) {
  /** @type {Record<string, string>} */
  const merged = {};

  for (const file of files) {
    Object.assign(merged, classmapForFile(file, root));
  }

  return merged;
}
