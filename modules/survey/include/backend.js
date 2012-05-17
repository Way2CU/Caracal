/**
 * Survey Backend Functions
 */

function survey_update_result_list() {
	var list = $('select#survey_type_list');
	var survey_window = window_system.getWindow('survey_results');

	if (survey_window.original_url == undefined)
		survey_window.original_url = survey_window.url;

	survey_window.loadContent(survey_window.original_url + '&type=' + list.val());
}

