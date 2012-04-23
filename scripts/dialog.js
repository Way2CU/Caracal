/**
 * Universal Dialog Component
 * 
 * Copyright (c) 2012. by Way2CU
 * Author: Mladen Mijatov
 */

function Dialog() {
	var self = this;
	
	this._background = $('<div>');
	this._container = $('<div>');
	this._title = $('<div>');
	this._title_text = $('<span>');
	this._close_button = $('<a>');
	this._content = $('<div>');
	this._inner_content = $('<div>');
	this._scrollbar = null;
	
	/**
	 * Complete object initialization
	 */
	this.init = function() {
		// configure background
		this._background
				.addClass('dialog-background')
				.appendTo($('body'));
		
		// configure container
		this._container
				.addClass('dialog')
				.appendTo($('body'));
		
		// configure title
		this._title
				.addClass('title')
				.appendTo(this._container);
		
		this._title_text
				.appendTo(this._title);
		
		this._close_button
				.addClass('close')
				.attr('title', language_handler.getText(null, 'close'))
				.attr('href', 'javascript: void(0);')
				.click(this.__handle_close_click)
				.appendTo(this._title);
		
		this._title.append($('<div>').css('clear', 'both'));
		
		// configure content
		this._content
				.css('position', 'relative')
				.addClass('content')
				.appendTo(this._container);

		this._inner_content
				.addClass('inner_content')
				.appendTo(this._content);
		
		// connect events
		$(window).bind('resize', this.__handle_window_resize);

		// create scrollbar
		this._scrollbar = new Scrollbar(this._content, 'div.inner_content');
	};
	
	/**
	 * Set dialog content from specified URL
	 * 
	 * @param string url
	 */
	this.setContentFromURL = function(url, container) {
		var callback = function() {
			self._scrollbar.content_updated();
		};

		if (container != null)
			this._inner_content.load(url + ' #' + container, callback); else
			this._inner_content.load(url, callback);
	};
	
	/**
	 * Set dialog content from DOM element retrieved by jQuery selection
	 * 
	 * @param string selection
	 */
	this.setContentFromDOM = function(selection, detach) {
		var element = $(selection).eq(0);
		
		element.detach();
		this._inner_content.html(element);
		this._scrollbar.content_updated();
	};
	
	/**
	 * Set dialog size
	 * 
	 * @param integer width
	 * @param integer height
	 */
	this.setSize = function(width, height) {
		// set dialog size
		this._content.css({
					width: width,
					height: height
				});
		
		// update dialog position
		this._update_position();
	};
	
	/**
	 * Set dialog title
	 * @param string title
	 */
	this.setTitle = function(title) {
		this._title_text.html(title);
	};
	
	/**
	 * Set scrollbar visibility
	 * @param string show_scrollbar
	 */
	this.setScroll = function(show_scrollbar) {
		if (show_scrollbar)
			this._content.addClass('scroll'); else
			this._content.removeClass('scroll');
	};
	
	/**
	 * Show dialog
	 */
	this.show = function() {
		var chain = new AnimationChain();
		
		// configure containers
		this._background.css({
					display: 'block',
					opacity: 0
				});
		
		this._container.css({
					display: 'block',
					opacity: 0
				});
		
		// create animation chain
		chain
			.addAnimation(
					this._background, 
					{opacity: 0.5}, 
					300
				)
			.addAnimation(
					this._container,
					{opacity: 1},
					300
				)
			.callback(function() {
				self._visible = true;
			});
		
		// start animation chain
		chain.start();
	};
	
	/**
	 * Hide dialog
	 */
	this.hide = function() {
		var chain = new AnimationChain();
		
		// create animation chain
		chain
			.addAnimation(
					this._container, 
					{opacity: 0}, 
					200
				)
			.addAnimation(
					this._background,
					{opacity: 0},
					200
				)
			.callback(function() {
				self._visible = false;
				
				self._container.css('display', 'none');
				self._background.css('display', 'none');
				self._inner_content.html('');
			});
			
		// start animation chain
		chain.start();
	};
	
	/**
	 * Update dialog position based on size
	 */
	this._update_position = function() {
		var window_width = $(window).width();
		var window_height = $(window).height();
		var width = this._container.width();
		var height = this._container.height();
		
		this._container.css({
					left: Math.round((window_width - width) / 2),
					top: Math.round((window_height - height) / 2)
				});
	};
	
	/**
	 * Handle clicking on close button
	 * @param object event
	 */
	this.__handle_close_click = function(event) {
		self.hide();
		event.preventDefault();
	};
	
	/**
	 * Handle browser window resize
	 * @param object event
	 */
	this.__handle_window_resize = function(event) {
		self._update_position();
	};
	
	// finish object initialization
	this.init();
}
