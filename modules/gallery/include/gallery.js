/**
 * Gallery JavaScript
 * Caracal Development Framework
 *
 * Copyright (c) 2018. by Way2CU, http://way2cu.com
 * Authors: Mladen Mijatov
 */

var Caracal = Caracal || new Object();
Caracal.Gallery = Caracal.Gallery || new Object();


/**
 * Thumbnail constraint constants.
 */
Caracal.Gallery.Constraint = {
	WIDTH: 0,
	HEIGHT: 1,
	BOTH: 2
};


/**
 * Loader object provides menu handling and image loading functions. It's
 * designed to be used in conjecture with other image handlers and galleries.
 */
Caracal.Gallery.Loader = function() {
	var self = this;

	self.handlers = new Object();
	self.galleries = null;
	self.callbacks = null;
	self.constructor = null;
	self.thumbnail_size = 100;
	self.constraint = 2;

	/**
	 * Complete object initialization.
	 */
	self._init = function() {
		self.galleries = new Array();
		self.callbacks = new Array();
	};

	/**
	 * Add gallery to control image loading of.
	 *
	 * Required gallery object functions:
	 * .images.set_container_status
	 * .images.clear
	 * .images.append
	 * .images.add
	 * .images.update
	 *
	 * @param object gallery
	 * @return object
	 */
	self.add_gallery = function(gallery) {
		self.galleries.push(gallery);
		return self;
	};

	/**
	 * Add callback function when images are loaded.
	 *
	 * @param callable callback
	 * @return object
	 */
	self.add_callback = function(callback) {
		if (typeof callback == 'function')
			self.callbacks.push(callback);
		return self;
	};

	/**
	 * Set image constructor function. This function is called for every image
	 * after loading and needs to return jQuery object. Loader itself handlers
	 * insertion to DOM tree.
	 *
	 * @param callable constructor
	 * @return object
	 */
	self.set_constructor = function(constructor) {
		if (typeof constructor == 'function')
			self.constructor = constructor;
		return self;
	};

	/**
	 * Set thumbnail size when loading images from server.
	 *
	 * @param integer size
	 * @param integer constraint
	 * @return self
	 */
	self.set_thumbnail_size = function(size, constraint) {
		if (size)
			self.thumbnail_size = size;

		if (constraint)
			self.constraint = constraint;

		return self;
	};

	/**
	 * Asynchronously load images from the gallery with specified numberical
	 * unique id. If load is successful constructor function will be used to
	 * create each image in all of the galleries added to loader.
	 *
	 * @param integer id
	 * @param boolean slideshow
	 * @return object
	 */
	self.load_by_group_id = function(id, slideshow) {
		// make sure communicator is loaded
		if (typeof Communicator != 'function')
			return self;

		// set container as busy
		for (var i=0, count=self.galleries.length; i<count; i++)
			self.galleries[i].images.set_container_status(true);

		// prepare data
		var data = {
				group_id: id,
				thumbnail_size: self.thumbnail_size,
				constraint: self.constraint
			};

		if (slideshow)
			data.slideshow = slideshow ? 1 : 0;

		// create communicator and send request
		new Communicator('gallery')
				.on_error(self.handlers.load_error)
				.on_success(self.handlers.load_success)
				.get('json_image_list', data);

		return self;
	};

	/**
	 * Asynchronously load images from image group with specified text id. If
	 * load is successful constructor function will be used to create each image
	 * all of the galleries added to loader.
	 *
	 * @param string text_id
	 * @param boolean slideshow
	 * @return object
	 */
	self.load_by_group_text_id = function(text_id, slideshow) {
		// make sure communicator is loaded
		if (typeof Communicator != 'function')
			return self;

		// set container as busy
		for (var i=0, count=self.galleries.length; i<count; i++)
			self.galleries[i].images.set_container_status(true);

		// prepare data
		var data = {
				group: text_id,
				thumbnail_size: self.thumbnail_size,
				constraint: self.constraint
			};

		if (slideshow)
			data.slideshow = slideshow ? 1 : 0;

		// create communicator and send request
		new Communicator('gallery')
				.on_error(self.handlers.load_error)
				.on_success(self.handlers.load_success)
				.get('json_image_list', data);

		return self;
	};

	/**
	 * Handle successful load of data from the server.
	 *
	 * @param object data
	 * @param mixed callback_data
	 */
	self.handlers.load_success = function(data, callback_data) {
		if (!data.error) {
			// create image storage array
			var storage = new Array();

			// create images
			for (var i=0, count=data.items.length; i<count; i++) {
				var image = null;
				var image_data = data.items[i];

				if (self.constructor != null)
					image = self.constructor(image_data); else
					image = Caracal.Gallery.create_image(image_data);

				if (image != null)
					storage.push(image);
			}

			// add images to every gallery
			for (var i=0, count=self.galleries.length; i<count; i++)
				self.galleries[i]
						.images.clear()
						.images.append(storage)
						.images.add(storage)
						.images.update();
		}

		// clear container as busy
		for (var i=0, count=self.galleries.length; i<count; i++)
			self.galleries[i].images.set_container_status(false);

		// call all function and notify them about load
		for (var i=0, count=self.callbacks.length; i<count; i++)
			self.callbacks[i]();
	};

	/**
	 * Handle server-side error when trying to load data.
	 *
	 * @param object xhr
	 * @param string transfer_status
	 * @param string description
	 * @param mixed callback_data
	 */
	self.handlers.load_error = function(xhr, transfer_status, description, callback_data) {
		// clear container as busy
		for (var i=0, count=self.galleries.length; i<count; i++)
			self.galleries[i].images.set_container_status(false);
	};

	// finalize object
	self._init();
};


/**
 * Slider type gallery provides horizontal gallery with specified number
 * of visible items. This object only controls the position of images and doesn't
 * provide transitions or other styling.
 *
 * If number of images is smaller or equal to number of visible items,
 * gallery animation will be disabled. Default number of visible items is 3.
 *
 *
 * Example usage:
 *
 * var gallery = new Caracal.Gallery.Slider(3);
 * gallery
 * 	.images.add('a.image')
 * 	.images.set_container('div.images')
 * 	.images.update()
 * 	.controls.attach_next('a.next');
 *
 *
 * Example loading from server:
 *
 * var gallery = new Caracal.Gallery.Slider(3);
 * gallery
 * 	.controls.attach_next('a.next')
 * 	.images.set_thumbnail_size(150)
 * 	.images.set_container('div.images')
 * 	.images.load(null, 'gallery');
 *
 * @param integer visible_items
 * @param boolean vertical
 */
Caracal.Gallery.Slider = function(visible_items, vertical) {
	var self = this;

	self.images = new Object();
	self.images.list = new Array();
	self.controls = new Object();
	self.controls.next = new Array();
	self.controls.previous = new Array();
	self.controls.direct = null;
	self.controls.constructor = null;
	self.container = null;
	self.direction = 1;
	self.step_size = 1;
	self.center = false;
	self.show_direct_controls = false;
	self.spacing = null;
	self.timer_id = null;
	self.timeout = null;
	self.pause_on_hover = true;
	self.visible_items = visible_items || 3;
	self.vertical = vertical ? true : false;

	/**
	 * Complete object initialization.
	 */
	self._init = function() {
		// detect list direction automatically
		if (document.querySelector('html').getAttribute('dir') == 'rtl' && !self.vertical)
			self.direction = -1;
	};

	/**
	 * Move images by one step forward.
	 */
	self.next_step = function() {
		if (self.visible_items < self.images.list.length)
			self.images._move(1);
	};

	/**
	 * Move images by one step backward.
	 */
	self.previous_step = function() {
		if (self.visible_items < self.images.list.length)
			self.images._move(-1);
	};

	/**
	 * Move images one step in specified direction.
	 *
	 * @param integer direction
	 */
	self.images._move = function(direction) {
		var real_direction = direction * self.direction;

		// shift images
		var slice = null;
		var images = self.images.list;
		var subset = null;

		if (real_direction == 1) {
			// move portion of array to the end
			slice = images.splice(0, self.step_size);
			images = images.concat(slice);

		} else {
			// move end part of array to the front
			slice = images.splice(-self.step_size, self.step_size);
			images = slice.concat(images);
		}

		// make jquery set from array
		self.images.list = images;

		// perform update
		self.images.update(real_direction);
	};

	/**
	 * Prepare element position before visibility update.
	 *
	 * @param object params
	 * @param integer direction
	 */
	self.images._prepare_position = function(params, direction) {
		if (direction == 1) {
			var incoming = self.images.list.slice(self.visible_items - self.step_size, self.visible_items);
			var outgoing = self.images.list.slice(-self.step_size);

		} else {
			var incoming = self.images.list.slice(0, self.step_size);
			var outgoing = self.images.list.slice(self.visible_items, self.visible_items + self.step_size);
		}

		// disable transitions for a moment
		incoming.forEach(function(image) {
			image.classList.add('transit');
		});

		// position elements
		if (direction == 1) {
			incoming.forEach(function(image) {
				image.style[params.property_name] = params.container_size.toString() + 'px';
			});
			outgoing.forEach(function(image) {
				image.style[params.property_name] = '-100%';
			});

		} else {
			incoming.forEach(function(image) {
				image.style[params.property_name] = '-100%';
			});
			outgoing.forEach(function(image) {
				image.style[params.property_name] = params.container_size.toString() + 'px';
			});
		}

		// trigger reflow and enable transitions
		incoming.forEach(function(image) {
			window.getComputedStyle(image).opacity;  // force restyle
			image.classList.remove('transit');
		});
	};

	/**
	 * Get image spacing parameters.
	 *
	 * @param array image_set
	 * @return object
	 */
	self.images._get_params = function(image_set) {
		var result = new Object();

		// get size of container
		result.container_size = 0;
		if (self.container)
			if (self.vertical)
				result.container_size = self.container.offsetHeight; else
				result.container_size = self.container.offsetWidth;

		// get total image width
		result.total_size = 0;
		image_set.forEach(function(image) {
			if (self.vertical)
				result.total_size += image.offestHeight; else
				result.total_size += image.offsetWidth;
		});
		result.negative_space = result.container_size - result.total_size;

		// calculate starting position
		if (self.center) {
			if (self.spacing == null) {
				result.spacing = result.negative_space / (self.visible_items + 1);
				result.start_position = result.spacing;

			} else {
				result.spacing = self.spacing;
				result.start_position = Math.abs((result.negative_space - (result.spacing * (self.visible_items - 1))) / 2);
			}

		} else {
			if (self.spacing == null) {
				if (self.visible_items > 1)  // avoid division by zero
					result.spacing = result.negative_space / (self.visible_items - 1); else
					result.spacing = 0;
			} else {
				result.spacing = self.spacing;
			}
			result.start_position = 0;
		}

		// store property name
		if (self.vertical)
			result.property_name = 'top'; else
			result.property_name = self.direction == 1 ? 'left' : 'right';

		return result;
	};

	/**
	 * Update image positions.
	 *
	 * @param array image_set
	 */
	self.images._update_position = function(image_set, params) {
		var pos = params.start_position;

		image_set.forEach(function(item) {
			var size = self.vertical ? item.offsetHeight : item.offsetWidth;
			item.style[params.property_name] = pos.toString() + 'px';
			pos += size + params.spacing;
		});
	};

	/**
	 * Update image visibility.
	 *
	 * @param array image_set
	 */
	self.images._update_visibility = function(image_set) {
		// update image visibility
		self.images.list.forEach(function(image) {
			if (image_set.indexOf(image) > -1)
				image.classList.add('visible'); else
				image.classList.remove('visible');
		});

		// update direct controls if they are used
		if (self.show_direct_controls) {
			// get controls for active image set
			var control_set = new Array();
			image_set.forEach(function(image) {
				control_set.push(image.data('control'));  // TODO: FIX!
			});

			// update controls
			self.controls.direct.forEach(function(control) {
				if (control_set.indexOf(control) > -1)
					control.classList.add('active'); else
					control.classList.remove('active');
			});
		}
	};

	/**
	 * Update image positions.
	 */
	self.images.update = function(direction) {
		// prepare for update
		subset = self.images.list.slice(0, self.visible_items);
		params = self.images._get_params(subset);

		// update image positions
		if (self.container != null && direction)
			self.images._prepare_position(params, direction);

		// update image visibility
		self.images._update_visibility(subset);

		// update image positions
		if (self.container != null)
			self.images._update_position(subset, params);
	};

	/**
	 * Set specified jQuery object or selector as image container. Unless
	 * container is specified gallery will only apply `visible` class to elements instead
	 * of actually specifying their position.
	 *
	 * @param mixed container
	 * @return object
	 */
	self.images.set_container = function(container) {
		self.container = typeof container == 'string' ? document.querySelector(container) : container;
		return self;
	};

	/**
	 * Set or clear container status as busy.
	 *
	 * @param boolean busy
	 * @return object
	 */
	self.images.set_container_status = function(busy) {
		if (busy)
			self.container.classList.add('loading'); else
			self.container.classList.remove('loading');

		return self;
	};

	/**
	 * Add images from jQuery set or from specified selector to the list. Only added images
	 * will be positioned.
	 *
	 * @param mixed images
	 * @return object
	 */
	self.images.add = function(images) {
		var container = self.container || document;
		var list = typeof images == 'string' ? container.querySelectorAll(images) : images;
		self.images.list = self.images.list.concat(Array.prototype.slice.call(list));

		// create direct controls if needed
		if (self.show_direct_controls)
			self.controls._create(list);

		return self;
	};

	/**
	 * Append list of images to container.
	 *
	 * @param array images
	 * @return object
	 */
	self.images.append = function(images) {
		// add images to container
		images.forEach(function(image) {
			self.container.append(image);
		})

		// create direct controls if needed
		if (self.show_direct_controls)
			self.controls._create(images);

		return self;
	};

	/**
	 * Remove all the images from DOM tree.
	 *
	 * @return object
	 */
	self.images.clear = function() {
		// remove images
		self.images.list.forEach(function(image) {
			image.remove();
		});
		self.images.list = new Array();

		// remove direct controls if they are visible
		if (self.show_direct_controls)
			self.controls._clear();

		return self;
	};

	/**
	 * Set images to be centered in container.
	 *
	 * @param boolean center
	 * @return object
	 */
	self.images.set_center = function(center) {
		self.center = center;
		self.images.update();
		return self;
	};

	/**
	 * Set maximum fixed image spacing. If this spacing is unachievable it
	 * will default back to zero.
	 *
	 * @param integer spacing
	 * @return object
	 */
	self.images.set_spacing = function(spacing) {
		self.spacing = spacing;
		self.images.update();
		return self;
	};

	/**
	 * Set number of visible images.
	 *
	 * @param integer count
	 * @return object
	 */
	self.images.set_visible_count = function(count) {
		self.visible_items = count;
		self.images.update();
		return self;
	};

	/**
	 * Set number of items to slide by.
	 *
	 * @param integer step
	 * @return object
	 */
	self.images.set_step_size = function(step) {
		self.step_size = step;
		return self;
	};

	/**
	 * Set direction of moving images. Direction can be -1 or 1.
	 *
	 * @param integer direction
	 * @return object
	 */
	self.controls.set_direction = function(direction) {
		if (Math.abs(direction) == 1)
			self.direction = direction;

		return self;
	};

	/**
	 * Set constructor function for direct controls.
	 *
	 * @param function constructor
	 * @return object
	 */
	self.controls.set_constructor = function(constructor) {
		self.controls.constructor = constructor;
		return self;
	};

	/**
	 * Set container for direct controls.
	 *
	 * @param string/object container
	 * @return object
	 */
	self.controls.set_direct_container = function(container) {
		// assign container
		var new_container = typeof container == 'string' ? document.querySelector(container) : container;
		self.controls.direct = new_container;

		return self;
	};

	/**
	 * Turn on or off showing of direct controls.
	 *
	 * @param boolean show_direct_controls
	 * @return object
	 */
	self.controls.set_show_direct_controls = function(show_direct_controls) {
		self.show_direct_controls = show_direct_controls;
		return self;
	};

	/**
	 * Create controls for specified images.
	 *
	 * @param array images
	 */
	self.controls._create = function(images) {
		// prepare constructor function
		var create_control = self.controls.constructor || Caracal.Gallery.create_control;

		// create controls
		images.forEach(function(image) {
			var control = create_control(image);
			self.controls.direct.append(control);
		});
	};

	/**
	 * Remove all controls from container.
	 */
	self.controls._clear = function() {
		self.controls.direct.empty();
	};

	/**
	 * Handle clicking on next control.
	 *
	 * @param object event
	 */
	self.controls._handle_next = function(event) {
		event.preventDefault();
		self.next_step();
	};

	/**
	 * Handle clicking on previous control.
	 *
	 * @param object event
	 */
	self.controls._handle_previous = function(event) {
		event.preventDefault();
		self.previous_step();
	};

	/**
	 * Handle mouse entering controls and container.
	 *
	 * @param object event
	 */
	self.controls._handle_mouse_enter = function(event) {
		if (!self.pause_on_hover)
			return;

		if (self.timer_id == null || self.timeout == null)
			return;

		clearInterval(self.timer_id);
		self.timer_id = null;
	};

	/**
	 * Handle mouse leaving controls and container.
	 *
	 * @param object event
	 */
	self.controls._handle_mouse_leave = function(event) {
		if (!self.pause_on_hover)
			return;

		if (self.timer_id != null || self.timeout == null)
			return;

		self.timer_id = setInterval(self.next_step, self.timeout);
	};

	/**
	 * Attach and re-attach handlers for controls and container.
	 *
	 * @param boolean next
	 * @param boolean previous
	 * @param boolean container
	 */
	self.controls._attach_handlers = function(next, previous, container) {
		var controls = new Array();

		if (next)
			controls = controls.concat(self.controls.next);
		if (previous)
			controls = controls.concat(self.controls.previous);
		if (container)
			controls = controls.concat(self.container);

		// make sure we have handlers to reset
		if (controls.length == 0)
			return;

		// reset click handlers
		if (next)
			self.controls.next.forEach(function(control) {
				control.removeEventListener('click', self.controls._handle_next);
				control.addEventListener('click', self.controls._handle_next);
			});

		if (previous)
			self.controls.previous.forEach(function(control) {
				control.removeEventListener('click', self.controls._handle_previous);
				control.addEventListener('click', self.controls._handle_previous);
			});

		// reset hover handlers
		controls.forEach(function(control) {
			control.removeEventListener('mouseenter', self.controls._handle_mouse_enter);
			control.removeEventListener('mouseleave', self.controls._handle_mouse_leave);
			control.addEventListener('mouseenter', self.controls._handle_mouse_enter);
			control.addEventListener('mouseleave', self.controls._handle_mouse_leave);
		});
	};

	/**
	 * Make specified jQuery object behave as previous button.
	 *
	 * @param object control
	 * @return object
	 */
	self.controls.attach_next = function(control) {
		// make sure control is valid before using
		if (!control)
			return self;

		// add control to the list
		self.controls.next.push(control);
		self.controls._attach_handlers(true, false, false);

		return self;
	};

	/**
	 * Make specified jQuery object behave as previous button.
	 *
	 * @param object control
	 * @return object
	 */
	self.controls.attach_previous = function(control) {
		// make sure control is valid before using
		if (!control)
			return self;

		// add control to the list
		self.controls.previous.push(control);
		self.controls._attach_handlers(false, true, false);

		return self;
	};

	/**
	 * Turn on auto-scrolling with specified timeout.
	 *
	 * @param integer timeout
	 * @return object
	 */
	self.controls.set_auto = function(timeout) {
		// store timeout for later use
		self.timeout = timeout;

		if (timeout == 0) {
			// clear existing timer
			if (self.timer_id != null)
				clearInterval(self.timer_id);
			self.timer_id = null;

		} else {
			// start new timer
			self.timer_id = setInterval(self.next_step, self.timeout);
		}

		// re-attach event handlers
		self.controls._attach_handlers(false, false, true);

		return self;
	};

	/**
	 * Set option to control whether sliding is paused on mouse hover or not.
	 *
	 * @param boolean pause_on_hover
	 * @return object
	 */
	self.controls.set_pause_on_hover = function(pause_on_hover) {
		self.pause_on_hover = pause_on_hover;
		return self;
	};

	// finalize object
	self._init();
};


/**
 * Simple gallery container. This container doesn't provide any special
 * behavior other than support for Gallery.Loader and container status.
 */
Caracal.Gallery.Container = function() {
	var self = this;

	self.images = new Object();
	self.container = null;

	/**
	 * Complete object initialization.
	 */
	self._init = function() {
		// create image container
		self.images.list = new Array();
	};

	/**
	 * Set specified jQuery object or selector as image container. Unless
	 * container is specified gallery will only apply `visible` class to elements instead
	 * of actually specifying their position.
	 *
	 * @param mixed container
	 * @return object
	 */
	self.images.set_container = function(container) {
		self.container = container;
		return self;
	};

	/**
	 * Set or clear container status as busy.
	 *
	 * @param boolean busy
	 * @return object
	 */
	self.images.set_container_status = function(busy) {
		if (busy)
			self.container.classList.add('loading'); else
			self.container.classList.remove('loading');

		return self;
	};

	/**
	 * Add images from jQuery set or from specified selector to the list. Only added images
	 * will be positioned.
	 *
	 * @param mixed images
	 * @return object
	 */
	self.images.add = function(images) {
		var container = self.container || document;
		var list = typeof images == 'string' ? container.querySelectorAll(images) : images;
		self.images.list = self.images.list.concat(Array.prototype.slice.call(list));

		return self;
	};

	/**
	 * Append list of images to container.
	 *
	 * @param array images
	 * @return object
	 */
	self.images.append = function(images) {
		images.forEach(function(image) {
			self.container.append(image);
		});

		return self;
	};

	/**
	 * Remove all the images from DOM tree.
	 *
	 * @return object
	 */
	self.images.clear = function() {
		self.images.list.forEach(function(image) {
			image.remove();
		});
		self.images.list = new Array();

		return self;
	};

	/**
	 * This function does nothing but is required by the loader class.
	 */
	self.images.update = function() {
	};

	// finalize object
	self._init();
};


/**
 * Default image constructor function. This function is used only when
 * images are loaded from the server to construct image container.
 *
 * @param object data
 * @return object
 */
Caracal.Gallery.create_image = function(data) {
	var link = document.createElement('a');
	link.classList.add('image');
	link.dataset.id = data.id;
	link.setAttribute('href', data.image);

	var thumbnail = new Image();
	thumbnail.src = data.thumbnail;
	thumbnail.alt = data.title;
	link.append(thumbnail);

	return link;
};


/**
 * Default control constructor function. This function is used only when
 * images are loaded from server and created in order to provide user with
 * list of controls for easy and fast switching.
 *
 * Please note that event handlers will be added by the gallery handler
 * itself. You are free however to add any local handlers if they are required.
 *
 * @param object image
 * @return object
 */
Caracal.Gallery.create_control = function(image) {
	var link = document.createElement('a');
	link.dataset.tooltip = image.alt;

	return link;
};
