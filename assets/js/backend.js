/* Built from assets/js/src/ with `npm run build:backend-js`, do not edit. */

// assets/js/src/ready.js
function ready(root2 = document.body) {
  root2.dispatchEvent(new CustomEvent("rex:ready", { bubbles: true }));
}

// assets/js/src/announce.js
var regions = /* @__PURE__ */ new WeakMap();
function region(assertive) {
  const container = [...document.querySelectorAll("dialog[open]")].filter((dialog2) => dialog2.matches(":modal")).at(-1) ?? document.body;
  const key2 = assertive ? "assertive" : "polite";
  const containerRegions = regions.get(container) ?? {};
  if (!containerRegions[key2]?.isConnected) {
    const element = document.createElement("div");
    element.className = "rex-u-visually-hidden";
    element.setAttribute("role", assertive ? "alert" : "status");
    container.append(element);
    containerRegions[key2] = element;
    regions.set(container, containerRegions);
  }
  return containerRegions[key2];
}
function announce(message, { assertive = false } = {}) {
  const element = region(assertive);
  element.textContent = "";
  requestAnimationFrame(() => element.textContent = message);
}

// assets/js/src/overlay.js
var stack = [];
var skipPopstate = 0;
var overlayCount = 0;
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
    this.dialog.setAttribute("aria-labelledby", `rex-overlay-title-${++overlayCount}`);
    this.dialog.innerHTML = `
            <header class="rex-overlay-header">
                <h2 class="rex-overlay-title" id="rex-overlay-title-${overlayCount}"></h2>
                <button type="button" class="rex-overlay-close" data-rex-overlay-close></button>
            </header>
            <div class="rex-overlay-body"></div>`;
    this.title = this.dialog.querySelector(".rex-overlay-title");
    this.title.tabIndex = -1;
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
    document.body.append(this.dialog);
    this.dialog.showModal();
    stack.push(this);
    history.pushState({ rexOverlay: stack.length }, "");
    this.load(this.url);
  }
  async load(url, init2 = {}) {
    this.loading?.abort();
    const loading = this.loading = new AbortController();
    const initial = !this.body.hasChildNodes();
    this.dialog.setAttribute("aria-busy", "true");
    try {
      const response = await fetch(url, { credentials: "same-origin", ...init2, signal: loading.signal });
      if (401 === response.status || "login" === pageOf(response.url)) {
        location.href = response.url;
        return;
      }
      const template = document.createElement("template");
      template.innerHTML = await response.text();
      if (loading.signal.aborted) {
        return;
      }
      const doc = template.content;
      const main = doc.getElementById("rex-js-page-main") ?? doc;
      const heading = main.querySelector(".rex-pageheader-title");
      this.title.textContent = heading?.textContent.trim() || (doc.querySelector("title")?.textContent ?? "").split(" \xB7 ")[0];
      heading?.remove();
      this.url = response.url;
      this.body.replaceChildren(...main.childNodes);
      this.body.scrollTop = 0;
      ready(this.body);
      if (!initial && !this.body.contains(document.activeElement)) {
        this.title.focus();
        announce(this.title.textContent);
      }
      const auto = this.body.querySelector("[data-rex-pick-auto]");
      if (auto) {
        this.pick(auto, [itemOf(auto)]);
      }
    } catch (error) {
      if (loading.signal.aborted) {
        return;
      }
      const alert = Object.assign(document.createElement("div"), { className: "rex-alert", textContent: String(error) });
      alert.dataset.variant = "danger";
      alert.setAttribute("role", "alert");
      this.body.replaceChildren(alert);
    } finally {
      if (this.loading === loading) {
        this.loading = null;
        this.dialog.removeAttribute("aria-busy");
      }
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
    const url = new URL(href, this.url);
    if (this.keeps(url)) {
      event.preventDefault();
      this.load(url);
    } else if (url.origin === location.origin && url.pathname !== new URL(this.url).pathname) {
      event.preventDefault();
      window.open(url, "_blank", "noopener");
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
    event.preventDefault();
    const data = new FormData(form, event.submitter);
    [...form.elements].filter((element) => element.matches('button:not([type]), [type="submit"], [type="image"]')).forEach((button) => button.disabled = true);
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
    trigger.dataset.rexPicked = "";
    if (this.multiple) {
      announce(items.map((item) => item.label).join(", ") + ": " + (window.rex.i18n?.picker_picked ?? "picked"));
    }
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
    this.loading?.abort();
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
window.addEventListener("submit", (event) => {
  stack.findLast((overlay2) => overlay2.dialog.contains(event.target))?.onSubmit(event);
});
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
function contentOf(value) {
  return value instanceof Node ? value : document.createTextNode(String(value));
}
function dialog({ title = "", content, buttons = [], className = "", role = "dialog", size = null }) {
  const id = "rex-dialog-" + ++count;
  const element = document.createElement("dialog");
  element.className = ("rex-dialog " + className).trim();
  element.innerHTML = `
        <form method="dialog" class="rex-dialog-inner">
            <header class="rex-dialog-header">
                <h2 class="rex-dialog-title" id="${id}-title"></h2>
                <button class="rex-dialog-close" value=""><i class="rex-icon fa-solid fa-xmark" aria-hidden="true"></i></button>
            </header>
            <div class="rex-dialog-body" id="${id}-body"></div>
            <footer class="rex-dialog-footer"></footer>
        </form>`;
  if (size) {
    element.dataset.size = size;
  }
  if ("alertdialog" === role) {
    element.setAttribute("role", "alertdialog");
  }
  const heading = element.querySelector(".rex-dialog-title");
  if (title) {
    heading.append(contentOf(title));
    element.setAttribute("aria-labelledby", heading.id);
  } else {
    heading.remove();
    element.setAttribute("aria-labelledby", id + "-body");
  }
  element.querySelector(".rex-dialog-close").setAttribute("aria-label", window.rex.i18n?.overlay_close ?? "Close");
  const body = element.querySelector(".rex-dialog-body");
  body.append(contentOf(content));
  const footer = element.querySelector(".rex-dialog-footer");
  for (const { label, icon, value = "", className: className2 = "", primary = false, onClick } of buttons) {
    const button = document.createElement("button");
    button.className = ("rex-button " + className2).trim();
    if (primary) {
      button.dataset.variant = "primary";
    }
    button.value = value;
    if (icon) {
      button.insertAdjacentHTML("beforeend", `<i class="rex-icon rex-icon-${CSS.escape(icon)}" aria-hidden="true"></i> `);
    }
    button.append(contentOf(label));
    button.autofocus = primary;
    if (onClick) {
      button.addEventListener("click", onClick);
    }
    footer.append(button);
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
async function confirm(message) {
  const i18n3 = window.rex.i18n ?? {};
  const { closed } = dialog({
    content: Object.assign(document.createElement("p"), { textContent: message }),
    size: "sm",
    role: "alertdialog",
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
  const newTab = element.matches("a[href]") && (event.ctrlKey || event.metaKey || event.shiftKey);
  confirm(element.dataset.confirm).then((ok) => {
    if (!ok) {
      return;
    }
    if (newTab) {
      window.open(element.href, "_blank", "noopener");
      return;
    }
    confirmed.add(element);
    element.click();
  });
}, true);
window.addEventListener("submit", (event) => {
  const form = event.target;
  if (!form.matches("form[data-confirm]") || confirmed.delete(form)) {
    return;
  }
  event.preventDefault();
  event.stopImmediatePropagation();
  confirm(form.dataset.confirm).then((ok) => {
    if (ok) {
      confirmed.add(form);
      form.requestSubmit(event.submitter);
    }
  });
}, true);

// assets/js/src/toggles.js
var TOGGLE = '[data-rex-dropdown], [data-toggle="dropdown"]';
var MENU = ":is(.rex-dropdown-menu, .dropdown-menu)";
function openDropdowns() {
  return document.querySelectorAll(`.open:has(> :is(${TOGGLE}))`);
}
function closeDropdowns(except = null) {
  for (const dropdown of openDropdowns()) {
    if (dropdown !== except) {
      dropdown.classList.remove("open");
      dropdown.querySelector(`:scope > :is(${TOGGLE})`).setAttribute("aria-expanded", "false");
    }
  }
}
function toggleDropdown(toggle) {
  const dropdown = toggle.parentElement;
  const open = !dropdown.classList.contains("open");
  closeDropdowns(dropdown);
  if (open) {
    const menu = dropdown.querySelector(`:scope > ${MENU}`);
    const rect = dropdown.getBoundingClientRect();
    dropdown.classList.remove("dropup");
    delete dropdown.dataset.placement;
    dropdown.classList.add("open");
    toggle.setAttribute("aria-expanded", "true");
    const height = menu?.offsetHeight ?? 0;
    if (innerHeight - rect.bottom < height && rect.top > height) {
      dropdown.classList.add("dropup");
      dropdown.dataset.placement = "top";
    }
  } else {
    dropdown.classList.remove("open");
  }
  toggle.setAttribute("aria-expanded", String(open));
}
document.addEventListener("click", (event) => {
  const toggle = event.target.closest(TOGGLE);
  if (toggle) {
    event.preventDefault();
    if (!toggle.matches(".disabled, :disabled")) {
      toggleDropdown(toggle);
    }
    return;
  }
  if (!event.target.closest(`${MENU} form`)) {
    closeDropdowns();
  }
});
function dropdownItems(dropdown) {
  return [...dropdown.querySelectorAll(`:scope > ${MENU} li:not(.disabled) :is(a[href], button:not(:disabled))`)];
}
document.addEventListener("keydown", (event) => {
  const closedToggle = event.target.closest(TOGGLE);
  if (closedToggle && !closedToggle.parentElement.classList.contains("open") && "ArrowDown" === event.key) {
    event.preventDefault();
    toggleDropdown(closedToggle);
    dropdownItems(closedToggle.parentElement)[0]?.focus();
    return;
  }
  const dropdown = event.target.closest(`.open:has(> :is(${TOGGLE}))`);
  if (!dropdown) {
    return;
  }
  const toggle = dropdown.querySelector(`:scope > :is(${TOGGLE})`);
  if ("Escape" === event.key) {
    event.preventDefault();
    closeDropdowns();
    toggle.focus();
  } else if (("ArrowDown" === event.key || "ArrowUp" === event.key) && !event.target.matches("input, select, textarea")) {
    event.preventDefault();
    const items = dropdownItems(dropdown);
    const index = items.indexOf(document.activeElement);
    const next = "ArrowDown" === event.key ? Math.min(index + 1, items.length - 1) : Math.max(index - 1, 0);
    items[next]?.focus();
  }
});
document.addEventListener("focusout", (event) => {
  const dropdown = event.target.closest(`.open:has(> :is(${TOGGLE}))`);
  if (dropdown && !dropdown.contains(event.relatedTarget) && null !== event.relatedTarget) {
    dropdown.classList.remove("open");
    dropdown.querySelector(`:scope > :is(${TOGGLE})`).setAttribute("aria-expanded", "false");
  }
});
function targetsOf(toggle) {
  const selector = toggle.dataset.target ?? toggle.getAttribute("href");
  if (!selector || !toggle.dataset.target && !/^#[^\s]+$/.test(selector)) {
    return [];
  }
  try {
    return [...document.querySelectorAll(selector)];
  } catch {
    return [];
  }
}
function targetOf(toggle) {
  return targetsOf(toggle)[0] ?? null;
}
function collapse(target, open = !target.classList.contains("in")) {
  if (open === target.classList.contains("in")) {
    return;
  }
  target.classList.toggle("in", open);
  for (const toggle of document.querySelectorAll('[data-toggle="collapse"]')) {
    if (targetsOf(toggle).includes(target)) {
      toggle.classList.toggle("collapsed", !open);
      toggle.setAttribute("aria-expanded", String(open));
    }
  }
  target.dispatchEvent(new CustomEvent("rex:collapse", { bubbles: true, detail: { open } }));
}
document.addEventListener("click", (event) => {
  const toggle = event.target.closest('[data-toggle="collapse"]');
  const targets = toggle ? targetsOf(toggle) : [];
  if (targets.length) {
    event.preventDefault();
    targets.forEach((target) => collapse(target));
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
function toggleSidebar(open = !document.body.classList.contains("rex-sidebar-open"), { returnFocus = false } = {}) {
  const wasOpen = document.body.classList.contains("rex-sidebar-open");
  document.body.classList.toggle("rex-sidebar-open", open);
  document.documentElement.style.overflowY = open ? "hidden" : "";
  const toggle = document.getElementById("rex-js-nav-main-toggle");
  toggle?.setAttribute("aria-expanded", String(open));
  for (const element of document.querySelectorAll(".rex-main, .rex-footer")) {
    element.inert = open;
  }
  if (open && !wasOpen) {
    document.querySelector("#rex-js-nav-main a[href]")?.focus();
  } else if (!open && wasOpen && returnFocus) {
    toggle?.focus();
  }
}
var topbar = {
  previous: 0,
  lastToggle: null,
  update() {
    const bar = document.querySelector('.rex-topbar[data-variant="fixed"]');
    if (!bar) {
      return;
    }
    const max = document.body.scrollHeight - document.body.clientHeight;
    const current3 = Math.min(Math.max(window.scrollY, 0), max);
    this.lastToggle ??= current3;
    if (!document.body.classList.contains("rex-sidebar-open")) {
      if (current3 > this.previous && current3 >= this.lastToggle + 150) {
        bar.dataset.concealed = "";
        this.lastToggle = current3;
      } else if (current3 < this.previous && (current3 <= this.lastToggle - 300 || current3 < 50)) {
        delete bar.dataset.concealed;
        this.lastToggle = current3;
      }
    }
    bar.toggleAttribute("data-elevated", current3 > 10);
    this.previous = current3;
  },
  reset() {
    const bar = document.querySelector('.rex-topbar[data-variant="fixed"]');
    if (bar) {
      delete bar.dataset.concealed;
    }
    this.lastToggle = this.previous;
  }
};
document.addEventListener("click", (event) => {
  if (event.target.closest("#rex-js-nav-main-toggle")) {
    event.preventDefault();
    toggleSidebar();
    topbar.reset();
  } else if (event.target.closest("#rex-js-nav-main-backdrop")) {
    toggleSidebar(false, { returnFocus: true });
  }
});
document.addEventListener("keydown", (event) => {
  if ("Escape" === event.key && document.body.classList.contains("rex-sidebar-open")) {
    toggleSidebar(false, { returnFocus: true });
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
function byFilename(items) {
  return items.map(({ value }) => ({ value, label: value }));
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
    open: (widget, options) => pick("media", { ...options, file: selectedValue(widget), multiple: true, onPick: (items) => addToList(widget, byFilename(items)) }),
    add: (widget, options) => pick("media", { ...options, upload: true, multiple: true, onPick: (items) => addToList(widget, byFilename(items)) }),
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
  const button = event.target.closest("[data-rex-widget-action]");
  const widget = button?.closest("[data-rex-widget]");
  if (!widget || button.disabled) {
    return;
  }
  event.preventDefault();
  const [action, argument] = button.dataset.rexWidgetAction.split(":");
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
for (const [show2, hide] of [["mouseover", "mouseout"], ["focusin", "focusout"]]) {
  document.addEventListener(show2, (event) => {
    const widget = event.target.closest("[data-rex-widget][data-rex-widget-preview]");
    if (widget && !widget.contains(event.relatedTarget)) {
      showPreview(widget);
    }
  });
  document.addEventListener(hide, (event) => {
    const widget = event.target.closest("[data-rex-widget][data-rex-widget-preview]");
    if (widget && !widget.contains(event.relatedTarget)) {
      widget.querySelector(".rex-js-media-preview")?.replaceChildren();
    }
  });
}
document.addEventListener("change", (event) => {
  const widget = event.target.closest('[data-rex-widget="medialist"][data-rex-widget-preview]');
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
            <button type="button" class="rex-input rex-select-toggle" aria-haspopup="listbox" aria-expanded="false" popovertarget="${this.id}">
                <span class="rex-select-value" id="${this.id}-value"></span>
            </button>
            <div class="rex-select-panel" id="${this.id}" popover>
                ${this.search ? `<input type="search" class="rex-input rex-select-search" autocomplete="off" aria-controls="${this.id}-options">` : ""}
                ${this.search && select.multiple ? `<div class="rex-select-actions">
                    <button type="button" class="rex-button" data-size="xs" data-rex-select-all></button>
                    <button type="button" class="rex-button" data-size="xs" data-rex-select-none></button>
                </div>` : ""}
                <ul class="rex-select-options" id="${this.id}-options" role="listbox"></ul>
                <p class="rex-select-empty" role="status"></p>
            </div>`;
    this.toggle = this.element.querySelector(".rex-select-toggle");
    this.panel = this.element.querySelector(".rex-select-panel");
    this.list = this.element.querySelector(".rex-select-options");
    this.input = this.element.querySelector(".rex-select-search");
    this.empty = this.element.querySelector(".rex-select-empty");
    this.list.setAttribute("aria-multiselectable", String(select.multiple));
    this.input?.setAttribute("aria-label", i18n().select_search ?? "Search");
    this.element.querySelector("[data-rex-select-all]")?.append(i18n().select_all ?? "All");
    this.element.querySelector("[data-rex-select-none]")?.append(i18n().select_none ?? "None");
    if (select.labels[0]) {
      const label = select.labels[0].id ||= this.id + "-label";
      this.toggle.setAttribute("aria-labelledby", label + " " + this.id + "-value");
      this.list.setAttribute("aria-labelledby", label);
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
    if (this.panel.matches(":popover-open")) {
      this.render();
    }
  }
  render() {
    this.list.replaceChildren();
    let groupList = null;
    for (const [index, option] of [...this.select.options].entries()) {
      const group = option.parentElement instanceof HTMLOptGroupElement ? option.parentElement : null;
      if (group && option === group.querySelector("option")) {
        const wrapper = document.createElement("li");
        wrapper.setAttribute("role", "none");
        const header = document.createElement("div");
        header.className = "rex-select-group";
        header.id = `${this.id}-group-${index}`;
        header.textContent = group.label;
        groupList = document.createElement("ul");
        groupList.setAttribute("role", "group");
        groupList.setAttribute("aria-labelledby", header.id);
        wrapper.append(header, groupList);
        this.list.append(wrapper);
      } else if (!group) {
        groupList = null;
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
      (groupList ?? this.list).append(item);
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
    for (const group of this.list.querySelectorAll('[role="group"]')) {
      group.parentElement.hidden = !group.querySelector('[role="option"]:not([hidden])');
    }
    this.empty.textContent = visible > 0 ? "" : i18n().select_no_results ?? "No results";
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
    } else if (("Home" === event.key || "End" === event.key) && index >= 0) {
      event.preventDefault();
      options.at("Home" === event.key ? 0 : -1)?.focus();
    } else if (1 === event.key.length && index >= 0 && !event.ctrlKey && !event.metaKey && !event.altKey && " " !== event.key) {
      clearTimeout(this.typeaheadTimer);
      this.typeahead = (this.typeahead ?? "") + event.key.toLocaleLowerCase();
      this.typeaheadTimer = setTimeout(() => this.typeahead = "", 500);
      const ordered = [...options.slice(index + 1), ...options.slice(0, index + 1)];
      ordered.find((option) => option.textContent.toLocaleLowerCase().startsWith(this.typeahead))?.focus();
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
    if (!this.panel.isConnected) {
      removeEventListener("scroll", this.position, true);
      removeEventListener("resize", this.position);
      return;
    }
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
  if ("Enter" !== event.key || !(event.metaKey || event.ctrlKey)) {
    return;
  }
  const form = event.target.closest?.("form");
  const button = form?.querySelector("[data-rex-submit-shortcut]") ?? form?.querySelector('[type="submit"]');
  if (button) {
    event.preventDefault();
    button.click();
  }
}, true);
document.addEventListener("change", (event) => {
  const all = event.target;
  if (!all.matches("input[data-rex-check-all]")) {
    return;
  }
  for (const checkbox of all.form.querySelectorAll(`input[name="${CSS.escape(all.dataset.rexCheckAll)}"]`)) {
    if (checkbox.checked !== all.checked) {
      checkbox.checked = all.checked;
      checkbox.dispatchEvent(new Event("change", { bubbles: true }));
    }
  }
});
document.addEventListener("change", (event) => {
  if (event.target.matches("[data-rex-autosubmit]")) {
    event.target.form?.requestSubmit();
  }
});
document.addEventListener("submit", (event) => {
  const form = event.target;
  form.querySelectorAll(":scope > input[data-rex-submit-param]").forEach((input) => input.remove());
  const params = event.submitter?.dataset.rexSubmitParams;
  if (!params) {
    return;
  }
  for (const [name, value] of Object.entries(JSON.parse(params))) {
    const input = document.createElement("input");
    Object.assign(input, { type: "hidden", name, value });
    input.dataset.rexSubmitParam = "";
    form.append(input);
  }
}, true);
function applyRequiresChecked(element) {
  const form = element.form ?? element.closest("form") ?? element;
  const name = CSS.escape(element.dataset.rexRequiresChecked);
  const disabled = !form.querySelector(`input[name="${name}"]:checked`);
  form.querySelectorAll('[type="submit"]').forEach((button) => button.disabled = disabled);
}
document.addEventListener("change", (event) => {
  const form = event.target.form;
  form?.querySelectorAll("[data-rex-requires-checked]").forEach(applyRequiresChecked);
  if (form?.matches("[data-rex-requires-checked]")) {
    applyRequiresChecked(form);
  }
});
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
  const button = event.target.closest("[data-rex-collection-add], [data-rex-collection-remove], [data-rex-collection-move]");
  const collection = button?.closest("[data-rex-collection]");
  if (!collection) {
    return;
  }
  if (button.matches("[data-rex-collection-add]")) {
    const index = parseInt(collection.dataset.rexCollectionNext, 10);
    const html = collection.querySelector(":scope > template").innerHTML.split(collection.dataset.rexCollectionPlaceholder).join(String(index));
    const items = collection.querySelector(":scope > .rex-form-collection-items");
    items.insertAdjacentHTML("beforeend", html);
    collection.dataset.rexCollectionNext = String(index + 1);
    updateCollection(collection);
    window.rex.ready(items.lastElementChild);
    items.lastElementChild.querySelector('input:not([type="hidden"]), select, textarea, button')?.focus();
    announce(window.rex.i18n?.collection_added ?? "Item added");
  } else if (button.matches("[data-rex-collection-remove]")) {
    const item = button.closest(".rex-form-collection-item");
    const next = item.nextElementSibling ?? item.previousElementSibling;
    item.remove();
    updateCollection(collection);
    (next?.querySelector("[data-rex-collection-remove]") ?? collection.querySelector(":scope > [data-rex-collection-add]"))?.focus();
    announce(window.rex.i18n?.collection_removed ?? "Item removed");
  } else {
    const item = button.closest(".rex-form-collection-item");
    if ("up" === button.dataset.rexCollectionMove) {
      item.previousElementSibling?.before(item);
    } else {
      item.nextElementSibling?.after(item);
    }
    button.focus();
  }
});
function addPasswordToggle(input) {
  if (input.dataset.rexPasswordToggle) {
    return;
  }
  input.dataset.rexPasswordToggle = "true";
  if (!input.parentElement.matches("div.rex-input-group, div.input-group")) {
    const group = document.createElement("div");
    group.className = "rex-input-group";
    input.before(group);
    group.append(input);
  }
  const addon = document.createElement("span");
  addon.className = "rex-input-group-actions";
  addon.innerHTML = '<button type="button" class="rex-button" data-variant="field" aria-pressed="false"><i class="rex-icon rex-icon-view" aria-hidden="true"></i></button>';
  input.after(addon);
  const button = addon.querySelector("button");
  button.setAttribute("aria-label", window.rex.i18n?.password_show ?? "Show password");
  if (input.id) {
    button.setAttribute("aria-controls", input.id);
  }
  button.addEventListener("click", (event) => {
    event.preventDefault();
    const visible = "password" === input.type;
    input.type = visible ? "text" : "password";
    button.setAttribute("aria-pressed", String(visible));
    button.querySelector("i").className = "rex-icon " + (visible ? "rex-icon-hide" : "rex-icon-view");
  });
}
document.addEventListener("rex:ready", (event) => {
  const root2 = event.target;
  root2.querySelectorAll("input[data-rex-hides]").forEach(applyHides);
  root2.querySelectorAll("[data-rex-requires-checked]").forEach(applyRequiresChecked);
  root2.querySelectorAll("[data-rex-collection]").forEach(updateCollection);
  root2.querySelectorAll('input[type="password"]').forEach(addPasswordToggle);
  if (root2 !== document.body) {
    root2.querySelector("[autofocus]")?.focus();
  }
});

// assets/js/src/content-sidebar.js
var key = "structure_content_sidebar_status";
var initialized = /* @__PURE__ */ new WeakSet();
document.addEventListener("rex:ready", (event) => {
  const sidebar = event.target.matches("#rex-page-content-edit") && event.target.querySelector("#rex-js-main-sidebar");
  if (!sidebar || initialized.has(sidebar)) {
    return;
  }
  initialized.add(sidebar);
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
var logout = { label: i18n2.session_timeout_logout_label, icon: "sign-out", value: "logout", onClick: () => location.href = rex.session_logout_url };
function paragraph(message) {
  const p = document.createElement("p");
  const [before, after = ""] = message.split("{0}");
  p.append(before);
  if (message.includes("{0}")) {
    const counter = document.createElement("span");
    counter.dataset.rexSessionCounter = "";
    p.append(counter, after);
  }
  return p;
}
function show(message, buttons) {
  current?.close();
  const box = dialog({ title: i18n2.session_timeout_title, content: paragraph(message), buttons, className: "rex-session-timeout-dialog", role: "alertdialog" });
  current = box;
  box.closed.then(() => {
    if (current === box) {
      current = null;
    }
  });
  return box;
}
function countdown({ body, element, closed }, end) {
  const update2 = () => {
    const seconds = (end() - now()) / 1e3;
    const output = body.querySelector("[data-rex-session-counter]");
    if (output) {
      output.textContent = "~" + Math.trunc(seconds / 60);
    }
    if (seconds <= 0) {
      element.querySelector('[value="refresh"]')?.remove();
      body.replaceChildren(paragraph(i18n2.session_timeout_message_has_expired));
      clearInterval(timer);
    }
  };
  const timer = setInterval(update2, 1e3);
  update2();
  closed.then(() => clearInterval(timer));
}
function showFailed() {
  show(i18n2.session_timeout_message_has_expired, [
    { label: i18n2.session_timeout_login_label, icon: "sign-in", primary: true, onClick: () => location.href = rex.session_login_url }
  ]);
}
function showExpand() {
  const message = i18n2.session_timeout_message_expand.replace("{1}", String(Math.trunc(rex.session_duration / 60)));
  const box = show(message, [
    logout,
    { label: i18n2.session_timeout_refresh_label, icon: "refresh", value: "refresh", primary: true, onClick: () => {
      keepAlive();
      setSessionWarningAt();
    } }
  ]);
  countdown(box, () => sessionWarningAt + warningTime * 1e3);
  box.closed.then((value) => {
    if ("" === value && null === current) {
      keepAlive();
      setSessionWarningAt();
    }
  });
}
function showOverallTimeout() {
  const box = show(i18n2.session_timeout_message_expired, [logout]);
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

// assets/js/src/theme.js
var dark = matchMedia("(prefers-color-scheme: dark)");
function effectiveTheme() {
  const { classList } = document.body;
  if (classList.contains("rex-theme-light")) {
    return "light";
  }
  if (classList.contains("rex-theme-dark")) {
    return "dark";
  }
  return dark.matches ? "dark" : "light";
}
var current2 = null;
function update() {
  const theme = effectiveTheme();
  if (null !== current2 && theme !== current2) {
    document.body.dispatchEvent(new CustomEvent("rex:theme.change", { bubbles: true, detail: { theme } }));
  }
  current2 = theme;
}
document.addEventListener("DOMContentLoaded", () => {
  update();
  dark.addEventListener("change", update);
  new MutationObserver(update).observe(document.body, { attributes: true, attributeFilter: ["class"] });
});

// assets/js/src/webauthn.js
function failed(form, error) {
  if (["NotAllowedError", "AbortError"].includes(error?.name)) {
    return;
  }
  const message = window.rex.i18n?.passkey_failed ?? "The passkey could not be used.";
  const alert = Object.assign(document.createElement("div"), { className: "rex-alert", textContent: message });
  alert.dataset.variant = "danger";
  alert.setAttribute("role", "alert");
  form.querySelector(':scope > .rex-alert[role="alert"]')?.remove();
  form.prepend(alert);
  announce(message, { assertive: true });
}
var authAddPasskey = function(form) {
  form.classList.remove("rex-u-hidden");
  const passkey = form.querySelector("[data-auth-passkey]");
  form.addEventListener("submit", (event) => {
    event.preventDefault();
    const options = JSON.parse(passkey.dataset.authPasskey);
    recursiveBase64StrToArrayBuffer(options);
    navigator.credentials.create(options).then((data) => {
      data = {
        clientDataJSON: data.response.clientDataJSON ? arrayBufferToBase64(data.response.clientDataJSON) : null,
        attestationObject: data.response.attestationObject ? arrayBufferToBase64(data.response.attestationObject) : null
      };
      passkey.value = JSON.stringify(data);
      form.submit();
    }).catch((error) => failed(form, error));
  });
};
var authPasskeyVerify = function(form) {
  const verify = form.querySelector("[data-auth-passkey-verify]");
  if (!verify) {
    return;
  }
  const fields = form.querySelectorAll("input[type=password], button[type=submit]");
  fields.forEach((field) => field.disabled = true);
  const button = verify.querySelector("button");
  button.addEventListener("click", () => {
    const passkey = verify.querySelector("input[type=hidden]");
    authVerify(verify.dataset.authPasskeyVerify, "required", form, (value) => {
      passkey.value = value;
      button.disabled = true;
      verify.querySelector("i").classList.remove("rex-u-hidden");
      fields.forEach((field) => field.disabled = false);
    });
  });
};
var authLogin = function(form) {
  const passkey = form.querySelector("[data-auth-passkey]");
  authVerify(passkey.dataset.authPasskey, "conditional", form, (value) => {
    passkey.value = value;
    form.submit();
  });
};
var authVerify = function(options, mediation, form, onSuccess) {
  options = JSON.parse(options);
  recursiveBase64StrToArrayBuffer(options);
  options.mediation = mediation;
  navigator.credentials.get(options).then((data) => {
    data = {
      id: data.id,
      clientDataJSON: data.response.clientDataJSON ? arrayBufferToBase64(data.response.clientDataJSON) : null,
      authenticatorData: data.response.authenticatorData ? arrayBufferToBase64(data.response.authenticatorData) : null,
      signature: data.response.signature ? arrayBufferToBase64(data.response.signature) : null,
      userHandle: data.response.userHandle ? arrayBufferToBase64(data.response.userHandle) : null
    };
    onSuccess(JSON.stringify(data));
  }).catch((error) => failed(form, error));
};
var recursiveBase64StrToArrayBuffer = function(obj) {
  let prefix = "=?BINARY?B?";
  let suffix = "?=";
  if (typeof obj === "object") {
    for (let key2 in obj) {
      if (typeof obj[key2] === "string") {
        let str = obj[key2];
        if (str.substring(0, prefix.length) === prefix && str.substring(str.length - suffix.length) === suffix) {
          str = str.substring(prefix.length, str.length - suffix.length);
          let binary_string = window.atob(str);
          let len = binary_string.length;
          let bytes = new Uint8Array(len);
          for (let i = 0; i < len; i++) {
            bytes[i] = binary_string.charCodeAt(i);
          }
          obj[key2] = bytes.buffer;
        }
      } else {
        recursiveBase64StrToArrayBuffer(obj[key2]);
      }
    }
  }
};
var arrayBufferToBase64 = function(buffer) {
  let binary = "";
  let bytes = new Uint8Array(buffer);
  let len = bytes.byteLength;
  for (let i = 0; i < len; i++) {
    binary += String.fromCharCode(bytes[i]);
  }
  return window.btoa(binary);
};
var initialized2 = /* @__PURE__ */ new WeakSet();
var once = (form) => form && !initialized2.has(form) && initialized2.add(form);
if (window.PublicKeyCredential) {
  document.addEventListener("rex:ready", (event) => {
    const form = event.target.querySelector("form[data-auth-change-password]");
    if (once(form)) {
      authPasskeyVerify(form);
    }
  });
}
if (window.PublicKeyCredential && PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable && PublicKeyCredential.isConditionalMediationAvailable) {
  let ready2 = false;
  document.addEventListener("rex:ready", function() {
    ready2 = true;
  });
  Promise.all([
    PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable(),
    PublicKeyCredential.isConditionalMediationAvailable()
  ]).then((results) => {
    if (!results.every((r) => r === true)) {
      return;
    }
    const init2 = function(container) {
      let form = container.querySelector("form[data-auth-add-passkey]");
      if (once(form)) {
        authAddPasskey(form);
        authPasskeyVerify(form);
      }
      form = container.querySelector("form[data-auth-login]");
      if (once(form)) {
        authLogin(form);
      }
    };
    if (ready2) {
      init2(document.body);
    }
    document.addEventListener("rex:ready", function(event) {
      init2(event.target);
    });
  });
}

// assets/js/src/history.js
var RANGE = 1e3;
var initialized3 = /* @__PURE__ */ new WeakSet();
document.addEventListener("rex:ready", (event) => {
  for (const root2 of event.target.querySelectorAll("[data-rex-history]")) {
    if (!initialized3.has(root2)) {
      initialized3.add(root2);
      init(root2);
    }
  }
});
function init(root2) {
  const url = new URL(root2.dataset.rexHistory, location.href);
  const [baseFrame, targetFrame] = root2.querySelectorAll(".rex-history-frame iframe");
  const base = root2.querySelector("[data-rex-history-base]");
  const target = root2.querySelector("[data-rex-history-target]");
  const steps = root2.querySelectorAll("[data-rex-history-step]");
  const restore = root2.querySelector('button[name="func"][value="restore"]');
  const options = [...target.options].reverse();
  let index = -1;
  let timer;
  const preview = (frame, params) => {
    const src = new URL(url);
    for (const [key2, value] of Object.entries(params)) {
      src.searchParams.set(key2, value);
    }
    frame.src = src.toString();
  };
  const select = (next, delay = 0) => {
    next = Math.max(0, Math.min(options.length - 1, next));
    if (next === index) {
      return;
    }
    index = next;
    target.value = options[index].value;
    steps.forEach((step) => step.disabled = !options[index + Number(step.dataset.rexHistoryStep)]);
    if (restore) {
      restore.disabled = "" === target.value;
    }
    timeline?.set(index);
    clearTimeout(timer);
    timer = setTimeout(() => preview(targetFrame, target.value ? { rex_history_date: target.value } : {}), delay);
  };
  const element = root2.querySelector(".rex-history-timeline");
  const timeline = element && createTimeline(element, options, select);
  const show2 = root2.querySelector("[data-rex-history-show]");
  const showBase = () => {
    preview(baseFrame, "1" === base?.value ? { rex_version: 1 } : {});
    if (show2) {
      show2.href = baseFrame.src;
    }
  };
  base?.addEventListener("change", showBase);
  target.addEventListener("change", () => select(options.indexOf(target.selectedOptions[0])));
  steps.forEach((step) => step.addEventListener("click", () => select(index + Number(step.dataset.rexHistoryStep), 300)));
  showBase();
  select(options.length - 2);
}
function positions(times, min) {
  const span = times.at(-1) - times[0] || 1;
  const gaps = times.slice(1).map((time, i) => (time - times[i]) / span);
  min = Math.min(min, 1 / gaps.length);
  const missing = gaps.reduce((sum, gap) => sum + Math.max(0, min - gap), 0);
  const spare = gaps.reduce((sum, gap) => sum + Math.max(0, gap - min), 0);
  let position = 0;
  return [0, ...gaps.map((gap) => position += gap < min ? min : gap - (spare && missing * (gap - min) / spare))].map((value) => Math.round(value * RANGE));
}
function createTimeline(element, options, select) {
  const track = element.querySelector(".rex-history-track");
  const range = element.querySelector(".rex-history-range");
  const points = positions(options.map((option) => Number(option.dataset.time)), 10 / (track.clientWidth || 800));
  const segments = points.slice(1).map((point, i) => {
    const segment = document.createElement("span");
    segment.className = "rex-history-segment";
    segment.style.setProperty("--from", points[i] / RANGE);
    segment.style.setProperty("--to", point / RANGE);
    return segment;
  });
  const ticks = points.map((point) => {
    const tick = document.createElement("span");
    tick.className = "rex-history-tick";
    tick.style.setProperty("--at", point / RANGE);
    return tick;
  });
  track.replaceChildren(...segments, ...ticks);
  const nearest = (value) => points.reduce((best, point, i) => Math.abs(point - value) < Math.abs(points[best] - value) ? i : best, 0);
  let current3 = 0;
  range.addEventListener("input", () => select(nearest(Number(range.value)), 300));
  range.addEventListener("change", () => range.value = points[current3]);
  range.addEventListener("keydown", (event) => {
    const next = {
      ArrowLeft: current3 - 1,
      ArrowDown: current3 - 1,
      PageDown: current3 - 1,
      ArrowRight: current3 + 1,
      ArrowUp: current3 + 1,
      PageUp: current3 + 1,
      Home: 0,
      End: points.length - 1
    }[event.key];
    if (void 0 !== next) {
      event.preventDefault();
      select(next, 300);
    }
  });
  return {
    set(index) {
      current3 = index;
      range.value = points[index];
      range.setAttribute("aria-valuetext", options[index].textContent.trim());
      segments.forEach((segment, i) => segment.toggleAttribute("data-past", i < index));
      ticks.forEach((tick, i) => tick.toggleAttribute("data-selected", i === index));
    }
  };
}

// node_modules/@github/clipboard-copy-element/dist/clipboard.js
function createNode(text) {
  const node = document.createElement("pre");
  node.style.width = "1px";
  node.style.height = "1px";
  node.style.position = "fixed";
  node.style.top = "5px";
  node.textContent = text;
  return node;
}
function normalizeText(text) {
  return text.replace(/[\u00A0\u1680\u2000-\u200A\u202F\u205F\u3000]/g, " ").replace(/[\u200B-\u200D\u2060\uFEFF\u180E]/g, "");
}
function copySelection(node) {
  const selection = getSelection();
  if (selection == null) {
    return Promise.reject(new Error());
  }
  selection.removeAllRanges();
  const range = document.createRange();
  range.selectNodeContents(node);
  selection.addRange(range);
  document.execCommand("copy");
  selection.removeAllRanges();
  return Promise.resolve();
}
function copyNode(node) {
  return copyText(node.textContent || "");
}
function copyText(text) {
  const normalized = normalizeText(text);
  if ("clipboard" in navigator) {
    return navigator.clipboard.writeText(normalized);
  }
  const body = document.body;
  if (!body) {
    return Promise.reject(new Error());
  }
  const node = createNode(normalized);
  body.appendChild(node);
  copySelection(node);
  body.removeChild(node);
  return Promise.resolve();
}

// node_modules/@github/clipboard-copy-element/dist/clipboard-copy-element.js
async function copy(button) {
  const id = button.getAttribute("for");
  const text = button.getAttribute("value");
  function trigger() {
    button.dispatchEvent(new CustomEvent("clipboard-copy", { bubbles: true }));
  }
  if (button.getAttribute("aria-disabled") === "true") {
    return;
  }
  if (text) {
    await copyText(text);
    trigger();
  } else if (id) {
    const root2 = "getRootNode" in Element.prototype ? button.getRootNode() : button.ownerDocument;
    if (!(root2 instanceof Document || "ShadowRoot" in window && root2 instanceof ShadowRoot))
      return;
    const node = root2.getElementById(id);
    if (node) {
      await copyTarget(node);
      trigger();
    }
  }
}
function copyTarget(content) {
  if (content instanceof HTMLInputElement || content instanceof HTMLTextAreaElement) {
    return copyText(content.value);
  } else if (content instanceof HTMLAnchorElement && content.hasAttribute("href")) {
    return copyText(content.href);
  } else {
    return copyNode(content);
  }
}
function clicked(event) {
  const button = event.currentTarget;
  if (button instanceof HTMLElement) {
    copy(button);
  }
}
function keydown(event) {
  if (event.key === " " || event.key === "Enter") {
    const button = event.currentTarget;
    if (button instanceof HTMLElement) {
      event.preventDefault();
      copy(button);
    }
  }
}
function focused(event) {
  ;
  event.currentTarget.addEventListener("keydown", keydown);
}
function blurred(event) {
  ;
  event.currentTarget.removeEventListener("keydown", keydown);
}
var ClipboardCopyElement = class extends HTMLElement {
  static define(tag = "clipboard-copy", registry = customElements) {
    registry.define(tag, this);
    return this;
  }
  constructor() {
    super();
    this.addEventListener("click", clicked);
    this.addEventListener("focus", focused);
    this.addEventListener("blur", blurred);
  }
  connectedCallback() {
    if (!this.hasAttribute("tabindex")) {
      this.setAttribute("tabindex", "0");
    }
    if (!this.hasAttribute("role")) {
      this.setAttribute("role", "button");
    }
  }
  get value() {
    return this.getAttribute("value") || "";
  }
  set value(text) {
    this.setAttribute("value", text);
  }
};

// node_modules/@github/clipboard-copy-element/dist/clipboard-copy-element-define.js
var root = typeof globalThis !== "undefined" ? globalThis : window;
try {
  root.ClipboardCopyElement = ClipboardCopyElement.define();
} catch (e) {
  if (!(root.DOMException && e instanceof DOMException && e.name === "NotSupportedError") && !(e instanceof ReferenceError)) {
    throw e;
  }
}

// assets/js/src/backend.js
document.addEventListener("clipboard-copy", () => announce(window.rex.i18n?.clipboard_copied ?? "Copied"));
Object.assign(window.rex, { ready, announce, overlay, pick, pickers, dialog, confirm, collapse });
document.addEventListener("DOMContentLoaded", () => ready(document.body));
