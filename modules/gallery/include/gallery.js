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

		if (real_direction > 0) {
			// move portion of array to the end
			slice = images.splice(0, self.step_size);
			images = images.concat(slice);

		} else {
			// move end part of array to the front
			slice = images.splice(-self.step_size, self.step_size);
			images = slice.concat(images);
		}

		self.images.list = $(images);

		// update image positions
		if (self.container != null)
			self.images._update_position();

		// update image visibility
		self.images._update_visibility();
	};

	/**
	 * Update image positions.
	 *
	 * @param object image_set
	 */
	self.images._update_position = function(image_set) {
		var total_width = 0;
		var container_width = self.container.outerWidth();
		var negative_space = 0;
		var pos_x = 0;
		var spacing = 0;

		// get total image width
		image_set.each(function() {
			total_width += $(this).outerWidth();
		});
		negative_space = container_width - total_width;

		// calculate starting position
		if (self.center) {
			if (self.spacing == null) {
				spacing = negative_space / self.visible_items + 1;
				pos_x = spacing;

			} else {
				spacing = self.spacing;
				pos_x = Math.abs(negative_space + (spacing * (self.visible_items - 1)) / 2);
			}

		} else {
			if (self.spacing == null)
				spacing = negative_space / self.visible_items - 1; else
				spacing = self.spacing;
		}

		// position images
		var property = self.direction == 1 ? 'left' : 'right';
		image_set.each(function() {
			var item = $(this);
			item.css(property, pos_x);
			pos_x += item.outerWidth() + spacing;
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

		self.timer_id = setTimetou(self.controls._handle_next, self.timeout);
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

		// start timer
		self.timer_id = setInterval(self.controls._handle_next, self.timeout);

		// re-attach event handlers
		self.controls._attach_handlers(false, false, true);

		return self;
	};

	// finalize object
	self._init();
}
