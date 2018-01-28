/**
 * Contact From Backend JavaScript
 *
 * Copyright (c) 2014. by Way2CU, http://way2cu.com
 * Authors: Mladen Mijatov
 */

var Caracal = Caracal || {};
var ContactForm = ContactForm || {};


function submissions_update_result_list() {
	var list = $('select#submission_form_list');
	var submissions_window = Caracal.window_system.get_window('contact_form_submissions');

	if (submissions_window.original_url == undefined)
		submissions_window.original_url = submissions_window.url;

	submissions_window.load_content(submissions_window.original_url + '&form=' + list.val());
}

/**
 * Generate hash code from string.
 *
 * @param string value
 * @return string
 */
ContactForm.hash_code = function(value) {
    var result = 0;

    if (value.length == 0)
    	return result;

    for (i = 0; i < value.length; i++) {
        var char = value.charCodeAt(i);
        result = ((result << 5) - result) + char;
        result = result & result;
    }

    return Math.abs(result);
};

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
	var field = $('<input>');
	var field_name = 'domain_' + ContactForm.hash_code(domain.val());

	// make sure domain is not already in the list
	if ($('input[name=' + field_name + ']').length > 0) {
		alert(language_handler.getText('contact_form', 'message_domain_already_in_list'));
		return;
	}

	field
		.attr('type', 'hidden')
		.attr('name', field_name)
		.attr('value', domain.val());

	remove
		.attr('href', 'javascript: void(0);')
		.click(ContactForm.remove_domain)
		.html(language_handler.getText(null, 'delete'))
		.appendTo(options);

	name
		.addClass('column')
		.css('width', 250)
		.html(domain.val())
		.append(field)
		.appendTo(item);

	options
		.addClass('column')
		.appendTo(item);

	item
		.addClass('list_item')
		.appendTo(list);

	// clear input field
	domain.val('');
};

/**
 * Remove list item from domain list.
 */
ContactForm.remove_domain = function() {
	var list_item = $(this).closest('.list_item');
	list_item.remove();
};
