/* Session timeout: keeps the session alive while the user is active and warns before it expires */

import {dialog} from './dialog.js';

const rex = window.rex;
const i18n = rex.i18n ?? {};

// seconds
const checkInterval = 5;
const keepAliveInterval = 180;
const warningTime = rex.session_warning_time + checkInterval;

// all points in time in milliseconds
let overallWarningAt;
let sessionWarningAt;
let keepAliveUntil;
let nextKeepAliveAt;
let interval;
let current = null;

const now = () => Date.now();

function setSessionWarningAt() {
    sessionWarningAt = now() + (rex.session_duration - warningTime) * 1000;
}

function setKeepAliveUntil() {
    keepAliveUntil = now() + rex.session_keep_alive * 1000;
}

function start() {
    nextKeepAliveAt = now() + keepAliveInterval * 1000;
    interval = setInterval(check, checkInterval * 1000);
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
        const response = await fetch(rex.session_keep_alive_url + '&' + now());
        if (!response.ok) {
            throw new Error(response.statusText);
        }
        const {rest_overall_time: rest} = await response.json();
        overallWarningAt = now() + (rest - warningTime) * 1000;
        setSessionWarningAt();
        start();
    } catch {
        showFailed();
    }
}

const logout = {label: i18n.session_timeout_logout_label, icon: 'sign-out', onClick: () => location.href = rex.session_logout_url};

/** Paragraph of the message, with the remaining minutes at the `{0}` placeholder. */
function paragraph(message) {
    const p = document.createElement('p');
    const [before, after = ''] = message.split('{0}');
    p.append(before);
    if (message.includes('{0}')) {
        const counter = document.createElement('span');
        counter.dataset.rexSessionCounter = '';
        p.append(counter, after);
    }
    return p;
}

function show(message, buttons) {
    current?.close();
    current = dialog({title: i18n.session_timeout_title, content: paragraph(message), buttons, className: 'rex-session-timeout-dialog', role: 'alertdialog'});
    current.closed.then(() => current = null);

    return current;
}

/** Shows the remaining minutes until `end()`, then replaces the message by the "has expired" one. */
function countdown({body, element, closed}, end) {
    const update = () => {
        const seconds = (end() - now()) / 1000;
        const output = body.querySelector('[data-rex-session-counter]');
        if (output) {
            output.textContent = '~' + Math.trunc(seconds / 60);
        }
        if (seconds <= 0) {
            element.querySelector('[value="refresh"]')?.remove();
            body.replaceChildren(paragraph(i18n.session_timeout_message_has_expired));
            clearInterval(timer);
        }
    };
    const timer = setInterval(update, 1000);
    update();
    closed.then(() => clearInterval(timer));
}

function showFailed() {
    show(i18n.session_timeout_message_has_expired, [
        {label: i18n.session_timeout_login_label, icon: 'sign-in', primary: true, onClick: () => location.href = rex.session_login_url},
    ]);
}

function showExpand() {
    const message = i18n.session_timeout_message_expand.replace('{1}', String(Math.trunc(rex.session_duration / 60)));
    const box = show(message, [
        logout,
        {label: i18n.session_timeout_refresh_label, icon: 'refresh', value: 'refresh', primary: true, onClick: () => {
            keepAlive();
            setSessionWarningAt();
        }},
    ]);
    countdown(box, () => sessionWarningAt + warningTime * 1000);
}

function showOverallTimeout() {
    const box = show(i18n.session_timeout_message_expired, [logout]);
    countdown(box, () => overallWarningAt + warningTime * 1000);
}

if (rex.session_keep_alive_url) {
    const serverOffset = Math.trunc(now() / 1000) - rex.session_server_time;
    overallWarningAt = (rex.session_start - serverOffset + rex.session_max_overall_duration - warningTime) * 1000;
    setKeepAliveUntil();
    setSessionWarningAt();
    start();

    document.addEventListener('rex:ready', () => {
        setSessionWarningAt();
        setKeepAliveUntil();
    });
}
