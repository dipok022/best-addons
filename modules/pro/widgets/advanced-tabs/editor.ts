/**
 * Advanced Tabs — Elementor editor behaviour.
 *
 * Loaded only inside the editor, on the editor screen alone. Two jobs the front
 * end has no reason to do:
 *
 *  1. Keep the indicator measured. The editor re-renders a widget's markup
 *     without re-running the frontend hook, so the bar would otherwise sit at
 *     the position it had before the user dragged a panel wider.
 *  2. Hold rotation still. Autoplay firing while someone is reading a panel in
 *     the editor is worse than in the front end, because there is no pointer to
 *     move away from.
 */

const ROOT = ".ba-advanced-tabs";

const reposition = (root: HTMLElement): void => {
  const list = root.querySelector<HTMLElement>(".ba-advanced-tabs__list");
  const active = root.querySelector<HTMLElement>('[aria-selected="true"]');
  if (!list || !active) return;

  list.style.setProperty("--ba-tabs-indicator-x", `${list.offsetLeft + active.offsetLeft}px`);
  list.style.setProperty("--ba-tabs-indicator-width", `${active.offsetWidth}px`);
  list.style.setProperty("--ba-tabs-indicator-y", `${active.offsetTop}px`);
  list.style.setProperty("--ba-tabs-indicator-height", `${active.offsetWidth}px`);
};

const watch = (root: HTMLElement): void => {
  // The editor changes a widget's width constantly, and a ResizeObserver catches
  // all of it — including the panel drags a media query cannot. The feature test
  // is a lookup rather than `"ResizeObserver" in window`, which narrows `window`
  // itself and makes the fallback branch unreachable to the type checker.
  if (typeof ResizeObserver !== "undefined") {
    new ResizeObserver(() => reposition(root)).observe(root);
  } else {
    window.addEventListener("resize", () => reposition(root));
  }

  // Removing the attribute puts the front-end script into its stopped state: it
  // reads autoplay from here, and there is no interval to interrupt.
  root.removeAttribute("data-tabs-autoplay");

  // The front-end script owns panel switching, including inside the editor, so
  // it only has to be re-measured afterwards.
  for (const tab of root.querySelectorAll<HTMLButtonElement>('[role="tab"]')) {
    tab.addEventListener("click", () => {
      window.requestAnimationFrame(() => reposition(root));
    });
  }

  // The first paint happened before this ran, so measure once up front.
  reposition(root);
};

window.addEventListener("elementor/frontend/init", () => {
  window.elementorFrontend?.hooks.addAction(
    "frontend/element_ready/best_addons_advanced_tabs.default",
    (scope) => {
      const root = scope.matches(ROOT) ? scope : scope.querySelector<HTMLElement>(ROOT);
      if (root) watch(root);
    },
  );
});

// A build entry with no import or export is a *script*, not a module: its
// top-level bindings become globals, so every module in the plugin would
// redeclare the same names. This line makes the file a module without adding
// a runtime import.
export {};
