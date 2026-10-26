/*
 REDAXO Mediapool JavaScript library
 */

function setAllCheckBoxes(FieldName, mthis)
{
    for (const checkbox of (mthis.closest('form') ?? document).querySelectorAll(`[name="${CSS.escape(FieldName)}"]`)) {
        checkbox.checked = mthis.checked;
    }
}
