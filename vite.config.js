import { defineConfig } from "vite";
import { readFileSync, existsSync } from "node:fs";
import { resolve, dirname } from "node:path";
import { fileURLToPath } from "node:url";
import react from "@vitejs/plugin-react";

const root = dirname(fileURLToPath(import.meta.url));
const abs = (relative) => resolve(root, relative);

const readInputs = () => {
  const path = abs("build/vite-inputs.json");

  if (!existsSync(path)) {
    // Failing here is much better than building an empty bundle: without the
    // manifest, every entry below would vanish and the plugin would ship with
    // 404s for CSS it believes it enqueued.
    throw new Error(
      "build/vite-inputs.json is missing. Run `npm run extract` before building.",
    );
  }

  return JSON.parse(readFileSync(path, "utf8"));
};

const readSpine = () => JSON.parse(readFileSync(abs("build/spine.json"), "utf8")).spine;

const { inputs } = readInputs();
const spine = readSpine();

/**
 * Module entries, straight from the manifest.
 *
 * Vite's own `name` for an entry is the key here, and the output pattern pins it
 * to `js/{name}.min.js` / `css/{name}.min.css` — which is exactly the path the
 * manifest recorded and PHP enqueues. Nothing here is hand-listed, so adding a
 * module folder and re-running `npm run extract` is the whole procedure.
 */
const moduleInput = Object.fromEntries(
  Object.entries(inputs).map(([name, file]) => [name, abs(file)]),
);

const spineInput = Object.fromEntries(
  Object.entries(spine).map(([name, entry]) => [name, abs(entry.input)]),
);

export default defineConfig({
  plugins: [react()],
  build: {
    outDir: "assets/dist",
    emptyOutDir: true,
    cssCodeSplit: true,
    // WP admin still has to support the browsers that ship with it.
    target: "es2020",
    chunkSizeWarningLimit: 1200,
    rollupOptions: {
      input: { ...moduleInput, ...spineInput },
      output: {
        entryFileNames: "js/[name].min.js",
        // PHP enqueues by path, so a shared chunk needs a name it can predict —
        // a content hash would force WordPress to guess on every build.
        chunkFileNames: "js/[name].min.js",
        manualChunks(id) {
          // React and the scheduler are the only modules the React entries
          // share. CodeMirror and dnd-kit stay inside the builder editor, so the
          // admin panel and the front end never download them.
          if (/node_modules[\\/](react|react-dom|scheduler)[\\/]/.test(id)) return "react";
          return undefined;
        },
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith(".css")) {
            return "css/[name].min.css";
          }
          return "[ext]/[name].[ext]";
        },
      },
    },
  },
});
