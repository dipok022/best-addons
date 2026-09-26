/**
 * Shared ambient types for the whole plugin.
 *
 * Not widget-builder specific: module entries under `modules/` compile against
 * this file too, which is why it lives outside any one bundle's source folder.
 * Every module is a separate Vite entry and therefore a separate program as far
 * as the type checker is concerned — a `Window` augmentation has to appear
 * exactly once or the declarations conflict.
 */

/**
 * Elementor is only present on the front end, and only sometimes. Declaring it
 * as optional lets a renderer hook `frontend/element_ready` without pulling
 * Elementor's own types into this bundle.
 */
interface ElementorFrontendLike {
  hooks: {
    /**
     * Elementor hands the callback the scope element the widget rendered into,
     * so a module that uses it must accept that argument.
     */
    addAction: (hook: string, callback: (scope: HTMLElement) => void) => void;
  };

  isEditMode?: () => boolean;
}

declare global {
  interface Window {
    elementorFrontend?: ElementorFrontendLike;
  }
}

export {};
