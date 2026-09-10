import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import App from "./App";
// `?inline` gives us the fully-compiled Tailwind CSS as a string so it can
// be injected into each Shadow DOM instead of the document <head> — this
// is what keeps the widget's styles from leaking into (or being
// overridden by) the WordPress theme.
import widgetStyles from "./index.css?inline";

declare global {
  interface Window {
    InstantQuoteWidget?: {
      init: (target: string | HTMLElement) => void;
    };
  }
}

const MOUNT_SELECTOR = "[data-instant-quote-widget]";
const MOUNTED_ATTR = "data-iqw-mounted";

function mount(host: HTMLElement) {
  if (host.getAttribute(MOUNTED_ATTR) === "true") return;
  host.setAttribute(MOUNTED_ATTR, "true");

  const shadowRoot = host.attachShadow({ mode: "open" });

  const style = document.createElement("style");
  style.textContent = widgetStyles;
  shadowRoot.appendChild(style);

  const mountPoint = document.createElement("div");
  shadowRoot.appendChild(mountPoint);

  createRoot(mountPoint).render(
    <StrictMode>
      <App />
    </StrictMode>,
  );
}

function mountAll() {
  document.querySelectorAll<HTMLElement>(MOUNT_SELECTOR).forEach(mount);
}

function init(target: string | HTMLElement) {
  const el = typeof target === "string" ? document.querySelector<HTMLElement>(target) : target;
  if (el) mount(el);
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", mountAll);
} else {
  mountAll();
}

window.InstantQuoteWidget = { init };
