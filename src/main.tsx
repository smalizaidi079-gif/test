import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import App from "./App";
import { initIframeAutoResize } from "./lib/iframeAutoResize";
import "./index.css";

createRoot(document.getElementById("root")!).render(
  <StrictMode>
    <App />
  </StrictMode>,
);

initIframeAutoResize();
