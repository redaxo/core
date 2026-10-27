/*
 Article history (content/history): previews of the compared versions, a timeline placing the versions by their time,
 and the restore button for the selected version
 */

const RANGE = 1000;

document.addEventListener('rex:ready', event => {
    event.target.querySelectorAll('[data-rex-history]').forEach(init);
});

function init(root) {
    const url = new URL(root.dataset.rexHistory, location.href);
    const [baseFrame, targetFrame] = root.querySelectorAll('.rex-history__frame iframe');
    const base = root.querySelector('[data-rex-history-base]');
    const target = root.querySelector('[data-rex-history-target]');
    const steps = root.querySelectorAll('[data-rex-history-step]');
    const restore = root.querySelector('button[name="func"][value="restore"]');
    // oldest first, the current version last
    const options = [...target.options].reverse();
    let index = -1;
    let timer;

    const preview = (frame, params) => {
        const src = new URL(url);
        for (const [key, value] of Object.entries(params)) {
            src.searchParams.set(key, value);
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
        steps.forEach(step => step.disabled = !options[index + Number(step.dataset.rexHistoryStep)]);
        if (restore) {
            restore.disabled = '' === target.value;
        }
        timeline?.set(index);

        clearTimeout(timer);
        timer = setTimeout(() => preview(targetFrame, target.value ? {rex_history_date: target.value} : {}), delay);
    };

    const element = root.querySelector('.rex-history__timeline');
    const timeline = element && createTimeline(element, options, select);

    const show = root.querySelector('[data-rex-history-show]');
    const showBase = () => {
        preview(baseFrame, '1' === base?.value ? {rex_version: 1} : {});
        if (show) {
            show.href = baseFrame.src;
        }
    };
    base?.addEventListener('change', showBase);
    target.addEventListener('change', () => select(options.indexOf(target.selectedOptions[0])));
    steps.forEach(step => step.addEventListener('click', () => select(index + Number(step.dataset.rexHistoryStep), 300)));

    showBase();
    // compares the current version with the latest saved one
    select(options.length - 2);
}

/**
 * Positions of the versions on the timeline (0 … RANGE) by their time; gaps narrower than `min` (fraction of the
 * whole) are widened at the expense of the wider ones, so close versions stay apart.
 */
function positions(times, min) {
    const span = times.at(-1) - times[0] || 1;
    const gaps = times.slice(1).map((time, i) => (time - times[i]) / span);
    min = Math.min(min, 1 / gaps.length);
    const missing = gaps.reduce((sum, gap) => sum + Math.max(0, min - gap), 0);
    const spare = gaps.reduce((sum, gap) => sum + Math.max(0, gap - min), 0);

    let position = 0;
    return [0, ...gaps.map(gap => position += gap < min ? min : gap - (spare && missing * (gap - min) / spare))]
        .map(value => Math.round(value * RANGE));
}

/** Timeline: a range input snapping to the versions, over a track with a segment and a tick per version. */
function createTimeline(element, options, select) {
    const track = element.querySelector('.rex-history__track');
    const range = element.querySelector('.rex-history__range');
    const points = positions(options.map(option => Number(option.dataset.time)), 10 / (track.clientWidth || 800));

    const segments = points.slice(1).map((point, i) => {
        const segment = document.createElement('span');
        segment.className = 'rex-history__segment';
        segment.style.setProperty('--from', points[i] / RANGE);
        segment.style.setProperty('--to', point / RANGE);
        return segment;
    });
    const ticks = points.map(point => {
        const tick = document.createElement('span');
        tick.className = 'rex-history__tick';
        tick.style.setProperty('--at', point / RANGE);
        return tick;
    });
    track.replaceChildren(...segments, ...ticks);

    const nearest = value => points.reduce((best, point, i) => Math.abs(point - value) < Math.abs(points[best] - value) ? i : best, 0);
    let current = 0;

    // dragging snaps to the versions and loads the preview once the thumb rests a moment
    range.addEventListener('input', () => select(nearest(Number(range.value)), 300));
    range.addEventListener('change', () => range.value = points[current]);
    // the arrow keys move from version to version instead of by single steps of the range
    range.addEventListener('keydown', event => {
        const next = {
            ArrowLeft: current - 1, ArrowDown: current - 1, PageDown: current - 1,
            ArrowRight: current + 1, ArrowUp: current + 1, PageUp: current + 1,
            Home: 0, End: points.length - 1,
        }[event.key];
        if (undefined !== next) {
            event.preventDefault();
            select(next, 300);
        }
    });

    return {
        set(index) {
            current = index;
            range.value = points[index];
            range.setAttribute('aria-valuetext', options[index].textContent.trim());
            segments.forEach((segment, i) => segment.classList.toggle('is-past', i < index));
            ticks.forEach((tick, i) => tick.classList.toggle('is-selected', i === index));
        },
    };
}
