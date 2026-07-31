/** deprecated */
function openREXMedia(id,param)
{
    console.log("openREXMedia deprecated, use var_media markup instead");
    var mediaid = 'REX_MEDIA_'+id;
    if (typeof(param) == 'undefined')
    {
        param = '';
    }
    return newPoolWindow('index.php?page=mediapool/media' + param + '&opener_input_field=' + mediaid);
}

/** deprecated */
function viewREXMedia(id,param)
{
    console.log("deleteREXMedia deprecated, use var_media markup instead");
    var mediaid = 'REX_MEDIA_'+id;
    var value = document.getElementById(mediaid).value;
    if ( typeof(param) == 'undefined')
    {
        param = '';
    }
    if (value != '') {
        param = param + '&file_name='+ value;
        return newPoolWindow('index.php?page=mediapool/media' + param + '&opener_input_field=' + mediaid);
    }
}

/** deprecated */
function deleteREXMedia(id)
{
    console.log("deleteREXMedia deprecated, use var_media markup instead");
    var input = new getObj("REX_MEDIA_" + id).obj;
    if (input !== null) {
        input.value = "";
        jQuery(input).trigger('change');
    } else {
        console.log("Media input field not found");
    }
}

/** deprecated */
function addREXMedia(id,params)
{
    console.log("addREXMedia deprecated, use var_media markup instead");
    if (typeof(params) == 'undefined')
    {
        params = '';
    }
    return newPoolWindow('index.php?page=mediapool/upload&opener_input_field=REX_MEDIA_'+id+params);
}

/** deprecated */
/*
    selectMedia
    instead of
    <a class="btn btn-xs btn-select" onclick="selectMedia(\'' . $file_name . '\', \'' . rex_escape($title) . '\'); return false;">
    use this
    <a class="btn btn-xs btn-select rex-js-media-select"
        data-id="' . $media->getId() . '" data-file_name="'.rex_escape($media->getFileName()).'" data-title="' . rex_escape($media->getTitle()) . '"
        data-select_type="' . (str_starts_with($openerInputField, 'REX_MEDIALIST_') ? "multiple" : "single") . '">
 */

function selectMedia(filename, alt)
{
    console.log("selectMedia deprecated, use var_media markup instead");
    var event = opener.jQuery.Event("rex:selectMedia");
    opener.jQuery(window).trigger(event, [filename, alt]);
    if (!event.isDefaultPrevented()) {
        if (rex.mediapoolOpenerInputField) {
            var input = opener.document.getElementById(rex.mediapoolOpenerInputField);
            if (input !== null) {
                input.value = filename;
                opener.jQuery(input).trigger('change');
                self.close();
            } else {
                console.log("Media input field not found");
            }
        } else {
            self.close();
        }
    }
}


// selectMedialist

function selectMedialist(filename)
{
    if (rex.mediapoolOpenerInputField && 0 === rex.mediapoolOpenerInputField.indexOf('REX_MEDIALIST_')) {
        var openerId = rex.mediapoolOpenerInputField.slice('REX_MEDIALIST_'.length);
        var medialist = "REX_MEDIALIST_SELECT_" + openerId;

        var source = opener.document.getElementById(medialist);
        var sourcelength = source.options.length;

        option = opener.document.createElement("OPTION");
        option.text = filename;
        option.value = filename;

        source.options.add(option, sourcelength);
        opener.writeREXMedialist(openerId);
    }
}
