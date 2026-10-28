/*
 Select boxes with search or multiple choice: `<select data-rex-select>`, plus `data-rex-select-search` for a search
 field and `data-rex-select-placeholder` for the text shown while nothing is selected. The native select stays the
 source of truth: it keeps the value, takes part in validation and receives the `change` events; the widget follows
 changes to its options and `disabled` state on its own.
 */

const i18n = () => window.rex.i18n ?? {};
let count = 0;

class Select {
    /** @param {HTMLSelectElement} select */
    constructor(select) {
        this.select = select;
        this.search = select.hasAttribute('data-rex-select-search');
        this.id = 'rex-select-' + ++count;

        this.element = document.createElement('div');
        this.element.className = 'rex-select';
        this.element.innerHTML = `
            <button type="button" class="rex-input rex-select-toggle" aria-haspopup="listbox" aria-expanded="false" popovertarget="${this.id}">
                <span class="rex-select-value" id="${this.id}-value"></span>
            </button>
            <div class="rex-select-panel" id="${this.id}" popover>
                ${this.search ? `<input type="search" class="rex-input rex-select-search" autocomplete="off" aria-controls="${this.id}-options">` : ''}
                ${this.search && select.multiple ? `<div class="rex-select-actions">
                    <button type="button" class="rex-button" data-size="xs" data-rex-select-all></button>
                    <button type="button" class="rex-button" data-size="xs" data-rex-select-none></button>
                </div>` : ''}
                <ul class="rex-select-options" id="${this.id}-options" role="listbox"></ul>
                <p class="rex-select-empty" role="status"></p>
            </div>`;

        this.toggle = this.element.querySelector('.rex-select-toggle');
        this.panel = this.element.querySelector('.rex-select-panel');
        this.list = this.element.querySelector('.rex-select-options');
        this.input = this.element.querySelector('.rex-select-search');
        this.empty = this.element.querySelector('.rex-select-empty');

        this.list.setAttribute('aria-multiselectable', String(select.multiple));
        this.input?.setAttribute('aria-label', i18n().select_search ?? 'Search');
        this.element.querySelector('[data-rex-select-all]')?.append(i18n().select_all ?? 'All');
        this.element.querySelector('[data-rex-select-none]')?.append(i18n().select_none ?? 'None');
        // the toggle reads as label and current value, the list of options as label
        if (select.labels[0]) {
            const label = select.labels[0].id ||= this.id + '-label';
            this.toggle.setAttribute('aria-labelledby', label + ' ' + this.id + '-value');
            this.list.setAttribute('aria-labelledby', label);
        }

        select.before(this.element);
        this.element.append(select);
        select.classList.add('rex-select-native');
        select.tabIndex = -1;

        this.toggle.addEventListener('keydown', event => {
            if ('ArrowDown' === event.key || 'ArrowUp' === event.key) {
                event.preventDefault();
                this.panel.showPopover();
            }
        });
        // validation and labels focus the hidden select
        select.addEventListener('focus', () => this.toggle.focus());
        select.addEventListener('change', () => this.update());

        this.panel.addEventListener('beforetoggle', event => this.beforeToggle('open' === event.newState));
        this.panel.addEventListener('toggle', event => {
            if ('open' === event.newState) {
                (this.input ?? this.list.querySelector('[aria-selected="true"]') ?? this.options()[0])?.focus();
            }
        });
        this.list.addEventListener('click', event => {
            const item = event.target.closest('[role="option"]');
            if (item) {
                this.choose(item);
            }
        });
        this.panel.addEventListener('keydown', event => this.keydown(event));
        this.input?.addEventListener('input', () => this.filter());
        this.element.querySelector('[data-rex-select-all]')?.addEventListener('click', () => this.selectAll(true));
        this.element.querySelector('[data-rex-select-none]')?.addEventListener('click', () => this.selectAll(false));

        new MutationObserver(() => this.update()).observe(select, {attributes: true, attributeFilter: ['disabled'], childList: true, subtree: true});
        this.position = this.position.bind(this);

        this.update();
    }

    /** Visible, enabled option items of the list. */
    options() {
        return [...this.list.querySelectorAll('[role="option"]:not([hidden], [aria-disabled="true"])')];
    }

    update() {
        const selected = [...this.select.selectedOptions].filter(option => '' !== option.value || !this.select.multiple);
        const label = selected.map(option => option.label).join(', ');
        this.element.querySelector('.rex-select-value').textContent = label || (this.select.dataset.rexSelectPlaceholder ?? '—');
        this.toggle.title = label;
        this.toggle.disabled = this.select.disabled;
        if (this.panel.matches(':popover-open')) {
            this.render();
        }
    }

    render() {
        this.list.replaceChildren();
        let groupList = null;
        for (const [index, option] of [...this.select.options].entries()) {
            const group = option.parentElement instanceof HTMLOptGroupElement ? option.parentElement : null;
            if (group && option === group.querySelector('option')) {
                // an option group is a labeled group of its options within the listbox
                const wrapper = document.createElement('li');
                wrapper.setAttribute('role', 'none');
                const header = document.createElement('div');
                header.className = 'rex-select-group';
                header.id = `${this.id}-group-${index}`;
                header.textContent = group.label;
                groupList = document.createElement('ul');
                groupList.setAttribute('role', 'group');
                groupList.setAttribute('aria-labelledby', header.id);
                wrapper.append(header, groupList);
                this.list.append(wrapper);
            } else if (!group) {
                groupList = null;
            }
            const item = document.createElement('li');
            item.setAttribute('role', 'option');
            item.tabIndex = -1;
            item.dataset.index = String(index);
            item.textContent = option.label || ' ';
            item.setAttribute('aria-selected', String(option.selected));
            if (option.disabled || group?.disabled) {
                item.setAttribute('aria-disabled', 'true');
            }
            (groupList ?? this.list).append(item);
        }
        this.filter();
    }

    filter() {
        const term = this.input?.value.trim().toLocaleLowerCase() ?? '';
        let visible = 0;
        for (const item of this.list.querySelectorAll('[role="option"]')) {
            item.hidden = '' !== term && !item.textContent.toLocaleLowerCase().includes(term);
            visible += item.hidden ? 0 : 1;
        }
        for (const group of this.list.querySelectorAll('[role="group"]')) {
            group.parentElement.hidden = !group.querySelector('[role="option"]:not([hidden])');
        }
        // a live region, so the empty result is announced
        this.empty.textContent = visible > 0 ? '' : (i18n().select_no_results ?? 'No results');
    }

    choose(item) {
        if ('true' === item.getAttribute('aria-disabled')) {
            return;
        }
        const option = this.select.options[Number(item.dataset.index)];
        if (this.select.multiple) {
            option.selected = !option.selected;
            item.setAttribute('aria-selected', String(option.selected));
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
            item.setAttribute('aria-selected', String(selected));
        }
        this.changed();
    }

    changed() {
        this.select.dispatchEvent(new Event('input', {bubbles: true}));
        this.select.dispatchEvent(new Event('change', {bubbles: true}));
    }

    keydown(event) {
        const options = this.options();
        const index = options.indexOf(document.activeElement);

        if ('ArrowDown' === event.key || 'ArrowUp' === event.key) {
            event.preventDefault();
            const next = 'ArrowDown' === event.key ? Math.min(index + 1, options.length - 1) : index - 1;
            (next < 0 ? this.input ?? options[0] : options[next])?.focus();
        } else if (('Home' === event.key || 'End' === event.key) && index >= 0) {
            event.preventDefault();
            options.at('Home' === event.key ? 0 : -1)?.focus();
        } else if (1 === event.key.length && index >= 0 && !event.ctrlKey && !event.metaKey && !event.altKey && ' ' !== event.key) {
            // type-ahead: the next option starting with the typed characters
            clearTimeout(this.typeaheadTimer);
            this.typeahead = (this.typeahead ?? '') + event.key.toLocaleLowerCase();
            this.typeaheadTimer = setTimeout(() => this.typeahead = '', 500);
            const ordered = [...options.slice(index + 1), ...options.slice(0, index + 1)];
            ordered.find(option => option.textContent.toLocaleLowerCase().startsWith(this.typeahead))?.focus();
        } else if (('Enter' === event.key || ' ' === event.key) && index >= 0) {
            event.preventDefault();
            this.choose(options[index]);
        } else if ('Enter' === event.key && document.activeElement === this.input) {
            event.preventDefault();
            if (options[0]) {
                this.choose(options[0]);
            }
        } else if ('Escape' === event.key) {
            // a search field would only clear itself on the first Escape
            event.preventDefault();
            this.panel.hidePopover();
            this.toggle.focus();
        } else if ('Tab' === event.key) {
            this.panel.hidePopover();
        }
    }

    beforeToggle(open) {
        this.toggle.setAttribute('aria-expanded', String(open));
        if (open) {
            if (this.input) {
                this.input.value = '';
            }
            this.render();
            this.position();
            addEventListener('scroll', this.position, true);
            addEventListener('resize', this.position);
        } else {
            removeEventListener('scroll', this.position, true);
            removeEventListener('resize', this.position);
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
            left: Math.max(0, Math.min(rect.left, innerWidth - Math.max(rect.width, 220))) + 'px',
            minWidth: rect.width + 'px',
            top: above ? '' : rect.bottom + 2 + 'px',
            bottom: above ? innerHeight - rect.top + 2 + 'px' : '',
            maxHeight: Math.max(150, (above ? rect.top : below) - 16) + 'px',
        });
    }
}

document.addEventListener('rex:ready', event => {
    for (const select of event.target.querySelectorAll('select[data-rex-select]')) {
        if (!select.closest('.rex-select')) {
            new Select(select);
        }
    }
});
