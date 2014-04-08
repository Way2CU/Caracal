/**
 * Leads Backend Functions
 */

function leads_update_result_list() {
	var list = $('select#leads_type_list');
	var leads_window = window_system.getWindow('leads_results');

	if (leads_window.original_url == undefined)
		leads_window.original_url = leads_window.url;

	leads_window.loadContent(leads_window.original_url + '&type=' + list.val());
}

