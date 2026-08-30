import { defineConfig } from "vite";
import { resolve } from "path";

export default defineConfig({
  build: {
    outDir: "assets/dist",
    emptyOutDir: true,
    cssCodeSplit: true,
    rollupOptions: {
      input: {
        "sample-widget": resolve(__dirname, "assets/src/js/sample-box.js"),
        "advanced-accordion": resolve(
          __dirname,
          "assets/src/js/advanced-accordion.js",
        ),
        accordion: resolve(__dirname, "assets/src/js/accordion.js"),
      },
      output: {
        entryFileNames: "js/[name].min.js",
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
