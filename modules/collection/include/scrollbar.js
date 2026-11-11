/**
 * Universal Scrollbar Component
 *
 * Copyright (c) 2012. by Way2CU
 * Author: Mladen Mijatov
 */

/**
 * Constructor function for new scrollbar
 *
 * @param mixed parent_selector		selector, DOM element or list of elements for parent
 * @param string content_selector	selector for elements to be scrolled, must be a child of parent
 * @param boolean is_rtl			whether scrollbar needs to be located on the left or right
 */
function Scrollbar(parent_selector, content_selector, is_rtl) {
	var self = this;

	self._parents = [];
	self._items = [];
	self._is_rtl = false;
	self._drag = null;

	/**
	 * Complete object initialization
	 */
	self.init = function() {
		self._parents = self._get_elements(parent_selector);

		if (is_rtl != undefined && is_rtl != null)
			self._is_rtl = is_rtl; else
			if (typeof Caracal.language != 'undefined' && Caracal.language != null)
				self._is_rtl = Caracal.language.is_rtl();

		// create scrollbar for each parent
		for (var i = 0; i < self._parents.length; i++)
			self._create_scrollbar(self._parents[i]);
	};

	/**
	 * Return list of elements for specified selector, DOM element or
	 * list of elements. Array-like collections, such as jQuery objects,
	 * are supported for backwards compatibility.
	 *
	 * @param mixed source
	 * @return array
	 */
	self._get_elements = function(source) {
		var result = [];

		if (typeof source == 'string')
			result = Array.prototype.slice.call(document.querySelectorAll(source)); else
		if (source instanceof Node)
			result = [source]; else
		if (source && typeof source.length == 'number')
			result = Array.prototype.slice.call(source);

		return result;
	};

	/**
	 * Return numeric value of specified style property.
	 *
	 * @param object element
	 * @param string property
	 * @return number
	 */
	self._get_style_value = function(element, property) {
		return parseFloat(window.getComputedStyle(element)[property]) || 0;
	};

	/**
	 * Return height of the element without padding and border.
	 *
	 * @param object element
	 * @return number
	 */
	self._get_height = function(element) {
		var result = 0;

		if (element !== null)
			result = element.clientHeight
				- self._get_style_value(element, 'paddingTop')
				- self._get_style_value(element, 'paddingBottom');

		return result;
	};

	/**
	 * Create scroll thumb for specified parent and connect events.
	 *
	 * @param object parent
	 */
	self._create_scrollbar = function(parent) {
		var item = {
				parent: parent,
				content: parent.querySelector(content_selector),
				thumb: document.createElement('div'),
				min: 0,
				max: 0
			};

		// create scroll thumb
		item.thumb.classList.add('scrollbar_thumb');
		item.thumb.style.touchAction = 'none';

		if (self._is_rtl)
			item.thumb.classList.add('rtl');

		item.thumb.addEventListener('pointerdown', function(event) {
			self._handle_pointer_down(event, item);
		});
		item.thumb.addEventListener('pointermove', function(event) {
			self._handle_pointer_move(event, item);
		});
		item.thumb.addEventListener('pointerup', function(event) {
			self._handle_pointer_up(event, item);
		});
		item.thumb.addEventListener('pointercancel', function(event) {
			self._handle_pointer_up(event, item);
		});

		// modify parent
		parent.style.position = 'relative';
		parent.appendChild(item.thumb);
		parent.addEventListener('wheel', function(event) {
			self._handle_wheel(event, item);
		}, {passive: false});

		if (item.content !== null)
			item.content.style.position = 'relative';

		self._items.push(item);
		self._update_limits(item);
	};

	/**
	 * Calculate minimum and maximum position of scroll thumb and
	 * move it to the starting position.
	 *
	 * @param object item
	 */
	self._update_limits = function(item) {
		var min_position = self._get_style_value(item.thumb, 'marginTop');
		var max_position = self._get_height(item.parent) - self._get_style_value(item.thumb, 'marginBottom');

		// reduce max_position by the size of thumb
		max_position -= self._get_height(item.thumb);

		// store max and min values and position element
		item.min = min_position;
		item.max = max_position;
		item.thumb.style.top = min_position + 'px';
	};

	/**
	 * Limit thumb position to allowed range.
	 *
	 * @param object item
	 * @param number position
	 * @return number
	 */
	self._limit_position = function(item, position) {
		var result = position;

		if (result > item.max)
			result = item.max;

		if (result < item.min)
			result = item.min;

		return result;
	};

	/**
	 * Handle pressing scroll thumb.
	 *
	 * @param object event
	 * @param object item
	 */
	self._handle_pointer_down = function(event, item) {
		if (event.button == 0) {
			var thumb_top = item.thumb.getBoundingClientRect().top;
			var parent_top = item.parent.getBoundingClientRect().top;

			// store starting position
			self._drag = {
					item: item,
					pointer: event.clientY,
					offset: thumb_top - parent_top
				};

			// add class to thumb
			item.thumb.classList.add('active');
			item.thumb.setPointerCapture(event.pointerId);

			// prevent default behavior
			event.preventDefault();
		}
	};

	/**
	 * Handle dragging scroll thumb.
	 *
	 * @param object event
	 * @param object item
	 */
	self._handle_pointer_move = function(event, item) {
		if (self._drag !== null && self._drag.item === item) {
			var difference = self._get_height(item.content) - self._get_height(item.parent);

			// move only if there is something to scroll
			if (item.content !== null && difference >= 0 && item.max > item.min) {
				var new_position = self._drag.offset + (event.clientY - self._drag.pointer);
				new_position = self._limit_position(item, new_position);

				// set new position
				item.thumb.style.top = new_position + 'px';

				// move content
				var content_offset = Math.round((difference * (new_position - item.min)) / (item.max - item.min));
				item.content.style.top = -content_offset + 'px';
			}

			// prevent default behavior
			event.preventDefault();
		}
	};

	/**
	 * Handle releasing scroll thumb.
	 *
	 * @param object event
	 * @param object item
	 */
	self._handle_pointer_up = function(event, item) {
		self._drag = null;
		item.thumb.classList.remove('active');

		if (item.thumb.hasPointerCapture(event.pointerId))
			item.thumb.releasePointerCapture(event.pointerId);
	};

	/**
	 * Handle mouse wheel scrolling.
	 *
	 * @param object event
	 * @param object item
	 */
	self._handle_wheel = function(event, item) {
		var parent_height = self._get_height(item.parent);
		var max_difference = self._get_height(item.content) - parent_height;
		var distance = event.deltaY;

		// convert lines and pages to pixels
		if (event.deltaMode == 1)
			distance *= 100 / 3; else
		if (event.deltaMode == 2)
			distance *= parent_height;

		// move only if there is something to scroll
		if (item.content !== null && max_difference > 0) {
			// calculate position of content
			var content_offset = self._get_style_value(item.content, 'top') - distance;
			if (content_offset > 0)
				content_offset = 0;

			if (content_offset < -max_difference)
				content_offset = -max_difference;

			// calculate position of thumb
			var new_position = item.min + Math.round(((item.max - item.min) * Math.abs(content_offset)) / max_difference);
			new_position = self._limit_position(item, new_position);

			// move content
			item.content.style.top = content_offset + 'px';
			item.thumb.style.top = new_position + 'px';
		}

		// prevent default behavior
		event.preventDefault();
	};

	/**
	 * Method called by the user when container size or content
	 * was changed.
	 *
	 * @param integer index
	 */
	self.content_updated = function(index) {
		if (index == undefined || index == null)
			index = 0;

		if (index in self._items)
			self._update_limits(self._items[index]);
	};

	// initialize object elements
	self.init();
}
