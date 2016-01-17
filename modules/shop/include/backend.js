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

	Caracal.window_system.openWindow(
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

/**
 * Handle changing filter in transaction lists.
 */
Caracal.Shop.handle_filter_change = function() {
	var transactions_window = Caracal.window_system.getWindow('shop_transactions');
	var selected_status = Caracal.window_system.container.find('select[name=status]');

	// store original URL for later use
	if (transactions_window.original_url == undefined)
		transactions_window.original_url = transactions_window.url;

	// prepare filter data
	var data = {
			'status':	selected_status.val()
		};
	var query = $.param(data);

	// reload content
	transactions_window.loadContent(transactions_window.original_url + '&' + query);
};

/**
 * Show print dialog for selected transaction.
 *
 * @param object button
 */
Caracal.Shop.print_transaction = function(button) {
	var backend_window = $(button).closest('.window');

	// get print iframe
	var iframe = backend_window.find('iframe.print');
	if (iframe.length == 0) {
		var iframe = $('<iframe>');

		iframe
			.addClass('print')
			.css('display', 'none')
			.appendTo(backend_window)
			.on('load', function(event) {
				iframe[0].contentWindow.print();
			});
	}

	// show print dialog
	iframe.attr('src', $(button).data('print-url') + '&timestamp=' + Date.now().toString());
};

/**
 * Commit entered data to the property list.
 *
 * @param object button
 */
Caracal.Shop.add_property = function(button) {
	var current_window = $(button).closest('.window');
	var property_list = current_window.find('#item_properties');

	// find input fields
	var input_name = current_window.find('input[name=property_name]');
	var input_text_id = current_window.find('input[name=property_text_id]');
	var input_type = current_window.find('select[name=property_type]');

	// find property id
	var properties = property_list.find('input[name^=property_data_]');
	var property_id = properties.length;  // initially get just the number

	var existing_property = properties.filter('input[name=property_data_' + property_id + ']');
	while (existing_property.length > 0) {
		property_id++;
		existing_property = properties.filter('input[name=property_data_' + property_id + ']');
	}

	// collect data
	var languages = language_handler.getLanguages();
	var prepared_value = '';

	switch (input_type.val()) {
		case 'number':
		case 'decimal':
		case 'text':
			var input_value = current_window.find('input[name=property_' + input_type.val() + ']');
			prepared_value = input_value.val();
			break;

		case 'ml_text':
			var input_value = current_window.find('input[name=property_ml_text]');
			var language_data = input_value.data('language');

			prepared_value = {};
			for (var language in languages)
				prepared_value = language_data[language] || '';

			break;

		case 'array':
			var input_value = current_window.find('input[name=property_array]');
			prepared_value = JSON.parse(input_value.val());
			break;

		case 'ml_array':
			var input_value = current_window.find('input[name=property_ml_array]');
			var language_data = input_value.data('language');

			prepared_value = {};
			for (var language in languages)
				prepared_value[language] = JSON.parse(language_data[language]) || '';

			break;
	}

	var data = {
			text_id: input_text_id.val(),
			name: input_name.data('language'),
			type: input_type.val(),
			value: prepared_value
		};

	// create and configure item property row
	var row = $('<div>');
	row
		.addClass('list_item')
		.appendTo(property_list);

	var data_field = $('<input>');
	data_field
		.attr('type', 'hidden')
		.attr('name', 'property_data_' + property_id)
		.val(JSON.stringify(data))
		.appendTo(row);

	// create columns
	var column_name = $('<span class="column">');
	var column_type = $('<span class="column">');
	var column_options = $('<span class="column">');

	column_name
		.html(data.name[language_handler.current_language])
		.attr('style', 'width: 250px')
		.appendTo(row);

	column_type
		.html(data.type)
		.attr('style', 'width: 60px')
		.appendTo(row);

	column_options.appendTo(row);

	// create options
	var option_change = $('<a>');
	var option_remove = $('<a>');

	option_change
		.on('click', Caracal.Shop.edit_property)
		.appendTo(column_options);

	option_remove
		.on('click', Caracal.Shop.delete_property)
		.appendTo(column_options);

	// load language constants for options
	language_handler.getTextArrayAsync(null, ['change', 'delete'], function(data) {
			option_change.html(data['change']);
			option_remove.html(data['delete']);
		});

	// clear input fields
	current_window
		.find('input[name^=property_]').not('[name^=property_data_]')
		.val('')
		.data('language', {})
		.data('original_data', {})
		.trigger('change');
};

/**
 * Save changed property data to the property list.
 */
Caracal.Shop.save_property = function(button) {
};

/**
 * Open item property from the list for editing.
 */
Caracal.Shop.edit_property = function(event) {
	if (event instanceof Event || event instanceof jQuery.Event)
		var row = $(this).closest('.list_item'); else
		var row = $(event).closest('.list_item');
	var current_window = row.closest('.window');
	var selector = current_window.find('.language_selector').data('selector');
	var property_list = current_window.find('#item_properties');

	// find input fields
	var input_name = current_window.find('input[name=property_name]');
	var input_text_id = current_window.find('input[name=property_text_id]');
	var input_type = current_window.find('select[name=property_type]');

	// get data
	var data_field = row.find('input[name^=property_data_]');
	var data = JSON.parse(data_field.val());

	// configure input elements
	input_text_id.val(data.text_id);
	input_type.val(data.type);

	input_name.data('language', data.name);
	input_name.data('original_data', data.name);

	// configure data
	switch(data.type) {
		case 'number':
		case 'decimal':
		case 'text':
			var input_value = current_window.find('input[name=property_' + input_type.val() + ']');
			input_value.val(data.value);
			break;

		case 'ml_text':
			var input_value = current_window.find('input[name=property_ml_text]');
			input_value
				.data('language', data.value)
				.data('original_data', data.value)
				.val(data.value[selector.current_language]);
			break;

		case 'array':
			var input_value = current_window.find('input[name=property_array]');
			input_value
				.val(JSON.stringify(data.value))
				.trigger('change');
			break;

		case 'ml_array':
			var input_value = current_window.find('input[name=property_ml_array]');
			input_value
				.data('language', data.value)
				.data('original_data', data.value)
				.val(data.value[selector.current_language])
				.trigger('change');
			break;
	}

	// set data for unused mutli-language fields
	var other_inputs = current_window.find('input[name=property_].multi-language').not(input_value);
	other_inputs
		.data('language', {})
		.data('original_data', {});
};
