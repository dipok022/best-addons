import "../sass/sample-box.scss";

export const initSampleBox = ($scope) => {
  const targetBox = $scope.find(".best-addons-sample-box");
  targetBox.on("click", () => {
    alert("Best Addons: Widget click recognized cleanly inside Editor Mode!");
  });
};

jQuery(window).on("elementor/frontend/init", () => {
  elementorFrontend.hooks.addAction(
    "frontend/element_ready/best_addons_sample.default",
    ($scope) => {
      initSampleBox($scope);
    },
  );
});
