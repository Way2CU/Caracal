/**
 * Downloads Toolbar Extension
 *
 * This toolbar extension provides controls to be used in conjunction
 * with downloads module.
 *
 * Copyright (c) 2018. by Way2CU
 * Author: Mladen Mijatov
 */
var Caracal = Caracal || new Object();
Caracal.Downloads = Caracal.Downloads || new Object();


Caracal.Downloads.Toolbar = function(toolbar) {
	var self = this;

	self.button = null;
	self.dialog = null;
	self.toolbar = null;

	// container namespaces
	self.ui = new Object();
	self.handler = new Object();

	/**
	 * Complete object initialization.
	 */
	self._init = function() {
		self.toolbar = toolbar;

		// create dialog
		self.dialog = new Caracal.WindowSystem.Dialog();

		// create control
		self.button = document.createElement('a');
		self.button.addEventListener('click', self.handler.button_click);

		var icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
		var base_url = document.querySelector('meta[property=base-url]').getAttribute('content');
		icon.innerHTML = '<use xlink:href="#icon-downloads-add-link"/>';
		self.button.append(icon);

		// configure dialog icon
		self.dialog.set_icon(icon.outerHTML);

		// load language constants
		var constants = [
				'toolbar_add_download', 'column_name', 'column_size',
				'column_downloads', 'column_filename'
			];
		Caracal.language.load_text_array('downloads', constants, self.handler.language_load);

		// add button to toolbar
		self.toolbar.container.append(self.button);

		// connect window events
		Caracal.window_system.events.connect('window-close', self.handler.window_close);
	};

	/**
	 * Initialize data load from server.
	 */
	self.load_data = function() {
		new Communicator('downloads')
			.on_success(self.handler.content_load)
			.on_error(self.handler.content_load_error)
			.get('json_list', {});
	};

	/**
	 * Handle language data load from server.
	 *
	 * @param object data
	 */
	self.handler.language_load = function(data) {
		self.button.title = data['toolbar_add_download'];
		self.dialog.set_title(data['toolbar_add_download']);
	};

	/**
	 * Handle successful data load.
	 *
	 * @param object data
	 */
	self.handler.content_load = function(data) {
		self.dialog.set_loading(false);

		// there's nothing to create, leave dialog empty
		if (data.error)
			return;

		var container = document.createElement('table');
		var header = document.createElement('thead');
		var list = document.createElement('tbody');

		// create header labels
		var header_name = document.createElement('td');
		var header_size = document.createElement('td');
		var header_count = document.createElement('td');
		var header_filename = document.createElement('td');

		header_name.style.width = '200px';
		header_name.innerHTML = Caracal.language.get_text('downloads', 'column_name');

		header_size.style.width = '100px';
		header_size.innerHTML = Caracal.language.get_text('downloads', 'column_size');

		header_count.style.width = '100px';
		header_count.innerHTML = Caracal.language.get_text('downloads', 'column_downloads');

		header_filename.innerHTML = Caracal.language.get_text('downloads', 'column_filename');

		// configure scrollable list
		var row = document.createElement('tr');
		row.append(header_name);
		row.append(header_size);
		row.append(header_count);
		row.append(header_filename);
		header.append(row);

		container.append(header);
		container.append(list);
		container.classList.add('list');

		// prepare for data processing
		var current_language = Caracal.language.current_language;
		if ('language_selector' in self.toolbar.target_window.ui) {
			var language_selector = self.toolbar.target_window.ui.language_selector;
			current_language = language_selector.language;
		}

		// create individual items
		for (var i=0, count=data.items.length; i<count; i++) {
			var item = data.items[i];

			// create elements
			var download = document.createElement('tr');
			var title = document.createElement('td');
			var size = document.createElement('td');
			var count = document.createElement('td');
			var filename = document.createElement('td');

			// configure
			title.innerHTML = item.name[current_language];
			size.innerHTML = item.size;
			count.innerHTML = item.count;
			filename.innerHTML = item.filename;

			download.append(title);
			download.append(size);
			download.append(count);
			download.append(filename);
			if (item.name[current_language] == '')
				download.dataset.name = item.filename; else
				download.dataset.name = item.name[current_language];
			download.dataset.url = item.download_url;
			download.addEventListener('click', self.handler.item_click);
			download.classList.add('selectable');

			list.append(download);
		}

		self.dialog.set_content(container);
	};

	/**
	 * Handle server side error during data load.
	 *
	 * @param object xhr
	 * @param string status_code
	 * @param string description
	 */
	self.handler.content_load_error = function(xhr, status_code, description) {
		self.dialog.set_loading(false);
	};

	/**
	 * Handle tool button click.
	 *
	 * @param object event
	 */
	self.handler.button_click = function(event) {
		event.preventDefault();

		// show containing dialog
		self.dialog.open();
		self.dialog.set_loading(true);

		// start data load
		self.load_data();
	};

	/**
	 * Handle clicking on download item.
	 *
	 * @param object event
	 */
	self.handler.item_click = function(event) {
		// prevent default behavior
		event.preventDefault();

		// collect data about selection
		var item = event.currentTarget;
		var name = item.dataset.name;
		var url = item.dataset.url;
		var element = self.toolbar.element;
		var start = element.selectionStart;
		var end = element.selectionEnd;

		if (start != end)
			name = element.value.substring(start, end);

		// prepare new value
		var new_value = '[' + name + '](' + url + ')';
		var cursor_position = start + new_value.length;

		// hide dialog
		self.dialog.close();

		// replace existing selection
		element.value = element.value.substr(0, start) + new_value + element.value.substr(end);
		element.focus();
		element.setSelectionRange(cursor_position, cursor_position);
	};

	/**
	 * Handle window closing.
	 *
	 * @param object window
	 */
	self.handler.window_close = function(affected_window) {
		if (affected_window.id == self.toolbar.target_window.id)
			self.dialog.destroy();
	};

	// finalize object
	self._init();
}

// register extension
window.addEventListener('load', function() {
	Caracal.Toolbar.register_extension('downloads', Caracal.Downloads.Toolbar);
});
