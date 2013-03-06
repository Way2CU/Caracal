/**
 * Universal Dialog Component
 * 
 * Copyright (c) 2012. by Way2CU
 * Author: Mladen Mijatov
 */

function Dialog() {
	var self = this;
	
	self._background = $('<div>');
	self._container = $('<div>');
	self._title = $('<div>');
	self._title_text = $('<span>');
	self._close_button = $('<a>');
	self._content = $('<div>');
	self._inner_content = $('<div>');
	self._scrollbar = null;
	self._clear_on_close = false;
	
	/**
	 * Complete object initialization
	 */
	self.init = function() {
		// configure background
		self._background
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
				.click(self.__handle_close_click)
				.appendTo(self._title);
		
		self._title.append($('<div>').css('clear', 'both'));
		
		// configure content
		self._content
				.css('position', 'relative')
				.addClass('content')
				.appendTo(self._container);

		self._inner_content
				.addClass('inner_content')
				.appendTo(self._content);
		
		// connect events
		$(window).bind('resize', self.__handle_window_resize);

		// create scrollbar
		if (typeof Scrollbar == 'function')
			self._scrollbar = new Scrollbar(self._content, 'div.inner_content');
	};
	
	/**
	 * Set dialog content from specified URL
	 * 
	 * @param string url
	 */
	self.setContentFromURL = function(url, container) {
		if (container != null)
			self._inner_content.load(url + ' #' + container); else
			self._inner_content.load(url);

		self._inner_content.css('top', 0);
	};
	
	/**
	 * Set dialog content from DOM element retrieved by jQuery selection
	 * 
	 * @param string selection
	 */
	self.setContentFromDOM = function(selection) {
		var element = $(selection).eq(0);
		
		element.detach();
		self._inner_content
				.html(element)
				.css('top', 0);
	};
	
	/**
	 * Set dialog size
	 * 
	 * @param integer width
	 * @param integer height
	 */
	self.setSize = function(width, height) {
		// set dialog size
		self._content.css({
					width: width,
					height: height
				});
	};
	
	/**
	 * Set dialog title
	 *
	 * @param string title
	 */
	self.setTitle = function(title) {
		self._title_text.html(title);
	};
	
	/**
	 * Set scrollbar visibility
	 *
	 * @param string show_scrollbar
	 */
	self.setScroll = function(show_scrollbar) {
		if (show_scrollbar)
			self._content.addClass('scroll'); else
			self._content.removeClass('scroll');
	};

	/**
	 * Whether content of dialog should be cleared on close.
	 *
	 * @param boolean clear
	 */
	self.setClearOnClose = function(clear) {
		self._clear_on_close = clear;
	};
	
	/**
	 * Show dialog
	 */
	self.show = function() {
		var chain = new AnimationChain();
		
		// configure containers
		self._background.css({
					display: 'block',
					opacity: 0
				});
		
		self._container.css({
					display: 'block',
					opacity: 0
				});

		// update dialog position
		self._update_position();
		
		// create animation chain
		chain
			.addAnimation(
					self._background, 
					{opacity: 0.5}, 
					300
				)
			.addAnimation(
					self._container,
					{opacity: 1},
					300
				)
			.callback(function() {
				// set visible marker
				self._visible = true;

				// update scrollbar
				if (self._scrollbar != null)
					self._scrollbar.content_updated();
			});
		
		// start animation chain
		chain.start();
	};
	
	/**
	 * Hide dialog
	 */
	self.hide = function() {
		var chain = new AnimationChain();
		
		// create animation chain
		chain
			.addAnimation(
					self._container, 
					{opacity: 0}, 
					200
				)
			.addAnimation(
					self._background,
					{opacity: 0},
					200
				)
			.callback(function() {
				self._visible = false;
				
				self._container.css('display', 'none');
				self._background.css('display', 'none');

				if (self._clear_on_close)
					self._inner_content.html('');
			});
			
		// start animation chain
		chain.start();
	};
	
	/**
	 * Update dialog position based on size
	 */
	self._update_position = function() {
		var window_width = $(window).width();
		var window_height = $(window).height();
		var width = self._container.width();
		var height = self._container.height();
		
		self._container.css({
					left: Math.round((window_width - width) / 2),
					top: Math.round((window_height - height) / 2)
				});
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
	 * Handle browser window resize
	 *
	 * @param object event
	 */
	self.__handle_window_resize = function(event) {
		self._update_position();
	};
	
	// finish object initialization
	self.init();
}
