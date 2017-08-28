/**
 * Window Management System
 *
 * Copyright (c) 2013. by Way2CU
 * Author: Mladen Mijatov
 *
 * This window management system was designed to be used with Caracal framework
 * administration system and has very little use outside of it. Is you are going
 * to take parts from this file leave credits intact.
 *
 * Requires jQuery 1.4.2+
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
 *
 */

var Caracal = Caracal || {};
Caracal.WindowSystem = Caracal.WindowSystem || {};


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
 * Window System
 */
Caracal.WindowSystem.System = function(container) {
	var self = this;  // used internally for events

	self.modal_dialog = null;
	self.modal_dialog_container = null;
	self.container = $(container);
	self.list = [];
	self.window_list = $('nav#windows');
	self.container_offset = self.container.offset();

	self.events = null;

	/**
	 * Finish object initialization.
	 */
	self.init = function() {
		// create event storage arrays
		self.events = new Caracal.EventSystem();
		self.events
 				.register('window-open')
 				.register('window-close')
 				.register('window-focus-gain')
 				.register('window-focus-lost')
 				.register('window-content-load')
 				.register('window-before-submit');
	};

	/**
	 * Show login window.
	 */
	self.showLoginWindow = function() {
		var base = $('meta[property=base-url]').attr('content');

		self.openWindow(
			'login_window',
			350,
			language_handler.getText('backend', 'title_login'),
			false,
			base+'/index.php?section=backend&action=login'
		);
	};

	/**
	 * Open new window (or focus existing) and load content from specified URL
	 *
	 * @param string id
	 * @param integer width
	 * @param string title
	 * @param boolean can_close
	 * @param string url
	 * @return object
	 */
	self.openWindow = function(id, width, title, can_close, url, caller) {
		if (self.windowExists(id)) {
			// window already exists, reload content and show it
			var window = self.getWindow(id);

			window
				.focus()
				.loadContent(url);

		} else {
			// window does not exist, create it
			var window = new Caracal.WindowSystem.Window(id, width, title, can_close, url, false);

			// preconfigure window
			self.list[id] = window;
			window.attach(self);

			// set window icon
			var icon = $(caller).find('svg');
			if (icon.length)
				window.setIcon(icon[0].outerHTML); else
				window.setIcon('<svg></svg>');

			// show window
			window
				.show(true)
				.loadContent();
		}

		return window;
	};

	/**
	 * Close window.
	 *
	 * @param string id
	 * @return boolean
	 */
	self.closeWindow = function(id) {
		if (self.windowExists(id))
			self.getWindow(id).close();
	};

	/**
	 * Remove window from list and container.
	 *
	 * @param object window
	 */
	self.removeWindow = function(window) {
		delete self.list[window.id];
	};

	/**
	 * Load window content from specified URL.
	 *
	 * @param string id
	 * @param string url
	 */
	self.loadWindowContent = function(id, url) {
		if (self.windowExists(id))
			self.getWindow(id).loadContent(url);
	};

	/**
	 * Focuses specified window.
	 *
	 * @param string id
	 */
	self.focusWindow = function(id) {
		if (self.windowExists(id))
			for (var window_id in self.list)
				if (window_id != id)
					self.list[window_id].loseFocus(); else
					self.list[window_id].gainFocus();
	};

	/**
	 * Focuses top level window.
	 */
	self.focusTopWindow = function() {
		var highest_id = self.getTopWindowId();

		if (highest_id != null)
			self.focusWindow(highest_id);
	};

	/**
	 * Get window based on text id.
	 *
	 * @param string id
	 * @return object
	 */
	self.getWindow = function(id) {
		return self.list[id];
	};

	/**
	 * Get top window object.
	 *
	 * @return object
	 */
	self.getTopWindow = function() {
		return self.getWindow(self.getTopWindowId());
	};

	/**
	 * Get top window id.
	 *
	 * @return string
	 */
	self.getTopWindowId = function() {
		var highest_id = null;
		var highest_index = 0;

		for (var window_id in self.list)
			if (self.list[window_id].stack_position > highest_index) {
				highest_id = self.list[window_id].id;
				highest_index = self.list[window_id].stack_position;
			}

		return highest_id;
	};

	/**
	 * Check if window exists.
	 *
	 * @param string id
	 * @return boolean
	 */
	self.windowExists = function(id) {
		return id in self.list;
	};

	/**
	 * Block backend, show modal dialog and return container.
	 *
	 * @return jquery object
	 */
	self.showModalDialog = function() {
		self.modal_dialog_container.css({
						top: Math.round(($(document).height() - self.modal_dialog_container.height()) / 2),
						left: Math.round(($(document).width() - self.modal_dialog_container.width()) / 2)
					});

		self.modal_dialog
					.css({
						opacity: 0,
						display: 'block'
					})
					.animate({opacity: 1}, 500);
	};

	/**
	 * Hide modal dialog and clear its content.
	 */
	self.hideModalDialog = function() {
		self.modal_dialog.animate({opacity: 0}, 500, function() {
			$(this).css('display', 'none');
			self.modal_dialog_container.html('');
		});
	};

	// finish object initialization
	self.init();
};


$(function() {
	Caracal.window_system = new Caracal.WindowSystem.System('div#container');

	// show login window is menu is not present
	if (document.querySelector('nav#main') == null)
		Caracal.window_system.showLoginWindow();
});
