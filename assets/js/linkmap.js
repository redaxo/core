/*
 REDAXO Linkmap JavaScript library
 */
function newLinkMapWindow(link)
{
    // 1200 = $screen-lg
    return newWindow( 'linkmappopup', link, 1200,800,',status=yes,resizable=yes');
}

function openLinkMap(id, param)
{
    if (typeof(id) == 'undefined')
    {
        id = '';
    }
    if (typeof(param) == 'undefined')
    {
        param = '';
    }
    return newLinkMapWindow('index.php?page=linkmap&opener_input_field=' + id + param);
}

function deleteREXLink(id)
{
    var link;
    link = new getObj("REX_LINK_"+id);
    link.obj.value = "";
    link = new getObj("REX_LINK_"+id+"_NAME");
    link.obj.value = "";
}



function openREXLinklist(id, param)
{
    var linklist = 'REX_LINKLIST_'+id;
    var linkselect = 'REX_LINKLIST_SELECT_'+id;
    var needle = new getObj(linkselect);
    var source = needle.obj;
    var sourcelength = source.options.length;

    if ( typeof(param) == 'undefined')
    {
        param = '';
    }

    for (var ii = 0; ii < sourcelength; ii++) {
        if (source.options[ii].selected) {
            param = '&action=link_details&file_name='+ source.options[ii].value;
            break;
        }
    }

    return newLinkMapWindow('index.php?page=linkmap&opener_input_field='+linklist+param);
}

function deleteREXLinklist(id){
    deleteREX(id, 'REX_LINKLIST_', 'REX_LINKLIST_SELECT_');
}

function moveREXLinklist(id, direction){
    moveREX(id, 'REX_LINKLIST_', 'REX_LINKLIST_SELECT_', direction);
}

function writeREXLinklist(id){
    writeREX(id, 'REX_LINKLIST_', 'REX_LINKLIST_SELECT_');
}
