/*
 * diffpreview plugin
 */

jQuery(function() {
    jQuery('#edbtn__changes').click(
        function() {
            window.onbeforeunload = '';
            textChanged = false;
            window.keepDraft = true; // needed to keep draft on page unload
        }
    );
});