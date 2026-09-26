/**
 * Sample Box — Gutenberg editor behaviour.
 *
 * Registers the block's editor UI with plain `wp.blocks` calls. There is no
 * build-tool scaffolding here on purpose: this module is the floor of the
 * architecture, so it has to work with nothing but what WordPress already puts
 * on the page. A module that wants a React edit component can add one; nothing
 * in the contract requires it.
 *
 * The block is *also* registered server-side by BaseBlock::register(), which owns
 * attributes, title, category and the render callback. This file only supplies
 * the editor half, so the two never disagree about what the attributes are.
 */

const BLOCK_NAME = "best-addons/sample-box";

interface BlockSettings {
  title: string;
  description?: string;
  category?: string;
  icon?: string;
  keywords?: string[];
  attributes: Record<string, unknown>;
  edit: (props: EditProps) => unknown;
  save: () => null;
}

interface EditProps {
  attributes: { title: string };
  setAttributes: (next: { title: string }) => void;
}

declare global {
  interface Window {
    wp?: {
      blocks?: {
        registerBlockType: (name: string, settings: BlockSettings) => unknown;
      };
      element?: {
        createElement: (...args: unknown[]) => unknown;
        useBlockProps?: () => Record<string, unknown>;
      };
      blockEditor?: {
        RichText?: unknown;
        InspectorControls?: unknown;
      };
      components?: {
        PanelBody?: unknown;
        TextControl?: unknown;
      };
    };
  }
}

const register = (): void => {
  const { blocks, element, blockEditor } = window.wp ?? {};

  if (!blocks?.registerBlockType || !element?.createElement) {
    // The editor script is enqueued on every admin screen that has the block
    // editor's `wp-blocks` package; a screen without it simply has no block to
    // register, and this is not an error worth surfacing.
    return;
  }

  const { useBlockProps } = element;
  const { RichText } = blockEditor ?? {};

  blocks.registerBlockType(BLOCK_NAME, {
    title: "Sample Box",
    description:
      "A minimal titled panel, used as the reference block module and as a starting point for new ones.",
    category: "widgets",
    icon: "info-outline",
    keywords: ["box", "panel", "callout", "sample"],
    attributes: {
      title: { type: "string", default: "" },
    },

    edit({ attributes, setAttributes }) {
      const props = useBlockProps ? useBlockProps() : {};

      if (!RichText) {
        // Degrade to the empty shell rather than throwing, so the block still
        // renders on the front end if the editor's package is unavailable.
        return element.createElement("div", props);
      }

      return element.createElement(
        "div",
        props,
        element.createElement(RichText, {
          tagName: "h3",
          className: "best-addons-sample-box__title",
          value: attributes.title,
          onChange: (title: string) => setAttributes({ title }),
          placeholder: "Sample Box",
        }),
      );
    },

    // Matches the PHP render callback exactly. A block whose save() is not null
    // would be validated against this markup and reported as invalid.
    save: () => null,
  });
};

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", register);
} else {
  register();
}

// A build entry with no import or export is a *script*, not a module: its
// top-level bindings become globals, so every module in the plugin would
// redeclare the same names. This line makes the file a module without adding
// a runtime import.
export {};
