// save & restore sidebar panel toggle status via localstorage
$(document).on('rex:ready', function (event, viewRoot) {
    var $sidebar = viewRoot.is('#rex-page-content-edit') && viewRoot.find('#rex-js-main-sidebar'),
        sidebar_status,
        $sections;
    function store_sidebar_status () {
        sidebar_status = [];
        $sections.each(function (k, v) {
            sidebar_status.push($(v).find('div.collapse').hasClass('in').toString());
        });
        localStorage.setItem('structure_content_sidebar_status', JSON.stringify(sidebar_status));
    }
    if ($sidebar.length) {
        sidebar_status = localStorage.getItem('structure_content_sidebar_status');
        $sections = $sidebar.children('section');
        if (!sidebar_status) {
            store_sidebar_status();
        }
        else {
            sidebar_status = JSON.parse(sidebar_status);
            if (sidebar_status.length !== $sections.length) {
                store_sidebar_status();
            }
            else {
                $sections.each(function (k, v) {
                    var $panel = $(v).find('div.collapse'),
                        panel_is_visible = $panel.hasClass('in');
                    if (panel_is_visible.toString() !== sidebar_status[k]) {
                        if (panel_is_visible) {
                            $panel.collapse('hide');
                        }
                        else {
                            $panel.collapse('show');
                        }
                    }
                });
            }
        }
        $sidebar.on('shown.bs.collapse hidden.bs.collapse', function() {
            store_sidebar_status ();
        });
    }
});
