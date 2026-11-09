/* Passkeys (WebAuthn): login, adding a passkey and confirming changes in the profile with a passkey */

import {announce} from './announce.js';

/**
 * Tells about a failed passkey request, unless the user canceled it or the browser aborted the conditional login
 * (both reject with `NotAllowedError` / `AbortError`).
 */
function failed(form, error) {
    if (['NotAllowedError', 'AbortError'].includes(error?.name)) {
        return;
    }
    const message = window.rex.i18n?.passkey_failed ?? 'The passkey could not be used.';
    const alert = Object.assign(document.createElement('div'), {className: 'rex-alert', textContent: message});
    alert.dataset.variant = 'danger';
    alert.setAttribute('role', 'alert');
    form.querySelector(':scope > .rex-alert[role="alert"]')?.remove();
    form.prepend(alert);
    announce(message, {assertive: true});
}

const authAddPasskey = function (form) {
    form.classList.remove('rex-u-hidden');

    const passkey = form.querySelector('[data-auth-passkey]');

    form.addEventListener('submit', (event) => {
        event.preventDefault();

        const options = JSON.parse(passkey.dataset.authPasskey);
        recursiveBase64StrToArrayBuffer(options);

        navigator.credentials.create(options).then(data => {
            data = {
                clientDataJSON: data.response.clientDataJSON  ? arrayBufferToBase64(data.response.clientDataJSON) : null,
                attestationObject: data.response.attestationObject ? arrayBufferToBase64(data.response.attestationObject) : null
            }

            passkey.value = JSON.stringify(data);

            form.submit();
        }).catch(error => failed(form, error));
    });
}

const authPasskeyVerify = function (form) {
    const verify = form.querySelector('[data-auth-passkey-verify]');
    if (!verify) {
        return;
    }

    const fields = form.querySelectorAll('input[type=password], button[type=submit]');
    fields.forEach(field => field.disabled = true);

    const button = verify.querySelector('button');

    button.addEventListener('click', () => {
        const passkey = verify.querySelector('input[type=hidden]');

        authVerify(verify.dataset.authPasskeyVerify, 'required', form, value => {
            passkey.value = value;
            button.disabled = true;
            verify.querySelector('i').classList.remove('rex-u-hidden');
            fields.forEach(field => field.disabled = false);
        });
    });
}

const authLogin = function (form) {
    const passkey = form.querySelector('[data-auth-passkey]');

    authVerify(passkey.dataset.authPasskey, 'conditional', form, value => {
        passkey.value = value;
        form.submit();
    });
}

const authVerify = function (options, mediation, form, onSuccess) {
    options = JSON.parse(options);
    recursiveBase64StrToArrayBuffer(options);

    options.mediation = mediation;

    navigator.credentials.get(options).then(data => {
        data = {
            id: data.id,
            clientDataJSON: data.response.clientDataJSON  ? arrayBufferToBase64(data.response.clientDataJSON) : null,
            authenticatorData: data.response.authenticatorData ? arrayBufferToBase64(data.response.authenticatorData) : null,
            signature: data.response.signature ? arrayBufferToBase64(data.response.signature) : null,
            userHandle: data.response.userHandle ? arrayBufferToBase64(data.response.userHandle) : null
        }

        onSuccess(JSON.stringify(data));
    }).catch(error => failed(form, error));
}

const recursiveBase64StrToArrayBuffer = function (obj) {
    let prefix = '=?BINARY?B?';
    let suffix = '?=';
    if (typeof obj === 'object') {
        for (let key in obj) {
            if (typeof obj[key] === 'string') {
                let str = obj[key];
                if (str.substring(0, prefix.length) === prefix && str.substring(str.length - suffix.length) === suffix) {
                    str = str.substring(prefix.length, str.length - suffix.length);

                    let binary_string = window.atob(str);
                    let len = binary_string.length;
                    let bytes = new Uint8Array(len);
                    for (let i = 0; i < len; i++)        {
                        bytes[i] = binary_string.charCodeAt(i);
                    }
                    obj[key] = bytes.buffer;
                }
            } else {
                recursiveBase64StrToArrayBuffer(obj[key]);
            }
        }
    }
}

const arrayBufferToBase64 = function (buffer) {
    let binary = '';
    let bytes = new Uint8Array(buffer);
    let len = bytes.byteLength;
    for (let i = 0; i < len; i++) {
        binary += String.fromCharCode( bytes[ i ] );
    }
    return window.btoa(binary);
}

// rex.ready() may run again on a part of the page, each form gets its listeners only once
const initialized = new WeakSet();
const once = form => form && !initialized.has(form) && initialized.add(form);

// Availability of `window.PublicKeyCredential` means WebAuthn is usable. Confirming the password change with a passkey
// works with any authenticator, so it needs nothing more.
if (window.PublicKeyCredential) {
    document.addEventListener('rex:ready', event => {
        const form = event.target.querySelector('form[data-auth-change-password]');
        if (once(form)) {
            authPasskeyVerify(form);
        }
    });
}

// Adding a passkey and the login with autofill need a user verifying platform authenticator and conditional mediation,
// `isUserVerifyingPlatformAuthenticatorAvailable` and `isConditionalMediationAvailable` tell whether they are there.
if (window.PublicKeyCredential &&
    PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable &&
    PublicKeyCredential.isConditionalMediationAvailable) {

    let ready = false;
    document.addEventListener('rex:ready', function () {
        ready = true;
    });

    // Check if user verifying platform authenticator is available.
    Promise.all([
        PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable(),
        PublicKeyCredential.isConditionalMediationAvailable(),
    ]).then(results => {
        if (!results.every(r => r === true)) {
            return;
        }

        const init = function (container) {
            let form = container.querySelector('form[data-auth-add-passkey]');
            if (once(form)) {
                authAddPasskey(form);
                authPasskeyVerify(form);
            }

            form = container.querySelector('form[data-auth-login]');
            if (once(form)) {
                authLogin(form);
            }
        }

        if (ready) {
            init(document.body);
        }

        document.addEventListener('rex:ready', function (event) {
            init(event.target);
        });
    });
}
