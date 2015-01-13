/**
 * Contact From Backend JavaScript
 *
 * Copyright (c) 2014. by Way2CU, http://way2cu.com
 * Authors: Mladen Mijatov
 */

var ContactForm = ContactForm || {};

function submissions_update_result_list() {
	var list = $('select#submission_form_list');
	var submissions_window = window_system.getWindow('contact_form_submissions');

	if (submissions_window.original_url == undefined)
		submissions_window.original_url = submissions_window.url;

	submissions_window.loadContent(submissions_window.original_url + '&form=' + list.val());
}

/**
 * Add domain to contact form domain list.
 */
ContactForm.add_domain = function() {
	var list = $('div#contact_form_domain_list');
	var domain = $('#contact_form_add input[name=domain], #contact_form_edit input[name=domain]');

	// create new list item
	var item = $('<div>');
	var name = $('<div>');
	var options = $('<div>');
	var remove = $('<a>');

	remove
		.attr('href', 'javascript: ContactForm.remove_domain();')
		.appendTo(options);

	name
		.addClass('column')
		.css('width', 250)
		.html(domain.val())
		.appendTo(item);

	options
		.addClass('column')
		.appendTo(item);

	item
		.addClass('list_item')
		.appendTo(list);
};

/**
 * Remove list item from domain list.
 */
ContactForm.remove_domain = function() {
	var list_item = $(this).parent('.list_item');
	list_item.remove();
};
