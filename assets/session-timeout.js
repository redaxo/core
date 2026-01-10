(function () {
    'use strict';

    const createSessionTimeoutManager = function () {

        let intervalCheckTime = 5;
        let sessionCheckInterval;
        let keepAliveTime = 180;
        let keepAliveEndTime;
        let keepAliveNextTime;
        let warningTime;
        let serverOffsetTime = 0; // Offset zwischen Serverzeit und Browserzeit in Sekunden
        let overallSessionWarningTime;
        let currentSessionWarningTime;
        let sessionCounterDelete;
        let OverallSessionCounterDelete;

        const init = function () {

            warningTime = rex.session_warning_time + intervalCheckTime;
            serverOffsetTime = parseInt(new Date().getTime() / 1000) - rex.session_server_time; // Offset in Sekunden
            overallSessionWarningTime = (rex.session_start - serverOffsetTime + rex.session_max_overall_duration - warningTime) * 1000;

            updateKeepAliveEndTime();
            setCurrentSessionWarningTime();

            startSessionInterval();

            $(document).on("rex:ready", function() {
                setCurrentSessionWarningTime();
                updateKeepAliveEndTime();
            });

        };

        const setCurrentSessionWarningTime = function () {
            currentSessionWarningTime = new Date().getTime() + ((rex.session_duration - warningTime) * 1000); // Zeitpunkt, bis zu dem die aktuelle Session gültig ist
        }

        const updateKeepAliveEndTime = function () {
            keepAliveEndTime = new Date().getTime() + (rex.session_keep_alive * 1000); // Zeitpunkt, bis zu dem die KeepAlive-Funktion aktiv ist
        }

        const startSessionInterval = function () {
            keepAliveNextTime = new Date().getTime() + (keepAliveTime * 1000); // Zeitpunkt, bis zum nächsten KeepAlive

            sessionCheckInterval = setInterval(function () {
                const currentTime = new Date().getTime();

                if (overallSessionWarningTime < currentTime) {
                    clearInterval(sessionCheckInterval);
                    viewSessionOverallTimeoutDialog();
                    return;
                }

                if (keepAliveEndTime > currentTime) {
                    if (keepAliveNextTime > currentTime) {
                        return;
                    }
                    clearInterval(sessionCheckInterval);
                    performKeepAlive();
                } else if (!rex.session_stay_logged_in && currentSessionWarningTime < currentTime) {
                    clearInterval(sessionCheckInterval);
                    viewSessionExpandDialog();
                }

            }, intervalCheckTime * 1000);
        };

        const performKeepAlive = function () {

            const xhr = new XMLHttpRequest();
            xhr.open('GET', rex.session_keep_alive_url + '&' + new Date().getTime(), true);
            xhr.onload = function () {
                if (xhr.status === 200) {
                    let response = JSON.parse(xhr.responseText);
                    overallSessionWarningTime = new Date().getTime() + ((response.rest_overall_time - warningTime) * 1000);
                    setCurrentSessionWarningTime();
                    startSessionInterval();
                } else {
                    viewSessionFailedDialog();
                }
            };

            xhr.onerror = function () {
                viewSessionFailedDialog();
            };

            xhr.send();
        };

        const createModalBox = function (options) {
            // Entferne existierende Dialoge
            const existingDialog = document.querySelector('.rex-session-timeout-dialog');
            if (existingDialog) {
                existingDialog.remove();
            }

            document.querySelector('.modal-backdrop')?.remove();

            // Erstelle Modal HTML
            const modal = document.createElement('div');
            modal.className = 'modal rex-session-timeout-dialog';

            let buttonHTML = '';
            if (options.buttons && options.buttons.length) {
                options.buttons.forEach(function (button) {
                    buttonHTML += '<button id="' + button.id + '" ' + button.attr + ' type="button">' + button.label + '</button>';
                });
            }

            modal.innerHTML = `
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                                <h4 class="modal-title">${rex.i18n.session_timeout_title}</h4>
                            </div>
                            <div class="modal-body">
                                <p>${options.message}</p>
                            </div>
                            <div class="modal-footer">
                                ${buttonHTML}
                            </div>
                        </div>
                    </div>
                `;

            document.body.appendChild(modal);

            // Event Listener für Buttons hinzufügen
            if (options.buttons && options.buttons.length) {
                options.buttons.forEach(function (button) {
                    if (button.click) {
                        const btnElement = document.getElementById(button.id);
                        if (btnElement) {
                            btnElement.addEventListener('click', button.click);
                        }
                    }
                });
            }

            // Close Button Funktionalität
            const closeButton = modal.querySelector('.close');
            if (closeButton) {
                closeButton.addEventListener('click', function () {
                    modal.remove();
                });
            }

            // Bootstrap Modal anzeigen (falls Bootstrap vorhanden)
            if (typeof $ !== 'undefined' && $.fn.modal) {
                $(modal).modal({
                    backdrop: 'static'
                });
                $(modal).on('hidden.bs.modal', function () {
                    modal.remove();
                });
            } else {
                // Fallback ohne Bootstrap
                modal.style.display = 'block';
                modal.classList.add('in');

                // Backdrop hinzufügen
                const backdrop = document.createElement('div');
                backdrop.className = 'modal-backdrop fade in';
                document.body.appendChild(backdrop);
            }
        };

        const viewSessionFailedDialog = function () {
            const options = {
                title: rex.i18n.session_timeout_title,
                message: rex.i18n.session_timeout_message_has_expired,
                buttons: [
                    {
                        id: 'rex-session-timeout-dialog-login',
                        label: '<i class="rex-icon rex-icon-sign-in"></i> ' + rex.i18n.session_timeout_login_label,
                        attr: 'class="btn btn-primary rex-session-timeout-dialog-login-btn"',
                        click: function () {
                            window.location.href = rex.session_login_url;
                        }
                    }
                ]
            };

            createModalBox(options);
        };

        const viewSessionExpandDialog = function () {

            let sessionButtonCounter = (currentSessionWarningTime + (warningTime * 1000) - new Date().getTime()) / 1000; // Zeit bis zum Ende der aktuellen Session in Sekunden

            const values = ['<span id="rex-session-timeout-counter">~' + parseInt(sessionButtonCounter / 60) + '</span>', parseInt(rex.session_duration / 60)];
            const message = rex.i18n.session_timeout_message_expand.replace(/{(\d+)}/g, function (match, index) {
                return values[Number(index)];
            });

            const options = {
                message: message,
                buttons: [
                    {
                        id: 'rex-session-timeout-dialog-logout',
                        label: '<i class="rex-icon rex-icon-sign-out"></i> ' + rex.i18n.session_timeout_logout_label,
                        attr: 'class="btn btn-default rex-session-timeout-dialog-logout"',
                        click: function () {
                            window.location.href = rex.session_logout_url;
                        }
                    },
                    {
                        id: 'rex-session-timeout-dialog-refresh',
                        label: '<i class="rex-icon rex-icon-refresh"></i> ' + rex.i18n.session_timeout_refresh_label,
                        attr: 'class="btn btn-primary rex-session-timeout-dialog-refresh" data-dismiss="modal"',
                        click: function () {
                            clearInterval(sessionCounterDelete);
                            performKeepAlive();
                            setCurrentSessionWarningTime(); // Zeitpunkt, bis zu dem die aktuelle Session gültig ist
                            document.querySelector('.rex-session-timeout-dialog').remove();
                            document.querySelector('.modal-backdrop').remove();
                        }
                    }
                ]
            };

            createModalBox(options);

            sessionCounterDelete = setInterval(function () {
                sessionButtonCounter = (currentSessionWarningTime + (warningTime * 1000) - new Date().getTime()) / 1000; // Zeit bis zum Ende der aktuellen Session in Sekunden
                let TimeOutElement = document.getElementById('rex-session-timeout-counter');
                if (TimeOutElement) {
                    TimeOutElement.innerHTML = "~" + parseInt(sessionButtonCounter / 60);
                    if (sessionButtonCounter <= 0) {
                        document.getElementById('rex-session-timeout-dialog-refresh').remove(); // Button entfernen
                        document.querySelector('.rex-session-timeout-dialog .modal-body p').innerHTML = rex.i18n.session_timeout_message_has_expired;
                        clearInterval(sessionCounterDelete);
                    }
                } else {
                    clearInterval(sessionCounterDelete);
                }
            }, 1000);

        };

        const viewSessionOverallTimeoutDialog = function () {

            let OverallSessionButtonCounter = (overallSessionWarningTime + (warningTime * 1000) - new Date().getTime()) / 1000;

            const values = ['<span id="rex-session-timeout-counter">~' + parseInt(OverallSessionButtonCounter / 60) + '</span>'];

            const message = rex.i18n.session_timeout_message_expired.replace(/{(\d+)}/g, function (match, index) {
                return values[Number(index)];
            });

            const options = {
                message: message,
                buttons: [
                    {
                        id: 'rex-session-timeout-dialog-logout',
                        attr: 'class="btn btn-default rex-session-timeout-dialog-logout"',
                        label: '<i class="rex-icon rex-icon-sign-out"></i> ' + rex.i18n.session_timeout_logout_label,
                        click: function () {
                            window.location.href = rex.session_logout_url;
                        }
                    }
                ]
            };

            createModalBox(options);

            OverallSessionCounterDelete = setInterval(function () {
                let OverallSessionButtonCounter = (overallSessionWarningTime + (warningTime * 1000) - new Date().getTime()) / 1000;
                let TimeOutElement = document.getElementById('rex-session-timeout-counter');
                if (TimeOutElement) {
                    TimeOutElement.innerHTML = "~" + parseInt(OverallSessionButtonCounter / 60);
                    if (OverallSessionButtonCounter <= 0) {
                        document.querySelector('.rex-session-timeout-dialog .modal-body p').innerHTML = rex.i18n.session_timeout_message_has_expired;
                        clearInterval(OverallSessionCounterDelete);
                    }
                } else {
                    clearInterval(OverallSessionCounterDelete);
                }
            }, 1000);

        };

        // Öffentliche API
        return {
            init: init
        };
    };

    // Session Manager erstellen und initialisieren
    const sessionManager = createSessionTimeoutManager();

    // Warte bis DOM geladen ist
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            sessionManager.init();
        });
    } else {
        sessionManager.init();
    }

})();
