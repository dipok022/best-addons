/**
 * Advanced Tabs — front-end behaviour.
 *
 * Implements the WAI-ARIA tabs pattern: roving tabindex, Home/End, and arrows
 * that follow the reading direction of the current layout. Written without
 * jQuery; the active-indicator properties it writes are the only thing the
 * stylesheet reads back.
 */

/** `get_name()` in Widget.php is `best_addons_` + the module id. */
const WIDGET_NAME = "best_addons_advanced_tabs";
const ROOT = ".ba-advanced-tabs";

type Layout = "horizontal" | "vertical";

const init = (scope: HTMLElement): void => {
  const root = scope.matches(ROOT) ? scope : scope.querySelector<HTMLElement>(ROOT);
  if (!root) return;

  // Scoped to this instance: two tab widgets on one page must not share a tablist.
  const tabs = Array.from(root.querySelectorAll<HTMLButtonElement>(".ba-advanced-tabs__tab"));
  if (tabs.length === 0) return;

  const panels = tabs.map((tab) =>
    document.getElementById(tab.getAttribute("aria-controls") ?? ""),
  );

  const layout: Layout = root.classList.contains("ba-advanced-tabs--vertical")
    ? "vertical"
    : "horizontal";

  const autoplay = Math.max(0, Number(root.dataset.tabsAutoplay) || 0);
  let timer = 0;
  let current = Math.max(0, tabs.findIndex((tab) => tab.getAttribute("aria-selected") === "true"));

  /**
   * Position the sliding indicator over the active tab.
   *
   * Measured rather than hard-coded, so it stays correct across responsive
   * breakpoints, a resized sidebar, and RTL.
   */
  const moveIndicator = (tab: HTMLElement): void => {
    if (root.classList.contains("ba-advanced-tabs--indicator-none")) return;

    const list = root.querySelector<HTMLElement>(".ba-advanced-tabs__list");
    if (!list) return;

    const active = list.offsetLeft + tab.offsetLeft;
    const size = tab.offsetWidth;

    list.style.setProperty("--ba-tabs-indicator-x", `${active}px`);
    list.style.setProperty("--ba-tabs-indicator-width", `${size}px`);

    // The vertical variant positions from the top instead of the left.
    list.style.setProperty("--ba-tabs-indicator-y", `${tab.offsetTop}px`);
    list.style.setProperty("--ba-tabs-indicator-height", `${size}px`);
  };

  const select = (index: number, { focus = false } = {}): void => {
    const next = tabs[index];
    if (!next) return;

    current = index;

    for (const [i, tab] of tabs.entries()) {
      const active = i === index;
      tab.setAttribute("aria-selected", String(active));

      // Roving tabindex: only the selected tab is in the tab order.
      tab.tabIndex = active ? 0 : -1;

      const panel = panels[i];
      if (panel) panel.hidden = !active;
    }

    moveIndicator(next);

    if (focus) next.focus();
  };

  tabs.forEach((tab, index) => {
    tab.addEventListener("click", () => select(index));
  });

  tabs.forEach((tab, index) => {
    tab.addEventListener("keydown", (event: KeyboardEvent) => {
      // Home/End are the same in both layouts; the arrows are not.
      const last = tabs.length - 1;

      const next = (() => {
        switch (event.key) {
          case "Home":
            return 0;
          case "End":
            return last;
          case "ArrowRight":
            return layout === "vertical" ? index : (index + 1) % tabs.length;
          case "ArrowLeft":
            return layout === "vertical" ? index : (index - 1 + tabs.length) % tabs.length;
          case "ArrowDown":
            return layout === "horizontal" ? index : (index + 1) % tabs.length;
          case "ArrowUp":
            return layout === "horizontal" ? index : (index - 1 + tabs.length) % tabs.length;
          default:
            return null;
        }
      })();

      if (next === null) return;

      event.preventDefault();
      select(next, { focus: true });
    });
  });

  if (autoplay > 0) {
    const start = (): void => {
      timer = window.setInterval(() => select((current + 1) % tabs.length), autoplay);
    };

    const stop = (): void => window.clearInterval(timer);

    // Rotating tabs must not fight the user: any pointer or keyboard interaction
    // takes over, and rotation only resumes once they have left.
    root.addEventListener("pointerenter", start);
    root.addEventListener("pointerleave", stop);
    root.addEventListener("focusin", stop);
    root.addEventListener("keydown", stop);

    start();
  }

  select(current);
};

window.addEventListener("elementor/frontend/init", () => {
  window.elementorFrontend?.hooks.addAction(
    `frontend/element_ready/${WIDGET_NAME}.default`,
    init,
  );
});

// A build entry with no import or export is a *script*, not a module: its
// top-level bindings become globals, so every module in the plugin would
// redeclare the same names. This line makes the file a module without adding
// a runtime import.
export {};
