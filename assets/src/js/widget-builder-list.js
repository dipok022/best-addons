(function ($) {
  "use strict";

  const { ajax_url, nonce, l10n } = window.baWidgetBuilder || {};

  $(function () {
    initModals();
    initShortcodeCopy();
    initExport();
  });

  // ── Modals ──────────────────────────────────────────────────────────────
  function initModals() {
    // Open create modal
    $("#ba-add-new-widget, #ba-add-new-widget-empty").on("click", function () {
      $("#ba-create-modal").fadeIn(200);
      $("#ba-widget-title").focus();
    });

    // Open import modal
    $("#ba-import-widget-btn").on("click", function () {
      $("#ba-import-modal").fadeIn(200);
    });

    // Close modals
    $(".ba-modal-close, .ba-modal-overlay").on("click", function () {
      $(this).closest(".ba-modal").fadeOut(200);
    });

    // Create widget submit
    $("#ba-create-widget-submit").on("click", function () {
      const title = $("#ba-widget-title").val().trim();
      const category = $("#ba-widget-category").val();

      if (!title) {
        alert("Widget title is required.");
        return;
      }

      const $btn = $(this);
      $btn.text(l10n?.creating || "Creating…").prop("disabled", true);

      $.post(ajax_url, {
        action: "ba_create_widget",
        nonce: nonce,
        title: title,
        category: category,
      })
        .done((res) => {
          if (res.success) {
            window.location.href = res.data.edit_url;
          } else {
            alert(res.data?.message || l10n?.create_error || "Failed to create widget.");
            $btn.text("Save Changes →").prop("disabled", false);
          }
        })
        .fail(() => {
          alert(l10n?.create_error || "Failed to create widget.");
          $btn.text("Save Changes →").prop("disabled", false);
        });
    });

    // Import file input
    $("#ba-import-file").on("change", function () {
      const file = this.files[0];
      if (file) {
        $("#ba-import-file-name").text(file.name);
        $("#ba-import-submit").prop("disabled", false);
      } else {
        $("#ba-import-file-name").text("");
        $("#ba-import-submit").prop("disabled", true);
      }
    });

    // Drag & drop for import
    const $dropZone = $("#ba-import-drop-zone");
    $dropZone.on("dragover", function (e) {
      e.preventDefault();
      $(this).addClass("ba-drag-over");
    });
    $dropZone.on("dragleave", function () {
      $(this).removeClass("ba-drag-over");
    });
    $dropZone.on("drop", function (e) {
      e.preventDefault();
      $(this).removeClass("ba-drag-over");
      const file = e.originalEvent.dataTransfer.files[0];
      if (file && file.name.endsWith(".json")) {
        $("#ba-import-file")[0].files = e.originalEvent.dataTransfer.files;
        $("#ba-import-file").trigger("change");
      } else {
        alert("Please drop a .json file.");
      }
    });

    // Click to browse
    $dropZone.on("click", function () {
      $("#ba-import-file").click();
    });
  }

  // ── Shortcode Copy ──────────────────────────────────────────────────────
  function initShortcodeCopy() {
    $(".ba-copy-shortcode").on("click", function () {
      const shortcode = $(this).data("shortcode");
      copyToClipboard(shortcode);
      showToast(l10n?.copied || "Copied!");
    });
  }

  // ── Export ──────────────────────────────────────────────────────────────
  function initExport() {
    $(".ba-export-single").on("click", function (e) {
      e.preventDefault();
      const widgetId = $(this).data("id");
      exportWidget(widgetId);
    });
  }

  function exportWidget(widgetId) {
    $.post(ajax_url, {
      action: "ba_export_widget",
      nonce: nonce,
      widget_id: widgetId,
    })
      .done((res) => {
        if (res.success) {
          const json = res.data.json;
          const filename = res.data.filename || "widget.json";
          downloadJSON(json, filename);
        } else {
          alert(res.data?.message || "Export failed.");
        }
      })
      .fail(() => {
        alert("Export failed.");
      });
  }

  // ── Utilities ───────────────────────────────────────────────────────────
  function copyToClipboard(text) {
    const $temp = $('<textarea style="position:absolute;left:-9999px;">');
    $("body").append($temp);
    $temp.val(text).select();
    document.execCommand("copy");
    $temp.remove();
  }

  function downloadJSON(json, filename) {
    const blob = new Blob([json], { type: "application/json" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
  }

  function showToast(message) {
    const $toast = $(`<div class="ba-toast">${escHtml(message)}</div>`);
    $("body").append($toast);
    setTimeout(() => $toast.addClass("show"), 10);
    setTimeout(() => {
      $toast.removeClass("show");
      setTimeout(() => $toast.remove(), 300);
    }, 2000);
  }

  function escHtml(str) {
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }
})(jQuery);
