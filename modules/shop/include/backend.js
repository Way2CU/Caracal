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
	var url = $('meta[property=base-url]').attr('content') + '?' + $.param(data);

	Caracal.window_system.open_window(
					'shop_search_results', 450,
					Caracal.language.get_text('shop', 'title_search_results'),
					url
				);
};

/**
 * Remove related item from the list.
 *
 * @param object caller
 */
Caracal.Shop.remove_related_item = function(caller) {
	$(caller).closest('div.list_item').remove();
};

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
		.html(Caracal.language.get_text(null, 'delete'))
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
};

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
	Caracal.Shop.add_color_item(color_name.val(), color_value.val());

	// reset color input fields
	color_name.val('');
	color_value.val('#FFFFFF');
};

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
};

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
};

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
		url: $('meta[property=base-url]').attr('content') + '/index.php',
		cache: false,
		dataType: 'json',
		method: 'POST',
		data: data,
		async: false,
		success: function(result) {
			// enable button and select
			update_button.removeAttr('disabled');
			select.removeAttr('disabled');
		}
	});
};

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
		url: $('meta[property=base-url]').attr('content') + '/index.php',
		cache: false,
		dataType: 'json',
		method: 'POST',
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
	var transactions_window = Caracal.window_system.get_window('shop_transactions');
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
	transactions_window.load_content(transactions_window.original_url + '&' + query);
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
Caracal.Shop.save_property = function(button) {
	var window_element = button.closest('.window');
	var window_id = window_element.getAttribute('id');
	var current_window = Caracal.window_system.get_window(window_id);
	if (!current_window)
		return;

	// find properties container
	var property_list = current_window.ui.content.querySelector('#item_properties');

	// find input fields
	var input_text_id = current_window.ui.content.querySelector('input[name=property_text_id]');
	var input_type = current_window.ui.content.querySelector('select[name=property_type]');

	// find property id by starting at number of properties
	// and increasing until property with new id is not found
	var properties = property_list.querySelectorAll('input[name^=property_data_]');
	var property_id = properties.length;

	var existing_property = property_list.querySelector('input[name=property_data_' + property_id + ']');
	while (existing_property) {
		property_id++;
		existing_property = property_list.querySelector('input[name=property_data_' + property_id + ']');
	}

	// prepare list of short language names
	var prepared_value = '';
	var languages = Caracal.language.get_languages();
	var languages_short = new Array();

	for (var index in languages)
		languages_short.push(languages[index].short);

	// collect data
	switch (input_type.value) {
		case 'number':
		case 'decimal':
		case 'text':
			var input_element_name = 'input[name=property_' + input_type.value + ']';
			var input_value = current_window.ui.content.querySelector(input_element_name);
			prepared_value = input_value.value;
			break;

		case 'ml_text':
			var language_data = current_window.ui.language_selector.get_values('property_ml_text');

			prepared_value = {};
			for (var index in languages_short) {
				var language = languages_short[index];
				prepared_value[language] = language_data[language] || '';
			}
			break;

		case 'array':
			var input_value = current_window.ui.content.querySelector('input[name=property_array]');
			prepared_value = JSON.parse(input_value.value);
			break;

		case 'ml_array':
			var language_data = current_window.ui.language_selector.get_values('property_ml_array');

			prepared_value = {};
			for (var index in languages_short) {
				var language = languages_short[index];
				prepared_value[language] = JSON.parse(language_data[language]) || '';
			}
			break;
	}

	var data = {
			text_id: input_text_id.value,
			name: current_window.ui.language_selector.get_values('property_name'),
			type: input_type.value,
			value: prepared_value
		};

	// create and configure item property row
	if (!current_window.editing_row) {
		// create storage field
		var data_field = document.createElement('input');
		data_field.type = 'hidden';
		data_field.name = 'property_data_' + property_id;
		data_field.value = JSON.stringify(data);

		// create columns
		var row = document.createElement('tr');

		var column_name = document.createElement('td');
		column_name.innerHTML = data.name[Caracal.language.current_language];
		column_name.append(data_field);

		var column_type = document.createElement('td');
		column_type.innerHTML = data.type;

		var column_options = document.createElement('td');
		column_options.classList.add('options');

		row.append(column_name);
		row.append(column_type);
		row.append(column_options);
		property_list.append(row);

		// create options
		var option_change = document.createElement('a');
		option_change.addEventListener('click', Caracal.Shop.edit_property);
		column_options.append(option_change);

		var option_remove = document.createElement('a');
		option_remove.addEventListener('click', Caracal.Shop.delete_property);
		column_options.append(option_remove);

		// load language constants for options
		Caracal.language.load_text_array(null, ['change', 'delete'], function(data) {
				option_change.innerHTML = data['change'];
				option_remove.innerHTML = data['delete'];
			});

	} else {
		// update existing field
		var row = current_window.editing_row;
		var data_field = row.querySelector('input[name^=property_data_]');
		data_field.value = JSON.stringify(data);

		// update column
		row.querySelectorAll('td')[0].innerHTML = data.name[Caracal.language.current_language];
	}

	// clear input fields
	var fields = current_window.ui.content.querySelectorAll('input[name^=property_]');
	for (var i=0, count=fields.length; i<count; i++) {
		var field = fields[i];
		if (field.name.substr(0, 14) == 'property_data_')
			continue;

		field.value = '';
		if (field.classList.contains('multi-language'))
			current_window.ui.language_selector.clear_values(field);

		// let the input field know data hase been reset
		var changed_event = document.createEvent('HTMLEvents');
		changed_event.initEvent('change', true, true);
		field.dispatchEvent(changed_event);
	}

	// show and hide buttons
	var button_add = current_window.ui.content.querySelector('button[name=add]');
	var button_reset = current_window.ui.content.querySelector('button[name=reset]');
	var button_cancel = current_window.ui.content.querySelector('button[name=cancel]');

	Caracal.language.load_text(null, 'add', function(constant, value) {
		button_add.innerHTML = value;
	});

	button_reset.style.display = 'inline-block';
	button_cancel.style.display = 'none';

	current_window.editing_row = null;
	input_type.disabled = false;
};

/**
 * Open item property from the list for editing.
 */
Caracal.Shop.edit_property = function(event) {
	if (event instanceof Event)
		var row = event.target.closest('tr'); else
		var row = event.closest('tr');

	var window_id = row.closest('.window').getAttribute('id');
	var current_window = Caracal.window_system.get_window(window_id);

	// store editing property
	current_window.editing_row = row;

	// find input fields
	var input_name = current_window.ui.content.querySelector('input[name=property_name]');
	var input_text_id = current_window.ui.content.querySelector('input[name=property_text_id]');
	var input_type = current_window.ui.content.querySelector('select[name=property_type]');

	// get data
	var data_field = row.querySelector('input[name^=property_data_]');
	var data = JSON.parse(data_field.value);

	if (!data)
		return;

	// configure input elements
	input_text_id.value = data.text_id;
	input_type.value = data.type;

	// let the input field know data hase been set
	var changed_event = document.createEvent('HTMLEvents');
	changed_event.initEvent('change', true, true);
	input_type.dispatchEvent(changed_event);

	// assign translations for each language
	current_window.ui.language_selector.set_values(input_name, data.name);

	// configure data
	switch(data.type) {
		case 'number':
		case 'decimal':
		case 'text':
			var input_element_name = 'input[name=property_' + input_type.value + ']';
			var input_value = current_window.ui.content.querySelector(input_element_name);
			input_value.value = data.value;
			break;

		case 'ml_text':
			var input_value = current_window.ui.content.querySelector('input[name=property_ml_text]');
			current_window.ui.language_selector.set_values(input_value, data.value)
			break;

		case 'array':
			var input_value = current_window.ui.content.querySelector('input[name=property_array]');
			input_value.value = JSON.stringify(data.value);

			var changed_event = document.createEvent('HTMLEvents');
			changed_event.initEvent('change', true, true);
			input_value.dispatchEvent(changed_event);
			break;

		case 'ml_array':
			var input_value = current_window.ui.content.querySelector('input[name=property_ml_array]');
			current_window.ui.language_selector.set_values(input_value, data.value)
			break;
	}

	// restore data for multi-language fields
	var input_name = current_window.ui.content.querySelector('input[name=property_name]');
	current_window.ui.language_selector.set_values(input_name, data.name);

	// show and hide buttons
	var button_add = current_window.ui.content.querySelector('button[name=add]');
	var button_reset = current_window.ui.content.querySelector('button[name=reset]');
	var button_cancel = current_window.ui.content.querySelector('button[name=cancel]');

	Caracal.language.load_text(null, 'save', function(constant, value) {
		button_add.innerHTML = value;
	});

	button_reset.style.display = 'none';
	button_cancel.style.display = 'inline-block';
	input_type.disabled = true;
};

/**
 * Reset input fields for property editing.
 *
 * @param object button
 */
Caracal.Shop.reset_property_fields = function(button) {
	var window_id = button.closest('.window').getAttribute('id');
	var current_window = Caracal.window_system.get_window(window_id);

	var fields = current_window.ui.content.querySelectorAll('input[name^=property_]');
	for (var i=0, count=fields.length; i<count; i++) {
		var field = fields[i];

		// make sure we are not changing data field
		if (field.name.substr(0, 14) == 'property_data_')
			continue;

		// reset value
		if (field.classList.contains('multi-language'))
			current_window.ui.language_selector.clear_values(field); else
			field.value = '';

		// notify event handlers input has changed
		var changed_event = document.createEvent('HTMLEvents');
		changed_event.initEvent('change', true, true);
		field.dispatchEvent(changed_event);
	}
};

/**
 * Remove current row from the list of properties.
 *
 * @param object event
 */
Caracal.Shop.delete_property = function(event) {
	if (event instanceof Event)
		var row = event.target.closest('tr'); else
		var row = event.closest('tr');

	row.remove();
};

/**
 * Cancel currently editing property.
 *
 * @param object window
 */
Caracal.Shop.cancel_property_edit = function(button) {
	var window_id = button.closest('.window').getAttribute('id');
	var current_window = Caracal.window_system.get_window(window_id);

	// remove editing row
	current_window.editing_row = null;

	// clear field values
	Caracal.Shop.reset_property_fields(button);

	// show and hide buttons
	var button_add = current_window.ui.content.querySelector('button[name=add]');
	var button_reset = current_window.ui.content.querySelector('button[name=reset]');
	var button_cancel = current_window.ui.content.querySelector('button[name=cancel]');

	button_reset.style.display = 'inline-block';
	button_cancel.style.display = 'none';

	Caracal.language.load_text(null, 'add', function(constant, value) {
		button_add.innerHTML = value;
	});

	var input_type = current_window.ui.content.querySelector('select[name=property_type]');
	input_type.disabled = false;
};

/**
 * Add coupon code data to the parent list.
 *
 * @param object button
 */
Caracal.Shop.add_coupon_code = function(button, code, discount) {
	// create new nodes
	var list_item = document.createElement('div');
	var column_code = document.createElement('span');
	var column_discount = document.createElement('span');
	var column_times = document.createElement('span');
	var column_options = document.createElement('span');
	var option_delete = document.createElement('a');

	// configure and pack user interface
	column_code.classList.add('column');
	column_code.style.width = '180px';
	column_discount.classList.add('column');
	column_discount.style.width = '180px';
	column_times.classList.add('column');
	column_times.style.width = '80px';

	var data = Caracal.language.getTextArray(null, ['delete', 'change']);
	option_delete.appendChild(document.createTextNode(data['delete']));
	option_delete.addEventListener('click', Caracal.Shop.delete_coupon_code);

	column_options.classList.add('column');
	column_options.appendChild(option_delete);

	list_item.appendChild(column_code);
	list_item.appendChild(column_discount);
	list_item.appendChild(column_times);
	list_item.appendChild(column_options);
	list_item.classList.add('list_item');

	// store data in the list
	var get_data = !code && !discount;
	if (get_data) {
		var content = button.parentNode.parentNode;
		var code = content.querySelector('input[name=code]').value;
		var discount = content.querySelector('select[name=discount]').value;
	}

	var hash_code = function(string) {
		var hash = 0;
		if (string.length == 0)
			return hash;

		for (i = 0; i < string.length; i++) {
			char = string.charCodeAt(i);
			hash = ((hash << 5) - hash) + char;
			hash = hash & hash; // convert to 32bit integer
		}
		return hash;
	};
	var index = hash_code(code);

	column_code.appendChild(document.createTextNode(code));
	column_times.appendChild(document.createTextNode(0));

	var data_code = document.createElement('input');
	data_code.setAttribute('type', 'hidden');
	data_code.setAttribute('name', 'code_' + index.toString());
	data_code.setAttribute('value', code);

	var data_discount = document.createElement('input');
	data_discount.setAttribute('type', 'hidden');
	data_discount.setAttribute('name', 'discount_' + index.toString());
	data_discount.setAttribute('value', discount);

	column_code.appendChild(data_code);
	column_code.appendChild(data_discount);

	// find parent window and add new option to it
	var codes_window = document.getElementById('shop_coupon_codes');
	var list = codes_window.querySelector('div.list_content');
	list.appendChild(list_item);

	// close window
	if (get_data)
		Caracal.window_system.close_window('shop_coupon_codes_add');
};

/**
 * Generate coupon codes and add them to the parent list.
 *
 * @param object button
 */
Caracal.Shop.generate_coupon_codes = function(sender) {
	var button = sender.target || sender;
	var content = button.parentNode.parentNode;
	var count = content.querySelector('input[name=count]').value;
	var length = content.querySelector('input[name=length]').value;
	var charset = content.querySelector('select[name=charset]').value;
	var prefix = content.querySelector('input[name=prefix]').value;
	var suffix = content.querySelector('input[name=suffix]').value;
	var discount = content.querySelector('select[name=discount]').value;

	generate_code = function() {
		var result = '';
		var charset_size = charset.length;

		for (var i=0; i<length; i++)
			result += charset[Math.floor(Math.random() * charset_size)];

		return prefix + result + suffix;
	};

	for (var i=0; i<count; i++) {
		var code = generate_code().toUpperCase();
		Caracal.Shop.add_coupon_code(sender, code, discount);
	}

	// close window
	Caracal.window_system.close_window('shop_coupon_codes_generate');
};

/**
 * Handle coupon code removal.
 *
 * @param object sender
 */
Caracal.Shop.delete_coupon_code = function(sender) {
	var button = sender.target || sender;
	var list_item = button.parentNode.parentNode;
	list_item.parentNode.removeChild(list_item);
};

/**
 * Update item management window when filters change.
 *
 * @param object sender
 */
Caracal.Shop.update_item_list = function(sender) {
	var items_window = Caracal.window_system.get_window('shop_items');
	var manufacturer = items_window.ui.container.querySelector('select[name=manufacturer]');
	var category = items_window.ui.container.querySelector('select[name=category]');

	// prepare data to send to server
	var data = {
			manufacturer: manufacturer.value,
			category: category.value
		};

	// save original url for later use
	if (items_window.original_url == undefined)
		items_window.original_url = items_window.url;

	// reload window
	items_window.load_content(items_window.original_url + '&' + $.param(data));
};
