/**
 * YouTube Toolbar Extension
 *
 * This toolbar extension provides controls to be used in conjunction
 * with YouTube module. Mainly allows embedding YouTube videos in articles.
 *
 * Copyright (c) 2021. by Way2CU
 * Author: Mladen Mijatov
 */
var Caracal = Caracal || new Object();
Caracal.YouTube = Caracal.YouTube || new Object();


Caracal.YouTube.Toolbar = function(toolbar) {
	self.embed_button = null;
	self.insert_button = null;
	self.dialog = null;
	self.toolbar = null;

	// namespaces
	self.handler = new Object();

	/**
	 * Complete object initialization.
	 */
	self._init = function() {
		self.toolbar = toolbar;

		// create backend dialog
		self.dialog = new Caracal.WindowSystem.Dialog();

		// create embed button
		self.embed_button = document.createElement('a');
		self.embed_button.addEventListener('click', self.handler.button_click);

		var icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
		var base_url = document.querySelector('meta[property=base-url]').getAttribute('content');
		icon.innerHTML = '<use xlink:href="#icon-youtube-embed"/>';
		self.embed_button.append(icon);

		// create insert
		self.insert_button = document.createElement('a');
		self.insert_button.addEventListener('click', self.handler.button_click);

		var icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
		var base_url = document.querySelector('meta[property=base-url]').getAttribute('content');
		icon.innerHTML = '<use xlink:href="#icon-youtube-insert"/>';
		self.insert_button.append(icon);

		// configure dialog icon
		self.dialog.set_icon(icon.outerHTML);

		// load language constants
		var constants = ['title_embed_video', 'title_insert_video'];
		Caracal.language.load_text_array('youtube', constants, self.handler.language_load);

		// add button to toolbar
		self.toolbar.container.append(self.embed_button);
		self.toolbar.container.append(self.insert_button);

		// connect window events
		Caracal.window_system.events.connect('window-close', self.handler.window_close);
	}

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

		self.dialog.set_title(event.currentTarget.title);

		// show containing dialog
		self.dialog.open();
		self.dialog.set_loading(true);

		// start data load
		self.load_data();
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
	 * Handle loading of language constants.
	 *
	 * @param object data
	 */
	self.handler.language_load = function(data) {
		self.embed_button.title = data['title_embed_video'];
		self.insert_button.title = data['title_insert_video'];
	};

	// finalize object
	self._init();
}

// register extension
window.addEventListener('load', function() {
	Caracal.Toolbar.register_extension('youtube', Caracal.YouTube.Toolbar);
});
