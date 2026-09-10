import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import path from "node:path";

// Embed build: a single self-mounting IIFE bundle (JS + CSS) for WordPress.
// Renders into a Shadow DOM so widget styles never leak into / clash with
// the host theme. This is what the WordPress plugin enqueues.
export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "src"),
    },
  },
  build: {
    outDir: "dist-embed",
    emptyOutDir: true,
    cssCodeSplit: false,
    lib: {
      entry: path.resolve(__dirname, "src/embed.tsx"),
      name: "InstantQuoteWidget",
      formats: ["iife"],
      fileName: () => "instant-quote-widget.js",
    },
    rollupOptions: {
      output: {
        // Keep the CSS filename predictable, though embed.tsx inlines
        // styles into the shadow root rather than relying on this file.
        assetFileNames: (assetInfo) =>
          assetInfo.name === "style.css"
            ? "instant-quote-widget.css"
            : "assets/[name][extname]",
      },
    },
  },
  define: {
    "process.env.NODE_ENV": JSON.stringify("production"),
  },
});
