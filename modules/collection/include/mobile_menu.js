/**
 * Mobile Menu JavaScript
 *
 * Copyright (c) 2017. by Way2CU
 * Author: Mladen Mijatov
 */

var Caracal = Caracal || new Object();


Caracal.MobileMenu = function() {
	var self = this;

	self._visible = false;

	// site components
	self._top_bar = null;
	self._menu = null;

	// swipe configuration
	self._page_rtl = false;
	self._start_time = null;
	self._start_params = null;
	self._ignore_swipe = false;
	self._allowed_time = 500;
	self._minimum_distance = 130;
	self._vertical_deviation = 100;

	/**
	 * Finalize object initialization.
	 */
	self._init = function() {
		// detect if page is in RTL
		self._page_rtl = document.documentElement.getAttribute('dir') == 'rtl';

		// assign site components
		self._top_bar = document.querySelector('.mobile_title');
		self._menu = document.querySelector('.mobile_menu');

		// connect events in main menu
		if (self._menu !== null) {
			var links = self._menu.querySelectorAll('a');

			for (var i = 0; i < links.length; i++)
				links[i].addEventListener('click', self._handle_menu_item_click);
		}

		// connect events in top bar
		if (self._top_bar !== null) {
			var menu_toggle = self._top_bar.querySelector('.menu');

			// create menu toggle if it doesn't exist
			if (menu_toggle === null) {
				menu_toggle = document.createElement('a');
				menu_toggle.setAttribute('href', 'javascript: void(0);');
				menu_toggle.setAttribute('role', 'button');
				menu_toggle.classList.add('menu');
				self._top_bar.appendChild(menu_toggle);
			}

			menu_toggle.addEventListener('click', self._handle_menu_button_click);
		}

		// connect window events, listeners are passive so scrolling is never delayed
		window.addEventListener('touchstart', self._handle_touch_start, {passive: true});
		window.addEventListener('touchend', self._handle_touch_end, {passive: true});
		window.addEventListener('touchcancel', self._handle_touch_cancel, {passive: true});
	};

	/**
	 * Show menu container.
	 */
	self.show_menu = function() {
		if (self._menu !== null)
			self._menu.classList.add('visible');

		if (self._top_bar !== null)
			self._top_bar.classList.add('visible');

		self._visible = true;
	};

	/**
	 * Hide menu container.
	 */
	self.hide_menu = function() {
		if (self._menu !== null)
			self._menu.classList.remove('visible');

		if (self._top_bar !== null)
			self._top_bar.classList.remove('visible');

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
			self.show_menu();
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
	 * Check if specified element, or any of its parents, can be scrolled
	 * horizontally. Swipes over such elements belong to them, not the menu.
	 *
	 * @param object element
	 * @return boolean
	 */
	self._is_horizontally_scrollable = function(element) {
		var result = false;

		while (!result && element instanceof Element && element !== document.documentElement) {
			var overflow = window.getComputedStyle(element).overflowX;

			result = (overflow == 'auto' || overflow == 'scroll') && element.scrollWidth > element.clientWidth;
			element = element.parentNode;
		}

		return result;
	};

	/**
	 * Handle starting event for swipe.
	 *
	 * @param object event
	 */
	self._handle_touch_start = function(event) {
		var touch = event.changedTouches[0];

		// only single finger gestures outside of scrollable elements count
		self._ignore_swipe = event.touches.length > 1 || self._is_horizontally_scrollable(event.target);

		// get data we need
		self._start_time = Date.now();
		self._start_params = {
				x: touch.pageX,
				y: touch.pageY
			};
	};

	/**
	 * Handle ending event for swipe.
	 *
	 * @param object event
	 */
	self._handle_touch_end = function(event) {
		if (self._start_params !== null && !self._ignore_swipe) {
			var touch = event.changedTouches[0];
			var distance = touch.pageX - self._start_params.x;
			var deviation = Math.abs(touch.pageY - self._start_params.y);
			var time = Date.now() - self._start_time;

			// modification for RTL pages
			var directions = self._page_rtl ? [-1, 1] : [1, -1];
			var direction = distance > 0 ? directions[0] : directions[1];

			// call event handler for swipe
			if (Math.abs(distance) >= self._minimum_distance && deviation < self._vertical_deviation && time <= self._allowed_time)
				self._handle_swipe(direction);
		}

		self._start_params = null;
	};

	/**
	 * Handle gesture being taken over by the browser.
	 *
	 * @param object event
	 */
	self._handle_touch_cancel = function(event) {
		self._start_params = null;
	};

	/**
	 * Handle swipe on body.
	 *
	 * @param integer direction
	 */
	self._handle_swipe = function(direction) {
		if (!self._visible && direction == 1)
			self.show_menu(); else
		if (direction == -1)
			self.hide_menu();
	};

	// complete initialization
	self._init();
};
