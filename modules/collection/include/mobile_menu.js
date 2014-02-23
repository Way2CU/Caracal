/**
 * Mobile Menu JavaScript
 *
 * Copyright (c) 2013. by Way2CU
 * Author: Mladen Mijatov
 */

function MobileMenu() {
	var self = this;

	self._visible = false;

	// site components
	self._top_bar = null;
	self._menu = null;

	// swipe configuration
	self._start_time = null;
	self._start_params = null;
	self._allowed_time = 500;
	self._minimum_distance = 130;
	self._vertical_deviation = 100;

	/**
	 * Finalize object initialization.
	 */
	self._init = function() {
		// assign site components
		self._top_bar = $('.mobile_title').eq(0);
		self._menu = $('.mobile_menu').eq(0);

		// configure top bar
		self._top_bar.css('z-index', 10001);

		// configure main menu
		self._menu
			.css({
				top: self._top_bar.height(),
				height: $(window).height() - self._top_bar.height(),
				zIndex: 10000
			})

		// connect events in main menu
		self._menu.find('a').click(self._handle_menu_item_click);

		// connect events in top bar
		self._top_bar.find('.menu').click(self._handle_menu_button_click);

		// connect window events
		$(window)
			.bind('resize', self._update_menu_size)
			.bind('touchstart', self._handle_touch_start)
			.bind('touchmove', self._handle_touch_move)
			.bind('touchend', self._handle_touch_end);
	};

	/**
	 * Update size of menu container.
	 */
	self._update_menu_size = function(event) {
		self._menu.css('height', $(window).height() - self._top_bar.height());
	};

	/**
	 * Show menu container.
	 */
	self.show_menu = function() {
		self._menu.addClass('visible');
		self._top_bar.addClass('menu_visible');
		self._visible = true;
	};

	/**
	 * Hide menu container.
	 */
	self.hide_menu = function() {
		self._menu.removeClass('visible');
		self._top_bar.removeClass('menu_visible');
		self._visible = false;
	};

	/**
	 * Handle clicking on menu button in top bar.
	 *
	 * @param object event
	 */
	self._handle_menu_button_click = function(event) {
		// prevent default behavior
		event.preventDefault();

		// toggle menu visibility
		if (self._visible)
			self.hide_menu(); else
			self.show_menu(self._from_left);
	};

	/**
	 * Handle clicking on menu item.
	 *
	 * @param object event
	 */
	self._handle_menu_item_click = function(event) {
		self.hide_menu();
	};

	/**
	 * Handle starting event for swipe.
	 *
	 * @param object event
	 */
	self._handle_touch_start = function(event) {
		var temp = event.originalEvent.changedTouches[0];

		// get data we need
		self._start_time = Date.now();
		self._start_params = {
				x: temp.pageX,
				y: temp.pageY
			};

		// prevent default handler
	};

	/**
	 * Prevent default behavior on dragging.
	 *
	 * @param object event
	 */
	self._handle_touch_move = function(event) {
		var temp = event.originalEvent.changedTouches[0];
		var vertical = Math.abs(temp.pageY - self._start_params.y);
		var horizontal = Math.abs(temp.pageX - self._start_params.x);

		if (horizontal > vertical)
			event.preventDefault();
	};

	/**
	 * Handle ending event for swipe.
	 *
	 * @param object event
	 */
	self._handle_touch_end = function(event) {
		var temp = event.originalEvent.changedTouches[0];
		var direction = 0;
		var distance = temp.pageX - self._start_params.x;
		var deviation = Math.abs(temp.pageY - self._start_params.y);
		var time = Date.now() - self._start_time;

		// determine in which direction user is swiping
		if (distance > 0)
			direction = 1; else
			direction = -1;

		// call event handler for swipe
		if (Math.abs(distance) >= self._minimum_distance && deviation < self._vertical_deviation && time <= self._allowed_time) {
			self._handle_swipe(direction);

			// prevent default behavior
			event.preventDefault();
		}
	};

	/**
	 * Handle swipe on body.
	 *
	 * @param integer direction
	 */
	self._handle_swipe = function(direction) {
		if (!self._visible && direction == 1) {
			self.show_menu(direction == 1);

		} else if (direction == -1) {
			self.hide_menu();
		}
	};

	// complete initialization
	self._init();
}

window['MobileMenu'] = MobileMenu;

// try to automatically create mobile menu
$(function() {
	if ($('.mobile_menu').length > 0 && $('.mobile_title').length > 0)
		new MobileMenu();
});
