/**
 * Gallery Toolbar Extension
 *
 * This toolbar extension provides controls to be used in conjunction
 * with gallery module.
 *
 * Copyright (c) 2018. by Way2CU
 * Author: Mladen Mijatov
 */
var Caracal = Caracal || new Object();
Caracal.Gallery = Caracal.Gallery || new Object();


Caracal.Gallery.Toolbar = function(toolbar) {
	var self = this;

	self.button = null;
	self.dialog = null;
	self.toolbar = null;

	// namespaces
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
		icon.innerHTML = '<use xlink:href="#icon-gallery-add-image"/>';
		self.button.append(icon);

		// configure dialog icon
		self.dialog.set_icon(icon.outerHTML);

		// load language constants
		var constants = ['title_insert_image'];
		language_handler.getTextArrayAsync('gallery', constants, self.handler.language_load);

		// add button to toolbar
		self.toolbar.container.append(self.button);

		// connect window events
		Caracal.window_system.events.connect('window-close', self.handler.window_close);
	};

	/**
	 * Load data from the server.
	 */
	self.load_data = function() {
		var parameters = {
				thumbnail_size: 100,
				all_languages: 1
			};

		new Communicator('gallery')
			.on_success(self.handler.content_load)
			.on_error(self.handler.content_load_error)
			.get('json_image_list', parameters);
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

	/**
	 * Handle clicking on attach image button.
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
	 * Handle completed data load from server.
	 *
	 * @param object data
	 */
	self.handler.content_load = function(data) {
		self.dialog.set_loading(false);

		if (data.error) {
			// report server-side error
			self.dialog.close();
			alert(data.error_message);
			return;
		}

		// prepare for data processing
		var list = document.createElement('div');
		var current_language = language_handler.current_language;
		if ('language_selector' in self.toolbar.target_window.ui) {
			var language_selector = self.toolbar.target_window.ui.language_selector;
			current_language = language_selector.language;
		}

		// no error, feed data into dialog
		for (var i=0, count=data.items.length; i<count; i++) {
			var image_data = data.items[i];

			// create interface elements
			var item = document.createElement('div');
			var container = document.createElement('div');
			var image = document.createElement('img');
			var label = document.createElement('span');

			// configure elements
			container.classList.add('image_holder');

			image.src = image_data.thumbnail;
			if (current_language in image_data.title) {
				image.alt = image_data.title[current_language];
				label.innerHTML = image_data.title[current_language];

			} else {
				image.alt = image_data.filename;
				label.innerHTML = image_data.filename;
			}

			label.classList.add('title');

			item.classList.add('thumbnail');
			item.dataset.id = image_data.id;
			if (current_language in image_data.title)
				item.dataset.title = image_data.title[current_language]; else
				item.dataset.title = image_data.filename;
			item.addEventListener('click', self.handler.item_click);

			// pack elements
			container.append(image);
			item.append(container);
			item.append(label);
			list.append(item);
		}

		self.dialog.set_content(list);
	};

	/**
	 * Handle server side error when loading data.
	 *
	 * @param object xhr
	 * @param string status_code
	 * @param string description
	 */
	self.handler.content_load_error = function(xhr, status_code, description) {
		self.dialog.set_loading(false);
	};

	/**
	 * Handle clicking on one of the presented images.
	 *
	 * @param object event
	 */
	self.handler.item_click = function(event) {
		// prevent default behavior
		event.preventDefault();

		// collect data about selection
		var item = event.currentTarget;
		var title = item.dataset.title;
		var image_id = item.dataset.id;
		var element = self.toolbar.element;
		var start = element.selectionStart;
		var end = element.selectionEnd;

		// prepare new value
		var new_value = '![' + title + '](' + image_id + ')';
		var cursor_position = start + new_value.length;

		// hide dialog
		self.dialog.close();

		// replace existing selection
		element.value = element.value.substr(0, start) + new_value + element.value.substr(end);
		element.focus();
		element.setSelectionRange(cursor_position, cursor_position);
	};

	/**
	 * Handle loading of language constants.
	 *
	 * @param object data
	 */
	self.handler.language_load = function(data) {
		self.button.title = data['title_insert_image'];
		self.dialog.set_title(data['title_insert_image']);
	};

	// finalize object
	self._init();
}

// register extension
window.addEventListener('load', function() {
	Caracal.Toolbar.register_extension('gallery', Caracal.Gallery.Toolbar);
});
