/**
 * Contact From Backend JavaScript
 *
 * Copyright (c) 2014. by Way2CU, http://way2cu.com
 * Authors: Mladen Mijatov
 */

function submissions_update_result_list() {
	var list = $('select#submission_form_list');
	var submissions_window = window_system.getWindow('contact_form_submissions');

	if (submissions_window.original_url == undefined)
		submissions_window.original_url = submissions_window.url;

	submissions_window.loadContent(submissions_window.original_url + '&form=' + list.val());
}
