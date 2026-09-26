/**
 * Content hashing.
 *
 * Produces the `ver` argument WordPress puts on every enqueue. Hashing source
 * content at build time is what lets the runtime stop calling `filemtime()` —
 * the old design cost two stat() calls per widget on every single request.
 */

import { createHash } from "node:crypto";
import { readFileSync } from "node:fs";

/**
 * Hash a module's own files into a short, stable token.
 *
 * The contract file is included so that changing only `title` or `keywords`
 * still busts the editor bundle, which embeds neither but may embed metadata.
 *
 * @param {string} contractPath Absolute path to module.json.
 * @param {Record<string, string>} sources Absolute paths, keyed by asset kind.
 * @returns {string} 10 hex chars.
 */
export function hashModule(contractPath, sources) {
  const digest = createHash("sha256");
  digest.update(readFileSync(contractPath));

  // Sorted so the result cannot depend on object key order.
  for (const path of Object.values(sources).sort()) {
    digest.update(path);
    digest.update(readFileSync(path));
  }

  return digest.digest("hex").slice(0, 10);
}
