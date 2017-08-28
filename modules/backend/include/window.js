/**
 * Window Management System
 * Generic Window Class
 *
 * This class is used to load content from specified URL and use it as a content for window. If
 * window contains form automatic submit event listener is added and properly handled.
 */

var Caracal = Caracal || {};
Caracal.WindowSystem = Caracal.WindowSystem || {};


/**
 * Window Constructor
 *
 * @param string id
 * @param integer width
 * @param string title
 * @param boolean can_close
 * @param string url
 */
Caracal.WindowSystem.Window = function(id, width, title, can_close, url) {
	var self = this;

	self.id = id;
	self.url = url;
	self.visible = false;
	self.stack_position = 1000;  // position of window in stack used by System
	self.icon = null;

	self.parent = null;
	self.window_system = null;

	// container namespaces
	self.ui = new Object();
	self.handler = new Object();
	self.drag = new Object();

	/**
	 * Finish object initialization.
	 */
	self.init = function() {
		// create new window structure
		self.ui.container = document.createElement('div');
		with (self.ui.container) {
			setAttribute('id', self.id);
			classList.add('window');
			style.width = width;
			addEventListener('mousedown', self.handler.container_mouse_down);
		}

		// create window title bar
		self.ui.title_bar = document.createElement('div');
		with (self.ui.title_bar) {
			classList.add('title');
			addEventListener('mousedown', self.handler.title_drag_start);
			addEventListener('touchstart', self.handler.title_drag_start);
		}
		self.ui.container.append(self.ui.title_bar);

		// create window icon
		self.icon = document.createElement('svg');
		self.ui.title_bar.append(self.icon);

		// title container
		self.ui.title = document.createElement('span');
		self.ui.title.innerHTML = title;
		self.ui.title_bar.append(self.ui.title);

		var window_container = document.createElement('div');
		window_container.classList.add('container');
		self.ui.container.append(window_container);

		// window content
		self.ui.content = document.createElement('div');
		self.ui.content.classList.add('content')
		window_container.append(self.ui.content);

		// add close button if needed
		if (can_close) {
			self.ui.close_button = document.createElement('a');
			with (self.ui.close_button) {
				innerHTML = '<svg><use xlink:href="#icon-close"/></svg>';
				classList.add('button');
				classList.add('close');
				addEventListener('click', self.handler.close_button_click)
			}

			self.ui.title_bar.append(self.ui.close_button);
		}
	};

	/**
	 * Handle clicking anywhere on window.
	 *
	 * @param object event
	 */
	self.handler.container_mouse_down = function(event) {
		if (self.window_system.getTopWindowId != self.id)
			self.window_system.focusWindow(self.id);
	};

	/**
	 * Handle clicking on window list.
	 *
	 * @param object event
	 */
	self.handler.window_list_item_click = function(event) {
		event.preventDefault();
		self.window_system.focusWindow(self.id);
	};

	/**
	 * Handle start dragging.
	 */
	self.handler.title_drag_start = function(event) {
		// allow dragging with first mouse button only
		if (event.type == 'mousemove' && button != 1)
			return;

		// prevent default behavior
		event.preventDefault();

		// store offset for later use
		if (event.type == 'mousedown') {
			self.drag.offset_x = event.clientX - self.ui.container.offsetLeft;
			self.drag.offset_y = event.clientY - self.ui.container.offsetTop;

		} else if (event.type == 'touchstart') {
			var touch = event.targetTouches.item(0);
			self.drag.offset_x = touch.clientX - self.ui.container.offsetLeft;
			self.drag.offset_y = touch.clientY - self.ui.container.offsetTop;
		}

		// add new event listeners
		with (self.parent) {
			addEventListener('mouseup', self.handler.title_drag_end);
			addEventListener('touchend', self.handler.title_drag_end);
			addEventListener('mousemove', self.handler.title_drag);
			addEventListener('touchmove', self.handler.title_drag);
		}

		// disable animations for smooth experience
		self.ui.container.style.transition = 'none';
	};

	/**
	 * Handle stop dragging.
	 */
	self.handler.title_drag_end = function(event) {
		// prevent default behavior
		event.preventDefault();

		// remove event listeners
		with (self.parent) {
			removeEventListener('mouseup', self.handler.title_drag_end);
			removeEventListener('touchend', self.handler.title_drag_end);
			removeEventListener('mousemove', self.handler.title_drag);
			removeEventListener('touchmove', self.handler.title_drag);
		}

		// restore animations
		self.ui.container.style.transition = '';
		self.drag_active = false;
	};

	/**
	 * Handle dragging window.
	 *
	 * @param object event
	 */
	self.handler.title_drag = function(event) {
		event.preventDefault();
		event.stopPropagation();

		// get container offset
		if (event.type == 'mousemove') {
			var position_top = event.clientY - self.drag.offset_y;
			var position_left = event.clientX - self.drag.offset_x;

		} else if (event.type == 'touchmove') {
			var touch = event.targetTouches.item(0);
			var position_top = touch.clientY - self.drag.offset_y;
			var position_left = touch.clientX - self.drag.offset_x;
		}

		// make sure window doesn't run away
		if (position_top < 0)
			position_top = 0;

		if (position_left < 0)
			position_left = 0;

		// update position
		self.ui.container.style.left = position_left;
		self.ui.container.style.top = position_top;
	};

	/**
	 * Handle clicking on close button in title bar.
	 *
	 * @param object event
	 */
	self.handler.close_button_click = function(event) {
		// prevent default behavior
		event.preventDefault();

		// close widnow
		self.close();
	};

	/**
	 * Attach window to window system and its containers.
	 *
	 * @param object system
	 */
	self.attach = function(system) {
		// attach container to main container
		system.container.append(self.ui.container);

		// save parent for later use
		self.parent = system.container;
		self.window_system = system;

		// add window list item
		self.ui.window_list_item = document.createElement('a');
		self.ui.window_list_item.innerHTML = self.ui.title.innerHTML;
		self.ui.window_list_item.addEventListener('click', self.handler.window_list_item_click);
		self.window_system.window_list.append(self.ui.window_list_item);

		return self;
	};

	/**
	 * Show window.
	 *
	 * @param boolean center
	 */
	self.show = function(center) {
		if (self.visible)
			return self;  // don't allow animation of visible window

		// center if needed
		if (center) {
			var position_top = Math.round((self.parent[0].offsetHeight - self.ui.container.offsetHeight) / 2);
			var position_left = Math.round((self.parent[0].offsetWidth - self.ui.container.offsetWidth) / 2);
			self.ui.container.style.left = position_left;
			self.ui.container.style.top = position_top;
		}

		// apply params and show window
		self.ui.container.classList.add('visible');
		self.visible = true;

		// set window to be top level
		self.focus();

		// emit event
		self.window_system.events.trigger('window-open', self);

		return self;
	};

	/**
	 * Close window.
	 */
	self.close = function() {
		self.visible = false;
		self.window_system.removeWindow(self);
		self.window_system.focusTopWindow();
		self.ui.window_list_item.remove();
		self.ui.container.remove();

		// emit event
		self.window_system.events.trigger('window-close', self);
	};

	/**
	 * Set focus on self.
	 */
	self.focus = function() {
		self.window_system.focusWindow(self.id);
		return self;
	};

	/**
	 * Event triggered when window gains focus.
	 */
	self.gainFocus = function() {
		// set level
		self.stack_position = 1000;
		self.ui.container.style.zIndex = self.stack_position;

		// update classes
		self.ui.container.classList.add('focused');
		self.ui.window_list_item.classList.add('active');

		// emit event
		self.window_system.events.trigger('window-focus-gain', self);
	};

	/**
	 * Event triggered when window loses focus.
	 */
	self.loseFocus = function() {
		self.stack_position--;
		self.ui.container.style.zIndex = self.stack_position;

		// upodate classes
		self.ui.container.classList.remove('focused');
		self.ui.window_list_item.classList.remove('active');

		// emit event
		self.window_system.events.trigger('window-focus-lost', self);
	};

	/**
	 * Load/reload window content.
	 *
	 * @param string url
	 */
	self.loadContent = function(url) {
		if (self.url == null)
			return;

		if (url != undefined)
			self.url = url;

		self.ui.container.classList.add('loading');

		$.ajax({
			cache: false,
			context: self,
			dataType: 'html',
			success: self.contentLoaded,
			error: self.contentError,
			url: self.url
		});

		return self;  // allow linking
	};

	/**
	 * Submit form content to server and load response.
	 *
	 * @param object form
	 */
	self.submitForm = function(form) {
		self.ui.container.classList.add('loading');

		var data = {};
		var field_types = ['input', 'select', 'textarea'];

		// emit event
		self.window_system.events.trigger('window-before-submit', self);

		// collect data from from
		for (var index in field_types) {
			var type = field_types[index];

			$(form).find(type).each(function() {
				var name = $(this).attr('name');

				// we ignore fields without name
				if (name == undefined)
					return;

				var is_list = name.substring(name.length - 2) == '[]';

				if ($(this).hasClass('multi-language')) {
					// multi-language input field, we need to gather other data
					var temp_data = $(this).data('language');

					for (var language in temp_data)
						data[name + '_' + language] = encodeURIComponent(temp_data[language]);

				} else {
					var field_type = $(this).attr('type');

					if (field_type == 'checkbox') {
						// checkbox
						data[name] = this.checked ? 1 : 0;

					} else if (field_type == 'radio') {
						// radio button
						if (data[name] == undefined) {
							var value = $(form).find('input:radio[name='+name+']:checked').val();
							data[name] = encodeURIComponent(value);
						}

					} else {
						// all other components
						var value = encodeURIComponent($(this).val());

						if (!is_list) {
							// store regular field value
							data[name] = value;

						} else {
							// create list storage
							if (data[name] == undefined)
								data[name] = new Array();

							// add value to the list
							data[name].push(value);
						}
					}
				}
			});
		};

		// send data to server
		$.ajax({
			cache: false,
			context: self,
			dataType: 'html',
			method: 'POST',
			data: data,
			success: self.contentLoaded,
			error: self.contentError,
			url: $(form).attr('action')
		});
	};

	/**
	 * Event fired on when window content has finished loading.
	 *
	 * @param string data
	 */
	self.contentLoaded = function(data) {
		// animate display
		var start_position = self.ui.container.offsetTop;
		var start_height = self.ui.content.offsetHeight;

		// set new window content
		self.ui.content.innerHTML = data;

		var top_position = start_position + Math.floor((start_height - self.ui.content.offsetHeight) / 2);

		// animate
		self.ui.container.style.top = top_position;
		self.ui.container.classList.add('loaded');

		// attach events
		self.attach_events();

		// remove loading indicator
		self.ui.container.classList.remove('loading');

		// emit event
		self.window_system.events.trigger('window-content-load', self);

		// if display level is right, focus first element
		if (self.stack_position == 1000)
			self.focus_input_element();
	};

	/**
	 * Focus first input element if it exists
	 */
	self.focus_input_element = function() {
		var element = self.ui.content.querySelector('input,select,textarea,button');
		if (element)
			element.focus();
	};

	/**
	 * Attach events to window content
	 */
	self.attach_events = function() {
		var forms = self.ui.content.querySelectorAll('form:not([target])');

		// make sure we have forms to attach events to
		if (forms.length == 0)
			return self;

		// attach events to forms
		for (var i = 0; i < forms.length; i++) {
			var form = forms[i];
			var file_inputs = form.querySelectorAll('input[type="file"]');

			if (file_inputs.length == 0) {
				// normal case submission without file uploads
				form.addEventListener('submit', function(event) {
					event.preventDefault();
					self.submitForm(this);
				});

			} else {
				// When submitting from with file uploads we use hidden iframe
				// which is configured to behave the same way we would normally
				// send data through AJAX. To achieve this we need to create hidden
				// fields for each individual language to simulate multi-language
				// data submission.
				form.addEventListener('submit', function(event) {
					// get all multi-language fields
					var fields = this.querySelectorAll('input.multi-language, textarea.multi-language');

					// make sure we have data to work with
					if (fields.length == 0)
						return;

					for (var j = 0; j < fields.length; j++) {
						var field = fields[j];
						var name = field.getAttribute('name');
						var data = field.dataset.language;

						// create hidden field for each language
						for (var language in data) {
							var hidden_field = document.createElement('input');

							with (hidden_field) {
								setAttribute('type', 'hidden');
								setAttribute('name', name + '_' + language);
								value = data[language];
							}

							field.parentNode.insertBefore(hidden_field, field.nextSibling);
						}
					}
				});

				// create target hidden iframe which will receive data
				var id = 'upload_frame_' + (Math.random() * 100).toString(16);
				var iframe = document.createElement('iframe');
				iframe.setAttribute('name', id);
				iframe.setAttribute('id', id);
				iframe.style.display = 'none';

				form.parentNode.insertBefore(iframe, form.nextSibling);
				form.setAttribute('target', id);

				// add event listener to iframe through timeout to avoid initial triggering on some browsers
				setTimeout(function() {
					iframe.addEventListener('load', function(event) {
						var content = iframe.contents().find('body');

						// trigger original form event
						self.contentLoaded(content.html());

						// reset frame content in order to prevent errors with other events
						content.html('');
					});
				}, 100);
			}
		}

		return self;
	};

	/**
	 * Event fired when there was an error loading AJAX data.
	 *
	 * @param object request
	 * @param string status
	 * @param string error
	 */
	self.contentError = function(request, status, error) {
		// animate display
		var start_params = {
						top: self.ui.container.position().top,
						left: self.ui.container.position().left,
						width: self.ui.container.width(),
						height: self.ui.container.height()
					};

		self.ui.content.html(status + ': ' + error);

		var end_params = {
					top: start_params.top + Math.floor((start_params.height - self.ui.container.height()) / 2),
					height: self.ui.container.height()
				};

		self.ui.container
				.stop(true, true)
				.css(start_params)
				.animate(end_params, 400);

		// remove loading indicator
		self.ui.container.removeClass('loading');
	};

	/**
	 * Return jQuery object of uploader frame. If frame doesn't exists, we crate it
	 *
	 * @return resource
	 */
	self.getUploaderFrame = function() {
		return result;
	};

	/**
	 * Set window icon.
	 *
	 * @param string background
	 */
	self.setIcon = function(background) {
		self.icon.outerHTML = background;
		self.ui.window_list_item.innerHTML = background;
	};

	// finish object initialization
	self.init();
};
