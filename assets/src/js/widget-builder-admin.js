import "../sass/widget-builder-admin.scss";

(function ($) {
  "use strict";

  // ── Global State ────────────────────────────────────────────────────────
  const editorData = JSON.parse(
    document.getElementById("ba-editor-data")?.textContent || "{}",
  );
  const {
    widget_id,
    nonce,
    ajax_url,
    controls,
    includes,
    ctrl_types,
    back_url,
    l10n,
  } = editorData;

  let controlsState = Array.isArray(controls)
    ? JSON.parse(JSON.stringify(controls))
    : [];
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
      const newIcon = prompt(
        "Enter icon class (e.g. dashicons-layout or eicon-code):",
      );
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
      const types = e.originalEvent.dataTransfer.types;
      const moves = dtHas(types, "application/x-ba-section");
      const ctrlMoves = dtHas(types, "application/x-ba-control");
      const isMove = moves || ctrlMoves;
      const onSection = !!$(e.target).closest(".ba-ctrl-section").length;
      e.originalEvent.dataTransfer.dropEffect = isMove ? "move" : "copy";
      $(this).removeClass(isMove ? "ba-drop-insert" : "ba-drop-over");
      // Over a section card → the section itself shows the cyan insert outline.
      if (!onSection)
        $(this).addClass(isMove ? "ba-drop-over" : "ba-drop-insert");
    });

    $(".ba-controls-drop-area").on("dragleave", function () {
      $(this).removeClass("ba-drop-over ba-drop-insert");
    });

    $(".ba-controls-drop-area").on("drop", function (e) {
      e.preventDefault();
      $(this).removeClass("ba-drop-over ba-drop-insert");
      // Dropping directly on a section card is handled by the section drop
      // handler — never also create an outside section here.
      if ($(e.target).closest(".ba-ctrl-section").length) return;
      const types = e.originalEvent.dataTransfer.types;

      // Section move → drop at end of this tab
      if (dtHas(types, "application/x-ba-section")) {
        const fromIdx = Number(
          e.originalEvent.dataTransfer.getData("application/x-ba-section"),
        );
        moveSectionToEnd(fromIdx);
        return;
      }

      // Control row move → drop at end of this tab (outside any section)
      if (dtHas(types, "application/x-ba-control")) {
        const fromIdx = Number(
          e.originalEvent.dataTransfer.getData("application/x-ba-control"),
        );
        if (Number.isInteger(fromIdx)) {
          moveControlToArea(fromIdx, $(this).data("tab"));
        }
        return;
      }

      // Control palette → add control
      const type = e.originalEvent.dataTransfer.getData("text/plain");
      const tab = $(this).data("tab");
      if (type) addControl(type, tab);
    });

    // Control row click → open settings drawer
    $(document).on("click", ".ba-control-row", function () {
      const index = $(this).data("index");
      if (isLockedControl(index)) return;
      openControlSettings(index);
    });

    // Remove control
    $(document).on("click", ".ba-remove-ctrl", function (e) {
      e.stopPropagation();
      const index = $(this).closest(".ba-control-row").data("index");
      if (isLockedControl(index)) return;
      if (confirm(l10n?.delete_ctrl || "Delete this control?")) {
        removeControl(index);
      }
    });

    // Section drag & drop reorder (drag the header / handle)
    $(document).on("dragstart", ".ba-ctrl-section-header", function (e) {
      const $sec = $(this).closest(".ba-ctrl-section");
      if ($sec.hasClass("is-locked")) {
        e.preventDefault();
        return;
      }
      e.originalEvent.dataTransfer.setData(
        "application/x-ba-section",
        String($sec.data("section-index")),
      );
      e.originalEvent.dataTransfer.effectAllowed = "move";
      $sec.addClass("ba-sec-dragging");
    });

    $(document).on("dragend", ".ba-ctrl-section-header", function () {
      $(this).closest(".ba-ctrl-section").removeClass("ba-sec-dragging");
    });

    $(document).on("dragover", ".ba-ctrl-section", function (e) {
      const types = e.originalEvent.dataTransfer.types;
      const moves = dtHas(types, "application/x-ba-section");
      const copies = dtHas(types, "text/plain");
      const ctrlMoves = dtHas(types, "application/x-ba-control");
      if (!moves && !copies && !ctrlMoves) return;
      e.preventDefault();
      e.originalEvent.dataTransfer.dropEffect = moves
        ? "move"
        : ctrlMoves
          ? "move"
          : "copy";
      $(this).closest(".ba-controls-drop-area").removeClass("ba-drop-insert");
      // Control rows show their own before/after marker — don't outline the section then.
      if ($(e.target).closest(".ba-control-row").length) return;
      // Cyan dashed outline: valid drop target (reorder OR add-control-inside).
      $(this).addClass("ba-sec-drop-over");
    });

    $(document).on("dragleave", ".ba-ctrl-section", function () {
      $(this).removeClass("ba-sec-drop-over");
      $(this).closest(".ba-controls-drop-area").removeClass("ba-drop-insert");
    });

    $(document).on("drop", ".ba-ctrl-section", function (e) {
      const types = e.originalEvent.dataTransfer.types;
      const moves = dtHas(types, "application/x-ba-section");
      const copies = dtHas(types, "text/plain");
      const ctrlMoves = dtHas(types, "application/x-ba-control");
      if (!moves && !copies && !ctrlMoves) return;
      e.preventDefault();
      e.stopPropagation();
      $(this).removeClass("ba-sec-drop-over");
      $(this).closest(".ba-controls-drop-area").removeClass("ba-drop-insert");

      // Control row dropped on a section body → append inside this section.
      if (ctrlMoves) {
        const fromIdx = Number(
          e.originalEvent.dataTransfer.getData("application/x-ba-control"),
        );
        if (Number.isInteger(fromIdx)) {
          moveControlToSection(fromIdx, $(this).data("section-index"));
        }
        return;
      }

      if (moves) {
        const fromIdx = Number(
          e.originalEvent.dataTransfer.getData("application/x-ba-section"),
        );
        const toIdx = $(this).data("section-index");
        if (
          Number.isInteger(fromIdx) &&
          Number.isInteger(toIdx) &&
          fromIdx !== toIdx
        ) {
          moveSectionBefore(fromIdx, toIdx);
        }
        return;
      }

      // Palette control dropped onto a section body → add it inside this section.
      const type = e.originalEvent.dataTransfer.getData("text/plain");
      const tab = $(this).data("tab") || "content";
      const sectionIndex = $(this).data("section-index");
      if (type) addControl(type, tab, sectionIndex);
    });

    // ── Control row: up / down / duplicate / edit / remove buttons ──
    $(document).on("click", ".ba-ctrl-up", function (e) {
      e.stopPropagation();
      const index = $(this).closest(".ba-control-row").data("index");
      if (isLockedControl(index)) return;
      moveUnitByIndex(index, -1);
    });
    $(document).on("click", ".ba-ctrl-down", function (e) {
      e.stopPropagation();
      const index = $(this).closest(".ba-control-row").data("index");
      if (isLockedControl(index)) return;
      moveUnitByIndex(index, 1);
    });
    $(document).on("click", ".ba-ctrl-duplicate", function (e) {
      e.stopPropagation();
      const index = $(this).closest(".ba-control-row").data("index");
      if (isLockedControl(index)) return;
      duplicateControl(index);
    });
    $(document).on("click", ".ba-ctrl-edit", function (e) {
      e.stopPropagation();
      const index = $(this).closest(".ba-control-row").data("index");
      if (isLockedControl(index)) return;
      openControlSettings(index);
    });

    // ── Control row drag: move it inside or across sections ──
    $(document).on("dragstart", ".ba-control-row", function (e) {
      const index = $(this).data("index");
      if (isLockedControl(index)) {
        e.preventDefault();
        return;
      }
      e.originalEvent.dataTransfer.setData(
        "application/x-ba-control",
        String(index),
      );
      e.originalEvent.dataTransfer.effectAllowed = "move";
      $(this).addClass("ba-ctrl-dragging");
    });

    $(document).on("dragend", ".ba-control-row", function () {
      $(this).removeClass("ba-ctrl-dragging");
    });

    $(document).on("dragover", ".ba-control-row", function (e) {
      if (
        !dtHas(e.originalEvent.dataTransfer.types, "application/x-ba-control")
      )
        return;
      e.preventDefault();
      e.originalEvent.dataTransfer.dropEffect = "move";
      const rect = this.getBoundingClientRect();
      const before = e.originalEvent.clientY < rect.top + rect.height / 2;
      $(this)
        .removeClass("ba-ctrl-drop-before ba-ctrl-drop-after")
        .addClass(before ? "ba-ctrl-drop-before" : "ba-ctrl-drop-after");
    });

    $(document).on("dragleave", ".ba-control-row", function () {
      $(this).removeClass("ba-ctrl-drop-before ba-ctrl-drop-after");
    });

    $(document).on("drop", ".ba-control-row", function (e) {
      if (
        !dtHas(e.originalEvent.dataTransfer.types, "application/x-ba-control")
      )
        return;
      e.preventDefault();
      e.stopPropagation();
      const fromIdx = Number(
        e.originalEvent.dataTransfer.getData("application/x-ba-control"),
      );
      const toIdx = $(this).data("index");
      $(this).removeClass("ba-ctrl-drop-before ba-ctrl-drop-after");
      $(this).closest(".ba-ctrl-section").removeClass("ba-sec-drop-over");
      if (Number.isInteger(fromIdx) && fromIdx !== toIdx) {
        const rect = this.getBoundingClientRect();
        const before = e.originalEvent.clientY < rect.top + rect.height / 2;
        moveControlAround(fromIdx, toIdx, before);
      }
    });

    // Safety net: clear any leftover drop classes when a drag ends anywhere.
    $(document).on("dragend", function () {
      $(
        ".ba-controls-drop-area, .ba-ctrl-section, .ba-control-row",
      ).removeClass(
        "ba-drop-over ba-drop-insert ba-sec-drop-over ba-sec-dragging ba-ctrl-dragging ba-ctrl-drop-before ba-ctrl-drop-after",
      );
    });

    // Control settings drawer close
    $("#ba-ctrl-settings-close").on("click", function () {
      $("#ba-ctrl-settings-drawer").removeClass("active");
    });
  }

  // ── Code Editors ────────────────────────────────────────────────────────
  function initCodeEditors() {
    if (typeof wp === "undefined" || !wp.codeEditor) return;

    const BA_HTML_TAGS =
      "a abbr address area article aside audio b base bdi bdo blockquote body br button caption cite code col colgroup data datalist dd del details dfn dialog div dl dt em fieldset figcaption figure footer form h1 h2 h3 h4 h5 h6 head header hgroup hr html i iframe img input ins kbd label legend li link main map mark menu meta meter nav noscript object ol optgroup option output p picture pre progress q rp rt ruby s samp script search section select slot small source span strong style sub summary sup table tbody td template textarea tfoot th thead time title tr track u ul var video".split(
        " ",
      );
    const BA_VOID_TAGS = new Set([
      "area",
      "base",
      "br",
      "col",
      "embed",
      "hr",
      "img",
      "input",
      "link",
      "meta",
      "param",
      "source",
      "track",
      "wbr",
    ]);

    // "div" + Enter → <div></div> and put the caret inside the open tag.
    function completeBareTag(cm) {
      const mode = cm.getOption("mode");
      if (mode === "htmlmixed" || mode === "html" || mode === "xml") {
        const cur = cm.getCursor();
        const line = cm.getLine(cur.line);
        const after = line.slice(cur.ch).trim();
        const before = line.slice(0, cur.ch);
        if (!after && /^[a-zA-Z][\w-]*$/.test(before)) {
          const tag = before;
          const start = { line: cur.line, ch: 0 };
          cm.replaceRange(`<${tag}></${tag}>`, start, cur);
          cm.setCursor({ line: cur.line, ch: tag.length + 1 });
          return;
        }
      }
      cm.execCommand("newlineAndIndent");
    }

    // Auto-align the whole document using the mode indentation.
    function formatDoc(cm) {
      cm.operation(function () {
        for (let i = 0; i < cm.lineCount(); i++) cm.indentLine(i, "smart");
      });
    }

    // Register an HTML tag suggestion helper for the autocomplete popup.
    CodeMirror.registerHelper("hint", "baTags", function (cm) {
      const cur = cm.getCursor();
      const line = cm.getLine(cur.line);
      let start = cur.ch;
      while (start > 0 && /[\w-]/.test(line.charAt(start - 1))) start--;
      const word = line.slice(start, cur.ch).toLowerCase();
      const list = BA_HTML_TAGS.filter((t) => t.startsWith(word)).map((t) => ({
        text: BA_VOID_TAGS.has(t) ? `<${t}>` : `<${t}></${t}>`,
        displayText: `<${t}>`,
      }));
      return { list, from: { line: cur.line, ch: start }, to: cur };
    });

    const cmCommon = {
      theme: "ba-dark",
      lineNumbers: true,
      lineWrapping: true,
      indentUnit: 2,
      tabSize: 2,
      lint: false, // remove lint error/warning gutter marks
      foldGutter: true,
      gutters: ["CodeMirror-foldgutter", "CodeMirror-linenumbers"],
      foldOptions: { minFoldSize: 2 },
      extraKeys: {
        Enter: completeBareTag,
        "Ctrl-Space": (cm) =>
          cm.showHint({ hint: CodeMirror.hint.baTags, completeSingle: false }),
        "Ctrl-S": (cm) => {
          formatDoc(cm);
          showToast(l10n?.formatted || "Aligned!");
        },
        "Meta-S": (cm) => {
          formatDoc(cm);
          showToast(l10n?.formatted || "Aligned!");
        },
        "Alt-Shift-F": (cm) => {
          formatDoc(cm);
          showToast(l10n?.formatted || "Aligned!");
        },
        "Ctrl-F": (cm) => cm.execCommand("find"),
        "Cmd-F": (cm) => cm.execCommand("find"),
        "Ctrl-H": (cm) => cm.execCommand("replace"),
        "Cmd-H": (cm) => cm.execCommand("replace"),
        "Shift-Ctrl-F": (cm) => cm.execCommand("replace"),
        "Cmd-Alt-F": (cm) => cm.execCommand("replace"),
      },
    };

    // HTML
    const htmlEl = document.getElementById("ba-html-editor");
    if (htmlEl) {
      htmlEditor = wp.codeEditor.initialize(htmlEl, {
        codemirror: Object.assign({}, cmCommon, { mode: "htmlmixed" }),
      });
      htmlEditor.codemirror.on("change", markUnsaved);
    }

    // CSS
    const cssEl = document.getElementById("ba-css-editor");
    if (cssEl) {
      cssEditor = wp.codeEditor.initialize(cssEl, {
        codemirror: Object.assign({}, cmCommon, { mode: "css" }),
      });
      cssEditor.codemirror.on("change", markUnsaved);
    }

    // JS
    const jsEl = document.getElementById("ba-js-editor");
    if (jsEl) {
      jsEditor = wp.codeEditor.initialize(jsEl, {
        codemirror: Object.assign({}, cmCommon, { mode: "javascript" }),
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

    // Auto Format → re-align the indentation of the whole document.
    $("#ba-code-format").on("click", function () {
      const editor = currentEditor();
      if (!editor) return;
      formatDoc(editor.codemirror);
      markUnsaved();
      showToast(l10n?.formatted || "Aligned!");
    });
  }

  // ── Docs Panel ──────────────────────────────────────────────────────────
  function initDocsPanel() {
    // Token copy
    $(document).on("click", ".ba-token-copy", function (e) {
      e.stopPropagation();
      const token = $(this).data("token");
      copyToClipboard(token);
      showToast(l10n?.copied || "Copied!");
    });

    // Click a token card → paste its code into the active code editor.
    $(document).on(
      "click",
      ".ba-docs-tokens [data-token-insert]",
      function (e) {
        if ($(e.target).closest(".ba-token-copy").length) return;
        e.stopPropagation();
        insertTokenToEditor($(this).data("tokenInsert"));
      },
    );
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
      // Section markers are structural, not usable tokens — never list them.
      if (ctrl.type === "section_start" || ctrl.type === "section_end") return;

      if (ctrl.type === "repeater") {
        // Repeater: show loop hint + sub-field tokens
        const subFields = ctrl.sub_fields || [];
        const loopToken = `{% for item in ${ctrl.id} %}…{% endfor %}`;
        let subTokensHtml = "";
        subFields.forEach((sf) => {
          if (!sf.id) return;
          const sfToken = `{{item.${sf.id}}}`;
          subTokensHtml += `
            <div class="ba-token-sub-item" data-token-insert="${escAttr(sfToken)}" title="Click to insert">
              <span class="ba-token-sub-label">${escHtml(sf.label || sf.id)}</span>
              <code class="ba-token-sub-code">${escHtml(sfToken)}</code>
              <button type="button" class="ba-token-copy" data-token="${escAttr(sfToken)}" title="Copy">
                <span class="dashicons dashicons-admin-page"></span>
              </button>
            </div>
          `;
        });

        const $item = $(`
          <div class="ba-token-item ba-token-item--repeater" data-token-insert="${escAttr("{{" + ctrl.id + "}}")}" title="Click to insert">
            <div class="ba-token-repeater-header">
              <span class="dashicons dashicons-menu ba-token-repeater-icon"></span>
              <div class="ba-token-label">${escHtml(ctrl.label || ctrl.id)}</div>
              <span class="ba-ctrl-type-badge">repeater</span>
            </div>
            <div class="ba-token-loop-hint" data-token-insert="${escAttr("{% for item in " + ctrl.id + " %}")}" title="Click to insert">
              <code>${escHtml(loopToken)}</code>
              <button type="button" class="ba-token-copy" data-token="${escAttr("{% for item in " + ctrl.id + " %}")}" title="Copy loop">
                <span class="dashicons dashicons-admin-page"></span>
              </button>
            </div>
            ${subFields.length ? `<div class="ba-token-sub-fields">${subTokensHtml}</div>` : '<p class="ba-token-no-subs">No sub-fields defined yet.</p>'}
          </div>
        `);
        $tokens.append($item);
      } else {
        const token = `{{${ctrl.id}}}`;
        const label = ctrl.label || ctrl.id;
        const $item = $(`
          <div class="ba-token-item" data-token-insert="${escAttr(token)}" title="Click to insert into editor">
            <div class="ba-token-head">
              <div class="ba-token-label">${escHtml(label)}</div>
              <button type="button" class="ba-token-copy" data-token="${escAttr(token)}" title="Copy">
                <span class="dashicons dashicons-admin-page"></span>
              </button>
            </div>
            <div class="ba-token-code">${escHtml(token)}</div>
          </div>
        `);
        $tokens.append($item);
      }
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
    const $list =
      type === "css" ? $("#ba-includes-css-list") : $("#ba-includes-js-list");
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
    renderControls();
    updateDocsPanel();
  }

  function addControl(type, tab, sectionIndex) {
    const base = {
      id: `control_${Date.now()}`,
      label: ctrl_types[type]?.label || type,
      type: type,
      tab: tab,
      default: "",
      options: [],
    };

    // "Add Section" → create a matched section_start + section_end pair
    if (type === "section_start") {
      const sectionId = `section_${Date.now()}`;
      controlsState.push({ ...base, id: sectionId, label: "New Section" });
      controlsState.push({
        ...base,
        id: `section_end_${Date.now()}`,
        type: "section_end",
        label: "Section End",
      });
      renderControls();
      updateDocsPanel();
      markUnsaved();
      // Auto-open settings so the user can name the section + set its ID
      setTimeout(() => openControlSettings(controlsState.length - 2), 100);
      return;
    }

    // Dropping a control ONTO a section card → it is added inside that section.
    const tree = buildControlTree();
    const unit = sectionIndex != null ? findUnit(tree, sectionIndex) : null;
    if (
      unit &&
      unit.isSection &&
      (unit.ctrl.tab || "content") === tab &&
      unit.endIndex !== null &&
      !unit.ctrl.locked
    ) {
      controlsState.splice(unit.endIndex, 0, base);
      renderControls();
      updateDocsPanel();
      markUnsaved();
      setTimeout(() => openControlSettings(unit.endIndex), 100);
      return;
    }

    // Dropping OUTSIDE any section → a brand-new section wraps the control.
    const wrapAt = controlsState.length;
    controlsState.push(
      {
        id: `section_${Date.now()}`,
        label: "New Section",
        type: "section_start",
        tab: tab,
        default: "",
        options: [],
      },
      base,
      {
        id: `section_end_${Date.now()}`,
        label: "Section End",
        type: "section_end",
        tab: tab,
        default: "",
        options: [],
      },
    );
    renderControls();
    updateDocsPanel();
    markUnsaved();
    setTimeout(() => openControlSettings(wrapAt + 1), 100);
  }

  // Groups the flat controls array into render units:
  //   - free controls → { index, ctrl }
  //   - sections      → { isSection, index, ctrl, children, endIndex, endCtrl }
  function buildControlTree() {
    const units = [];
    const stack = [];
    controlsState.forEach((ctrl, i) => {
      if (ctrl.type === "section_start") {
        const section = {
          isSection: true,
          index: i,
          ctrl: ctrl,
          children: [],
          endIndex: null,
          endCtrl: null,
        };
        if (stack.length) stack[stack.length - 1].children.push(section);
        else units.push(section);
        stack.push(section);
      } else if (ctrl.type === "section_end") {
        const open = stack.pop();
        if (open) {
          open.endIndex = i;
          open.endCtrl = ctrl;
        } else {
          units.push({ index: i, ctrl });
        }
      } else {
        const item = { index: i, ctrl };
        if (stack.length) stack[stack.length - 1].children.push(item);
        else units.push(item);
      }
    });
    return units;
  }

  function findUnit(units, index) {
    for (const unit of units) {
      if (unit.index === index) return unit;
      if (unit.children) {
        const found = findUnit(unit.children, index);
        if (found) return found;
      }
    }
    return null;
  }

  function findUnitPos(units, index) {
    for (let i = 0; i < units.length; i++) {
      if (units[i].index === index) return i;
    }
    return -1;
  }

  // True when the control itself, or the section it lives in, is locked.
  function isLockedControl(index) {
    let locked = false;
    (function walk(units, parentLocked) {
      for (const unit of units) {
        if (unit.isSection) {
          const secLocked = parentLocked || !!unit.ctrl.locked;
          if (
            unit.index === index ||
            (unit.children || []).some((c) => c.index === index)
          ) {
            locked = secLocked;
            return;
          }
          walk(unit.children || [], secLocked);
          if (locked) return;
        } else if (unit.index === index) {
          locked = parentLocked;
          return;
        }
      }
    })(buildControlTree(), false);
    return locked;
  }

  function renderControls() {
    $(".ba-controls-drop-area").each(function () {
      const tab = $(this).data("tab");
      $(this).empty().append(`
        <div class="ba-drop-hint" id="ba-drop-hint-${tab}">
          <button type="button" class="ba-add-section-btn" data-tab="${tab}">
            <span class="dashicons dashicons-plus-alt2"></span>
            Add Section
          </button>
          <span class="dashicons dashicons-arrow-left-alt2"></span>
          <p>${l10n?.drag_hint || "Drag controls from the left panel."}</p>
        </div>
      `);
    });

    const tree = buildControlTree();
    const freeControls = tree.filter((u) => !u.isSection);
    tree.forEach((unit) => {
      const tab = unit.ctrl.tab || "content";
      const $area = $(`#ba-controls-${tab}`);
      $area.find(".ba-drop-hint").hide();
      if (unit.isSection) {
        const sections = tree.filter((u) => u.isSection);
        renderSection(unit, $area, {
          firstSection: sections[0] === unit,
          lastSection: sections[sections.length - 1] === unit,
        });
      } else {
        renderControlRow(unit.ctrl, unit.index, $area, {
          first: freeControls[0] === unit,
          last: freeControls[freeControls.length - 1] === unit,
        });
      }
    });
  }

  function renderControlRow(ctrl, index, $container, pos = {}) {
    const tab = ctrl.tab || "content";
    const $area = $container || $(`#ba-controls-${tab}`);
    const icon = ctrl_types[ctrl.type]?.icon || "dashicons-layout";
    const locked = isLockedControl(index);
    const noUp = locked || pos.first;
    const noDown = locked || pos.last;

    const $row = $(`
      <div class="ba-control-row" data-index="${index}" data-tab="${tab}" data-type="${ctrl.type}" draggable="${!locked}" title="Click to edit — drag to another section">
        <div class="ba-ctrl-icon">
          <span class="dashicons ${icon}"></span>
        </div>
        <div class="ba-ctrl-info">
          <div class="ba-ctrl-label">${escHtml(ctrl.label || ctrl.id)}</div>
          <div class="ba-ctrl-meta">
            <code class="ba-ctrl-id">{{${ctrl.id}}}</code>
          </div>
        </div>
        <div class="ba-ctrl-actions">
          <button type="button" class="ba-ctrl-up" title="Move up" ${noUp ? "disabled" : ""}>
            <span class="dashicons dashicons-arrow-up-alt2"></span>
          </button>
          <button type="button" class="ba-ctrl-down" title="Move down" ${noDown ? "disabled" : ""}>
            <span class="dashicons dashicons-arrow-down-alt2"></span>
          </button>
          <button type="button" class="ba-ctrl-duplicate" title="Duplicate" ${locked ? "disabled" : ""}>
            <span class="dashicons dashicons-admin-page"></span>
          </button>
          <button type="button" class="ba-ctrl-edit" title="Edit" ${locked ? "disabled" : ""}>
            <span class="dashicons dashicons-admin-generic"></span>
          </button>
          <button type="button" class="ba-remove-ctrl" title="Remove">
            <span class="dashicons dashicons-trash"></span>
          </button>
        </div>
      </div>
    `);

    // Hide drop hint
    $area.find(".ba-drop-hint").hide();
    $area.append($row);
  }

  // Elementor-style section header bar with drag handle, name + ID,
  // up/down arrows and lock / edit / duplicate / delete actions.
  function renderSection(section, $container, pos = {}) {
    const start = section.ctrl;
    const tab = start.tab || "content";
    const id = start.id || "section";
    const label = start.label || "Section";
    const locked = !!start.locked;
    const collapsed = !!start.collapsed;
    const lockT = locked ? "Unlock section" : "Lock section";
    // First section can't move up; last section can't move down.
    const noUp = locked || pos.firstSection;
    const noDown = locked || pos.lastSection;

    const $el = $(`
      <div class="ba-ctrl-section${locked ? " is-locked" : ""}${collapsed ? " is-collapsed" : ""}" data-section-index="${section.index}" data-tab="${tab}">
        <div class="ba-ctrl-section-header" draggable="true" title="Drag to reorder section">
          <button type="button" class="ba-sec-collapse" title="${collapsed ? "Expand section" : "Collapse section"}">
            <span class="dashicons dashicons-arrow-down-alt2 ba-sec-collapse-icon"></span>
          </button>
          <div class="ba-sec-info">
            <span class="ba-sec-name">${escHtml(label)}</span>
            <code class="ba-sec-id">{{${escHtml(id)}}}</code>
          </div>
          <div class="ba-sec-actions">
            <button type="button" class="ba-sec-up" title="Move up" ${noUp ? "disabled" : ""}>
              <span class="dashicons dashicons-arrow-up-alt2"></span>
            </button>
            <button type="button" class="ba-sec-down" title="Move down" ${noDown ? "disabled" : ""}>
              <span class="dashicons dashicons-arrow-down-alt2"></span>
            </button>
            <button type="button" class="ba-sec-lock" title="${lockT}">
              <span class="dashicons dashicons-${locked ? "lock" : "unlock"}"></span>
            </button>
            <button type="button" class="ba-sec-edit" title="Edit section" ${locked ? "disabled" : ""}>
              <span class="dashicons dashicons-admin-generic"></span>
            </button>
            <button type="button" class="ba-sec-duplicate" title="Duplicate section" ${locked ? "disabled" : ""}>
              <span class="dashicons dashicons-admin-page"></span>
            </button>
            <button type="button" class="ba-sec-delete" title="Delete section" ${locked ? "disabled" : ""}>
              <span class="dashicons dashicons-trash"></span>
            </button>
          </div>
        </div>
        <div class="ba-ctrl-section-body"></div>
      </div>
    `);

    const $body = $el.find(".ba-ctrl-section-body");
    const children = section.children || [];
    const childSections = children.filter((u) => u.isSection);
    const childControls = children.filter((u) => !u.isSection);
    children.forEach((unit) => {
      if (unit.isSection) {
        renderSection(unit, $body, {
          firstSection: childSections[0] === unit,
          lastSection: childSections[childSections.length - 1] === unit,
        });
      } else {
        renderControlRow(unit.ctrl, unit.index, $body, {
          first: childControls[0] === unit,
          last: childControls[childControls.length - 1] === unit,
        });
      }
    });

    $container.append($el);
  }

  function extractBlock(unit) {
    const start = unit.index;
    const end = unit.isSection ? unit.endIndex : unit.index;
    return controlsState.splice(start, end - start + 1);
  }

  function toggleSectionCollapse(index) {
    const ctrl = controlsState[index];
    if (!ctrl) return;
    ctrl.collapsed = !ctrl.collapsed;
    renderControls();
    markUnsaved();
  }

  // Adds a new section immediately AFTER the given section (from its footer
  function toggleSectionLock(index) {
    const ctrl = controlsState[index];
    if (!ctrl) return;
    ctrl.locked = !ctrl.locked;
    renderControls();
    markUnsaved();
  }

  function duplicateSection(index) {
    const unit = findUnit(buildControlTree(), index);
    if (!unit || !unit.isSection) return;
    const block = JSON.parse(
      JSON.stringify(controlsState.slice(unit.index, unit.endIndex + 1)),
    );
    const suffix = Date.now();
    block.forEach((c) => {
      c.id = (c.id || "control") + "_copy" + suffix;
      if (c.locked) c.locked = false;
      if (c.collapsed) c.collapsed = false;
    });
    controlsState.splice(unit.endIndex + 1, 0, ...block);
    renderControls();
    updateDocsPanel();
    markUnsaved();
  }

  function deleteSection(index) {
    const unit = findUnit(buildControlTree(), index);
    if (!unit || !unit.isSection) return;
    controlsState.splice(unit.index, unit.endIndex - unit.index + 1);
    $("#ba-ctrl-settings-drawer").removeClass("active");
    renderControls();
    updateDocsPanel();
    markUnsaved();
  }

  // Move a whole section / free control one slot up (-1) or down (+1).
  function moveUnitByIndex(index, direction) {
    const tree = buildControlTree();
    const pos = findUnitPos(tree, index);
    if (pos === -1) return;
    const target = tree[pos + direction];
    if (!target) return;
    const block = extractBlock(tree[pos]);
    let insertAt;
    if (direction === -1) {
      insertAt = Math.max(0, target.index);
    } else {
      const tEnd = target.isSection ? target.endIndex : target.index;
      insertAt = Math.max(0, tEnd - block.length + 1);
    }
    controlsState.splice(Math.min(insertAt, controlsState.length), 0, ...block);
    renderControls();
    markUnsaved();
  }

  // Drag & drop: place the dragged section before the drop target section.
  function moveSectionBefore(fromIdx, toIdx) {
    const tree = buildControlTree();
    const from = findUnit(tree, fromIdx);
    const to = findUnit(tree, toIdx);
    if (!from || !to || from === to) return;
    // No-op when dropping a section onto one of its own nested children.
    if (
      to.isSection &&
      from.isSection &&
      toIdx > fromIdx &&
      toIdx <= from.endIndex
    ) {
      return;
    }
    const block = extractBlock(from);
    let insertAt = to.index;
    if (to.index > from.index) insertAt = to.index - block.length;
    controlsState.splice(Math.max(0, insertAt), 0, ...block);
    renderControls();
    markUnsaved();
  }

  // Drag & drop onto an empty drop area → move the section to the end.
  function moveSectionToEnd(fromIdx) {
    const tree = buildControlTree();
    const from = findUnit(tree, fromIdx);
    if (!from) return;
    const block = extractBlock(from);
    controlsState.push(...block);
    renderControls();
    markUnsaved();
  }

  // Duplicate a single control row (inserts a copy right after it).
  function duplicateControl(index) {
    const ctrl = controlsState[index];
    if (!ctrl) return;
    const copy = JSON.parse(JSON.stringify(ctrl));
    copy.locked = false;
    copy.id = (ctrl.id || "control") + "_copy" + Date.now();
    controlsState.splice(index + 1, 0, copy);
    renderControls();
    updateDocsPanel();
    markUnsaved();
  }

  // Drag & drop: append a control row to the end of a section's body.
  function moveControlToSection(fromIdx, sectionIndex) {
    const removed = controlsState.splice(fromIdx, 1)[0];
    if (!removed) {
      renderControls();
      return;
    }
    const unit = findUnit(buildControlTree(), sectionIndex);
    if (!unit || !unit.isSection || unit.ctrl.locked) {
      renderControls();
      return;
    }
    removed.tab = unit.ctrl.tab || removed.tab;
    controlsState.splice(unit.endIndex, 0, removed);
    renderControls();
    updateDocsPanel();
    markUnsaved();
  }

  // Drag & drop: place a control row right before/after another control row.
  // Works within one section, or moves it across sections.
  function moveControlAround(fromIdx, targetIdx, before) {
    if (fromIdx === targetIdx) return;
    const removed = controlsState.splice(fromIdx, 1)[0];
    if (!removed) {
      renderControls();
      return;
    }
    let tIdx = targetIdx;
    if (fromIdx < targetIdx) tIdx -= 1;
    const targetCtrl = controlsState[tIdx];
    if (targetCtrl) removed.tab = targetCtrl.tab || removed.tab;
    controlsState.splice(before ? tIdx : tIdx + 1, 0, removed);
    renderControls();
    updateDocsPanel();
    markUnsaved();
  }

  // Drag & drop: move a control row out to the end of a tab's outer area.
  function moveControlToArea(fromIdx, tab) {
    const removed = controlsState.splice(fromIdx, 1)[0];
    if (!removed) {
      renderControls();
      return;
    }
    removed.tab = tab;
    controlsState.push(removed);
    renderControls();
    updateDocsPanel();
    markUnsaved();
  }

  function removeControl(index) {
    controlsState.splice(index, 1);
    renderControls();
    $("#ba-ctrl-settings-drawer").removeClass("active");
    updateDocsPanel();
    markUnsaved();
  }

  function openControlSettings(index) {
    if (isLockedControl(index)) return;
    currentControlIndex = index;
    const ctrl = controlsState[index];
    if (!ctrl) return;

    $("#ba-ctrl-settings-title").text((ctrl.label || ctrl.id) + " Settings");

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

    // ── Repeater: sub-fields editor ────────────────────────────────────
    if (ctrl.type === "repeater") {
      const subFields = ctrl.sub_fields || [];

      // Available sub-field types
      const subFieldTypes = [
        { value: "text", label: "Text" },
        { value: "textarea", label: "Textarea" },
        { value: "media", label: "Media (Image)" },
        { value: "url", label: "URL" },
        { value: "color", label: "Color" },
        { value: "select", label: "Select" },
        { value: "switcher", label: "Switcher" },
        { value: "icons", label: "Icons" },
        { value: "number", label: "Number" },
        { value: "wysiwyg", label: "WYSIWYG" },
      ];
      const typeOptions = subFieldTypes
        .map((t) => `<option value="${t.value}">${t.label}</option>`)
        .join("");

      // Build existing sub-field rows
      let subFieldsHtml = "";
      subFields.forEach((sf, si) => {
        const optsSel = subFieldTypes
          .map(
            (t) =>
              `<option value="${t.value}" ${sf.type === t.value ? "selected" : ""}>${t.label}</option>`,
          )
          .join("");
        subFieldsHtml += `
          <div class="ba-subfield-row" data-sf-index="${si}">
            <div class="ba-subfield-row-header">
              <span class="ba-subfield-drag dashicons dashicons-menu"></span>
              <strong class="ba-subfield-title">${escHtml(sf.label || sf.id || "Sub-Field")}</strong>
              <button type="button" class="ba-remove-subfield button-link-delete" title="Remove sub-field">
                <span class="dashicons dashicons-trash"></span>
              </button>
            </div>
            <div class="ba-subfield-body">
              <div class="ba-subfield-grid">
                <div class="ba-subfield-col">
                  <label>Field ID</label>
                  <input type="text" class="ba-sf-id widefat" value="${escAttr(sf.id || "")}" placeholder="e.g. item_image">
                </div>
                <div class="ba-subfield-col">
                  <label>Label</label>
                  <input type="text" class="ba-sf-label widefat" value="${escAttr(sf.label || "")}" placeholder="e.g. Item Image">
                </div>
                <div class="ba-subfield-col">
                  <label>Type</label>
                  <select class="ba-sf-type widefat">${optsSel}</select>
                </div>
                <div class="ba-subfield-col">
                  <label>Default</label>
                  <input type="text" class="ba-sf-default widefat" value="${escAttr(sf.default || "")}" placeholder="Default value">
                </div>
              </div>
              <div class="ba-subfield-token-hint">
                Token: <code>{{item.${escHtml(sf.id || "field_id")}}}</code>
              </div>
            </div>
          </div>
        `;
      });

      $body.append(`
        <div class="ba-ctrl-field ba-repeater-section">
          <label class="ba-repeater-label">
            <span class="dashicons dashicons-menu"></span>
            Repeater Sub-Fields
          </label>
          <p class="ba-field-desc">
            Define the fields inside each repeater item.<br>
            Use <code>{{item.field_id}}</code> in your HTML template to render sub-field values.<br>
            Wrap with <code>{% for item in ${ctrl.id || "control_id"} %}...{% endfor %}</code> to loop.
          </p>
          <div class="ba-subfields-list" id="ba-ctrl-subfields-list">
            ${subFieldsHtml}
          </div>
          <button type="button" class="button ba-btn-add-subfield">
            <span class="dashicons dashicons-plus-alt2"></span> Add Sub-Field
          </button>
        </div>
      `);

      // Live-update token hint on ID input
      $body.on("input", ".ba-sf-id", function () {
        const $row = $(this).closest(".ba-subfield-row");
        const newId = $(this).val() || "field_id";
        $row.find(".ba-subfield-token-hint code").text(`{{item.${newId}}}`);
        $row
          .find(".ba-subfield-title")
          .text($row.find(".ba-sf-label").val() || newId);
      });

      // Live-update title on label input
      $body.on("input", ".ba-sf-label", function () {
        const $row = $(this).closest(".ba-subfield-row");
        const newLabel =
          $(this).val() || $row.find(".ba-sf-id").val() || "Sub-Field";
        $row.find(".ba-subfield-title").text(newLabel);
      });

      // Add sub-field
      $body.on("click", ".ba-btn-add-subfield", function () {
        const $list = $("#ba-ctrl-subfields-list");
        const si = $list.children(".ba-subfield-row").length;
        const $sf = $(`
          <div class="ba-subfield-row" data-sf-index="${si}">
            <div class="ba-subfield-row-header">
              <span class="ba-subfield-drag dashicons dashicons-menu"></span>
              <strong class="ba-subfield-title">New Sub-Field</strong>
              <button type="button" class="ba-remove-subfield button-link-delete" title="Remove sub-field">
                <span class="dashicons dashicons-trash"></span>
              </button>
            </div>
            <div class="ba-subfield-body">
              <div class="ba-subfield-grid">
                <div class="ba-subfield-col">
                  <label>Field ID</label>
                  <input type="text" class="ba-sf-id widefat" placeholder="e.g. item_image">
                </div>
                <div class="ba-subfield-col">
                  <label>Label</label>
                  <input type="text" class="ba-sf-label widefat" placeholder="e.g. Item Image">
                </div>
                <div class="ba-subfield-col">
                  <label>Type</label>
                  <select class="ba-sf-type widefat">${typeOptions}</select>
                </div>
                <div class="ba-subfield-col">
                  <label>Default</label>
                  <input type="text" class="ba-sf-default widefat" placeholder="Default value">
                </div>
              </div>
              <div class="ba-subfield-token-hint">
                Token: <code>{{item.field_id}}</code>
              </div>
            </div>
          </div>
        `);
        $list.append($sf);
        $sf.find(".ba-sf-id").trigger("focus");
      });

      // Remove sub-field
      $body.on("click", ".ba-remove-subfield", function () {
        $(this).closest(".ba-subfield-row").remove();
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

    $("#ba-ctrl-settings-drawer").addClass("active");
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

    // Save repeater sub-fields
    if (ctrl.type === "repeater") {
      const subFields = [];
      $("#ba-ctrl-subfields-list .ba-subfield-row").each(function () {
        const id = $(this).find(".ba-sf-id").val().trim();
        const label = $(this).find(".ba-sf-label").val().trim();
        const type = $(this).find(".ba-sf-type").val();
        const def = $(this).find(".ba-sf-default").val();
        if (id) {
          subFields.push({ id, label, type, default: def });
        }
      });
      ctrl.sub_fields = subFields;
    }

    renderControls();
    updateDocsPanel();
    markUnsaved();
    $("#ba-ctrl-settings-drawer").removeClass("active");
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
            const newUrl =
              window.location.href + "&widget_id=" + res.data.widget_id;
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
  function currentEditor() {
    const tab = $(".ba-code-tab.active").data("code-tab");
    if (tab === "html") return htmlEditor;
    if (tab === "css") return cssEditor;
    if (tab === "js") return jsEditor;
    return null;
  }

  // Paste a token into the active code editor at the caret position.
  function insertTokenToEditor(token) {
    if (!currentEditor()) {
      showToast(l10n?.noEditor || "Open a code tab first");
      return;
    }
    const cm = currentEditor().codemirror;
    cm.focus();
    cm.replaceSelection(token, "end");
    markUnsaved();
  }

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

  function dtHas(types, token) {
    if (!types) return false;
    return Array.prototype.indexOf.call(types, token) !== -1;
  }

  function bindEvents() {
    // Unsaved changes warning
    $(window).on("beforeunload", function () {
      if (unsavedChanges) {
        return "You have unsaved changes. Leave anyway?";
      }
    });

    // + Add Section button → adds a section_start control to that tab
    $(document).on("click", ".ba-add-section-btn", function () {
      const tab = $(this).data("tab");
      addControl("section_start", tab);
    });

    // ── Section header: up / down / lock / edit / duplicate / delete ──
    $(document).on("click", ".ba-sec-collapse", function () {
      toggleSectionCollapse(
        $(this).closest(".ba-ctrl-section").data("section-index"),
      );
    });
    $(document).on("click", ".ba-sec-up", function () {
      moveUnitByIndex(
        $(this).closest(".ba-ctrl-section").data("section-index"),
        -1,
      );
    });
    $(document).on("click", ".ba-sec-down", function () {
      moveUnitByIndex(
        $(this).closest(".ba-ctrl-section").data("section-index"),
        1,
      );
    });
    $(document).on("click", ".ba-sec-lock", function () {
      toggleSectionLock(
        $(this).closest(".ba-ctrl-section").data("section-index"),
      );
    });
    $(document).on("click", ".ba-sec-edit", function () {
      openControlSettings(
        $(this).closest(".ba-ctrl-section").data("section-index"),
      );
    });
    $(document).on("click", ".ba-sec-duplicate", function () {
      duplicateSection(
        $(this).closest(".ba-ctrl-section").data("section-index"),
      );
    });
    $(document).on("click", ".ba-sec-delete", function () {
      const idx = $(this).closest(".ba-ctrl-section").data("section-index");
      if (
        confirm(
          l10n?.delete_section ||
            "Delete this entire section and its controls?",
        )
      ) {
        deleteSection(idx);
      }
    });

    // Keep topbar settings label in sync with title input
    $(document).on("input", "#ba-widget-title-input", function () {
      $(".ba-topbar-settings-label").text($(this).val() || "Untitled Widget");
    });
  }
})(jQuery);
