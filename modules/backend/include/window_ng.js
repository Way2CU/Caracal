/**
 * Window Management System
 *
 * Copyright (c) 2010. by MeanEYE.rcf
 * http://rcf-group.com
 *
 * This window management system was designed to be used with RCF WebEngine
 * administration system and has very little use outside of it. Is you are going
 * to take parts from this file leave credits intact.
 *
 * Requires jQuery 1.4.2+
 */

function Window(id, width, title, can_close, url) {
	this.id = id;
	this.url = url;

	this.$container = $('<div id="'+id+'">').hide().addClass('window');
	this.$title = $('<div>').addClass('title');
	this.$content = $('<div>').addClass('content');

	this.$container.append(this.$title);
	this.$container.append(this.$content);

	/**
	 * Show window
	 */
	this.show = function() {
		this.$container.show(300);
	}

	/**
	 * Hide window
	 */
	this.hide = function() {
		this.$container.hide(300);
	}

	this.loadContent = function() {
	}
}

var WindowSystem = {
	// internal window list
	list: [],

	/**
	 * Open new window (or focus existing) and load content from specified URL
	 *
	 * @param string id
	 * @param integer width
	 * @param string title
	 * @param boolean can_close
	 * @param string url
	 * @returns Window
	 */
	openWindow: function(id, width, title, can_close, url) {
		if (this.windowExists(id)) {
		} else {
			var window = new Window(id, width, title, can_close, url);
			list.push(window);
		}
	},

	closeWindow: function(id) {
	},

	getWindow: function(id) {
	},

	windowExists: function(id) {
	}
}
