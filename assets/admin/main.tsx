/**
 * Admin app entry point.
 *
 * Mounts once the DOM is ready. The `#ba-admin-root` element is printed by
 * `OptionsPage::render()` before this script is enqueued as a footer dependency,
 * so the mount target always exists by the time `DOMContentLoaded` fires.
 */

import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import { App } from "./App";
import "./admin.scss";

const ROOT_ID = "ba-admin-root";

const mount = (): void => {
  const container = document.getElementById(ROOT_ID);

  if (!container) {
    // The script was enqueued on a screen that is not the options page, or the
    // page markup changed. Neither is worth a console error the site owner
    // cannot act on.
    return;
  }

  createRoot(container).render(
    <StrictMode>
      <App />
    </StrictMode>,
  );
};

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", mount);
} else {
  mount();
}
