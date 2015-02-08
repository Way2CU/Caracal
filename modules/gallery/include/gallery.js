/**
 * Gallery JavaScript
 * Caracal Development Framework
 *
 * Copyright (c) 2015. by Way2CU, http://way2cu.com
 * Authors: Mladen Mijatov
 */

var Caracal = Caracal || {};
Caracal.Gallery = Caracal.Gallery || {};


/**
 * Slider type gallery provides horizontal gallery with specified number
 * of visible items. This object only controls the position of images and doesn't
 * provide transitions or other styling.
 *
 * If number of images is smaller or equal to number of visible items,
 * gallery animation will be disabled.
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
 * @param integer visible_items
 */
Caracal.Gallery.Slider = function(visible_items) {
	var self = this;

	self.images = {};
	self.controls = {};
	self.container = null;
	self.direction = 1;
	self.step_size = 1;
	self.center = false;
	self.spacing = null;
	self.timer_id = null;
	self.timeout = null;
	self.visible_items = null;

	/**
	 * Complete object initialization.
	 */
	self._init = function() {
		// set number of visible items
		self.visible_items = visible_items || 3;

		// create image container
		self.images.list = $();

		// create control containers
		self.controls.next = $();
		self.controls.previous = $();
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
		var images = self.images.list.toArray();
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
		self.images.list = $(images);

		// perform update
		self.images.update(real_direction);
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
	 * Prepare element position before visibility update.
	 *
	 * @param object params
	 * @param integer direction
	 */
	self.images._prepare_position = function(params, direction) {
		if (direction == 1) {
			var incoming = self.images.list.slice(self.visible_items - self.step_size, self.visible_items);
			var outgoing = self.images.list.slice(-self.step_size).not(incoming);
		} else {
			var incoming = self.images.list.slice(0, self.step_size);
			var outgoing = self.images.list.slice(self.visible_items, self.visible_items + self.step_size).not(incoming);
		}

		// disable transitions for a moment
		incoming.addClass('transit');

		// position elements
		if (direction == 1) {
			incoming.css(params.property_name, params.container_width);
			outgoing.css(params.property_name, -100);
		} else {
			incoming.css(params.property_name, -100);
			outgoing.css(params.property_name, params.container_width);
		}

		// trigger reflow on all incomming elements
		incoming.each(function() {
			this.offsetHeight;
		});

		// enable transitions
		incoming.removeClass('transit');
	};

	/**
	 * Get image spacing parameters.
	 *
	 * @param object image_set
	 * @return object
	 */
	self.images._get_params = function(image_set) {
		var result = {};

		// get width of container
		result.container_width = self.container.outerWidth();

		// get total image width
		result.total_width = 0;
		image_set.each(function() {
			result.total_width += $(this).outerWidth();
		});
		result.negative_space = result.container_width - result.total_width;

		// calculate starting position
		if (self.center) {
			if (self.spacing == null) {
				result.spacing = result.negative_space / (self.visible_items + 1);
				result.start_x = result.spacing;

			} else {
				result.spacing = self.spacing;
				result.start_x = Math.abs((result.negative_space - (result.spacing * (self.visible_items - 1))) / 2);
			}

		} else {
			if (self.spacing == null)
				result.spacing = result.negative_space / (self.visible_items - 1); else
				result.spacing = self.spacing;
			result.start_x = 0;
		}

		// store property name
		result.property_name = self.direction == 1 ? 'left' : 'right';

		return result;
	};

	/**
	 * Update image positions.
	 *
	 * @param object image_set
	 */
	self.images._update_position = function(image_set, params) {
		var pos_x = params.start_x;

		image_set.each(function() {
			var item = $(this);
			item.css(params.property_name, pos_x);
			pos_x += item.outerWidth() + params.spacing;
		});
	};

	/**
	 * Update image visibility.
	 *
	 * @param object image_set
	 */
	self.images._update_visibility = function(image_set) {
		self.images.list.not(image_set).removeClass('visible');
		image_set.addClass('visible');
	};

	/**
	 * Set specified jQuery object or selector as image container. Unless
	 * container is specified gallery will only apply `visible` class to elements instead
	 * of actually specifying their position.
	 *
	 * @param mixed container
	 * @return self
	 */
	self.images.set_container = function(container) {
		self.container = $(container);
		return self;
	};

	/**
	 * Add images from jQuery set or from specified selector to the list. Only added images
	 * will be positioned.
	 *
	 * @param mixed images
	 * @return self
	 */
	self.images.add = function(images) {
		$.extend(self.images.list, $(images));
		return self;
	};

	/**
	 * Set images to be centered in container.
	 *
	 * @param boolean center
	 * @return self
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
	 * @return self
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
	 * @return self
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
	 * @return self
	 */
	self.images.set_step_size = function(step) {
		self.step_size = step;
		return self;
	};

	/**
	 * Set direction of moving images. Direction can be -1 or 1.
	 *
	 * @param integer direction
	 * @return self
	 */
	self.controls.set_direction = function(direction) {
		if (Math.abs(direction) == 1)
			self.direction = direction;

		return self;
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
		if (self.timer_id == null)
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
		if (self.timer_id != null)
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
		var controls = $();

		if (next)
			$.extend(controls, self.controls.next);

		if (previous)
			$.extend(controls, self.controls.previous);

		if (container)
			$.extend(controls, self.container);

		// reset click handlers
		if (next)
			self.controls.next
				.off('click', self.controls._handle_next)
				.on('click', self.controls._handle_next);

		if (previous)
			self.controls.previous
				.off('click', self.controls._handle_next)
				.on('click', self.controls._handle_next);

		// reset hover handlers
		controls
			.off('mouseenter', self.controls._handle_mouse_enter)
			.off('mouseleave', self.controls._handle_mouse_leave)
			.on('mouseenter', self.controls._handle_mouse_enter)
			.on('mouseleave', self.controls._handle_mouse_leave);
	};

	/**
	 * Make specified jQuery object behave as previous button.
	 *
	 * @param object control
	 * @return self
	 */
	self.controls.attach_next = function(control) {
		// add control to the list
		$.extend(self.controls.next, $(control));

		// re-attach event handlers
		self.controls._attach_handlers(true, false, false);

		return self;
	};

	/**
	 * Make specified jQuery object behave as previous button.
	 *
	 * @param object control
	 * @return self
	 */
	self.controls.attach_previous = function(control) {
		// add control to the list
		$.extend(self.controls.previous, $(control));

		// re-attach event handlers
		self.controls._attach_handlers(false, true, false);

		return self;
	};

	/**
	 * Turn on auto-scrolling with specified timeout.
	 *
	 * @param integer timeout
	 * @return self
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

	// finalize object
	self._init();
}
