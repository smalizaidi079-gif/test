// Copies the built embed bundle into the WordPress plugin's assets folder
// so the plugin directory is always a ready-to-zip, ready-to-install
// deliverable after `npm run build:wp`.
import { copyFileSync, mkdirSync, existsSync } from "node:fs";
import { fileURLToPath } from "node:url";
import path from "node:path";

const root = path.dirname(fileURLToPath(import.meta.url)) + "/..";
const src = path.join(root, "dist-embed", "instant-quote-widget.js");
const destDir = path.join(root, "wordpress-plugin", "instant-quote-widget", "assets");
const dest = path.join(destDir, "instant-quote-widget.js");

if (!existsSync(src)) {
  console.error(`Missing build output at ${src}. Run "npm run build:embed" first.`);
  process.exit(1);
}

mkdirSync(destDir, { recursive: true });
copyFileSync(src, dest);
console.log(`Copied embed bundle -> ${dest}`);
