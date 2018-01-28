/**
 * Window Management System
 *
 * This window management system was designed to be used with Caracal framework
 * administration system and has very little use outside of it. Is you are going
 * to take parts from this file leave credits intact.
 *
 * Events available from WindowSystem:
 *  window-open
 *  window-close
 *  window-focus-gain
 *  indow-focus-lost
 *  window-content-load
 *  window-before-submit
 *
 * All callbacks will receive only window affected as parameter. An example
 * callback function would look like:
 *
 * 	function Callback(affected_window) {
 * 		if (affected_window.id == 'login')
 * 			console.log('test');
 * 	}
 */

var Caracal = Caracal || new Object();
Caracal.WindowSystem = Caracal.WindowSystem || new Object();


/**
 * Dialog Constructor
 */
Caracal.WindowSystem.Dialog = function() {
	var self = this;

	self.cover = $('<div>');
	self.dialog = $('<div>');
	self.title = $('<div>');
	self.title_bar = $('<div>');
	self.close_button = $('<a>');
	self.container = $('<div>');

	/**
	 * Finish object initialization
	 */
	self.init = function() {
		self.cover
				.css('display', 'none')
				.addClass('dialog')
				.append(self.dialog)
				.appendTo($('body'));

		self.dialog
				.addClass('container')
				.append(self.title_bar)
				.append(self.container);

		self.title_bar
				.addClass('title_bar')
				.append(self.close_button)
				.append(self.title);

		self.title.addClass('title');
		self.container.addClass('content');

		self.close_button
				.addClass('close_button')
				.click(self.hide);
	};

	/**
	 * Show dialog
	 */
	self.show = function() {
		self.dialog.css('opacity', 0);

		self.cover
				.css({
					display: 'block',
					opacity: 0
				})
				.animate(
					{opacity: 1}, 300,
					function() {
						self.adjustPosition();
						self.dialog
								.delay(200)
								.animate({opacity: 1}, 300);
					}
				);
	};

	/**
	 * Hide dialog
	 */
	self.hide = function() {
		self.cover
				.css('opacity', 1)
				.animate(
					{opacity: 0}, 300,
					function() {
						self.cover.css('display', 'none');
						self.container.html('');
					}
				);
	};

	/**
	 * Set dialog content and make proper animation
	 *
	 * @param mixed content
	 * @param integer width
	 * @param integer height
	 */
	self.setContent = function(content, width, height) {
		var start_params = {
				top: Math.round($(document).height() / 2),
				left: Math.round($(document).width() / 2),
				width: 0
			};

		var c_start_params = {
				height: 0
			};

		var end_params = {
				top: Math.round(($(document).height() - height) / 2),
				left: Math.round(($(document).width() - width) / 2),
				width: width
			};

		var c_end_params = {
				height: height
			};

		// assign content
		self.container.css(c_start_params);

		self.dialog
				.css(start_params)
				.animate(
					end_params, 500,
					function() {
						self.container.animate(
								c_end_params, 500,
								function() {
									self.container.html(content);
								}
							);
					}
				);
	};

	/**
	 * Set dialog title
	 *
	 * @param string title
	 */
	self.setTitle = function(title) {
		self.title.html(title);
	};

	/**
	 * Set dialog in loading state
	 */
	self.setLoadingState = function() {
		self.dialog.addClass('loading');
	};

	/**
	 * Set dialog in normal state
	 */
	self.setNormalState = function() {
		self.dialog.removeClass('loading');
	};

	/**
	 * Adjust position of inner container
	 */
	self.adjustPosition = function() {
		self.dialog.css({
						top: Math.round(($(document).height() - self.dialog.height()) / 2),
						left: Math.round(($(document).width() - self.dialog.width()) / 2)
					});

	};

	// finish object initialization
	self.init();
};


/**
 * Constructur function for window system.
 *
 * @param string container    - Query selector for window container
 * @param string window_list  - Query selector for window list
 * @param string default_icon - Query selector for default window icon
 */
Caracal.WindowSystem.System = function(container, window_list, default_icon) {
	var self = this;  // used internally for events

	self._default_icon = null;
	self.container = document.querySelector(container);
	self.window_list = document.querySelector(window_list);

	self.list = [];
	self.events = null;

	/**
	 * Finish object initialization.
	 */
	self._init = function() {
		// create event storage arrays
		self.events = new Caracal.EventSystem();
		self.events
 				.register('window-open')
 				.register('window-close')
 				.register('window-focus-gain')
 				.register('window-focus-lost')
 				.register('window-content-load')
 				.register('window-before-submit');

 		// get default icon
 		if (default_icon)
 			self._default_icon = document.querySelector(default_icon);
	};

	/**
	 * Show login window.
	 */
	self.open_login_window = function() {
		var base = document.querySelector('meta[property=base-url]').getAttribute('content');

		self.open_window(
			'login_window', 350,
			language_handler.getText('backend', 'title_login'),
			base+'/index.php?section=backend&action=login'
		);
	};

	/**
	 * Open new window (or focus existing) and load content from specified URL
	 *
	 * @param string id
	 * @param integer width
	 * @param string title
	 * @param string url
	 * @return object
	 */
	self.open_window = function(id, width, title, url, caller) {
		var result = null;

		if (self.window_exists(id)) {
			// window already exists, reload content and show it
			result = self.get_window(id);
			result.focus().load_content(url);

		} else {
			// window does not exist, create it
			result = new Caracal.WindowSystem.Window(id, width, title, url, false);

			// preconfigure window
			self.list[id] = result;
			result.attach_to_system(self);

			// set window icon
			var caller_icon = null;

			// get icon from caller
			if (caller)
				var caller_icon = caller.querySelector('svg');

			if (caller_icon) {
				// set icon same as caller
				result.set_icon(caller_icon.outerHTML);

			} else if (self._default_icon) {
				// set system default icon
				result.set_icon(self._default_icon.outerHTML);
			}

			// load content after opening the window
			result.open(true).load_content();
		}

		return result;
	};

	/**
	 * Close window.
	 *
	 * @param string id
	 * @return boolean
	 */
	self.close_window = function(id) {
		if (!self.window_exists(id))
			return false;

		self.get_window(id).close();
		return true;
	};

	/**
	 * Remove window from list and container.
	 *
	 * @param object window
	 */
	self.remove_window = function(window) {
		delete self.list[window.id];
	};

	/**
	 * Load window content from specified URL.
	 *
	 * @param string id
	 * @param string url
	 * @return boolean
	 */
	self.load_window_content = function(id, url) {
		if (!self.window_exists(id))
			return false;

		self.get_window(id).load_content(url);
		return true;
	};

	/**
	 * Focuses specified window.
	 *
	 * @param string id
	 */
	self.focus_window = function(id) {
		if (!self.window_exists(id))
			return;

		for (var index in self.list) {
			var current_window = self.list[index];

			if (current_window.id != id)
				current_window.lose_focus(); else
				current_window.gain_focus();
		}
	};

	/**
	 * Focuses top level window.
	 */
	self.focus_top_window = function() {
		var top_window = self.get_top_window();

		if (top_window)
			self.focus_window(top_window.id);
	};

	/**
	 * Get window based on text id.
	 *
	 * @param string id
	 * @return object
	 */
	self.get_window = function(id) {
		return self.list[id];
	};

	/**
	 * Get top window object.
	 *
	 * @return object
	 */
	self.get_top_window = function() {
		var result = null;

		for (var index in self.list) {
			var current_window = self.list[index];

			if (result == null || current_window.stack_position > result.stack_position)
				result = current_window;
		}

		return result;
	};

	/**
	 * Check if window exists.
	 *
	 * @param string id
	 * @return boolean
	 */
	self.window_exists = function(id) {
		return id in self.list;
	};

	// finish object initialization
	self._init();
};


$(function() {
	Caracal.window_system = new Caracal.WindowSystem.System(
			'div#container',
			'nav#window_list',
			'svg:nth-child(2)'
		);

	// show login window is menu is not present
	if (document.querySelector('nav#main') == null)
		Caracal.window_system.open_login_window();
});
