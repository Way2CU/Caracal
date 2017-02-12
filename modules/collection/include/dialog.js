/**
 * Universal Dialog Component
 *
 * Copyright (c) 2017. by Way2CU
 * Author: Mladen Mijatov
 */
var Caracal = Caracal || new Object();


/**
 * Controller object which allows only one dialog to be displayed
 * at a time. This object is automatically created to ensure best
 * operation as all dialogs include background and appear modal.
 */
Caracal.DialogController = function() {
	var self = this;
	self.dialog = null;
	self.background = null;
	self.handler = new Object();

	/**
	 * Connect dialog events to local handlers.
	 *
	 * @param object dialog
	 */
	self.connect = function(dialog) {
		dialog.events.connect('open', self.handler.dialog_open);
		dialog.events.connect('close', self.handler.dialog_close);
	};

	/**
	 * Handle dialog opening.
	 *
	 * @param object dialog
	 */
	self.handler.dialog_open = function(event, dialog) {
		if (self.dialog !== null)
			self.dialog.close();

		if (self.background === null) {
			self.background = dialog.get_background();
			self.background.addEventListener('click', self.handler.background_click);
		}

		self.dialog = dialog;
	};

	/**
	 * Handle dialog closing.
	 *
	 * @param object dialog
	 */
	self.handler.dialog_close = function(event, dialog) {
		if (self.dialog === dialog)
			self.dialog = null;
	};

	/**
	 * Handle clicking on dialog background.
	 *
	 * @param object event
	 */
	self.handler.background_click = function(event) {
		event.preventDefault();
		if (self.dialog)
			self.dialog.close();
	};
}


/**
 * Create new dialog with optionally specified configuration.
 *
 * Configuration parameters:
 * - Parameter `clear_on_close`:
 *		Default: false. Clear dialog content on close.
 *
 * - Parameter `open_on_load`:
 *		Default: false. Open dialog automatically once content
 *		has finished loading when content is set from URL.
 *
 * - Parameter `include_close_button`:
 *		Default: true. Create close button automatically.
 *
 *
 * Language constants:
 * - `close`: Close button language constant;
 * - `title`: Dialog title language constant;
 * - `message`: Dialog message language constant.
 *
 *
 * Supported events:
 * - Callback for `open`:
 *		function (dialog)
 *
 *		Triggered before immediately before dialog is to be shown,
 *		giving custom scripts opportunity to configure content and
 *		handle other aspects of dialog.
 *
 *	- Callback for `close`:
 *		function (dialog)
 *
 *		Triggered after dialog is closed.
 *
 * @param object config
 * @param object constants
 */
Caracal.Dialog = function(config, constants) {
	var self = this;

	// components
	self._background = null;
	self._scrollbar = null;
	self._container = null;
	self._title = null;
	self._title_text = null;
	self._close_button = null;
	self._content = null;
	self._inner_content = null;
	self._command_bar = null;

	// configuration
	var config = config || new Object();
	self.config = {
			'include_close_button': config.include_close_button || true,
			'clear_on_close': config.clear_on_close || false,
			'open_on_load': config.open_on_load || false
		}

	self._content_loaded = false;

	self.events = null;
	self.handler = new Object();

	// assign language constants
	var constants = constants || new Object();
	self.constants = {
			'close': constants.close || 'close',
			'title': constants.title || null,
			'message': constants.message || null
		};

	/**
	 * Complete object initialization
	 */
	self._init = function() {
		// configure background
		self._background = document.querySelector('div.dialog-background');
		if (self._background === null) {
			self._background = document.createElement('div');
			self._background.classList.add('dialog-background');
			document.querySelector('body').appendChild(self._background);
		}

		// configure container
		self._container = document.createElement('div');
		self._container.classList.add('dialog');
		document.querySelector('body').appendChild(self._container);

		// configure title
		self._title = document.createElement('div');
		self._title.classList.add('title')
		self._container.appendChild(self._title);

		self._title_text = document.createElement('span');
		self._title.appendChild(self._title_text);

		// configure content
		self._content = document.createElement('div');
		self._content.classList.add('content');
		self._container.appendChild(self._content);

		self._inner_content = document.createElement('div');
		self._inner_content.classList.add('inner_content');
		self._content.appendChild(self._inner_content);

		// configure command bar
		self._command_bar = document.createElement('div');
		self._command_bar.classList.add('command_bar');
		self._content.appendChild(self._command_bar);

		// create close button
		if (self.config.include_close_button) {
			self._close_button = document.createElement('a');
			with (self._close_button) {
				classList.add('close');
				setAttribute('href', 'javascript: void(0);');
				addEventListener('click', self.handler.close_click);
			}
			self._command_bar.appendChild(self._close_button);
		}

		// create scrollbar
		if (typeof Scrollbar == 'function')
			self._scrollbar = new Scrollbar($(self._content), 'div.inner_content');

		if (typeof Caracal.Scrollbar == 'function')
			self._scrollbar = new Caracal.Scrollbar($(self._content), 'div.inner_content');

		// load language constants used
		self._load_constants();

		// create events handling system
		self.events = new Caracal.EventSystem();
		self.events
			.register('open')
			.register('close');

		// connect with dialog controller
		Caracal.dialog_controller.connect(self);
	};

	/**
	 * Load configured language constants.
	 */
	self._load_constants = function() {
		var constants = new Array();
		for (var index in self.constants)
			if (self.constants[index])
				constants.push(self.constants[index]);

		language_handler.getTextArrayAsync(null, constants, self.handler.constants_load);
	};

	/**
	 * Log deprecation warning for function.
	 *
	 * @param string name
	 * @param string target
	 */
	self.__make_deprecated = function(name, target) {
		self[name] = function() {
			if (console)
				console.log(
					'Calling `' + name + '` is deprecated! ' +
					'Please use `' + target + '`.'
				);

			var params = Array.prototype.slice.call(arguments);
			var callable = self[target];
			return callable.apply(self, params);
		};
	};

	/**
	 * Add additional class to dialog.
	 *
	 * @param string class_names
	 * @return object
	 */
	self.add_class = function(class_names) {
		var class_list = class_names.split(' ');

		for (var i=0, count=class_list.length; i<count; i++)
			self._container.classList.add(class_list[i]);
		return self;
	};

	self.__make_deprecated('addClass', 'add_class');

	/**
	 * Add control to command bar.
	 *
	 * @param object control
	 * @return object
	 */
	self.add_control = function(control) {
		// support jQuery objects
		if (control instanceof jQuery)
			control = control.get(0);

		self._command_bar.appendChild(control);
		return self;
	};

	self.__make_deprecated('addControl', 'add_control');

	/**
	 * Insert control at specific place. Indexes lower than zero
	 * are considered from the end of the node list.
	 *
	 * @param DOMElement control
	 * @param integer index
	 * @return object
	 */
	self.insert_control = function(control, index) {
		var children = self._command_bar.childNodes;

		if (index < 0)
			var reference = children[children.length + index]; else
			var reference = children[index];

		self._command_bar.insertBefore(control, reference);

		return self;
	};

	/**
	 * Set dialog content from jQuery object or string
	 *
	 * @param mixed content
	 * @return object
	 */
	self.set_content = function(content) {
		if (content instanceof jQuery) {
			content = content.get(0);
			self._inner_content.appendChild(content);

		} else if (content instanceof HTMLElement) {
			self._inner_content.appendChild(content);

		} else {
			// treat content as text
			self._inner_content.innerHTML = content;
		}

		// set content state flag
		self._content_loaded = true;
		self._inner_content.style.top = 0;  // reset scroll position

		return self;
	};

	self.__make_deprecated('setContent', 'set_content');

	/**
	 * Set dialog content from specified URL
	 *
	 * @param string url
	 * @return object
	 */
	self.set_content_from_url = function(url) {
		// reset content state flag
		self._content_loaded = false;

		// initiate loading process
		$.ajax({
			url: url,
			async: true,
			dataType: 'html',
			headers: {'X-Requested-With': 'Dialog'},
			success: self.handler.content_load
		});

		// reset scroll position
		self._inner_content.style.top = 0;

		return self;
	};

	self.__make_deprecated('setContentFromURL', 'set_content_from_url');

	/**
	 * Set dialog content from DOM element retrieved by jQuery selection
	 *
	 * @param string selection
	 * @return object
	 */
	self.set_content_from_dom = function(selection) {
		var element = document.querySelector(selection);

		// make sure selection exists
		if (!element)
			return self;

		// detach and reattach content
		with (self._inner_content) {
			appendChild(element);
			style.top = 0;  // reset scroll position
		}

		// set content state flag
		self._content_loaded = true;

		return self;
	};

	self.__make_deprecated('setContentFromDOM', 'set_content_from_dom');

	/**
	 * Set dialog size
	 *
	 * @param integer width
	 * @param integer height
	 * @return object
	 */
	self.set_size = function(width, height) {
		// set dialog size
		with (self._inner_content) {
			style.width = width;
			style.height = height;
		}

		return self;
	};

	self.__make_deprecated('setSize', 'set_size');

	/**
	 * Set dialog title
	 *
	 * @param string title
	 * @return object
	 */
	self.set_title = function(title) {
		self._title_text.innerHTML = title;
		return self;
	};

	self.__make_deprecated('setTitle', 'set_title');

	/**
	 * Set scrollbar visibility
	 *
	 * @param string show_scrollbar
	 * @return object
	 */
	self.set_scrollable = function(show_scrollbar) {
		if (show_scrollbar)
			self._content.classList.add('scroll'); else
			self._content.classList.remove('scroll');

		return self;
	};

	self.__make_deprecated('setScroll', 'set_scrollable');

	/**
	 * Whether content of dialog should be cleared on close.
	 *
	 * @param boolean clear
	 * @return object
	 */
	self.set_clear_on_close = function(clear) {
		self.config.clear_on_close = clear;
		return self;
	};

	self.__make_deprecated('setClearOnClose', 'set_clear_on_close');

	/**
	 * Set function to be executed on close.
	 *
	 * @param callable callback
	 * @return object
	 */
	self._add_close_handler = function(callback) {
		if (typeof callback == 'function')
			self.events.connect('close', callback);

		return self;
	};

	self.__make_deprecated('setCloseCallback', '_add_close_handler');

	/**
	 * Clear close function callback.
	 *
	 * @return object
	 */
	self._clearCloseCallback = function() {
		return self;
	};

	self.__make_deprecated('clearCloseCallback', 'null');

	/**
	 * Set dialog as error report.
	 *
	 * @param boolean error
	 * @return object
	 */
	self.set_error = function(is_error) {
		if (is_error)
			self._container.classList.add('error'); else
			self._container.classList.remove('error');

		return self;
	};

	self.__make_deprecated('setError', 'set_error');

	/**
	 * Get dialog background.
	 *
	 * @return object
	 */
	self.get_background = function() {
		return self._background;
	};

	/**
	 * Open dialog.
	 */
	self.open = function() {
		// trigger event handlers
		self.events.trigger('open', self);

		// set classes
		self._background.classList.add('visible');
		self._container.classList.add('visible');
		self._container.classList.add('active');
		self._visible = true;

		// update scrollbar
		if (self._scrollbar != null)
			self._scrollbar.content_updated();
	};

	self.__make_deprecated('show', 'open');

	/**
	 * Show dialog when content is ready.
	 */
	self.open_when_ready = function() {
		// prevent user from clicking more than once
		self._background.classList.add('visible');

		// show content now or later
		if (self._content_loaded)
			self.open(); else
			self.config.open_on_load = true;
	};

	self.__make_deprecated('showWhenReady', 'open_when_ready');

	/**
	 * Close dialog
	 */
	self.close = function() {
		// triger connected events
		self.events.trigger('close', self);

		// remove classes
		self._background.classList.remove('visible');
		self._container.classList.remove('visible');
		self._visible = false;

		// clear content if neede
		if (self.config.clear_on_close)
			setTimeout(self.handler.content_clear, 1000);
	};

	self.__make_deprecated('hide', 'close');

	/**
	 * Handle clicking on close button
	 *
	 * @param object event
	 */
	self.handler.close_click = function(event) {
		event.preventDefault();
		self.close();
	};

	/**
	 * Handle content load from URL.
	 *
	 * @param mixed data
	 */
	self.handler.content_load = function(data) {
		// update content state flag
		self._content_loaded = true;

		// set dialog content
		self._inner_content.innerHTML = data;

		// show dialog if needed
		if (self.config.open_on_load)
			self.open();
	};

	/**
	 * Handle timeout for clearing inner content.
	 *
	 * @param object event
	 */
	self.handler.content_clear = function(event) {
		self._inner_content.innerHTML = '';
		self._content_loaded = false;
	};

	/**
	 * Handle loading language constants.
	 *
	 * @param object data
	 */
	self.handler.constants_load = function(data) {
		var map = new Object();

		// map translation to local constants
		for (var index in self.constants) {
			var constant = self.constants[index];

			if (constant in data)
				map[index] = data[constant];
		}

		// assign translations
		if (map['close'] && self._close_button)
			self._close_button.innerHTML = map['close'];

		if (map['title'])
			self._title_text.innerHTML = map['title'];

		if (map['message'])
			self._inner_content.innerHTML = map['message'];
	};

	// finish object initialization
	self._init();
}

/**
 * Deprecation warning.
 */
function Dialog() {
	if (console)
		console.log(
			'Creating dialogs using global `Dialog` function is ' +
			'no longer supported. Please use `Caracal.Dialog`.'
		);
	return new Caracal.Dialog();
}


// initialize dialog system
Caracal.dialog_controller = new Caracal.DialogController();
