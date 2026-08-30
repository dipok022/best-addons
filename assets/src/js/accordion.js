import "../sass/accordion.scss";

export const initAccordion = ($scope) => {};

jQuery(window).on("elementor/frontend/init", () => {
  elementorFrontend.hooks.addAction(
    "frontend/element_ready/best_addons_accordion.default",
    ($scope) => {
      initAccordion($scope);
    },
  );
});
