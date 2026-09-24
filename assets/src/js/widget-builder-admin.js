import "../sass/widget-builder-admin.scss";

(function ($) {
  "use strict";

  // ── Global State ────────────────────────────────────────────────────────
  const editorData = JSON.parse(
    document.getElementById("ba-editor-data")?.textContent || "{}",
  );
  const { widget_id, nonce, ajax_url, controls, includes, ctrl_types, back_url, l10n } =
    editorData;

  let controlsState = Array.isArray(controls) ? JSON.parse(JSON.stringify(controls)) : [];
  let includesState = includes || { css: [], js: [] };
  let activeTab = "content";
  let currentControlIndex = null;
  let unsavedChanges = false;
  let autoSaveTimer = null;

  // CodeMirror editors
  let htmlEditor, cssEditor, jsEditor;

  // ── Init ────────────────────────────────────────────────────────────────
  $(function () {
    initTopbar();
    initSettingsPanel();
    initPalette();
    initControlPanel();
    initCodeEditors();
    initDocsPanel();
    initIncludes();
    loadControls();
    bindEvents();
    startAutoSave();
  });

  // ── Topbar ──────────────────────────────────────────────────────────────
  function initTopbar() {
    // Title edit
    $("#ba-widget-title-input").on("input", function () {
      markUnsaved();
      $("#ba-settings-title").val($(this).val());
    });

    // Settings gear → slide in panel
    $("#ba-widget-settings-btn").on("click", function () {
      $("#ba-settings-panel").addClass("active");
    });

    // Preview
    $("#ba-preview-btn").on("click", function () {
      alert(l10n?.preview || "Preview functionality coming soon!");
    });

    // Save
    $("#ba-save-btn").on("click", function () {
      saveWidget();
    });
  }

  // ── Settings Panel ──────────────────────────────────────────────────────
  function initSettingsPanel() {
    // Close
    $("#ba-settings-panel-close").on("click", function () {
      $("#ba-settings-panel").removeClass("active");
    });

    // Title sync
    $("#ba-settings-title").on("input", function () {
      $("#ba-widget-title-input").val($(this).val());
      markUnsaved();
    });

    // Icon picker (simple placeholder — full picker can be enhanced)
    $("#ba-change-icon-btn").on("click", function () {
      const newIcon = prompt("Enter icon class (e.g. dashicons-layout or eicon-code):");
      if (newIcon) {
        $("#ba-widget-icon").val(newIcon);
        $("#ba-icon-preview-span").attr("class", newIcon);
        markUnsaved();
      }
    });

    // Category change
    $("#ba-widget-category").on("change", markUnsaved);

    // Status change
    $("#ba-widget-status").on("change", function () {
      const status = $(this).val();
      const $dot = $("#ba-status-dot");
      const $label = $("#ba-status-label");
      if (status === "publish") {
        $dot.removeClass("ba-status-draft").addClass("ba-status-live");
        $label.text("Active");
      } else {
        $dot.removeClass("ba-status-live").addClass("ba-status-draft");
        $label.text("Draft");
      }
      markUnsaved();
    });

    // Export
    $("#ba-export-widget-btn").on("click", function () {
      exportWidget();
    });
  }

  // ── Palette (Control Types) ─────────────────────────────────────────────
  function initPalette() {
    // Search
    $("#ba-palette-search").on("input", function () {
      const query = $(this).val().toLowerCase();
      $(".ba-palette-item").each(function () {
        const label = $(this).data("label").toLowerCase();
        $(this).toggle(label.includes(query));
      });
    });

    // Drag start
    $(".ba-palette-item").on("dragstart", function (e) {
      const type = $(this).data("type");
      e.originalEvent.dataTransfer.setData("text/plain", type);
      e.originalEvent.dataTransfer.effectAllowed = "copy";
    });
  }

  // ── Control Panel (CONTENT / ADVANCED / STYLE tabs) ────────────────────
  function initControlPanel() {
    // Tab switching
    $(".ba-panel-tab").on("click", function () {
      const tab = $(this).data("tab");
      $(".ba-panel-tab").removeClass("active");
      $(this).addClass("active");
      $(".ba-panel-tab-content").removeClass("active");
      $("#ba-tab-" + tab).addClass("active");
      activeTab = tab;
    });

    // Drop zones
    $(".ba-controls-drop-area").on("dragover", function (e) {
      e.preventDefault();
      e.originalEvent.dataTransfer.dropEffect = "copy";
      $(this).addClass("ba-drop-over");
    });

    $(".ba-controls-drop-area").on("dragleave", function () {
      $(this).removeClass("ba-drop-over");
    });

    $(".ba-controls-drop-area").on("drop", function (e) {
      e.preventDefault();
      $(this).removeClass("ba-drop-over");
      const type = e.originalEvent.dataTransfer.getData("text/plain");
      const tab = $(this).data("tab");
      addControl(type, tab);
    });

    // Control row click → open settings drawer
    $(document).on("click", ".ba-control-row", function () {
      const index = $(this).data("index");
      openControlSettings(index);
    });

    // Remove control
    $(document).on("click", ".ba-remove-ctrl", function (e) {
      e.stopPropagation();
      const index = $(this).closest(".ba-control-row").data("index");
      if (confirm(l10n?.delete_ctrl || "Delete this control?")) {
        removeControl(index);
      }
    });

    // Control settings drawer close
    $("#ba-ctrl-settings-close").on("click", function () {
      $("#ba-ctrl-settings-drawer").hide();
    });
  }

  // ── Code Editors ────────────────────────────────────────────────────────
  function initCodeEditors() {
    if (typeof wp === "undefined" || !wp.codeEditor) return;

    // HTML
    const htmlEl = document.getElementById("ba-html-editor");
    if (htmlEl) {
      htmlEditor = wp.codeEditor.initialize(htmlEl, {
        codemirror: {
          mode: "htmlmixed",
          lineNumbers: true,
          lineWrapping: true,
          theme: "default",
          indentUnit: 2,
          tabSize: 2,
        },
      });
      htmlEditor.codemirror.on("change", markUnsaved);
    }

    // CSS
    const cssEl = document.getElementById("ba-css-editor");
    if (cssEl) {
      cssEditor = wp.codeEditor.initialize(cssEl, {
        codemirror: {
          mode: "css",
          lineNumbers: true,
          lineWrapping: true,
          theme: "default",
          indentUnit: 2,
          tabSize: 2,
        },
      });
      cssEditor.codemirror.on("change", markUnsaved);
    }

    // JS
    const jsEl = document.getElementById("ba-js-editor");
    if (jsEl) {
      jsEditor = wp.codeEditor.initialize(jsEl, {
        codemirror: {
          mode: "javascript",
          lineNumbers: true,
          lineWrapping: true,
          theme: "default",
          indentUnit: 2,
          tabSize: 2,
        },
      });
      jsEditor.codemirror.on("change", markUnsaved);
    }

    // Code tab switching
    $(".ba-code-tab").on("click", function () {
      const tab = $(this).data("code-tab");
      $(".ba-code-tab").removeClass("active");
      $(this).addClass("active");
      $(".ba-code-panel").removeClass("active");
      $("#ba-code-panel-" + tab).addClass("active");
      // Refresh CodeMirror when switching tabs
      setTimeout(() => {
        if (tab === "html" && htmlEditor) htmlEditor.codemirror.refresh();
        if (tab === "css" && cssEditor) cssEditor.codemirror.refresh();
        if (tab === "js" && jsEditor) jsEditor.codemirror.refresh();
      }, 50);
    });
  }

  // ── Docs Panel ──────────────────────────────────────────────────────────
  function initDocsPanel() {
    // Token copy
    $(document).on("click", ".ba-token-copy", function () {
      const token = $(this).data("token");
      copyToClipboard(token);
      showToast(l10n?.copied || "Copied!");
    });
  }

  function updateDocsPanel() {
    const $tokens = $("#ba-docs-tokens");
    const $empty = $("#ba-docs-empty");

    if (controlsState.length === 0) {
      $empty.show();
      $tokens.empty();
      return;
    }

    $empty.hide();
    $tokens.empty();

    controlsState.forEach((ctrl) => {
      if (!ctrl.id) return;
      const token = `{{${ctrl.id}}}`;
      const label = ctrl.label || ctrl.id;
      const $item = $(`
        <div class="ba-token-item">
          <div class="ba-token-label">${escHtml(label)}</div>
          <div class="ba-token-code">${escHtml(token)}</div>
          <button type="button" class="ba-token-copy" data-token="${escAttr(token)}" title="Copy">
            <span class="dashicons dashicons-admin-page"></span>
          </button>
        </div>
      `);
      $tokens.append($item);
    });
  }

  // ── Includes ────────────────────────────────────────────────────────────
  function initIncludes() {
    // Add CSS
    $(".ba-btn-add-include[data-target='css']").on("click", function () {
      addIncludeRow("css");
    });

    // Add JS
    $(".ba-btn-add-include[data-target='js']").on("click", function () {
      addIncludeRow("js");
    });

    // Remove include
    $(document).on("click", ".ba-remove-include", function () {
      $(this).closest(".ba-include-row").remove();
      markUnsaved();
    });

    // Input changes
    $(document).on("input", ".ba-include-handle, .ba-include-src", markUnsaved);
  }

  function addIncludeRow(type) {
    const $list = type === "css" ? $("#ba-includes-css-list") : $("#ba-includes-js-list");
    const placeholder =
      type === "css"
        ? "https://cdn.example.com/style.css"
        : "https://cdn.example.com/library.js";
    const $row = $(`
      <div class="ba-include-row">
        <input type="text" class="ba-include-handle" placeholder="Handle (unique name)">
        <input type="url" class="ba-include-src" placeholder="${placeholder}">
        <button type="button" class="ba-remove-include button-link-delete">
          <span class="dashicons dashicons-trash"></span>
        </button>
      </div>
    `);
    $list.append($row);
    markUnsaved();
  }

  // ── Control Management ──────────────────────────────────────────────────
  function loadControls() {
    controlsState.forEach((ctrl, index) => {
      renderControlRow(ctrl, index);
    });
    updateDocsPanel();
  }

  function addControl(type, tab) {
    const ctrl = {
      id: `control_${Date.now()}`,
      label: ctrl_types[type]?.label || type,
      type: type,
      tab: tab,
      default: "",
      options: [],
    };
    controlsState.push(ctrl);
    renderControlRow(ctrl, controlsState.length - 1);
    updateDocsPanel();
    markUnsaved();
    // Auto-open settings
    setTimeout(() => openControlSettings(controlsState.length - 1), 100);
  }

  function renderControlRow(ctrl, index) {
    const tab = ctrl.tab || "content";
    const $area = $(`#ba-controls-${tab}`);
    const icon = ctrl_types[ctrl.type]?.icon || "dashicons-layout";

    const $row = $(`
      <div class="ba-control-row" data-index="${index}" data-tab="${tab}">
        <div class="ba-ctrl-icon">
          <span class="dashicons ${icon}"></span>
        </div>
        <div class="ba-ctrl-info">
          <div class="ba-ctrl-label">${escHtml(ctrl.label || ctrl.id)}</div>
          <div class="ba-ctrl-meta">
            <code class="ba-ctrl-id">{{${ctrl.id}}}</code>
            <span class="ba-ctrl-type-badge">${escHtml(ctrl.type)}</span>
          </div>
        </div>
        <button type="button" class="ba-remove-ctrl" title="Remove">
          <span class="dashicons dashicons-trash"></span>
        </button>
      </div>
    `);

    // Hide drop hint
    $area.find(".ba-drop-hint").hide();
    $area.append($row);
  }

  function removeControl(index) {
    controlsState.splice(index, 1);
    reRenderControls();
    $("#ba-ctrl-settings-drawer").hide();
    updateDocsPanel();
    markUnsaved();
  }

  function reRenderControls() {
    $(".ba-controls-drop-area").each(function () {
      const tab = $(this).data("tab");
      $(this).empty().append(`
        <div class="ba-drop-hint" id="ba-drop-hint-${tab}">
          <span class="dashicons dashicons-arrow-left-alt2"></span>
          <p>${l10n?.drag_hint || "Drag controls from the left panel."}</p>
        </div>
      `);
    });

    controlsState.forEach((ctrl, index) => {
      renderControlRow(ctrl, index);
    });
  }

  function openControlSettings(index) {
    currentControlIndex = index;
    const ctrl = controlsState[index];
    if (!ctrl) return;

    $("#ba-ctrl-settings-title").text(
      (ctrl.label || ctrl.id) + " Settings",
    );

    const $body = $("#ba-ctrl-settings-body");
    $body.empty();

    // Build form
    $body.append(`
      <div class="ba-ctrl-field">
        <label>Control ID</label>
        <input type="text" id="ba-ctrl-edit-id" value="${escAttr(ctrl.id)}" class="widefat" placeholder="my_control">
        <p class="ba-field-desc">Used as {{${ctrl.id}}} in your HTML/CSS/JS.</p>
      </div>
      <div class="ba-ctrl-field">
        <label>Label</label>
        <input type="text" id="ba-ctrl-edit-label" value="${escAttr(ctrl.label)}" class="widefat" placeholder="My Control">
      </div>
      <div class="ba-ctrl-field">
        <label>Type</label>
        <input type="text" value="${escAttr(ctrl.type)}" class="widefat" disabled>
      </div>
      <div class="ba-ctrl-field">
        <label>Default Value</label>
        <input type="text" id="ba-ctrl-edit-default" value="${escAttr(ctrl.default)}" class="widefat">
      </div>
      <div class="ba-ctrl-field">
        <label>Tab</label>
        <select id="ba-ctrl-edit-tab" class="widefat">
          <option value="content" ${ctrl.tab === "content" ? "selected" : ""}>Content</option>
          <option value="advanced" ${ctrl.tab === "advanced" ? "selected" : ""}>Advanced</option>
          <option value="style" ${ctrl.tab === "style" ? "selected" : ""}>Style</option>
        </select>
      </div>
    `);

    // Options editor for select/choose
    if (ctrl.type === "select" || ctrl.type === "choose") {
      const opts = ctrl.options || [];
      let optsHtml = "";
      opts.forEach((opt, i) => {
        optsHtml += `
          <div class="ba-option-row">
            <input type="text" class="ba-opt-value" value="${escAttr(opt.value || "")}" placeholder="value">
            <input type="text" class="ba-opt-label" value="${escAttr(opt.label || "")}" placeholder="Label">
            ${ctrl.type === "choose" ? `<input type="text" class="ba-opt-icon" value="${escAttr(opt.icon || "")}" placeholder="eicon-circle">` : ""}
            <button type="button" class="ba-remove-option button-link-delete">×</button>
          </div>
        `;
      });

      $body.append(`
        <div class="ba-ctrl-field">
          <label>Options</label>
          <div class="ba-options-list" id="ba-ctrl-options-list">
            ${optsHtml}
          </div>
          <button type="button" class="button ba-btn-add-option">+ Add Option</button>
        </div>
      `);

      // Add option
      $body.on("click", ".ba-btn-add-option", function () {
        const $list = $("#ba-ctrl-options-list");
        const iconField =
          ctrl.type === "choose"
            ? `<input type="text" class="ba-opt-icon" placeholder="eicon-circle">`
            : "";
        const $opt = $(`
          <div class="ba-option-row">
            <input type="text" class="ba-opt-value" placeholder="value">
            <input type="text" class="ba-opt-label" placeholder="Label">
            ${iconField}
            <button type="button" class="ba-remove-option button-link-delete">×</button>
          </div>
        `);
        $list.append($opt);
      });

      // Remove option
      $body.on("click", ".ba-remove-option", function () {
        $(this).closest(".ba-option-row").remove();
      });
    }

    // Save button
    $body.append(`
      <div class="ba-ctrl-field">
        <button type="button" id="ba-save-ctrl-settings" class="button button-primary">
          Save Control Settings
        </button>
      </div>
    `);

    // Save handler
    $("#ba-save-ctrl-settings").on("click", function () {
      saveControlSettings(index);
    });

    $("#ba-ctrl-settings-drawer").show();
  }

  function saveControlSettings(index) {
    const ctrl = controlsState[index];
    if (!ctrl) return;

    ctrl.id = $("#ba-ctrl-edit-id").val().trim();
    ctrl.label = $("#ba-ctrl-edit-label").val().trim();
    ctrl.default = $("#ba-ctrl-edit-default").val();
    ctrl.tab = $("#ba-ctrl-edit-tab").val();

    // Save options if present
    if (ctrl.type === "select" || ctrl.type === "choose") {
      const options = [];
      $("#ba-ctrl-options-list .ba-option-row").each(function () {
        const value = $(this).find(".ba-opt-value").val();
        const label = $(this).find(".ba-opt-label").val();
        const icon = $(this).find(".ba-opt-icon").val();
        if (value) {
          options.push({ value, label, icon });
        }
      });
      ctrl.options = options;
    }

    reRenderControls();
    updateDocsPanel();
    markUnsaved();
    $("#ba-ctrl-settings-drawer").hide();
  }

  // ── Save / Export ───────────────────────────────────────────────────────
  function saveWidget() {
    const $btn = $("#ba-save-btn");
    $btn.text(l10n?.saving || "Saving…").prop("disabled", true);

    // Gather includes
    const cssIncludes = [];
    $("#ba-includes-css-list .ba-include-row").each(function () {
      const handle = $(this).find(".ba-include-handle").val();
      const src = $(this).find(".ba-include-src").val();
      if (handle && src) {
        cssIncludes.push({ handle, src });
      }
    });

    const jsIncludes = [];
    $("#ba-includes-js-list .ba-include-row").each(function () {
      const handle = $(this).find(".ba-include-handle").val();
      const src = $(this).find(".ba-include-src").val();
      if (handle && src) {
        jsIncludes.push({ handle, src });
      }
    });

    includesState = { css: cssIncludes, js: jsIncludes };

    const data = {
      action: "ba_save_widget",
      nonce: nonce,
      widget_id: widget_id || 0,
      title: $("#ba-widget-title-input").val(),
      status: $("#ba-widget-status").val(),
      icon: $("#ba-widget-icon").val(),
      category: $("#ba-widget-category").val(),
      controls: JSON.stringify(controlsState),
      html: htmlEditor ? htmlEditor.codemirror.getValue() : "",
      css: cssEditor ? cssEditor.codemirror.getValue() : "",
      js: jsEditor ? jsEditor.codemirror.getValue() : "",
      includes: JSON.stringify(includesState),
    };

    $.post(ajax_url, data)
      .done((res) => {
        if (res.success) {
          $btn.text(l10n?.saved || "Saved!");
          unsavedChanges = false;
          $("#ba-autosave-indicator").text("");
          setTimeout(() => {
            $btn.text("Save Settings").prop("disabled", false);
          }, 1500);

          // If new widget, reload to add widget_id to URL
          if (!widget_id && res.data.widget_id) {
            const newUrl = window.location.href + "&widget_id=" + res.data.widget_id;
            window.location.href = newUrl;
          }
        } else {
          alert(res.data?.message || l10n?.save_error || "Save failed.");
          $btn.text("Save Settings").prop("disabled", false);
        }
      })
      .fail(() => {
        alert(l10n?.save_error || "Save failed.");
        $btn.text("Save Settings").prop("disabled", false);
      });
  }

  function exportWidget() {
    $.post(ajax_url, {
      action: "ba_export_widget",
      nonce: nonce,
      widget_id: widget_id,
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

  // ── Auto-save ───────────────────────────────────────────────────────────
  function startAutoSave() {
    autoSaveTimer = setInterval(() => {
      if (unsavedChanges && widget_id) {
        $("#ba-autosave-indicator").text("Auto-saving…");
        saveWidget();
      }
    }, 60000); // Every 60s
  }

  function markUnsaved() {
    unsavedChanges = true;
    $("#ba-autosave-indicator").text("●");
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

  function escAttr(str) {
    return String(str).replace(/"/g, "&quot;");
  }

  function bindEvents() {
    // Unsaved changes warning
    $(window).on("beforeunload", function () {
      if (unsavedChanges) {
        return "You have unsaved changes. Leave anyway?";
      }
    });
  }
})(jQuery);
