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

var window_system = null;

/**
 * Dialog Constructor
 */
function Dialog() {
	var self = this;  // used internaly for nested functions

	this.$cover = $('<div>');
	this.$dialog = $('<div>');
	this.$title = $('<div>');
	this.$title_bar = $('<div>');
	this.$close_button = $('<a>');
	this.$container = $('<div>');

	// configure
	this.$cover
			.css('display', 'none')
			.addClass('dialog')
			.append(this.$dialog);

	this.$dialog
			.addClass('container')
			.append(this.$title_bar)
			.append(this.$container);

	this.$title_bar
			.addClass('title_bar')
			.append(this.$close_button)
			.append(this.$title);

	this.$title.addClass('title');
	this.$container.addClass('content');

	this.$close_button
			.addClass('close_button')
			.click(function() {
				self.hide();
			});

	// add dialog to body
	$('body').append(this.$cover);

	/**
	 * Show dialog
	 */
	this.show = function() {
		this.$dialog.css('opacity', 0);

		this.$cover
				.css({
					display: 'block',
					opacity: 0
				})
				.animate(
					{opacity: 1},
					300,
					function() {
						self.adjustPosition();
						self.$dialog
								.delay(200)
								.animate({opacity: 1}, 300);
					}
				);
	};

	/**
	 * Hide dialog
	 */
	this.hide = function() {
		this.$cover
				.css('opacity', 1)
				.animate(
					{opacity: 0},
					300,
					function() {
						self.$cover.css('display', 'none');
						self.$container.html('');
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
	this.setContent = function(content, width, height) {
		// create starting parameters for animation
		var $container = this.$container;

		var start_params = {
				width: this.$dialog.width(),
			};

		var c_start_params = {
				height: 0,
			};

		var end_params = {
				top: Math.round(($(document).height() - height) / 2),
				left: Math.round(($(document).width() - width) / 2),
				width: width,
			};

		var c_end_params = {
				height: height
			};

		// assign content
		this.$dialog
				.css(start_params)
				.animate(
					end_params,
					500,
					function() {
						$container
							.css(c_start_params)
							.animate(
								c_end_params,
								500,
								function() {
									$container.html(content);
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
	this.setTitle = function(title) {
		this.$title.html(title)
	}

	/**
	 * Set dialog in loading state
	 */
	this.setLoadingState = function() {
		this.$dialog.addClass('loading');
	};

	/**
	 * Set dialog in normal state
	 */
	this.setNormalState = function() {
		this.$dialog.removeClass('loading');
	};

	/**
	 * Adjust position of inner container
	 */
	this.adjustPosition = function() {
		this.$dialog.css({
						top: Math.round(($(document).height() - this.$dialog.height()) / 2),
						left: Math.round(($(document).width() - this.$dialog.width()) / 2)
					});

	};
}

/**
 * Window Constructor
 *
 * @param string id
 * @param integer width
 * @param string title
 * @param boolean can_close
 * @param string url
 * @param boolean existing_structure Allow creating window from existing structure
 */
function Window(id, width, title, can_close, url, existing_structure) {
	var self = this;  // used for nested functions

	this.id = id;
	this.url = url;
	this.visible = false;
	this.zIndex = 1000;

	// create interface
	this.$parent = null;
	this.window_system = null;

	if (!existing_structure) {
		// create new window structure
		this.$container = $('<div id="'+id+'">').hide().addClass('window');
		this.$title = $('<div>').addClass('title').html(title);
		this.$content = $('<div>').addClass('content');

		this.$container.append(this.$title);
		this.$container.append(this.$content);

	} else {
		// inherit existing structure and configure it
		this.$container = $('#' + id);
		this.$title = this.$container.children('div.title').eq(0);
		this.$content = this.$container.children('div.content').eq(0);
	}

	if (can_close) {
		var $close_button = $('<a>').addClass('close_button');

		$close_button.click(function() {
			self.close();
		});
	}

	this.$title.append($close_button);

	// configure interface
	this.$container.css({
					width: width
				});

	this.$container.click(function() {
		self.window_system.focusWindow(self.id);
	});

	this.$title.drag(function(event, pos) {
		self.$container.css({
					top: pos.offsetY,
					left: pos.offsetX
				});
	});

	/**
	 * Show window
	 */
	this.show = function(center) {
		if (this.visible) return this;  // don't allow animation of visible window

		var params = {
			display: 'block',
			opacity: 0
		};

		// center if needed
		if (center != undefined && center) {
			params.top = Math.floor((this.$parent.height() - this.$container.height()) / 2) - 50;
			params.left = Math.floor((this.$parent.width() - this.$container.width()) / 2);

			if (params.top < 35)
				params.top = 35;
		}

		// apply params and show window
		this.$container
			.css(params)
			.animate({opacity: 1}, 300);

		this.visible = true;
		this.window_system.focusWindow(this.id);
		return this;
	};

	/**
	 * Close window
	 */
	this.close = function() {
		this.$container.animate({opacity: 0}, 300, function() {
			this.visible = false;
			self.window_system.removeWindow(self);
			self.window_system.focusTopWindow();
		});
	};

	/**
	 * Set focus on self
	 */
	this.focus = function() {
		this.window_system.focusWindow(this.id);
		return this;  // allow linking
	};

	/**
	 * Attach window to specified container
	 *
	 * @param object system
	 */
	this.attach = function(system, $container) {
		// attach container to main container
		system.$container.append(this.$container);

		// save parent for later use
		this.$parent = system.$container;
		this.window_system = system;
	};

	/**
	 * Event triggered when window gains focus
	 */
	this.gainFocus = function() {
		this.zIndex = 1000;
		this.$container
				.css({zIndex: this.zIndex})
				.animate({opacity: 1}, 300);
	};

	/**
	 * Event triggered when window looses focus
	 */
	this.loseFocus = function() {
		this.zIndex--;
		this.$container
				.css({zIndex: this.zIndex})
				.animate({opacity: 0.6}, 300);
	};

	/**
	 * Load/reload window content
	 *
	 * @param string url
	 */
	this.loadContent = function(url) {
		if (this.url == null) return;
		if (url != undefined) this.url = url;

		this.$container.addClass('loading');

		$.ajax({
			cache: false,
			context: this,
			dataType: 'html',
			success: this.contentLoaded,
			error: this.contentError,
			url: this.url
		});

		return this;  // allow linking
	};

	/**
	 * Submit form content to server and load response
	 *
	 * @param object form
	 */
	this.submitForm = function(form) {
		this.$container.addClass('loading');

		var data = {};
		var field_types = ['input', 'select', 'textarea'];

		// collect data from from
		for(var index in field_types) {
			var type = field_types[index];

			$(form).find(type).each(function() {
				if ($(this).hasClass('multi-language')) {
					// multi-language input field, we need to gather other data
					var name = $(this).attr('name');
					var temp_data = $(this).data('language');

					for (var language in temp_data)
						data[name + '_' + language] = temp_data[language];

				} else {
					// normal input field or checkbox
					if ($(this).attr('type') != 'checkbox')
						data[$(this).attr('name')] = $(this).val(); else
						data[$(this).attr('name')] = this.checked ? 1 : 0;
				}
			});
		};

		// send data to server
		$.ajax({
			cache: false,
			context: this,
			dataType: 'html',
			type: 'POST',
			data: data,
			success: this.contentLoaded,
			error: this.contentError,
			url: $(form).attr('action')
		});
	};

	/**
	 * Event fired on when window content has finished loading
	 *
	 * @param string response
	 * @param string status
	 */
	this.contentLoaded = function(data) {
		// animate display
		var start_params = {
						top: this.$container.position().top,
						left: this.$container.position().left,
						width: this.$container.width(),
						height: this.$container.height()
					};

		self.$content.html(data);

		var end_params = {
					top: start_params.top + Math.floor((start_params.height - self.$container.height()) / 2),
					height: self.$container.height()
				};

		// prevent window from going under menu
		if (end_params.top < 35)
			end_params.top = 35;

		// animate
		self.$container
				.stop(true, true)
				.css(start_params)
				.animate(end_params, 400);


		// attach events
		self.attachEvents()

		// remove loading indicator
		self.$container.removeClass('loading');
	};

	/**
	 * Attach events to window content
	 */
	this.attachEvents = function() {
		self.$content.find('form').each(function() {
			if ($(this).find('input:file').length == 0) {
				// normal case submission without file uploads
				$(this).submit(function(event) {
					event.preventDefault();
					self.submitForm(this);
				});

			} else {
				// submission with file uploads
				self.$container.addClass('loading');

				// remove standard components and replace them with multi-language data on submit
				$(this).submit(function() {
					$(this).find('input.multi-language, textarea.multi-language').each(function() {
						var name = $(this).attr('name');
						var data = $(this).data('language');

						for (var language in data) {
							var $hidden_field = $('<input>');

							$hidden_field
									.attr('type', 'hidden')
									.attr('name', name + '_' + language)
									.val(data[language])
									.insertAfter($(this));
						}
					});
				});

				var $iframe = self.getUploaderFrame();
				$(this).attr('target', $iframe.attr('id'));

				// handle frame load event
				$iframe.one('load', function() {
					var content = $iframe.contents().find('body');

					// trigger original form event
					self.contentLoaded(content.html());

					// reset frame content in order to prevent errors with other events
					content.html('');
				});
			}
		});
	}

	/**
	 * Event fired when there was an error loading AJAX data
	 *
	 * @param object request
	 * @param string status
	 * @param string error
	 */
	this.contentError = function(request, status, error) {
		// animate display
		var start_params = {
						top: this.$container.position().top,
						left: this.$container.position().left,
						width: this.$container.width(),
						height: this.$container.height()
					};

		this.$content.html(status + ': ' + error);

		var end_params = {
					top: start_params.top + Math.floor((start_params.height - self.$container.height()) / 2),
					height: self.$container.height()
				};

		self.$container
				.stop(true, true)
				.css(start_params)
				.animate(end_params, 400);

		// remove loading indicator
		self.$container.removeClass('loading');
	};

	/**
	 * Return jQuery object of uploader frame. If frame doesn't exists, we crate it
	 *
	 * @return resource
	 */
	this.getUploaderFrame = function() {
		var $result = $('iframe#file_upload_frame');

		if ($result.length == 0) {
			// element does not exist, create it
			$result = $('<iframe>');

			$result
				.attr('name', 'file_upload_frame')
				.attr('id', 'file_upload_frame')
				.css({display: 'none'});

			$('body').append($result);
		}

		return $result;
	};
}

function WindowSystem($container) {
	var self = this;  // used internally for events

	this.$modal_dialog = null;
	this.$modal_dialog_container = null;
	this.$container = $container;
	this.list = [];

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
	this.openWindow = function(id, width, title, can_close, url) {
		if (this.windowExists(id)) {
			// window already exists, reload content and show it
			this.getWindow(id).focus().loadContent(url);

		} else {
			// window does not exist, create it
			var window = new Window(id, width, title, can_close, url, false);

			this.list[id] = window;
			window.attach(this);
			window.show(true);
			window.loadContent();
		}
	};

	/**
	 * Attach window class to existing structure
	 *
	 * @param string id
	 * @param integer width
	 * @param boolean can_close
	 */
	this.attachToStructure = function(id, width, can_close) {
		if (this.windowExists(id)) {
			// window already exists, reload content and show it
			this.getWindow(id).focus()

		} else {
			// window does not exist, create it
			var window = new Window(id, width, null, can_close, null, true);

			this.list[id] = window;
			window.attach(this);
			window.attachEvents();
			window.show(true);
		}
	};

	/**
	 * Close window
	 *
	 * @param string id
	 * @return boolean
	 */
	this.closeWindow = function(id) {
		if (this.windowExists(id))
			this.getWindow(id).close();
	};

	/**
	 * Remove window from list and container
	 *
	 * @param object window
	 */
	this.removeWindow = function(window) {
		delete this.list[window.id];
		window.$container.remove();
	};

	/**
	 * Load window content from specified URL
	 *
	 * @param string id
	 * @param string url
	 */
	this.loadWindowContent = function(id, url) {
		if (this.windowExists(id)) {
			this.getWindow(id).loadContent(url);
		}
	};

	/**
	 * Focuses specified window
	 *
	 * @param string id
	 */
	this.focusWindow = function(id) {
		if (this.windowExists(id)) {
			for (var window_id in this.list)
				if (window_id != id)
					this.list[window_id].loseFocus(); else
					this.list[window_id].gainFocus();
		}
	};

	/**
	 * Focuses top level window
	 */
	this.focusTopWindow = function() {
		var highest_id = null;
		var highest_index = 0;

		for (var window_id in this.list)
			if (this.list[window_id].zIndex > highest_index) {
				highest_id = this.list[window_id].id;
				highest_index = this.list[window_id].zIndex;
			}

		if (highest_id != null)
			this.focusWindow(highest_id);
	};

	/**
	 * Get window based on text Id
	 *
	 * @param string id
	 * @return object
	 */
	this.getWindow = function(id) {
		return this.list[id];
	};

	/**
	 * Check if window exists
	 *
	 * @param string id
	 * @return boolean
	 */
	this.windowExists = function(id) {
		return id in this.list;
	};

	/**
	 * Block backend, show modal dialog and return container
	 *
	 * @return jquery object
	 */
	this.showModalDialog = function() {
		this.$modal_dialog_container.css({
						top: Math.round(($(document).height() - this.$modal_dialog_container.height()) / 2),
						left: Math.round(($(document).width() - this.$modal_dialog_container.width()) / 2)
					});

		this.$modal_dialog
					.css({
						opacity: 0,
						display: 'block'
					})
					.animate({opacity: 1}, 500);
	};

	/**
	 * Hide modal dialog and clear its content
	 */
	this.hideModalDialog = function() {
		this.$modal_dialog.animate({opacity: 0}, 500, function() {
			$(this).css({display: 'none'});
			self.$modal_dialog_container.html('');
		});
	};
}

$(document).ready(function() {
	window_system = new WindowSystem($('#wrap'));
});
