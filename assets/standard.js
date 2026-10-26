/*
 REDAXO JavaScript library
 */

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

function setValue(id,value)
{
    var field = new getObj(id);
    field.obj.value = value;
}

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
});

jQuery(document).ready(function($) {

    if (!("autofocus" in document.createElement("input"))) {
        $(".rex-js-autofocus").focus();
    }

    // hides the element the checkbox points to while it is checked, e.g. the item select next to an "all" checkbox
    $(document).on('change', 'input[data-rex-hides]', function () {
        $($(this).attr('data-rex-hides')).toggle(!this.checked);
    });
    $(document).on('rex:ready', function (event) {
        $(event.target).find('input[data-rex-hides]').each(function () {
            $($(this).attr('data-rex-hides')).toggle(!this.checked);
        });
    });

    // collection fields: add, remove and reorder items
    var updateCollection = function ($collection) {
        var max = parseInt($collection.attr('data-rex-collection-max'), 10);
        var count = $collection.children('.rex-form-collection-items').children().length;
        $collection.children('[data-rex-collection-add]').toggle(isNaN(max) || count < max);
    };
    $(document).on('click', '[data-rex-collection-add]', function () {
        var $collection = $(this).closest('[data-rex-collection]');
        var index = parseInt($collection.attr('data-rex-collection-next'), 10);
        var html = $collection.children('template').html().split($collection.attr('data-rex-collection-placeholder')).join(index);
        var $item = $(html);
        $collection.attr('data-rex-collection-next', index + 1);
        $collection.children('.rex-form-collection-items').append($item);
        updateCollection($collection);
        rex.ready($item[0]);
    });
    $(document).on('click', '[data-rex-collection-remove]', function () {
        var $collection = $(this).closest('[data-rex-collection]');
        $(this).closest('.rex-form-collection-item').remove();
        updateCollection($collection);
    });
    $(document).on('click', '[data-rex-collection-move]', function () {
        var $item = $(this).closest('.rex-form-collection-item');
        if ('up' === $(this).attr('data-rex-collection-move')) {
            $item.prev().before($item);
        } else {
            $item.next().after($item);
        }
    });
    $(document).on('rex:ready', function (event) {
        $(event.target).find('[data-rex-collection]').each(function () {
            updateCollection($(this));
        });
    });

    // add eye-toggle to each password input
    $(document).on('rex:ready', function (event) {
        $(event.target).find('input[type="password"]').each(function() {
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

    rex.ready(document.body);

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
