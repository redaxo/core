$(document).on('rex:ready', function (event, container) {
    if (container.find(rex.customizer_codemirror_selectors).length > 0) {
        // Zusätzliche Themes?
        themes = rex.customizer_codemirror_defaulttheme + ',' + rex.customizer_codemirror_defaultdarktheme + ',';
        container.find(rex.customizer_codemirror_selectors).each(function () {
            $.each(this.attributes, function () {
                if (this.specified) {
                    if (this.name == 'data-codemirror-theme') {
                        themes = themes + this.value + ',';
                    }
                }
            });
        });
        if (themes != '') {
            themes = themes.substring(0, themes.length - 1);
        }
        if (typeof CodeMirror !== 'function') {
            // this could better use javascript Promises, but browser support..
            var cssLoaded = false,
                scriptLoaded = false;
            var css = document.createElement('link');
            css.rel = 'stylesheet';
            css.href = '?codemirror_output=css&buster=' + rex.customizer_codemirror_cssbuster + '&themes=' + themes;
            css.onload = function () {
                cssLoaded = true;

                if (cssLoaded && scriptLoaded) {
                    Customizer.init(container);
                }
            }
            document.head.appendChild(css);

            var script = document.createElement('script');
            script.src = '?codemirror_output=javascript&buster=' + rex.customizer_codemirror_jsbuster;
            document.head.appendChild(script);
            script.onload = function () {
                scriptLoaded = true;

                if (cssLoaded && scriptLoaded) {
                    Customizer.init(container);
                }
            };
        } else {
            Customizer.init(container);
        }
    } else {
        Customizer.init(container);
    }
});

var Customizer = function () { };

// shared across inits, so generated ids stay unique when rex:ready is triggered for further containers
Customizer.codemirrorIdCounter = 0;

Customizer.init = function (container) {
    container.find(rex.customizer_codemirror_selectors).each(function () {
        var t = $(this);
        var editor = null;

        // skip textareas already converted, e.g. when rex:ready is triggered again for the same container
        if (t.next('.CodeMirror').length) {
            return;
        }

        var id = t.attr("id");

        if (typeof id === "undefined") {
            Customizer.codemirrorIdCounter++;
            id = 'codemirror-id-' + Customizer.codemirrorIdCounter;
            t.attr("id", id);
        }

        var mode = "application/x-httpd-php";
        var theme = rex.customizer_codemirror_defaulttheme;

        let systemDarkModeDetector = null;
        if (window.matchMedia) {
            systemDarkModeDetector = window.matchMedia('(prefers-color-scheme: dark)');
            // Systemseitige Einstellung abfragen
            if (systemDarkModeDetector.matches) {
                theme = rex.customizer_codemirror_defaultdarktheme;
            }
            // Einstellung aus Profil
            if (document.body.classList.contains('rex-theme-light')) {
                theme = rex.customizer_codemirror_defaulttheme;
            } else if (document.body.classList.contains('rex-theme-dark')) {
                theme = rex.customizer_codemirror_defaultdarktheme;
            }
            // Systemseitiges Umschalten Dark/Light erkennen
            systemDarkModeDetector.addEventListener('change', function (e) {
                if (systemDarkModeDetector.matches) {
                    theme = rex.customizer_codemirror_defaultdarktheme;
                } else {
                    theme = rex.customizer_codemirror_defaulttheme;
                }
                if (document.body.classList.contains('rex-theme-light')) {
                    theme = rex.customizer_codemirror_defaulttheme;
                } else if (document.body.classList.contains('rex-theme-dark')) {
                    theme = rex.customizer_codemirror_defaultdarktheme;
                }
                if (editor) {
                    editor.setOption('theme', theme);
                }
            });
        }

        var new_mode = t.attr("data-codemirror-mode");
        var new_theme = t.attr("data-codemirror-theme");
        var new_options = t.attr("data-codemirror-options");

        if (typeof new_mode !== "undefined") {
            mode = new_mode;
        }

        if (typeof new_theme !== "undefined") {
            theme = new_theme;
        }
        if (typeof CodeMirror === "function") {
            cm_options = {
                mode: mode,
                theme: theme,
                autoRefresh: true,
                lineNumbers: true,
                lineWrapping: false,
                styleActiveLine: true,
                matchBrackets: true,
                autoCloseBrackets: true,
                matchTags: {
                    bothTags: true
                },
                tabSize: 4,
                indentUnit: 4,
                indentWithTabs: false,
                smartIndent: true,
                enterMode: "keep",
                tabMode: "shift",
                gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"],
                foldGutter: true,
                extraKeys: {
                    "F11": function (cm) {
                        cm.setOption("fullScreen", !cm.getOption("fullScreen"));
                    },
                    "Esc": function (cm) {
                        cm.setOption("fullScreen", !cm.getOption("fullScreen"));
                    },
                    "Tab": function (cm) {
                        if (cm.doc.somethingSelected()) {
                            return CodeMirror.Pass;
                        }
                        if (!cm.getOption("indentWithTabs")) {
                            var spacesPerTab = cm.getOption("indentUnit");
                            var spacesToInsert = spacesPerTab - (cm.doc.getCursor("start").ch % spacesPerTab);
                            var spaces = Array(spacesToInsert + 1).join(" ");
                            cm.replaceSelection(spaces, "end", "+input");
                        } else {
                            cm.replaceSelection('\t', "end", "+input");
                        }
                    }
                }
            }
            try {
                if (rex.customizer_codemirror_options) {
                    additional_options = jQuery.parseJSON(rex.customizer_codemirror_options);
                    $.extend(cm_options, additional_options);
                }
            } catch (e) {
                console.log('Error in global Codemirror-Options! \nOptions: ' + rex.customizer_codemirror_options);
                console.log(e);
            }
            try {
                if (new_options) {
                    additional_options = jQuery.parseJSON(new_options);
                    $.extend(cm_options, additional_options);
                }
            } catch (e) {
                console.log('Error in Textarea-Codemirror-Options! \nOptions: ' + new_options);
                console.log(e);
            }
            editor = CodeMirror.fromTextArea(this, cm_options);
        }
    });

    if (typeof rex.customizer_labelcolor !== "undefined" && rex.customizer_labelcolor != '') {
        $('.rex-nav-top .navbar').css({
            'border-bottom': '5px solid transparent',
            'border-bottom-color': rex.customizer_labelcolor
        });
    }

    if (typeof rex.customizer_showlink !== "undefined" && rex.customizer_showlink != '' && !$('.be-style-customizer-title').length) {
        $('.rex-nav-top .navbar-header').append(rex.customizer_showlink);
    }
};

