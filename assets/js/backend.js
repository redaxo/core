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
  for (const [key, value] of Object.entries(params)) {
    if (value !== void 0 && value !== null && value !== "") {
      search.set(key, String(value));
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
    const current = Math.min(Math.max(window.scrollY, 0), max);
    this.lastToggle ??= current;
    if (!document.body.classList.contains("rex-sidebar-open")) {
      if (current > this.previous && current >= this.lastToggle + 150) {
        bar.classList.add("is-hidden");
        this.lastToggle = current;
      } else if (current < this.previous && (current <= this.lastToggle - 300 || current < 50)) {
        bar.classList.remove("is-hidden");
        this.lastToggle = current;
      }
    }
    bar.classList.toggle("is-elevated", current > 10);
    this.previous = current;
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
document.addEventListener("rex:ready", (event) => {
  if (!event.target.closest?.(".rex-overlay")) {
    toggleSidebar(false);
    topbar.update();
  }
});

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

// assets/js/src/backend.js
Object.assign(window.rex, { ready, overlay, pick, pickers });
