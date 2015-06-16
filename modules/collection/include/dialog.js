/**
 * Universal Dialog Component
 *
 * Copyright (c) 2014. by Way2CU
 * Author: Mladen Mijatov
 */

function Dialog() {
	var self = this;

	self._background = null;
	self._container = $('<div>');
	self._title = $('<div>');
	self._title_text = $('<span>');
	self._close_button = $('<a>');
	self._content = $('<div>');
	self._inner_content = $('<div>');
	self._scrollbar = null;
	self._clear_on_close = false;
	self._show_on_load = false;
	self._content_loaded = false;
	self._command_bar = $('<div>');
	self._close_callback = null;

	/**
	 * Complete object initialization
	 */
	self.init = function() {
		// configure background
		self._background = $('div.dialog-background');
		if (self._background.length == 0)
			self._background = $('<div>')
					.addClass('dialog-background')
					.appendTo($('body'));

		// configure container
		self._container
				.addClass('dialog')
				.appendTo($('body'));

		// configure title
		self._title
				.addClass('title')
				.appendTo(self._container);

		self._title_text
				.appendTo(self._title);

		self._close_button
				.addClass('close')
				.attr('href', 'javascript: void(0);')
				.html(language_handler.getText(null, 'close'))
				.click(self.__handle_close_click)
				.appendTo(self._command_bar);

		self._title.append($('<div>').css('clear', 'both'));

		// connect click event for background
		self._background.click(self.__handle_close_click);

		// configure content
		self._content
				.css('position', 'relative')
				.addClass('content')
				.appendTo(self._container);

		self._inner_content
				.addClass('inner_content')
				.appendTo(self._content);

		// configure command bar
		self._command_bar
				.addClass('command_bar')
				.appendTo(self._content);

		// create scrollbar
		if (typeof Scrollbar == 'function')
			self._scrollbar = new Scrollbar(self._content, 'div.inner_content');
	};

	/**
	 * Add additional class to dialog.
	 *
	 * @param string class_name
	 * @return object
	 */
	self.addClass = function(class_name) {
		self._container.addClass(class_name);
		return self;
	};

	/**
	 * Add control to command bar.
	 *
	 * @param object control
	 * @return object
	 */
	self.addControl = function(control) {
		self._command_bar.prepend(control);
		return self;
	};

	/**
	 * Set dialog content from jQuery object or string
	 *
	 * @param mixed content
	 * @return object
	 */
	self.setContent = function(content) {
		// set specified content
		self._inner_content
					.html(content)
					.css('top', 0);  // reset scroll position

		// set content state flag
		self._content_loaded = true;

		return self;
	};

	/**
	 * Set dialog content from specified URL
	 *
	 * @param string url
	 * @return object
	 */
	self.setContentFromURL = function(url) {
		// reset content state flag
		self._content_loaded = false;

		// initiate loading process
		$.ajax({
			url: url,
			async: true,
			dataType: 'html',
			headers: {'X-Requested-With': 'Dialog'},
			success: self.__handle_content_load
		});

		// reset scroll position
		self._inner_content.css('top', 0);

		return self;
	};

	/**
	 * Set dialog content from DOM element retrieved by jQuery selection
	 *
	 * @param string selection
	 * @return object
	 */
	self.setContentFromDOM = function(selection) {
		var element = $(selection).eq(0);

		// detach and reattach content
		element.detach();
		self._inner_content
				.html(element)
				.css('top', 0);  // reset scroll position

		// set content state flag
		self._content_loaded = true;

		return self;
	};

	/**
	 * Set dialog size
	 *
	 * @param integer width
	 * @param integer height
	 * @return object
	 */
	self.setSize = function(width, height) {
		// set dialog size
		self._inner_content.css({
					width: width,
					height: height,
				});

		if (typeof width == 'number')
			self._container.css('margin-left', -Math.round(width / 2));

		return self;
	};

	/**
	 * Set dialog title
	 *
	 * @param string title
	 * @return object
	 */
	self.setTitle = function(title) {
		self._title_text.html(title);
		return self;
	};

	/**
	 * Set scrollbar visibility
	 *
	 * @param string show_scrollbar
	 * @return object
	 */
	self.setScroll = function(show_scrollbar) {
		if (show_scrollbar)
			self._content.addClass('scroll'); else
			self._content.removeClass('scroll');

		return self;
	};

	/**
	 * Whether content of dialog should be cleared on close.
	 *
	 * @param boolean clear
	 * @return object
	 */
	self.setClearOnClose = function(clear) {
		self._clear_on_close = clear;
		return self;
	};

	/**
	 * Set function to be executed on close.
	 *
	 * @param callable callback
	 * @return object
	 */
	self.setCloseCallback = function(callback) {
		if (typeof callback == 'function')
			self._close_callback = callback;

		return self;
	};

	/**
	 * Clear close function callback.
	 *
	 * @return object
	 */
	self.clearCloseCallback = function() {
		self._close_callback == null;
		return self;
	};

	/**
	 * Set dialog as error report.
	 *
	 * @param boolean error
	 * @return object
	 */
	self.setError = function(error) {
		if (error)
			self._container.addClass('error'); else
			self._container.removeClass('error');

		return self;
	};

	/**
	 * Show dialog
	 */
	self.show = function() {
		// set classes
		self._background.addClass('visible');
		self._container.addClass('visible');
		self._visible = true;

		// update scrollbar
		if (self._scrollbar != null)
			self._scrollbar.content_updated();
	};

	/**
	 * Show dialog when content is ready.
	 */
	self.showWhenReady = function() {
		// prevent user from clicking more than once
		self._background.addClass('visible');

		// show content now or later
		if (self._content_loaded)
			self.show(); else
			self._show_on_load = true;
	};

	/**
	 * Hide dialog
	 */
	self.hide = function() {
		// call function if configured
		if (self._close_callback != null)
			self._close_callback(self);

		// remove classes
		self._background.removeClass('visible');
		self._container.removeClass('visible');
		self._visible = false;

		// clear content if neede
		if (self._clear_on_close) {
			self._inner_content.html('');

			// reset content state flag
			self._content_loaded = false;
		}
	};

	/**
	 * Handle clicking on close button
	 *
	 * @param object event
	 */
	self.__handle_close_click = function(event) {
		self.hide();
		event.preventDefault();
	};

	/**
	 * Handle content load from URL.
	 *
	 * @param mixed data
	 */
	self.__handle_content_load = function(data) {
		// update content state flag
		self._content_loaded = true;

		// set dialog content
		self._inner_content.html(data)

		// show dialog if needed
		if (self._show_on_load)
			self.show();
	};

	// finish object initialization
	self.init();
}
