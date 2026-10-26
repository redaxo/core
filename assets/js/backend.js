/* Built from assets/js/src/ with `npm run build:backend-js`, do not edit. */

// assets/js/src/ready.js
function ready(root = document.body) {
  root.dispatchEvent(new CustomEvent("rex:ready", { bubbles: true }));
}

// assets/js/src/overlay.js
var stack = [];
var skipPopstate = 0;
function pageOf(url) {
  return new URL(url, location.href).searchParams.get("page") ?? "";
}
function scopeOf(url) {
  return pageOf(url).split("/")[0];
}
var Overlay = class {
  /**
   * @param {string|URL} url
   * @param {{multiple?: boolean, onPick?: (items: Array<{value: string, label: string}>) => void}} options
   */
  constructor(url, { multiple = false, onPick = null } = {}) {
    this.url = new URL(url, location.href).toString();
    this.scope = scopeOf(this.url);
    this.multiple = multiple;
    this.onPick = onPick;
    this.picked = [];
    this.closed = new Promise((resolve) => this.resolveClosed = resolve);
    this.dialog = document.createElement("dialog");
    this.dialog.className = "rex-overlay";
    this.dialog.innerHTML = `
            <header class="rex-overlay-header">
                <h2 class="rex-overlay-title"></h2>
                <button type="button" class="rex-overlay-close" data-rex-overlay-close></button>
            </header>
            <div class="rex-overlay-body"></div>`;
    this.title = this.dialog.querySelector(".rex-overlay-title");
    this.body = this.dialog.querySelector(".rex-overlay-body");
    this.body.tabIndex = -1;
    this.body.autofocus = true;
    const close = this.dialog.querySelector(".rex-overlay-close");
    close.setAttribute("aria-label", window.rex.i18n?.overlay_close ?? "Close");
    close.innerHTML = '<i class="rex-icon fa-solid fa-xmark" aria-hidden="true"></i>';
    this.dialog.addEventListener("cancel", (event) => {
      event.preventDefault();
      this.close();
    });
    this.dialog.addEventListener("click", (event) => this.onClick(event));
    this.dialog.addEventListener("submit", (event) => this.onSubmit(event));
    document.body.append(this.dialog);
    this.dialog.showModal();
    stack.push(this);
    history.pushState({ rexOverlay: stack.length }, "");
    this.load(this.url);
  }
  async load(url, init = {}) {
    this.dialog.classList.add("is-loading");
    try {
      const response = await fetch(url, { credentials: "same-origin", ...init });
      if (401 === response.status || "login" === pageOf(response.url)) {
        location.href = response.url;
        return;
      }
      const doc = new DOMParser().parseFromString(await response.text(), "text/html");
      const main = doc.getElementById("rex-js-page-main") ?? doc.body;
      const heading = main.querySelector(".rex-pageheader__title");
      this.title.textContent = heading?.textContent.trim() || doc.title.split(" \xB7 ")[0];
      heading?.remove();
      this.url = response.url;
      this.body.replaceChildren(...main.childNodes);
      this.body.scrollTop = 0;
      ready(this.body);
      const auto = this.body.querySelector("[data-rex-pick-auto]");
      if (auto) {
        this.pick(auto, [itemOf(auto)]);
      }
    } catch (error) {
      this.body.textContent = String(error);
    } finally {
      this.dialog.classList.remove("is-loading");
    }
  }
  /** Whether a URL is loaded inside this overlay (instead of leaving it for the main window). */
  keeps(url) {
    return url.origin === location.origin && url.pathname === new URL(this.url).pathname && scopeOf(url) === this.scope;
  }
  onClick(event) {
    if (event.target.closest("[data-rex-overlay-close]")) {
      this.close();
      return;
    }
    const trigger = event.target.closest("[data-rex-pick], [data-rex-pick-checked]");
    if (trigger) {
      event.preventDefault();
      const items = trigger.hasAttribute("data-rex-pick-checked") ? [...this.body.querySelectorAll(`input[name="${CSS.escape(trigger.dataset.rexPickChecked)}"]:checked`)].map((input) => ({ value: input.value, label: input.value })) : [itemOf(trigger)];
      this.pick(trigger, items);
      return;
    }
    const link = event.target.closest("a[href]");
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || link.target || link.hasAttribute("download") || link.hasAttribute("data-rex-overlay")) {
      return;
    }
    const href = link.getAttribute("href");
    if (href.startsWith("#") || href.startsWith("javascript:")) {
      return;
    }
    event.stopPropagation();
    if (link.dataset.confirm && !confirm(link.dataset.confirm)) {
      event.preventDefault();
      return;
    }
    const url = new URL(href, this.url);
    if (this.keeps(url)) {
      event.preventDefault();
      this.load(url);
    } else if (url.origin === location.origin) {
      event.preventDefault();
      closeAll(true);
      location.href = url;
    }
  }
  onSubmit(event) {
    const form = event.target;
    const url = new URL(event.submitter?.getAttribute("formaction") || form.getAttribute("action") || this.url, this.url);
    if (event.defaultPrevented || form.target || !this.keeps(url)) {
      return;
    }
    event.stopPropagation();
    if (form.dataset.confirm && !confirm(form.dataset.confirm)) {
      event.preventDefault();
      return;
    }
    event.preventDefault();
    const data = new FormData(form, event.submitter);
    const method = (event.submitter?.getAttribute("formmethod") || form.getAttribute("method") || "get").toUpperCase();
    if ("GET" === method) {
      url.search = new URLSearchParams([...data].filter(([, value]) => "string" === typeof value)).toString();
      this.load(url);
    } else {
      this.load(url, { method, body: data });
    }
  }
  /** Hands the items to the opener, unless a `rex:pick` listener cancels; a single picker closes afterwards. */
  pick(trigger, items) {
    if (!items.length) {
      return;
    }
    const event = new CustomEvent("rex:pick", { bubbles: true, cancelable: true, detail: { items, overlay: this } });
    if (!trigger.dispatchEvent(event)) {
      return;
    }
    this.picked.push(...items);
    this.onPick?.(items);
    trigger.classList.add("rex-is-picked");
    if (!this.multiple) {
      this.close();
    }
  }
  close(fromHistory = false) {
    const index = stack.indexOf(this);
    if (index < 0) {
      return;
    }
    stack.splice(index, 1);
    this.dialog.close();
    this.dialog.remove();
    this.resolveClosed(this.picked);
    if (!fromHistory) {
      ++skipPopstate;
      history.back();
    }
  }
};
function itemOf(element) {
  return { value: element.dataset.rexPick, label: element.dataset.rexPickLabel || element.dataset.rexPick };
}
function closeAll(keepHistory = false) {
  while (stack.length) {
    stack.at(-1).close(keepHistory);
  }
}
window.addEventListener("popstate", () => {
  if (skipPopstate > 0) {
    --skipPopstate;
    return;
  }
  stack.at(-1)?.close(true);
});
function overlay(url, options = {}) {
  return new Overlay(url, options);
}

// assets/js/src/pickers.js
function backendUrl(page, params) {
  const search = new URLSearchParams({ page });
  for (const [key2, value] of Object.entries(params)) {
    if (value !== void 0 && value !== null && value !== "") {
      search.set(key2, String(value));
    }
  }
  return "index.php?" + search;
}
var pickers = {
  media: ({ multiple, upload, types, category, file }) => backendUrl(upload ? "mediapool/upload" : "mediapool/media", {
    picker: multiple ? "multiple" : "single",
    types: types?.join(","),
    rex_file_category: category,
    file_name: file
  }),
  article: ({ multiple, category, language }) => backendUrl("linkmap", {
    picker: multiple ? "multiple" : "single",
    category_id: category,
    language
  })
};
function pick(type, options = {}) {
  if (!pickers[type]) {
    throw new Error(`Unknown picker type "${type}".`);
  }
  return overlay(pickers[type](options), options).closed;
}

// assets/js/src/dialog.js
var count = 0;
function dialog({ title = "", content, buttons = [], className = "" }) {
  const element = document.createElement("dialog");
  element.className = ("rex-dialog " + className).trim();
  element.innerHTML = `
        <form method="dialog" class="rex-dialog-inner">
            <header class="rex-dialog-header">
                <h2 class="rex-dialog-title"></h2>
                <button class="rex-dialog-close" value=""><i class="rex-icon fa-solid fa-xmark" aria-hidden="true"></i></button>
            </header>
            <div class="rex-dialog-body"></div>
            <footer class="rex-dialog-footer"></footer>
        </form>`;
  const header = element.querySelector(".rex-dialog-header");
  if (title) {
    element.querySelector(".rex-dialog-title").innerHTML = title;
    element.setAttribute("aria-labelledby", element.querySelector(".rex-dialog-title").id = "rex-dialog-title-" + ++count);
  } else {
    header.remove();
  }
  element.querySelector(".rex-dialog-close")?.setAttribute("aria-label", window.rex.i18n?.overlay_close ?? "Close");
  const body = element.querySelector(".rex-dialog-body");
  content instanceof Node ? body.append(content) : body.innerHTML = content;
  const footer = element.querySelector(".rex-dialog-footer");
  for (const { label, value = "", className: className2 = "", primary = false, onClick } of buttons) {
    const button2 = document.createElement("button");
    button2.className = ["btn", primary ? "btn-primary" : "btn-default", className2].join(" ").trim();
    button2.value = value;
    button2.innerHTML = label;
    button2.autofocus = primary;
    if (onClick) {
      button2.addEventListener("click", onClick);
    }
    footer.append(button2);
  }
  if (!buttons.length) {
    footer.remove();
  }
  const closed = new Promise((resolve) => element.addEventListener("close", () => {
    resolve(element.returnValue);
    element.remove();
  }, { once: true }));
  document.body.append(element);
  element.showModal();
  return { element, body, close: (value = "") => element.close(value), closed };
}
async function confirm2(message) {
  const i18n3 = window.rex.i18n ?? {};
  const { closed } = dialog({
    content: Object.assign(document.createElement("p"), { textContent: message }),
    className: "rex-dialog--confirm",
    buttons: [
      { label: i18n3.dialog_cancel ?? "Cancel", value: "cancel" },
      { label: i18n3.dialog_confirm ?? "OK", value: "confirm", primary: true }
    ]
  });
  return "confirm" === await closed;
}
var confirmed = /* @__PURE__ */ new WeakSet();
window.addEventListener("click", (event) => {
  const element = event.target.closest?.("a[data-confirm], button[data-confirm], input[data-confirm]");
  if (!element || confirmed.delete(element)) {
    return;
  }
  event.preventDefault();
  event.stopImmediatePropagation();
  confirm2(element.dataset.confirm).then((ok) => {
    if (ok) {
      confirmed.add(element);
      element.click();
    }
  });
}, true);
window.addEventListener("submit", (event) => {
  const form = event.target;
  if (!form.matches("form[data-confirm]") || confirmed.delete(form)) {
    return;
  }
  event.preventDefault();
  event.stopImmediatePropagation();
  confirm2(form.dataset.confirm).then((ok) => {
    if (ok) {
      confirmed.add(form);
      form.requestSubmit(event.submitter);
    }
  });
}, true);

// assets/js/src/toggles.js
function openDropdowns() {
  return document.querySelectorAll('.open:has(> [data-toggle="dropdown"])');
}
function closeDropdowns(except = null) {
  for (const dropdown of openDropdowns()) {
    if (dropdown !== except) {
      dropdown.classList.remove("open");
      dropdown.querySelector(':scope > [data-toggle="dropdown"]').setAttribute("aria-expanded", "false");
    }
  }
}
function toggleDropdown(toggle) {
  const dropdown = toggle.parentElement;
  const open = !dropdown.classList.contains("open");
  closeDropdowns(dropdown);
  if (open) {
    const menu = dropdown.querySelector(":scope > .dropdown-menu");
    const rect = dropdown.getBoundingClientRect();
    dropdown.classList.remove("dropup");
    dropdown.classList.add("open");
    const height = menu?.offsetHeight ?? 0;
    dropdown.classList.toggle("dropup", innerHeight - rect.bottom < height && rect.top > height);
  } else {
    dropdown.classList.remove("open");
  }
  toggle.setAttribute("aria-expanded", String(open));
}
document.addEventListener("click", (event) => {
  const toggle = event.target.closest('[data-toggle="dropdown"]');
  if (toggle) {
    event.preventDefault();
    if (!toggle.matches(".disabled, :disabled")) {
      toggleDropdown(toggle);
    }
    return;
  }
  if (!event.target.closest(".dropdown-menu form")) {
    closeDropdowns();
  }
});
document.addEventListener("keydown", (event) => {
  const dropdown = event.target.closest('.open:has(> [data-toggle="dropdown"])');
  if (!dropdown) {
    return;
  }
  const toggle = dropdown.querySelector(':scope > [data-toggle="dropdown"]');
  if ("Escape" === event.key) {
    event.preventDefault();
    closeDropdowns();
    toggle.focus();
  } else if ("ArrowDown" === event.key || "ArrowUp" === event.key) {
    event.preventDefault();
    const items = [...dropdown.querySelectorAll(":scope > .dropdown-menu li:not(.disabled) > a")];
    const index = items.indexOf(document.activeElement);
    const next = "ArrowDown" === event.key ? Math.min(index + 1, items.length - 1) : Math.max(index - 1, 0);
    items[next]?.focus();
  }
});
function targetOf(toggle) {
  const selector = toggle.dataset.target ?? toggle.getAttribute("href");
  return selector?.startsWith("#") ? document.querySelector(selector) : null;
}
function collapse(target, open = !target.classList.contains("in")) {
  if (open === target.classList.contains("in")) {
    return;
  }
  target.classList.toggle("in", open);
  for (const toggle of document.querySelectorAll('[data-toggle="collapse"]')) {
    if (targetOf(toggle) === target) {
      toggle.classList.toggle("collapsed", !open);
      toggle.setAttribute("aria-expanded", String(open));
    }
  }
  target.dispatchEvent(new CustomEvent("rex:collapse", { bubbles: true, detail: { open } }));
}
document.addEventListener("click", (event) => {
  const toggle = event.target.closest('[data-toggle="collapse"]');
  const target = toggle && targetOf(toggle);
  if (target) {
    event.preventDefault();
    collapse(target);
  }
});
document.addEventListener("click", (event) => {
  const link = event.target.closest('[data-toggle="tab"]');
  const pane = link && targetOf(link);
  if (!pane) {
    return;
  }
  event.preventDefault();
  const item = link.closest("li") ?? link;
  for (const sibling of item.parentElement.children) {
    sibling.classList.toggle("active", sibling === item);
    sibling.querySelector('[data-toggle="tab"]')?.setAttribute("aria-selected", String(sibling === item));
  }
  for (const sibling of pane.parentElement.children) {
    sibling.classList.toggle("active", sibling === pane);
    sibling.classList.toggle("in", sibling === pane && sibling.classList.contains("fade"));
  }
});

// assets/js/src/shell.js
var smallScreen = matchMedia("(max-width: 991px)");
function toggleSidebar(open = !document.body.classList.contains("rex-sidebar-open")) {
  document.body.classList.toggle("rex-sidebar-open", open);
  document.documentElement.style.overflowY = open ? "hidden" : "";
  document.getElementById("rex-js-nav-main-toggle")?.setAttribute("aria-expanded", String(open));
}
var topbar = {
  previous: 0,
  lastToggle: null,
  update() {
    const bar = document.querySelector(".rex-topbar--fixed");
    if (!bar) {
      return;
    }
    const max = document.body.scrollHeight - document.body.clientHeight;
    const current2 = Math.min(Math.max(window.scrollY, 0), max);
    this.lastToggle ??= current2;
    if (!document.body.classList.contains("rex-sidebar-open")) {
      if (current2 > this.previous && current2 >= this.lastToggle + 150) {
        bar.classList.add("is-hidden");
        this.lastToggle = current2;
      } else if (current2 < this.previous && (current2 <= this.lastToggle - 300 || current2 < 50)) {
        bar.classList.remove("is-hidden");
        this.lastToggle = current2;
      }
    }
    bar.classList.toggle("is-elevated", current2 > 10);
    this.previous = current2;
  },
  reset() {
    document.querySelector(".rex-topbar--fixed")?.classList.remove("is-hidden");
    this.lastToggle = this.previous;
  }
};
document.addEventListener("click", (event) => {
  if (event.target.closest("#rex-js-nav-main-toggle")) {
    event.preventDefault();
    toggleSidebar();
    topbar.reset();
  } else if (event.target.closest("#rex-js-nav-main-backdrop")) {
    toggleSidebar(false);
  }
});
var ticking = false;
window.addEventListener("scroll", () => {
  if (!ticking) {
    ticking = true;
    requestAnimationFrame(() => {
      topbar.update();
      ticking = false;
    });
  }
}, { passive: true });
smallScreen.addEventListener("change", () => {
  toggleSidebar(false);
  topbar.reset();
});
topbar.update();

// assets/js/src/widgets.js
function setValue(input, value) {
  input.value = value;
  input.dispatchEvent(new Event("change", { bubbles: true }));
}
function syncList(widget) {
  const options = [...widget.querySelector("[data-rex-widget-list]").options];
  setValue(widget.querySelector("[data-rex-widget-value]"), options.map((option) => option.value).join(","));
}
function addToList(widget, items) {
  const list = widget.querySelector("[data-rex-widget-list]");
  for (const { value, label } of items) {
    list.add(new Option(label, value));
  }
  syncList(widget);
}
function moveInList(widget, direction) {
  const list = widget.querySelector("[data-rex-widget-list]");
  const option = list.selectedOptions[0];
  if (!option) {
    return;
  }
  switch (direction) {
    case "top":
      list.prepend(option);
      break;
    case "up":
      option.previousElementSibling?.before(option);
      break;
    case "down":
      option.nextElementSibling?.after(option);
      break;
    case "bottom":
      list.append(option);
      break;
  }
  syncList(widget);
}
function selectedValue(widget) {
  return widget.querySelector("[data-rex-widget-list]").selectedOptions[0]?.value;
}
var widgetActions = {
  media: {
    open: (widget, options) => pick("media", { ...options, onPick: ([item]) => setValue(widget.querySelector("[data-rex-widget-value]"), item.value) }),
    add: (widget, options) => widgetActions.media.open(widget, { ...options, upload: true }),
    view: (widget, options) => {
      const file = widget.querySelector("[data-rex-widget-value]").value;
      if (file) {
        widgetActions.media.open(widget, { ...options, file });
      }
    },
    remove: (widget) => setValue(widget.querySelector("[data-rex-widget-value]"), "")
  },
  medialist: {
    open: (widget, options) => pick("media", { ...options, file: selectedValue(widget), multiple: true, onPick: (items) => addToList(widget, items) }),
    add: (widget, options) => pick("media", { ...options, upload: true, multiple: true, onPick: (items) => addToList(widget, items) }),
    view: (widget, options) => {
      const file = selectedValue(widget);
      if (file) {
        widgetActions.medialist.open(widget, options);
      }
    },
    remove: (widget) => {
      widget.querySelector("[data-rex-widget-list]").selectedOptions[0]?.remove();
      syncList(widget);
    },
    move: (widget, options, direction) => moveInList(widget, direction)
  },
  article: {
    open: (widget, options) => pick("article", { ...options, onPick: ([item]) => {
      widget.querySelector("[data-rex-widget-label]").value = item.label;
      setValue(widget.querySelector("[data-rex-widget-value]"), item.value);
    } }),
    remove: (widget) => {
      widget.querySelector("[data-rex-widget-label]").value = "";
      setValue(widget.querySelector("[data-rex-widget-value]"), "");
    }
  },
  articlelist: {
    open: (widget, options) => pick("article", { ...options, multiple: true, onPick: (items) => addToList(widget, items) }),
    remove: (widget, options) => widgetActions.medialist.remove(widget, options),
    move: (widget, options, direction) => moveInList(widget, direction)
  }
};
document.addEventListener("click", (event) => {
  const button2 = event.target.closest("[data-rex-widget-action]");
  const widget = button2?.closest("[data-rex-widget]");
  if (!widget || button2.disabled) {
    return;
  }
  event.preventDefault();
  const [action, argument] = button2.dataset.rexWidgetAction.split(":");
  const options = JSON.parse(widget.dataset.rexWidgetOptions || "{}");
  widgetActions[widget.dataset.rexWidget]?.[action]?.(widget, options, argument);
});
function showPreview(widget) {
  const preview = widget.querySelector(".rex-js-media-preview");
  const file = "medialist" === widget.dataset.rexWidget ? widget.querySelector("[data-rex-widget-list]").selectedOptions[0]?.value : widget.querySelector("[data-rex-widget-value]").value;
  if (!preview || !file) {
    preview?.replaceChildren();
    return;
  }
  const img = preview.querySelector("img") ?? preview.appendChild(document.createElement("img"));
  img.src = file.endsWith(".svg") ? "../media/" + encodeURIComponent(file) : "index.php?" + new URLSearchParams({ rex_media_type: "rex_media_small", rex_media_file: file });
}
document.addEventListener("mouseover", (event) => {
  const widget = event.target.closest("[data-rex-widget].rex-js-widget-preview");
  if (widget && !widget.contains(event.relatedTarget)) {
    showPreview(widget);
  }
});
document.addEventListener("mouseout", (event) => {
  const widget = event.target.closest("[data-rex-widget].rex-js-widget-preview");
  if (widget && !widget.contains(event.relatedTarget)) {
    widget.querySelector(".rex-js-media-preview")?.replaceChildren();
  }
});
document.addEventListener("change", (event) => {
  const widget = event.target.closest('[data-rex-widget="medialist"].rex-js-widget-preview');
  if (widget) {
    showPreview(widget);
  }
});
document.addEventListener("click", (event) => {
  const link = event.target.closest("a[data-rex-overlay]");
  if (link && 0 === event.button && !event.metaKey && !event.ctrlKey && !event.shiftKey) {
    event.preventDefault();
    event.stopPropagation();
    overlay(link.href);
  }
});

// assets/js/src/select.js
var i18n = () => window.rex.i18n ?? {};
var count2 = 0;
var Select = class {
  /** @param {HTMLSelectElement} select */
  constructor(select) {
    this.select = select;
    this.search = select.hasAttribute("data-rex-select-search");
    this.id = "rex-select-" + ++count2;
    this.element = document.createElement("div");
    this.element.className = "rex-select";
    this.element.innerHTML = `
            <button type="button" class="form-control rex-select-toggle" aria-haspopup="listbox" aria-expanded="false" popovertarget="${this.id}">
                <span class="rex-select-value"></span>
            </button>
            <div class="rex-select-panel" id="${this.id}" popover>
                ${this.search ? '<input type="search" class="form-control rex-select-search" autocomplete="off">' : ""}
                ${this.search && select.multiple ? `<div class="rex-select-actions">
                    <button type="button" class="btn btn-default btn-xs" data-rex-select-all></button>
                    <button type="button" class="btn btn-default btn-xs" data-rex-select-none></button>
                </div>` : ""}
                <ul class="rex-select-options" role="listbox"></ul>
                <p class="rex-select-empty" hidden></p>
            </div>`;
    this.toggle = this.element.querySelector(".rex-select-toggle");
    this.panel = this.element.querySelector(".rex-select-panel");
    this.list = this.element.querySelector(".rex-select-options");
    this.input = this.element.querySelector(".rex-select-search");
    this.empty = this.element.querySelector(".rex-select-empty");
    this.list.setAttribute("aria-multiselectable", String(select.multiple));
    this.empty.textContent = i18n().select_no_results ?? "No results";
    this.element.querySelector("[data-rex-select-all]")?.append(i18n().select_all ?? "All");
    this.element.querySelector("[data-rex-select-none]")?.append(i18n().select_none ?? "None");
    if (select.labels[0]) {
      this.toggle.setAttribute("aria-labelledby", select.labels[0].id ||= this.id + "-label");
    }
    select.before(this.element);
    this.element.append(select);
    select.classList.add("rex-select-native");
    select.tabIndex = -1;
    this.toggle.addEventListener("keydown", (event) => {
      if ("ArrowDown" === event.key || "ArrowUp" === event.key) {
        event.preventDefault();
        this.panel.showPopover();
      }
    });
    select.addEventListener("focus", () => this.toggle.focus());
    select.addEventListener("change", () => this.update());
    this.panel.addEventListener("beforetoggle", (event) => this.beforeToggle("open" === event.newState));
    this.panel.addEventListener("toggle", (event) => {
      if ("open" === event.newState) {
        (this.input ?? this.list.querySelector('[aria-selected="true"]') ?? this.options()[0])?.focus();
      }
    });
    this.list.addEventListener("click", (event) => {
      const item = event.target.closest('[role="option"]');
      if (item) {
        this.choose(item);
      }
    });
    this.panel.addEventListener("keydown", (event) => this.keydown(event));
    this.input?.addEventListener("input", () => this.filter());
    this.element.querySelector("[data-rex-select-all]")?.addEventListener("click", () => this.selectAll(true));
    this.element.querySelector("[data-rex-select-none]")?.addEventListener("click", () => this.selectAll(false));
    new MutationObserver(() => this.update()).observe(select, { attributes: true, attributeFilter: ["disabled"], childList: true, subtree: true });
    this.position = this.position.bind(this);
    this.update();
  }
  /** Visible, enabled option items of the list. */
  options() {
    return [...this.list.querySelectorAll('[role="option"]:not([hidden], [aria-disabled="true"])')];
  }
  update() {
    const selected = [...this.select.selectedOptions].filter((option) => "" !== option.value || !this.select.multiple);
    const label = selected.map((option) => option.label).join(", ");
    this.element.querySelector(".rex-select-value").textContent = label || (this.select.dataset.rexSelectPlaceholder ?? "\u2014");
    this.toggle.title = label;
    this.toggle.disabled = this.select.disabled;
    this.element.classList.toggle("is-disabled", this.select.disabled);
    if (this.panel.matches(":popover-open")) {
      this.render();
    }
  }
  render() {
    this.list.replaceChildren();
    for (const [index, option] of [...this.select.options].entries()) {
      const group = option.parentElement instanceof HTMLOptGroupElement ? option.parentElement : null;
      if (group && option === group.querySelector("option")) {
        const header = document.createElement("li");
        header.className = "rex-select-group";
        header.setAttribute("role", "presentation");
        header.textContent = group.label;
        this.list.append(header);
      }
      const item = document.createElement("li");
      item.setAttribute("role", "option");
      item.tabIndex = -1;
      item.dataset.index = String(index);
      item.textContent = option.label || "\xA0";
      item.setAttribute("aria-selected", String(option.selected));
      if (option.disabled || group?.disabled) {
        item.setAttribute("aria-disabled", "true");
      }
      item.classList.toggle("is-grouped", null !== group);
      this.list.append(item);
    }
    this.filter();
  }
  filter() {
    const term = this.input?.value.trim().toLocaleLowerCase() ?? "";
    let visible = 0;
    for (const item of this.list.querySelectorAll('[role="option"]')) {
      item.hidden = "" !== term && !item.textContent.toLocaleLowerCase().includes(term);
      visible += item.hidden ? 0 : 1;
    }
    for (const header of this.list.querySelectorAll(".rex-select-group")) {
      let next = header.nextElementSibling;
      let hasVisible = false;
      for (; next && !next.matches(".rex-select-group"); next = next.nextElementSibling) {
        hasVisible ||= !next.hidden;
      }
      header.hidden = !hasVisible;
    }
    this.empty.hidden = visible > 0;
  }
  choose(item) {
    if ("true" === item.getAttribute("aria-disabled")) {
      return;
    }
    const option = this.select.options[Number(item.dataset.index)];
    if (this.select.multiple) {
      option.selected = !option.selected;
      item.setAttribute("aria-selected", String(option.selected));
    } else {
      option.selected = true;
      this.panel.hidePopover();
      this.toggle.focus();
    }
    this.changed();
  }
  selectAll(selected) {
    for (const item of this.options()) {
      this.select.options[Number(item.dataset.index)].selected = selected;
      item.setAttribute("aria-selected", String(selected));
    }
    this.changed();
  }
  changed() {
    this.select.dispatchEvent(new Event("input", { bubbles: true }));
    this.select.dispatchEvent(new Event("change", { bubbles: true }));
  }
  keydown(event) {
    const options = this.options();
    const index = options.indexOf(document.activeElement);
    if ("ArrowDown" === event.key || "ArrowUp" === event.key) {
      event.preventDefault();
      const next = "ArrowDown" === event.key ? Math.min(index + 1, options.length - 1) : index - 1;
      (next < 0 ? this.input ?? options[0] : options[next])?.focus();
    } else if (("Enter" === event.key || " " === event.key) && index >= 0) {
      event.preventDefault();
      this.choose(options[index]);
    } else if ("Enter" === event.key && document.activeElement === this.input) {
      event.preventDefault();
      if (options[0]) {
        this.choose(options[0]);
      }
    } else if ("Escape" === event.key) {
      event.preventDefault();
      this.panel.hidePopover();
      this.toggle.focus();
    } else if ("Tab" === event.key) {
      this.panel.hidePopover();
    }
  }
  beforeToggle(open) {
    this.toggle.setAttribute("aria-expanded", String(open));
    this.element.classList.toggle("is-open", open);
    if (open) {
      if (this.input) {
        this.input.value = "";
      }
      this.render();
      this.position();
      addEventListener("scroll", this.position, true);
      addEventListener("resize", this.position);
    } else {
      removeEventListener("scroll", this.position, true);
      removeEventListener("resize", this.position);
    }
  }
  /** Places the panel below the toggle, or above when there is more room there. */
  position(event) {
    if (event?.target instanceof Node && this.panel.contains(event.target)) {
      return;
    }
    const rect = this.toggle.getBoundingClientRect();
    const below = innerHeight - rect.bottom;
    const above = rect.top > below;
    Object.assign(this.panel.style, {
      left: Math.max(0, Math.min(rect.left, innerWidth - Math.max(rect.width, 220))) + "px",
      minWidth: rect.width + "px",
      top: above ? "" : rect.bottom + 2 + "px",
      bottom: above ? innerHeight - rect.top + 2 + "px" : "",
      maxHeight: Math.max(150, (above ? rect.top : below) - 16) + "px"
    });
  }
};
document.addEventListener("rex:ready", (event) => {
  for (const select of event.target.querySelectorAll("select[data-rex-select]")) {
    if (!select.closest(".rex-select")) {
      new Select(select);
    }
  }
});

// assets/js/src/forms.js
document.addEventListener("keydown", (event) => {
  if (!window.rex.accesskeys || event.altKey || event.ctrlKey || event.metaKey || !/^[0-9a-z]$/.test(event.key)) {
    return;
  }
  if (event.target.closest?.('input, button, textarea, select, option, [contenteditable="true"]')) {
    return;
  }
  const target = document.querySelector(`input[accesskey="${event.key}"], button[accesskey="${event.key}"]`) ?? document.querySelector(`a[accesskey="${event.key}"]:is([onclick], [href]:not([href="#"]))`);
  if (target) {
    event.preventDefault();
    target.click();
  }
});
document.addEventListener("keydown", (event) => {
  if ("Enter" !== event.key || !(event.metaKey || event.ctrlKey)) {
    return;
  }
  const form = event.target.closest?.("form");
  (form?.querySelector(".btn-apply") ?? form?.querySelector('[type="submit"]'))?.click();
}, true);
function applyHides(checkbox) {
  for (const element of document.querySelectorAll(checkbox.dataset.rexHides)) {
    element.style.display = checkbox.checked ? "none" : "";
  }
}
document.addEventListener("change", (event) => {
  if (event.target.matches("input[data-rex-hides]")) {
    applyHides(event.target);
  }
});
function updateCollection(collection) {
  const max = parseInt(collection.dataset.rexCollectionMax, 10);
  const count3 = collection.querySelector(":scope > .rex-form-collection-items").children.length;
  const add = collection.querySelector(":scope > [data-rex-collection-add]");
  if (add) {
    add.style.display = isNaN(max) || count3 < max ? "" : "none";
  }
}
document.addEventListener("click", (event) => {
  const button2 = event.target.closest("[data-rex-collection-add], [data-rex-collection-remove], [data-rex-collection-move]");
  const collection = button2?.closest("[data-rex-collection]");
  if (!collection) {
    return;
  }
  if (button2.matches("[data-rex-collection-add]")) {
    const index = parseInt(collection.dataset.rexCollectionNext, 10);
    const html = collection.querySelector(":scope > template").innerHTML.split(collection.dataset.rexCollectionPlaceholder).join(String(index));
    const items = collection.querySelector(":scope > .rex-form-collection-items");
    items.insertAdjacentHTML("beforeend", html);
    collection.dataset.rexCollectionNext = String(index + 1);
    updateCollection(collection);
    window.rex.ready(items.lastElementChild);
  } else if (button2.matches("[data-rex-collection-remove]")) {
    button2.closest(".rex-form-collection-item").remove();
    updateCollection(collection);
  } else {
    const item = button2.closest(".rex-form-collection-item");
    if ("up" === button2.dataset.rexCollectionMove) {
      item.previousElementSibling?.before(item);
    } else {
      item.nextElementSibling?.after(item);
    }
  }
});
function addPasswordToggle(input) {
  if (input.dataset.rexPasswordToggle) {
    return;
  }
  input.dataset.rexPasswordToggle = "true";
  if (!input.parentElement.matches("div.input-group")) {
    const group = document.createElement("div");
    group.className = "input-group";
    input.before(group);
    group.append(input);
  }
  const addon = document.createElement("span");
  addon.className = "input-group-btn";
  addon.innerHTML = '<button type="button" class="btn btn-view" tabindex="-1"><i class="rex-icon rex-icon-view" aria-hidden="true"></i></button>';
  input.after(addon);
  addon.querySelector("button").addEventListener("click", (event) => {
    event.preventDefault();
    const visible = "password" === input.type;
    input.type = visible ? "text" : "password";
    addon.querySelector("i").className = "rex-icon " + (visible ? "rex-icon-hide" : "rex-icon-view");
  });
}
document.addEventListener("rex:ready", (event) => {
  const root = event.target;
  root.querySelectorAll("input[data-rex-hides]").forEach(applyHides);
  root.querySelectorAll("[data-rex-collection]").forEach(updateCollection);
  root.querySelectorAll('input[type="password"]').forEach(addPasswordToggle);
  if (root !== document.body) {
    root.querySelector("[autofocus]")?.focus();
  }
});

// assets/js/src/content-sidebar.js
var key = "structure_content_sidebar_status";
document.addEventListener("rex:ready", (event) => {
  const sidebar = event.target.matches("#rex-page-content-edit") && event.target.querySelector("#rex-js-main-sidebar");
  if (!sidebar) {
    return;
  }
  const panels = [...sidebar.children].filter((child) => child.matches("section")).map((section) => section.querySelector("div.collapse"));
  const store = () => {
    try {
      localStorage.setItem(key, JSON.stringify(panels.map((panel) => String(Boolean(panel?.classList.contains("in"))))));
    } catch {
    }
  };
  let status = null;
  try {
    status = JSON.parse(localStorage.getItem(key) ?? "null");
  } catch {
  }
  if (Array.isArray(status) && status.length === panels.length) {
    panels.forEach((panel, index) => panel && collapse(panel, "true" === status[index]));
  } else {
    store();
  }
  sidebar.addEventListener("rex:collapse", store);
});

// assets/js/src/session-timeout.js
var rex = window.rex;
var i18n2 = rex.i18n ?? {};
var checkInterval = 5;
var keepAliveInterval = 180;
var warningTime = rex.session_warning_time + checkInterval;
var overallWarningAt;
var sessionWarningAt;
var keepAliveUntil;
var nextKeepAliveAt;
var interval;
var current = null;
var now = () => Date.now();
function setSessionWarningAt() {
  sessionWarningAt = now() + (rex.session_duration - warningTime) * 1e3;
}
function setKeepAliveUntil() {
  keepAliveUntil = now() + rex.session_keep_alive * 1e3;
}
function start() {
  nextKeepAliveAt = now() + keepAliveInterval * 1e3;
  interval = setInterval(check, checkInterval * 1e3);
}
function check() {
  const time = now();
  if (overallWarningAt < time) {
    clearInterval(interval);
    showOverallTimeout();
  } else if (keepAliveUntil > time) {
    if (nextKeepAliveAt <= time) {
      clearInterval(interval);
      keepAlive();
    }
  } else if (!rex.session_stay_logged_in && sessionWarningAt < time) {
    clearInterval(interval);
    showExpand();
  }
}
async function keepAlive() {
  try {
    const response = await fetch(rex.session_keep_alive_url + "&" + now());
    if (!response.ok) {
      throw new Error(response.statusText);
    }
    const { rest_overall_time: rest } = await response.json();
    overallWarningAt = now() + (rest - warningTime) * 1e3;
    setSessionWarningAt();
    start();
  } catch {
    showFailed();
  }
}
var button = (icon, label) => `<i class="rex-icon rex-icon-${icon}"></i> ${label}`;
var logout = { label: button("sign-out", i18n2.session_timeout_logout_label), onClick: () => location.href = rex.session_logout_url };
var counter = "<span data-rex-session-counter></span>";
function show(message, buttons) {
  current?.close();
  current = dialog({ title: i18n2.session_timeout_title, content: `<p>${message}</p>`, buttons, className: "rex-session-timeout-dialog" });
  current.closed.then(() => current = null);
  return current;
}
function countdown({ body, element, closed }, end) {
  const update = () => {
    const seconds = (end() - now()) / 1e3;
    const output = body.querySelector("[data-rex-session-counter]");
    if (output) {
      output.textContent = "~" + Math.trunc(seconds / 60);
    }
    if (seconds <= 0) {
      element.querySelector('[value="refresh"]')?.remove();
      body.innerHTML = `<p>${i18n2.session_timeout_message_has_expired}</p>`;
      clearInterval(timer);
    }
  };
  const timer = setInterval(update, 1e3);
  update();
  closed.then(() => clearInterval(timer));
}
function showFailed() {
  show(i18n2.session_timeout_message_has_expired, [
    { label: button("sign-in", i18n2.session_timeout_login_label), primary: true, onClick: () => location.href = rex.session_login_url }
  ]);
}
function showExpand() {
  const message = i18n2.session_timeout_message_expand.replace("{0}", counter).replace("{1}", String(Math.trunc(rex.session_duration / 60)));
  const box = show(message, [
    logout,
    { label: button("refresh", i18n2.session_timeout_refresh_label), value: "refresh", primary: true, onClick: () => {
      keepAlive();
      setSessionWarningAt();
    } }
  ]);
  countdown(box, () => sessionWarningAt + warningTime * 1e3);
}
function showOverallTimeout() {
  const box = show(i18n2.session_timeout_message_expired.replace("{0}", counter), [logout]);
  countdown(box, () => overallWarningAt + warningTime * 1e3);
}
if (rex.session_keep_alive_url) {
  const serverOffset = Math.trunc(now() / 1e3) - rex.session_server_time;
  overallWarningAt = (rex.session_start - serverOffset + rex.session_max_overall_duration - warningTime) * 1e3;
  setKeepAliveUntil();
  setSessionWarningAt();
  start();
  document.addEventListener("rex:ready", () => {
    setSessionWarningAt();
    setKeepAliveUntil();
  });
}

// assets/js/src/backend.js
Object.assign(window.rex, { ready, overlay, pick, pickers, dialog, confirm: confirm2, collapse });
document.addEventListener("DOMContentLoaded", () => ready(document.body));
