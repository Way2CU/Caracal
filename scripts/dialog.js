/**
 * Universal Dialog Component
 * 
 * Copyright (c) 2011. by Way2CU
 * Author: MeanEYE.rcf
 */

function Dialog() {
	var self = this;
	
	this._visible = false;
	
	this._background = $('<div>');
	this._container = $('<div>');
	this._title = $('<span>');
	this._close_button = $('<a>');
	this._content = $('<div>');
	
	/**
	 * Complete object initialization
	 */
	this.init = function() {
		// configure background
		this._background
				.addClass('dialog-background')
				.appendTo($(body));
		
		// configure container
		this._container
				.addClass('dialog')
				.appendTo($(body));
		
		// configure title
		this._title
				.addClass('title')
				.appendTo(this._container);
		
		// configure content
		this._content
				.addClass('content')
				.appendto(this._container);
	};
	
	/**
	 * Set dialog content from specified URL
	 * 
	 * @param string url
	 */
	this.setContentFromURL = function(url, container) {
		if (container != null)
			this._content.load(url + ' #' + container); else
			this._content.load(url);
	};
	
	/**
	 * Set dialog content from DOM element retrieved by jQuery selection
	 * 
	 * @param string selection
	 */
	this.setContentFromDOM = function(selection, detach) {
		var element = $(selection).eq(0);
		
		element.detach();
		this._content.html(element);
	};
	
	/**
	 * Set dialog size
	 * 
	 * @param integer width
	 * @param integer height
	 */
	this.setSize = function(width, height) {
	};
	
	/**
	 * Set dialog title
	 */
	this.setTitle = function(title) {
		this._title.html(title);
	};
	
	/**
	 * Show dialog
	 */
	this.show = function() {
		
	};
	
	/**
	 * Hide dialog
	 */
	this.hide = function() {
		
	};
	
	// finish object initialization
	this.init();
}