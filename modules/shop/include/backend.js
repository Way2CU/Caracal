/**
 * Shop backend helper functions
 *
 * Copyright (c) 2012. by Way2Cu
 * Author: Mladen Mijatov
 */

var Caracal = Caracal || {};
Caracal.Shop = Caracal.Shop || {};


/**
 * Show search results window.
 */
Caracal.Shop.open_item_search = function() {
	var value = $('input[name=search_query]').val();
	var data = {
			section: 'backend_module',
			action: 'transfer_control',
			module: 'shop',
			backend_action: 'items',
			sub_action: 'search_results',
			query: value
		};
	var url = backend_url + '?' + $.param(data);

	window_system.openWindow(
					'shop_search_results',
					450,
					language_handler.getText('shop', 'title_search_results'),
					true, url
				);
}

/**
 * Remove related item from the list.
 *
 * @param object caller
 */
Caracal.Shop.remove_related_item = function(caller) {
	$(caller).closest('div.list_item').remove();
}

/**
 * Add color list item.
 *
 * @param string color_name
 * @param string color_value
 */
Caracal.Shop.add_color_item = function(color_name, color_value) {
	var container = $('div#color_list');
	var item = $('<div>');
	var span_column_name = $('<span>');
	var span_column_value = $('<span>');
	var span_options = $('<span>');
	var span_preview = $('<span>');
	var button_remove = $('<a>');

	span_column_name
		.addClass('column')
		.css('width', 250)
		.html(color_name)
		.appendTo(item);

	span_column_value
		.addClass('column')
		.css('width', 100)
		.css('direction', 'ltr')
		.html(color_value)
		.append(span_preview)
		.appendTo(item);

	span_options
		.addClass('column')
		.html(button_remove)
		.appendTo(item);

	button_remove
		.html(language_handler.getText(null, 'delete'))
		.click(Caracal.Shop.delete_color);

	span_preview.css({
			backgroundColor: color_value,
			display: 'block',
			width: 30,
			height: 13,
			float: 'right'
		});

	item
		.addClass('list_item')
		.data('name', color_name)
		.data('value', color_value)
		.appendTo(container);
}

/**
 * Handle adding item color.
 */
Caracal.Shop.add_color = function() {
	var colors = $('input[name=colors]');
	var color_name = $('input[name=color_name]');
	var color_value = $('input[name=color_value]');

	// append color to list
	if (colors.val() != '')
		colors.val(colors.val() + ',' + color_name.val() + ':' + color_value.val()); else
		colors.val(color_name.val() + ':' + color_value.val());

	// add new item to the list
	Caracal.Shop.add_color_item(color_name.val(), color_value.val())

	// reset color input fields
	color_name.val('');
	color_value.val('#FFFFFF');
}

/**
 * Handle clicking on remove button for color.
 *
 * @param object event
 */
Caracal.Shop.delete_color = function(event) {
	var item = $(this);
	var parent = item.closest('div.list_item');
	var colors = $('input[name=colors]');

	// repack colors
	var color_values = colors.val().split(',');
	var new_values = [];
	var excluded_value = parent.data('name') + ':' + parent.data('value');

	for (var i=0; i<color_values.length; i++) {
		if (color_values[i] != excluded_value)
			new_values.push(color_values[i]);
	}

	colors.val(new_values.join(','));

	// remove list item
	parent.remove();

	event.preventDefault();
}

/**
 * Parse colors string and populate list.
 */
Caracal.Shop.parse_colors = function() {
	var color_string = $('input[name=colors]').val();
	var colors = color_string.split(',');

	if (color_string == '')
		return;

	for (var i=0; i<colors.length; i++) {
		var data = colors[i].split(':');
		Caracal.Shop.add_color_item(data[0], data[1]);
	}
}

/**
 * Update transaction status.
 *
 * @param object button
 */
Caracal.Shop.update_transaction_status = function(button) {
	var backend_window = $(button).closest('.window');
	var select = backend_window.find('select[name=status]').eq(0);
	var transaction_status = select.val();
	var transaction_id = backend_window.find('input[name=uid]').eq(0).val();
	var update_button = $(button);

	var data = {
		section: 'backend_module',
		action: 'transfer_control',
		module: 'shop',
		backend_action: 'transactions',
		sub_action: 'json_update_status',
		id: transaction_id,
		status: transaction_status
	};

	// disable button and select
	update_button.attr('disabled', 'disabled');
	select.attr('disabled', 'disabled');

	// send data to server
	$.ajax({
		url: $('base').attr('href') + '/index.php',
		cache: false,
		dataType: 'json',
		type: 'POST',
		data: data,
		async: false,
		success: function(result) {
			// enable button and select
			update_button.removeAttr('disabled');
			select.removeAttr('disabled');
		}
	});
}

/**
 * Update handling and total transaction amount.
 *
 * @param object button
 */
Caracal.Shop.update_total_amount = function(button) {
	var backend_window = $(button).closest('.window');
	var handling = backend_window.find('input[name=handling]').eq(0);
	var total = backend_window.find('input[name=total]').eq(0);
	var transaction_id = backend_window.find('input[name=uid]').eq(0).val();
	var update_button = $(button);

	var handling_amount = handling.val() || 0;
	var total_amount = total.val() || 0;

	var data = {
		section: 'backend_module',
		action: 'transfer_control',
		module: 'shop',
		backend_action: 'transactions',
		sub_action: 'json_update_total',
		id: transaction_id,
		handling: handling_amount,
		total: total_amount
	};

	// disable button and select
	update_button.attr('disabled', 'disabled');

	// send data to server
	$.ajax({
		url: $('base').attr('href') + '/index.php',
		cache: false,
		dataType: 'json',
		type: 'POST',
		data: data,
		async: false,
		success: function(result) {
			// enable button and select
			update_button.removeAttr('disabled');
		}
	});
};
