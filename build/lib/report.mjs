/**
 * The build report.
 *
 * The extractor is the thing a developer runs on every change, so its output is
 * part of the developer experience: it has to show what was found, what was
 * dropped, and where the assets are going.
 */

const BOLD = "\u001b[1m";
const DIM = "\u001b[2m";
const GREEN = "\u001b[32m";
const YELLOW = "\u001b[33m";
const RESET = "\u001b[0m";

const useColor = process.stdout.isTTY && !process.env.NO_COLOR;
const paint = (code, text) => (useColor ? `${code}${text}${RESET}` : text);

/**
 * @param {{modules: Array<Record<string,any>>, categories: string[], stats: Record<string, number>, manifestPath: string, inputsPath: string, dropped: string[], checked?: boolean}} result
 */
export function report(result) {
  const { modules, categories, stats, manifestPath, inputsPath, dropped, checked = false } = result;

  console.log("");
  console.log(paint(BOLD, "  best-addons · folder extractor"));
  console.log(paint(DIM, `  ${"─".repeat(58)}`));

  if (modules.length === 0) {
    console.log(paint(YELLOW, "  No modules found under modules/."));
    console.log(paint(DIM, "  That is valid, but the plugin will register nothing."));
    console.log("");
    return;
  }

  const width = Math.max(...modules.map((m) => `${m.tier}/${m.type}/${m.id}`.length));

  for (const module of modules) {
    const label = `${module.tier}/${module.type}/${module.id}`.padEnd(width);
    const tier = module.tier === "pro" ? paint(YELLOW, "pro") : paint(GREEN, "free");
    const kinds = Object.keys(module.assets);

    console.log(
      `  ${tier}  ${label}  ${paint(DIM, module.hash)}  ${
        kinds.length ? paint(DIM, kinds.join(" ")) : paint(DIM, "no assets")
      }`,
    );

    for (const [kind, asset] of Object.entries(module.assets)) {
      console.log(paint(DIM, `        ${kind.padEnd(7)}→ ${asset.file}`));
    }
  }

  console.log(paint(DIM, `  ${"─".repeat(58)}`));
  console.log(
    `  ${stats.modules} module(s) · ${stats.free} free · ${stats.pro} pro · ${stats.classes} class(es) · ${categories.length} categor(ies)`,
  );
  const verb = checked ? "✓" : "→";
  console.log(paint(DIM, `  ${verb} ${manifestPath}${checked ? " up to date" : ""}`));
  console.log(paint(DIM, `  ${verb} ${inputsPath}${checked ? " up to date" : ""}`));

  for (const note of dropped) {
    console.log(paint(YELLOW, `  ! ${note}`));
  }

  console.log("");
}
