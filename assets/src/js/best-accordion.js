import "../sass/best-accordion.scss";

export const initAccordion = ($scope) => {
  // Add Items Max
  // index
  const $items = $scope.find(".best-accordion-header");
  const totalItems = $items.length;

  if (window.elementorFrontend && window.elementorFrontend.isEditMode()) {
    const $controlInput = window.parent.jQuery(
      '.best-addons-active-index input[type="number"]',
    );

    if ($controlInput.length && totalItems > 0) {
      $controlInput.attr("max", totalItems);
    }
  }

  console.log("items", $items.length);
};

jQuery(window).on("elementor/frontend/init", () => {
  elementorFrontend.hooks.addAction(
    "frontend/element_ready/best_addons_best_accordion.default",
    ($scope) => {
      initAccordion($scope);
    },
  );
});
