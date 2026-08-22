/*
 REDAXO JavaScript library
 */

// -------------------------------------------------------------------------------------------------------------------
window.addEventListener("unload", (event) => {
    closeAll();
});

// -------------------------------------------------------------------------------------------------------------------

function getObj(name)
{
    if (document.getElementById)
    {
        this.obj = document.getElementById(name);
        if(this.obj)
            this.style = this.obj.style;
    }
    else if (document.all)
    {
        this.obj = document.all[name];
        if(this.obj)
            this.style = this.obj.style;
    }
    else if (document.layers)
    {
        this.obj = document.layers[name];
        if(this.obj)
            this.style = this.obj;
    }
}

function getObjArray(name)
{
    return document.getElementsByName(name);
}

// -------------------------------------------------------------------------------------------------------------------

function changeImage(id,img)
{
    if(document.getElementById(id)) {
        document.getElementById(id).src = img;
    }

}

// -------------------------------------------------------------------------------------------------------------------

var pageloaded = false;

function init()
{
    pageloaded = true;
}

// -------------------------------------------------------------------------------------------------------------------

function makeWinObj(name,url,posx,posy,width,height,extra)
{
    if (extra == 'toolbar') extra = 'scrollbars=yes,toolbar=yes';
    else if (extra == 'empty') extra = 'scrollbars=no,toolbar=no';
    else extra = 'scrollbars=yes,toolbar=no' + extra;

    this.name=name;
    this.url=url;
    this.obj=window.open(url,name,'left='+posx+',top='+posy+',width='+width+',height='+height+', ' + extra);

    // alert("x: "+posx+" | posy: "+posy);

    // this.obj.moveTo(posx,posy);
    this.obj.focus();

    return this;
}

function closeAll()
{
    for( var i=0;i<=winObjCounter;i++)
    {
        if(winObj[i]) winObj[i].obj.close();
    }
}

function newWindow(name,link,width,height,type)
{
    if (width==0) width=550;
    if (height==0) height=400;

    if (type == 'scrollbars')
    {
        extra = 'toolbar';
    }else if (type == 'empty')
    {
        extra = 'empty';
    }else
    {
        extra = type;
    }

    if (type=="nav")
    {
        posx = parseInt(screen.width/2)-390;
        posy = parseInt(screen.height/2) - 24 - 290;
        width= 320;
        height=580;
    }else if (type=="content")
    {
        posx = parseInt(screen.width/2) - 390 + 330;
        posy = parseInt(screen.height/2) - 24 - 290;
        width= 470;
        height=580;
    }else
    {
        posx = parseInt((screen.width-width)/2);
        posy = parseInt((screen.height-height)/2) - 24;
    }

    winObjCounter++;
    winObj[winObjCounter] = new makeWinObj(name,link,posx,posy,width,height,extra);

    if (rex.popupEvents && rex.popupEvents[name]) {
        rex.popupEvents[name] = {};
    }

    return winObj[winObjCounter].obj;
}

var winObj = new Array();
if (opener != null)
{
    try{
        if (typeof(opener.winObjCounter) == "number")
        {
            var winObjCounter = opener.winObjCounter;
        }
    } catch(e) {
        // in x-origin cases opener.winObjCounter would not be readable
        var winObjCounter = -1;
    }
}else
{
    var winObjCounter = -1;
}

function rex_retain_popup_event_handlers(eventName) {
    if (!opener || !opener.rex || !window.name) {
        return;
    }

    var events = opener.rex.popupEvents || {};

    if (events[window.name] && events[window.name][eventName]) {
        $.each(events[window.name][eventName], function (i, event) {
            opener.jQuery(window).on(eventName, event);
        });

        return;
    }

    var events = opener.jQuery._data(window, 'events');

    if (!events || !events[eventName]) {
        return;
    }

    var handlers = [];
    $.each(events[eventName], function (i, event) {
        handlers.push(event.handler);
    });

    if (!opener.rex.popupEvents) {
        opener.rex.popupEvents = {};
    }
    if (!opener.rex.popupEvents[window.name]) {
        opener.rex.popupEvents[window.name] = {};
    }

    opener.rex.popupEvents[window.name][eventName] = handlers;
}

function setValue(id,value)
{
    var field = new getObj(id);
    field.obj.value = value;
}

function deleteREX(id, i_list, i_select)
{
    var medialist = i_select+id;
    var needle = new getObj(medialist);
    var source = needle.obj;
    var sourcelength = source.options.length;
    var position = null;

    for (ii = 0; ii < sourcelength; ii++) {
        if (source.options[ii].selected) {
            position = ii;
            break;
        }
    }

    if(position != null)
    {
        source.options[position] = null;
        sourcelength--;

        // Wenn das erste geloescht wurde
        if(position == 0)
        {
            // Und es gibt noch weitere,
            // -> selektiere das "neue" erste
            if(sourcelength > 0)
                source.options[0].selected = "selected";
        }
        else
        {
            // -> selektiere das neue an der stelle >position<
            if(sourcelength > position)
                source.options[position].selected= "selected";
            else
                source.options[position-1].selected= "selected";
        }
        writeREX(id, i_list, i_select);
    }
}

function moveREX(id, i_list, i_select, direction)
{
    var medialist = i_select+id;
    var needle = new getObj(medialist);
    var source = needle.obj;
    var sourcelength = source.options.length;
    var elements = new Array();
    var was_selected = new Array();

    for (ii = 0; ii < sourcelength; ii++) {

        elements[ii] = new Array();
        elements[ii]['value'] = source.options[ii].value;
        elements[ii]['title'] = source.options[ii].text;
        was_selected[ii] = false;

    }

    var inserted = 0;
    var was_moved = new Array();
    was_moved[-1] = true;
    was_moved[sourcelength] = true;

    if (direction == 'top') {
        for (ii = 0; ii < sourcelength; ii++) {
            if (source.options[ii].selected) {
                elements = moveItem(elements, ii, inserted);
                was_selected[inserted] = true;
                inserted++;
            }
        }
    }

    if (direction == 'up') {
        for (ii = 0; ii < sourcelength; ii++) {
            was_moved[ii] = false;
            if (source.options[ii].selected) {
                to = ii-1;
                if (was_moved[to]) {
                    to = ii;
                }
                elements = moveItem(elements, ii, to);
                was_selected[to] = true;
                was_moved[to] = true;
            }
        }
    }

    if (direction == 'down') {
        for (ii = sourcelength-1; ii >= 0; ii--) {
            was_moved[ii] = false;
            if (source.options[ii].selected) {
                to = ii+1;
                if (was_moved[to]) {
                    to = ii;
                }
                elements = moveItem(elements, ii, to);
                was_selected[to] = true;
                was_moved[to] = true;
            }
        }
    }

    if (direction == 'bottom') {
        inserted = 0;
        for (ii = sourcelength-1; ii >= 0; ii--) {
            if (source.options[ii].selected) {
                to = sourcelength - inserted-1;
                if (to > sourcelength) {
                    to = sourcelength;
                }

                elements = moveItem(elements, ii, to);
                was_selected[to] = true;
                inserted++;
            }
        }
    }

    for (ii = 0; ii < sourcelength; ii++) {
        source.options[ii] = new Option(elements[ii]['title'], elements[ii]['value']);
        source.options[ii].selected = was_selected[ii];
    }
    writeREX(id, i_list, i_select);
}

function writeREX(id, i_list, i_select)
{

    var v_list = i_list+id;
    var v_select = i_select+id;
    var source = document.getElementById(v_select);
    var sourcelength = source.options.length;
    var target = document.getElementById(v_list);

    target.value = "";

    for (i=0; i < sourcelength; i++) {
        target.value += (source[i].value);
        if (sourcelength > (i+1))  target.value += ',';
    }
}

function moveItem(arr, from, to)
{
    if (from == to || to < 0)
    {
        return arr;
    }

    tmp = arr[from];
    if (from > to)
    {
        for (index = from; index > to; index--) {
            arr[index] = arr[index-1];
        }
    } else {
        for (index = from; index < to; index++) {
            arr[index] = arr[index+1];
        }
    }
    arr[to] = tmp;
    return arr;
}

// Checkbox mit der ID <id> anhaken
function checkInput(id)
{
    if(id)
    {
        var result = new getObj(id);
        var input = result.obj;
        if(input != null)
        {
            input.checked = 'checked';
        }
    }
}

// Inputfield (Checkbox/Radio) mit der ID <id> Haken entfernen
function uncheckInput(id)
{
    if(id)
    {
        var result = new getObj(id);
        var input = result.obj;
        if(input != null)
        {
            input.checked = '';
        }
    }
}

// Wenn der 2. Parameter angegeben wird, wird die style.display Eigenschaft auf den entsprechenden wert gesetzt,
// Sonst wird der wert getoggled
function toggleElement(id,display)
{
    var needle;

    if(typeof(id) != 'object')
    {
        needle = new getObj(id);
    }
    else
    {
        needle = id;
    }

    if (typeof(display) == 'undefined')
    {
        display = needle.style.display == '' ? 'none' : '';
    }

    needle.style.display = display;
    return display;
}


jQuery(function($){
    // ------------------ Accesskey Navigation
    $(document).keypress(function(event) {
        // return true if !rex.accesskeys or key is not 0-9 or a-z
        // keycodes: 48 => '0', 57 => '9', 97 => 'a', 122 => 'z'
        if (!rex.accesskeys || event.which < 48 || (event.which > 57 && event.which < 97) || event.which > 122) {
            return true;
        }

        var key = String.fromCharCode(event.which);
        var haystack = $("input[accesskey='"+ key +"'], button[accesskey='"+ key +"']");

        if(haystack.length > 0)
        {
            $(haystack.get(0)).click();
            return false;
        }
        else
        {
            haystack = $("a[accesskey='"+ key +"']");

            if(haystack.length > 0)
            {
                var hit = $(haystack.get(0));
                if(hit.attr("onclick") != undefined)
                    hit.click();
                else if(hit.attr("href") != undefined && hit.attr("href") != "#")
                    document.location = hit.attr("href");

                return false;
            }
        }
    });

    var laststate;
    $("body")
        .on("focus", "input,button,textarea,select,option,[contenteditable=true]", function(event) {
            laststate = rex.accesskeys;
            rex.accesskeys = false;
        })
        .on("blur", "input,button,textarea,select,option,[contenteditable=true]", function(event) {
            rex.accesskeys = laststate;
        });
    $("[autofocus]").trigger("focus");

    if (false !== rex.directory_protection_check && $('#rex-page-setup, #rex-page-login').length == 0 && getCookie('rex_directory_protection_check') != '1')
    {
        // urls which are not expected to be accessible
        var urls = [
            'bin/console',
            'data/.redaxo',
            'src/core/boot.php',
            'cache/.redaxo'
        ];

        // Only one directory is checked per day. All of them are protected the same way, so a
        // setup where that protection is missing (e.g. nginx or "AllowOverride None") shows up on
        // any of them. Checking a single directory keeps the number of denied requests low enough
        // to not trip tools like fail2ban, which ban a client after a few denied requests.
        var previousInsecureUrl = getCookie('rex_directory_protection_check');
        var url = previousInsecureUrl !== '' ? previousInsecureUrl : urls[Math.floor(Math.random() * urls.length)];

        var setCheckCookie = function (value) {
            time = new Date();
            time.setTime(time.getTime() + 1000 * 60 * 60 * 24);

            setCookie(
                'rex_directory_protection_check',
                value,
                time.toGMTString(),
                rex.cookie_params.path,
                rex.cookie_params.domain,
                rex.cookie_params.secure,
                rex.cookie_params.samesite.toLowerCase()
            );
        };

        setCheckCookie('1');

        // NOTE: the setup process runs a similar check, see setup.step2.php
        $.ajax({
            // add a human readable suffix so people get an idea what we are doing here
            url: url + '?redaxo-security-self-test',
            cache: false,
            success: function (data) {
                $('#rex-js-page-main').prepend('<div class="alert alert-danger" style="margin-top: 20px;">The directory <code>redaxo/' + url + '</code> is insecure. Make sure this directory is not publicly accessible.</div>');

                // keep checking this directory, so the warning stays visible. its request succeeds
                // anyway, so it does not produce denied requests.
                setCheckCookie(url);
            }
        });
    }
});


// cookie functions

function setCookie(name, value, expires, path, domain, secure, samesite) {
    if (typeof expires != undefined && expires == "never") {
        // never expire means expires in 3000 days
        expires = new Date();
        expires.setTime(expires.getTime() + (1000 * 60 * 60 * 24 * 3000));
        expires = expires.toGMTString();
    }

    document.cookie = name + "=" + escape(value)
        + ((expires) ? "; expires=" + expires : "")
        + ((path) ? "; path=" + path : "")
        + ((domain) ? "; domain=" + domain : "")
        + ((secure) ? "; secure" : "")
        + ((samesite) ? "; samesite=" + samesite : "");
}

function getCookie(cookieName) {
    var theCookie = "" + document.cookie;
    var ind = theCookie.indexOf(cookieName);
    if (ind == -1 || cookieName == "")
        return "";

    var ind1 = theCookie.indexOf(';', ind);
    if (ind1 == -1)
        ind1 = theCookie.length;

    return unescape(theCookie.substring(ind + cookieName.length + 1, ind1));
}

// scroll to anchor element
// (scrollIntoView honors the CSS scroll-padding-top, so the sticky navbar
// offset is handled automatically — no manual correction needed)
function scrollToAnchor() {
    if (!window.location.hash) {
        return;
    }
    var anchorItem = document.querySelector(window.location.hash);
    if (!anchorItem) {
        return;
    }

    var scrollToItem = function () {
        anchorItem.scrollIntoView({block: 'start'});
    };

    // Defer to the next frame so the freshly swapped-in pjax content is laid
    // out before we scroll, instead of guessing a fixed timeout.
    requestAnimationFrame(function () {
        requestAnimationFrame(scrollToItem);
    });

    // Images that are still loading will shift the layout once they arrive and
    // push the anchor out of position. Re-scroll whenever one finishes, but
    // stop as soon as the user scrolls manually so we don't hijack the page.
    var container = anchorItem.closest('[data-pjax-container]') || document;
    var pending = Array.prototype.filter.call(container.querySelectorAll('img'), function (img) {
        return !img.complete;
    });
    if (!pending.length) {
        return;
    }

    var remaining = pending.length;
    var stop = function () {
        window.removeEventListener('wheel', stop);
        window.removeEventListener('touchstart', stop);
        pending.forEach(function (img) {
            img.removeEventListener('load', onImageDone);
            img.removeEventListener('error', onImageDone);
        });
    };
    var onImageDone = function () {
        scrollToItem();
        if (--remaining === 0) {
            stop();
        }
    };

    window.addEventListener('wheel', stop, {passive: true});
    window.addEventListener('touchstart', stop, {passive: true});
    pending.forEach(function (img) {
        img.addEventListener('load', onImageDone);
        img.addEventListener('error', onImageDone);
    });
}

var rex_loader = {
    timeoutId: null,

    show: function() {
        document.documentElement.style.overflowY = 'hidden'; // freeze scroll position
        document.querySelector('#rex-js-ajax-loader').classList.add('rex-visible');
    },
    hide: function() {
        document.querySelector('#rex-js-ajax-loader').classList.remove('rex-visible');
        document.documentElement.style.overflowY = null;
    },

    showAfterDelay: function() {
        // show loader only if page takes longer than 200 ms to load
        window.clearTimeout(rex_loader.timeoutId);

        rex_loader.timeoutId = setTimeout(function () {
            rex_loader.show();
        }, 200);
    },
    hideAfterDelay: function() {
        // make sure loader was visible for at least 500 ms to avoid flickering
        window.clearTimeout(rex_loader.timeoutId);

        rex_loader.timeoutId = setTimeout(function () {
            rex_loader.hide();
        }, 500);
    }
};

jQuery(document).ready(function($) {

    if (!("autofocus" in document.createElement("input"))) {
        $(".rex-js-autofocus").focus();
    }

    confDialog = function(event) {
        if(!confirm($(this).attr('data-confirm'))) {
            event.stopImmediatePropagation();
            return false;
        }
    };

    // confirm dialog behavior for links and buttons
    $(document).on('click', 'a[data-confirm], button[data-confirm], input[data-confirm]', confDialog);
    // confirm dialog behavior for forms
    $(document).on('submit', 'form[data-confirm]', confDialog);

    // add eye-toggle to each password input
    $(document).on('rex:ready', function (event, viewRoot) {
        $(viewRoot).find('input[type="password"]').each(function() {
            var $el = $(this);
            var $eye = jQuery('<i class="rex-icon rex-icon-view" aria-hidden="true"></i>');

            if ($el.parent("div.input-group").length == 0) {
                $el.wrap('<div class="input-group"></div>');
            }

            // insert into DOM first, as wrap() only works on DOM attached nodes.
            $el.after($eye);
            $eye
                .wrap('<span class="input-group-btn"></span>')
                .wrap('<button type="button" class="btn btn-view" tabindex="-1"></button>');

            $el.next('span.input-group-btn').find('button.btn').click(function(event) {
                $eye.toggleClass("rex-icon-view rex-icon-hide");

                if ($el.attr("type") === "password") {
                    $el.attr("type", "text");
                } else {
                    $el.attr("type", "password");
                }

                $el.val($el.val()); // necessary for passwords generated by Safari

                event.stopPropagation();
                event.preventDefault();
            });
        });
    });

    if ($.support.pjax) {
        $.pjax.defaults.timeout = 10000;
        $.pjax.defaults.maxCacheLength = 0;

        var pjaxHandler = function(event) {
            var self = $(this), container;

            if(event.isDefaultPrevented()) {
                return;
            }
            var isForm = self.is('form');
            var regex = new RegExp('\\bpage=' + rex.page + '(\\b[^\/]|$)');
            if (isForm) {
                if (self.attr('method') == 'get') {
                    if (self.find('input[name="page"][value="' + rex.page + '"]').length == 0) {
                        return;
                    }
                } else if (!regex.test(self.attr('action'))) {
                    return;
                }
            } else if (!regex.test(self.attr('href'))) {
                return;
            }

            if (self.is('[download]')) {
                return;
            }
            if (self.is('[data-pjax]')) {
                container = self.attr('data-pjax');
            }
            if ('false' === container) {
                return;
            }
            if (!container || 'true' === container) {
                container = self.closest('[data-pjax-container]').attr('data-pjax-container');
            }
            if (!container) {
                container = '#rex-page-main';
            }

            var options = {container: container, fragment: container};

            options.push = !self.closest('[data-pjax-no-history]').data('pjax-no-history');

            options.scrollTo = self.closest('[data-pjax-scroll-to]').data('pjax-scroll-to');
            if (typeof options.scrollTo == 'undefined') {
                options.scrollTo = isForm ? 0 : false;
            }

            if (isForm) {
                var clicked = self.find(':submit[data-clicked]');
                if (clicked.length) {
                    // https://github.com/defunkt/jquery-pjax/issues/304
                    self.append('<input type="hidden" name="' + clicked.attr('name') + '" value="' + clicked.val() + '"/>');
                }
                return $.pjax.submit(event, options);
            }
            return $.pjax.click(event, options);
        };

        var rexAjaxLoaderId;
        $(document)
            // install pjax handlers, see defunkt/jquery-pjax#142
            .on('click', '[data-pjax-container] a, a[data-pjax]', pjaxHandler)
            .on('submit', '[data-pjax-container] form, form[data-pjax]', pjaxHandler)
            .on('click', '[data-pjax-container] form :submit, form[data-pjax] :submit', function() {
                $(this).attr('data-clicked', 1);
            })
            // add pjax error handling
            .on('pjax:error', function(e, xhr, err) {
                // user not authorized -> redirect to login page
                if (xhr.status === 401) {
                    window.location = 'index.php?page=login';
                    return false;
                }
                if (xhr.status === 500) {
                    var newDoc = document.open("text/html", "replace");
                    newDoc.write(xhr.responseText);
                    newDoc.close();
                    return false;
                }
            })
            /*.on('pjax:success', function(e, data, status, xhr, options) {
             })*/
            .on('pjax:start', function () {
                rex_loader.showAfterDelay();
            })
            .on('pjax:end',   function (event, xhr, options) {
                // adjust scroll position for anchors depending on scroll-margin-top value
                scrollToAnchor();

                rex_loader.hideAfterDelay();

                options.context.trigger('rex:ready', [options.context]);
            });
    }

    $('body').trigger('rex:ready', [$('body')]);

    /*
     * Replace all SVG images with inline SVG
     */
    $('img.rex-js-svg').each(function(){
        var $img = jQuery(this);
        var imgID = $img.attr('id');
        var imgClass = $img.attr('class');
        var imgURL = $img.attr('src');

        jQuery.get(imgURL, function(data) {
            // Get the SVG tag, ignore the rest
            var $svg = jQuery(data).find('svg');

            // Add replaced image's ID to the new SVG
            if(typeof imgID !== 'undefined') {
                $svg = $svg.attr('id', imgID);
            }
            // Add replaced image's classes to the new SVG
            if(typeof imgClass !== 'undefined') {
                $svg = $svg.attr('class', imgClass+' replaced-svg');
            }

            // Remove any invalid XML tags as per https://validator.w3.org
            $svg = $svg.removeAttr('xmlns:a');

            // Replace image with new SVG
            $img.replaceWith($svg);

        }, 'xml');

    });

    $(document).on('show.bs.dropdown', '.dropdown', function () {
        var windowHeight = $(window).height();
        var rect = this.getBoundingClientRect();
        if (rect.top > windowHeight) return;
        var menuHeight = $(this).children('.dropdown-menu').height();
        $(this).toggleClass('dropup',
            ((windowHeight - rect.bottom) < menuHeight) &&
            (rect.top > menuHeight));
    });

    document.addEventListener('keydown', handleKeyEvents, true);
});

// handle key events
var handleKeyEvents = function (event) {

    // submit forms via strg/cmd + enter
    if (event.metaKey && event.keyCode === 13) {
        var form = event.target.closest('form');
        if (form) {
            // click apply button if available (e.g. when editing content)
            var applyButton = form.querySelector('.btn-apply');
            if (applyButton) {
                applyButton.click();
            } else {
                // click (first) submit button
                var submitButton = form.querySelector('[type=\'submit\']');
                if (submitButton) {
                    submitButton.click();
                }
            }
        }
    }
}

/**
 * @param {string} selector_id
 */
function rex_searchfield_init(selector_id) {

    $(selector_id).find('input[type="text"]').on('input propertychange', function () {
        var $this = $(this);
        var visible = Boolean($this.val());
        $this.siblings('.form-control-clear').toggleClass('hidden', !visible);
    }).trigger('propertychange');

    $(selector_id).find('.form-control-clear, .clear-button').click(function (event) {
        event.stopPropagation();
        $(this).siblings('input[type="text"]').val('').trigger("keyup")
            .trigger('propertychange').focus();
    });
}
