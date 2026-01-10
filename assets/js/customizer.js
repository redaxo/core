$(document).on('rex:ready', function (event, container) {
    Customizer.init(container);
});

var Customizer = function () { };

Customizer.init = function (container) {
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

