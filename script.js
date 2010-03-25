/*
 * diffpreview plugin
 */

addInitEvent(function() {
	var btn_changes = $('edbtn__changes');
	if(btn_changes)
		btn_changes.onclick = function(){ textChanged = false; }; 
});