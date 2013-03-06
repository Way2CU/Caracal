/**
 * Universal Scrollbar Component
 * 
 * Copyright (c) 2012. by Way2CU
 * Author: Mladen Mijatov
 */

/**
 * Constructor function for new scrollbar
 * 
 * @param string parent_selector	selector for parent
 * @param string content_selector	selector for elements to be scrolled, must be a child of parent
 * @param boolean is_rtl			whether scrollbar needs to be located on the left or right
 */
function Scrollbar(parent_selector, content_selector, is_rtl) {
	var self = this;

	if (typeof(parent_selector) == 'string')
		this._parents = $(parent_selector); else
		this._parents = parent_selector;

	if (is_rtl != undefined && is_rtl != null) 
		this._is_rtl = is_rtl; else
		if (typeof language_handler != 'undefined')
			this._is_rtl = language_handler.isRTL(); else
			this._is_rtl = false;

	/**
	 * Complete object initialization
	 */
	this.init = function() {
		// create scrollbar for each parent
		this._parents.each(function() {
			var item = $(this);
			var scroll_thumb = $('<div>');
			var content = item.find(content_selector).eq(0);
			var min_position = 0;
			var max_position = item.height();

			// create scroll thumb
			scroll_thumb
				.data('parent', item)
				.data('content', content)
				.addClass('scrollbar_thumb')
				.drag(self._handle_drag)
				.mousedown(self._handle_mouse_down);

			$(document).mouseup(function(event) {
					scroll_thumb.removeClass('active');
				});

			if (self._is_rtl)
				scroll_thumb.addClass('rtl');

			// modify parent
			item
				.css('position', 'relative')
				.append(scroll_thumb)
				.data('scroll', scroll_thumb)
				.mousewheel(self._handle_wheel);

			content
				.css('position', 'relative');

			// position thumb and modify variables
			if (scroll_thumb.css('margin-top') != undefined)
				min_position += parseInt(scroll_thumb.css('margin-top').replace('px', ''));

			if (scroll_thumb.css('margin-bottom') != undefined)
				max_position -= parseInt(scroll_thumb.css('margin-bottom').replace('px', ''));

			// reduce max_position by the size of thumb
			max_position -= scroll_thumb.height();

			// store max and mix values and position element
			scroll_thumb
				.css('top', min_position)
				.data('min', min_position)
				.data('max', max_position);
		});
	};

	/**
	 * Handle mouse down event on scroll thumb
	 */
	this._handle_mouse_down = function(event) {
		var item = $(this);
		
		// add class to thumb
		item.addClass('active');

		// prevent default behavior
		event.preventDefault();
	};

	/**
	 * Handle dragging scroll thumb
	 * 
	 * @param object event
	 * @param object position
	 */
	this._handle_drag = function(event, position) {
		var item = $(this);
		var container = item.data('parent');
		var content = item.data('content');
		var max_position = item.data('max');
		var min_position = item.data('min');
		var content_offset = 0;
		var difference = content.height() - container.height();

		// return if there's nothing to scroll
		if (difference < 0)
			return false;

		// calculate new position
		var new_position = position.offsetY - container.offset().top;
		if (new_position > max_position)
			new_position = max_position;

		if (new_position < min_position)
			new_position = min_position;

		// set new position
		item.css('top', new_position);

		// move content
		content_offset = Math.round((difference * (new_position - min_position)) / (max_position - min_position));
		content.css('top', -content_offset);

		// prevent default behavior
		event.preventDefault();
	};

	/**
	 * Handle mouse wheel scrolling
	 *
	 * @param object event
	 * @param int delta
	 */
	this._handle_wheel = function(event, delta) {
		var item = $(this).data('scroll');
		var container = item.data('parent');
		var content = item.data('content');
		var max_position = item.data('max');
		var min_position = item.data('min');
		var content_offset = 0;
		var max_difference = content.height() - container.height();
		var new_position = 0;

		// return if there's nothing to scroll
		if (max_difference < 0)
			return false;

		// calculate position of content
		content_offset = content.position().top + (delta * 100);
		if (content_offset > 0)
			content_offset = 0;

		if (content_offset < -max_difference)
			content_offset = -max_difference;

		// calculate position of thumb
		new_position = Math.round(((max_position - min_position) * Math.abs(content_offset)) / max_difference);
		if (new_position > max_position)
			new_position = max_position;

		if (new_position < min_position)
			new_position = min_position;

		// move content
		content.css('top', content_offset);
		item.css('top', new_position); 

		// prevent default behavior
		event.preventDefault();
	};

	/**
	 * Method called by the user when container size or content
	 * was changed.
	 */
	this.content_updated = function(index) {
		if (index == undefined || index == null)
			var index = 0;

		var item = this._parents.eq(index);
		var scroll_thumb = item.data('scroll');
		var max_position = item.height();
		var min_position = 0;

		// position thumb and modify variables
		if (scroll_thumb.css('margin-top') != undefined)
			min_position += parseInt(scroll_thumb.css('margin-top').replace('px', ''));

		if (scroll_thumb.css('margin-bottom') != undefined)
			max_position -= parseInt(scroll_thumb.css('margin-bottom').replace('px', ''));

		// reduce max_position by the size of thumb
		max_position -= scroll_thumb.height();

		// store max and mix values and position element
		scroll_thumb
			.css('top', min_position)
			.data('min', min_position)
			.data('max', max_position);
	};

	// initialize object elements
	this.init();
}
