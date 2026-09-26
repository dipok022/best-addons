/**
 * Advanced Accordion — front-end behaviour.
 *
 * Vanilla, no jQuery. Elementor guarantees jQuery on the page, but a module that
 * can avoid it should: this file is what a page using this one widget downloads.
 *
 * The collapsed state is the `hidden` attribute that PHP already rendered, so the
 * accordion is correct with JavaScript disabled. This file only adds the
 * transition, and must keep putting `hidden` back when it finishes.
 */

const ROOT = ".ba-advanced-accordion";
const DURATION = 250;
const EASING = "cubic-bezier(0.4, 0, 0.2, 1)";

/** `getName()` in Widget.php is `best_addons_` + the module id. */
const WIDGET_NAME = "best_addons_advanced_accordion";

const reducedMotion = (): boolean =>
  window.matchMedia?.("(prefers-reduced-motion: reduce)").matches ?? false;

const setOpen = (item: HTMLElement, open: boolean): void => {
  const header = item.querySelector<HTMLButtonElement>(".best-accordion-header");
  const content = item.querySelector<HTMLElement>(".best-accordion-content");

  item.classList.toggle("is-active", open);
  header?.setAttribute("aria-expanded", String(open));

  if (!content) return;

  if (open) {
    content.hidden = false;

    if (reducedMotion()) return;

    content.animate([{ height: "0px" }, { height: `${content.scrollHeight}px` }], {
      duration: DURATION,
      easing: EASING,
    });
    return;
  }

  if (reducedMotion()) {
    content.hidden = true;
    return;
  }

  // Collapse from the current height, and only then set `hidden` — doing it the
  // other way round would make the element display:none mid-animation and the
  // transition would never be seen.
  const animation = content.animate(
    [{ height: `${content.scrollHeight}px` }, { height: "0px" }],
    { duration: DURATION, easing: EASING },
  );

  animation.addEventListener("finish", () => {
    content.hidden = true;
  });
};

const init = (scope: HTMLElement): void => {
  const root = scope.matches(ROOT) ? scope : scope.querySelector<HTMLElement>(ROOT);
  if (!root) return;

  // A second accordion on the same page must not close this one's panels, so the
  // query is scoped to this instance rather than the document.
  const items = Array.from(root.querySelectorAll<HTMLElement>(".best-accordion-item"));

  for (const item of items) {
    item
      .querySelector<HTMLButtonElement>(".best-accordion-header")
      ?.addEventListener("click", () => {
        const isOpen = item.classList.contains("is-active");

        // Single-open behaviour: close the siblings, leave this one to toggle.
        if (!isOpen) {
          for (const sibling of items) {
            if (sibling !== item && sibling.classList.contains("is-active")) {
              setOpen(sibling, false);
            }
          }
        }

        setOpen(item, !isOpen);
      });
  }
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
