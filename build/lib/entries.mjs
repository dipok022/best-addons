/**
 * Asset naming.
 *
 * The contract declares *source files*. Output filenames and WordPress handles
 * are derived here, from one rule, so they cannot drift from each other or be
 * written twice.
 *
 *   entry name   {tier}-{id}-{kind}
 *   dist file    {dir}/{entry name}.{min.css|min.js}
 *   handle       ba-{tier}-{id}-{kind}
 *
 * The tier is part of the filename on purpose: a Free and a Pro module can never
 * collide on an output path, and a free-only zip can drop `js/pro-*` without a
 * single rename.
 */

import { extname, join } from "node:path";

/** @type {Record<string, {dir: string, ext: string[], outExt: string}>} */
const KINDS = {
  style: { dir: "css", ext: [".scss", ".sass", ".css"], outExt: "min.css" },
  script: { dir: "js", ext: [".ts", ".tsx", ".js", ".jsx"], outExt: "min.js" },
  editor: { dir: "js", ext: [".ts", ".tsx", ".js", ".jsx"], outExt: "min.js" },
};

export const ASSET_KINDS = Object.keys(KINDS);

/** The `dist/` sub-folder an asset kind is emitted into. */
export function distDirFor(kind) {
  return KINDS[kind].dir;
}

/** True when `kind` accepts a file with this extension. */
export function acceptsExtension(kind, file) {
  return KINDS[kind].ext.includes(extname(file).toLowerCase());
}

/** `@example entryName('pro', 'advanced-tabs', 'editor') → 'pro-advanced-tabs-editor'` */
export function entryName(tier, id, kind) {
  return `${tier}-${id}-${kind}`;
}

/** @example handleFor('free', 'advanced-accordion', 'style') → 'ba-free-advanced-accordion-style'` */
export function handleFor(tier, id, kind) {
  return `ba-${tier}-${id}-${kind}`;
}

/**
 * Turn a module's declared assets into Vite inputs and enqueue metadata.
 *
 * @param {{tier: string, id: string, assets?: Record<string,string>}} module
 * @param {string} dir Absolute module folder.
 * @param {string} hash Content hash, used as the enqueue `ver`.
 * @returns {{
 *   entries: Record<string, string>,
 *   assets: Record<string, {handle: string, file: string, ver: string, kind: string, source: string}>
 * }}
 */
export function assetPlan(module, dir, hash) {
  /** @type {Record<string, string>} */
  const entries = {};
  /** @type {Record<string, {handle: string, file: string, ver: string, kind: string, source: string}>} */
  const assets = {};

  for (const kind of ASSET_KINDS) {
    const declared = module.assets?.[kind];
    if (!declared) continue;

    const name = entryName(module.tier, module.id, kind);
    const absolute = join(dir, declared);

    entries[name] = absolute;
    assets[kind] = {
      handle: handleFor(module.tier, module.id, kind),
      file: `${KINDS[kind].dir}/${name}.${KINDS[kind].outExt}`,
      ver: hash,
      kind,
      source: declared,
    };
  }

  return { entries, assets };
}
