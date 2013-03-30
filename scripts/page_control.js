/**
 * Page Control Implementation
 *
 * Copyright (c) 2013. by Way2CU
 * Author: Mladen Mijatov
 */

function PageControl(selector, page_selector) {
	var self = this;

	self.pages = null;
	self.current_page = null;
	self.container = null;
	self.controls = null;

	/**
	 * Finalize object initalization.
	 */
	self.init = function() {
		self.container = $(selector || 'div.pages');

		// get all pages
		self.pages = self.container.find(page_selector || 'div.page');
		self.pages.each(self._connectButtonEvents);

		self._switchContainer(0);
	};

	/**
	 * Connect button events for each page.
	 *
	 * @param integer index
	 */
	self._connectButtonEvents = function(index) {
		var button_next = $(this).find('button.next, input[type=button].next, input[type=submit].next');
		var button_previous = $(this).find('button.previous, input[type=button].previous, input[type=submit].previous');

		if (button_next.length > 0)
			button_next.data('index', index).click(self._handleNext);

		if (button_previous.length > 0)
			button_previous.data('index', index).click(self._handlePrevious);
	};

	/**
	 * Handle clicking on submit button.
	 *
	 * @param object event
	 * @return boolean
	 */
	self._handleNext = function(event) {
		var index = $(this).data('index');

		if (index + 1 < self.pages.length) {
			self._switchContainer(index + 1)
			event.preventDefault();
		}
	};

	/**
	 * Handle clicking on back button.
	 *
	 * @param object event
	 * @return boolean
	 */
	self._handlePrevious = function(event) {
		var index = $(this).data('index');

		if (index - 1 >= 0) {
			self._switchContainer(index - 1)
			event.preventDefault();
		}
	};

	/**
	 * Change active page.
	 *
	 * @param integer page
	 */
	self._switchContainer = function(page) {
		if (self.current_page != null) {
			var to_hide = self.pages.eq(self.current_page);
			var to_show = self.pages.eq(page);

			if (to_hide != to_show)
				// swap pages containers
				to_hide
					.css('display', 'block')
					.animate({opacity: 0}, 200, function() {
						to_hide.css('display', 'none');
						to_show
							.css({
								display: 'block',
								opacity: 0
							})
							.animate({opacity: 1}, 200);
					});
		} else {
			self.pages.each(function(index) {
				$(this).css('display', index == page ? 'block' : 'none');
			});
		}

		self.current_page = page;
	};

	/**
	 * Method that can be used to handle clicking on specified page or a button.
	 * This method looks for HTML5 data-page parameter to determine which page to
	 * show. If no page is defined event is ignored.
	 *
	 * @param object event
	 */
	self.handleClick = function(event) {
		var page = $(this).data('page');

		if (page != null)
			self._switchContainer(page);

		event.preventDefault();
	};

	/**
	 * Connect click event handler to children of specified selector. If children
	 * do not specify data-page their indexes will be applied.
	 *
	 * @param string selector
	 */
	self.attachControls = function(selector) {
		self.controls = $(selector);

		if (self.controls.length > 0) {
			// make sure every control has a page index associated
			self.controls.each(function(index) {
				var control = $(this);

				if (control.data('page') == undefined)
					control.data('page', index);
			});

			// conect event handler
			self.controls.click(self.handleClick);
		}

		return self;
	};

	// finish object initialization
	self.init();
}
