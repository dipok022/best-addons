import "../sass/advanced-accordion.scss";

export const initAdvancedAccordion = ($scope) => {
  const accordionWrapper = $scope.find(".best-advanced-accordion");
  const items = accordionWrapper.find(".best-accordion-item");

  items.each(function () {
    const item = jQuery(this);
    const header = item.find(".best-accordion-header");
    const content = item.find(".best-accordion-content");

    header.on("click", (e) => {
      e.preventDefault();
      if (item.hasClass("is-active")) {
        item.removeClass("is-active");
        content.slideUp(250);
      } else {
        items.removeClass("is-active");
        accordionWrapper.find(".best-accordion-content").slideUp(250);
        item.addClass("is-active");
        content.slideDown(250);
      }
    });
  });
};

jQuery(window).on("elementor/frontend/init", () => {
  elementorFrontend.hooks.addAction(
    "frontend/element_ready/best_addons_advanced_accordion.default",
    ($scope) => {
      initAdvancedAccordion($scope);
    },
  );
});
