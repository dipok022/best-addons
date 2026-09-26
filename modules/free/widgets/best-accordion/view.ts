/**
 * Best Accordion — front-end behaviour.
 *
 * Vanilla, no jQuery. Everything the widget needs is on the wrapper as data
 * attributes, so this file never reads the DOM to work out how it should
 * behave — a widget instance is configured by its own markup alone.
 *
 * The collapsed state is the `hidden` attribute PHP already rendered, so the
 * accordion is correct with JavaScript disabled. This file adds the transition
 * and must put `hidden` back once a collapse animation finishes.
 */

/** `get_name()` in Widget.php is `best_addons_` + the module id. */
const WIDGET_NAME = "best_addons_best_accordion";
const ROOT = ".ba-best-accordion";

const TIMING = {
  slide: { duration: 250, easing: "cubic-bezier(0.4, 0, 0.2, 1)" },
  fade: { duration: 200, easing: "ease-out" },
  none: null,
} as const;

type Animation = keyof typeof TIMING;

const reducedMotion = (): boolean =>
  window.matchMedia?.("(prefers-reduced-motion: reduce)").matches ?? false;

const setOpen = (
  item: HTMLElement,
  open: boolean,
  kind: Animation,
): void => {
  const header = item.querySelector<HTMLButtonElement>(".best-accordion-header");
  const content = item.querySelector<HTMLElement>(".best-accordion-content");

  item.classList.toggle("is-active", open);
  header?.setAttribute("aria-expanded", String(open));

  if (!content) return;

  const timing = reducedMotion() ? null : TIMING[kind];

  if (!timing) {
    content.hidden = !open;
    return;
  }

  if (open) {
    content.hidden = false;

    const keyframes =
      kind === "fade"
        ? [{ opacity: 0 }, { opacity: 1 }]
        : [{ height: "0px" }, { height: `${content.scrollHeight}px` }];

    content.animate(keyframes, timing);
    return;
  }

  // Collapse from the current height, and only then set `hidden`. The other order
  // would make the element display:none mid-animation, so the collapse would
  // never be seen.
  const keyframes =
    kind === "fade"
      ? [{ opacity: 1 }, { opacity: 0 }]
      : [{ height: `${content.scrollHeight}px` }, { height: "0px" }];

  const animation = content.animate(keyframes, timing);

  animation.addEventListener("finish", () => {
    content.hidden = true;
  });
};

/**
 * Close every panel except `$keep`.
 */
const closeSiblings = (
  root: HTMLElement,
  keep: HTMLElement,
  kind: Animation,
): void => {
  for (const item of root.querySelectorAll<HTMLElement>(".best-accordion-item")) {
    if (item !== keep && item.classList.contains("is-active")) {
      setOpen(item, false, kind);
    }
  }
};

const init = (scope: HTMLElement): void => {
  const root = scope.matches(ROOT) ? scope : scope.querySelector<HTMLElement>(ROOT);
  if (!root) return;

  // Scoped to this instance, never the document: two accordions on one page must
  // not close each other's panels.
  const items = Array.from(root.querySelectorAll<HTMLElement>(".best-accordion-item"));

  const kind = (root.dataset.animation ?? "slide") as Animation;
  const trigger = root.dataset.trigger === "hover" ? "hover" : "click";
  const interaction = root.dataset.interaction ?? "single";
  const orientation = root.dataset.orientation === "horizontal" ? "horizontal" : "vertical";
  const activeIndex = Math.max(1, Number(root.dataset.activeIndex) || 1) - 1;

  if (items.length === 0) return;

  // Reconcile the rendered open panel with the configured one. A horizontal
  // accordion always shows the first panel regardless of the index control, and
  // "all collapsed" shows none.
  if (interaction !== "multiple") {
    const shouldBeOpen = (index: number): boolean =>
      interaction !== "all_collapsed" &&
      (orientation === "horizontal" ? index === 0 : index === activeIndex);

    for (const [index, item] of items.entries()) {
      if (item.classList.contains("is-active") !== shouldBeOpen(index)) {
        setOpen(item, shouldBeOpen(index), kind);
      }
    }
  }

  for (const [index, item] of items.entries()) {
    const header = item.querySelector<HTMLButtonElement>(".best-accordion-header");
    if (!header) continue;

    const toggle = (): void => {
      const isOpen = item.classList.contains("is-active");

      if (!isOpen && (interaction === "single" || orientation === "horizontal")) {
        closeSiblings(root, item, kind);
      }

      setOpen(item, !isOpen, kind);
    };

    if (trigger === "hover") {
      // Hover-to-open with a leave delay, so crossing a panel's own padding does
      // not collapse it mid-read.
      let timer = 0;

      item.addEventListener("mouseenter", () => {
        window.clearTimeout(timer);
        toggle();
      });

      item.addEventListener("mouseleave", () => {
        timer = window.setTimeout(() => {
          if (interaction !== "multiple") setOpen(item, false, kind);
        }, 120);
      });

      continue;
    }

    header.addEventListener("click", toggle);

    // Vertical accordions are a list of disclosure buttons; arrow keys move
    // between them the way a native <details> group would.
    if (orientation === "vertical") {
      header.addEventListener("keydown", (event: KeyboardEvent) => {
        const keys: Record<string, number> = {
          ArrowDown: index + 1,
          ArrowUp: index - 1,
          Home: 0,
          End: items.length - 1,
        };

        const target = keys[event.key];
        if (target === undefined) return;

        event.preventDefault();

        const next = items[target];
        next?.querySelector<HTMLButtonElement>(".best-accordion-header")?.focus();
      });
    }
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
