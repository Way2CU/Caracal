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
	self.reached_page = 0;
	self.allow_forward = true;
	self.disabled_pages = new Array();
	self.page_redirection = new Object();
	self.form = null;
	self.submit_on_end = false;

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
			button_next.click(self._handleNext);

		if (button_previous.length > 0)
			button_previous.click(self._handlePrevious);
	};

	/**
	 * Handle clicking on submit button.
	 *
	 * @param object event
	 * @return boolean
	 */
	self._handleNext = function(event) {
		if (self.current_page + 1 < self.pages.length) {
			self._switchContainer(self.current_page + 1)
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
		if (self.current_page - 1 >= 0) {
			self._switchContainer(self.current_page - 1)
			event.preventDefault();
		}
	};

	/**
	 * Change active page.
	 *
	 * @param integer page
	 */
	self._switchContainer = function(page) {
		var new_page = page;

		// skip page if specified one is disabled
		if (self.isPageDisabled(page)) 
			new_page += page > self.current_page ? 1 : -1;

		// submit on last page
		if (new_page > self.pages.length - 1 && self.submit_on_end) 
			self.form.submit()

		// if first or last page is disabled ignore switch request
		if (new_page < 0 || new_page > self.pages.length -1) 
			return;

		// redirect if needed
		if (new_page in self.page_redirection) {
			window.navigate(self.page_redirection[new_page]);
			return;
		}

		// make sure we don't show animation if page is already visible
		if (new_page == self.current_page)
			return;

		// switch page
		if (self.current_page != null) {
			var to_hide = self.pages.eq(self.current_page);
			var to_show = self.pages.eq(new_page);

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
				$(this).css('display', index == new_page ? 'block' : 'none');
			});
		}

		// update controls if available
		if (self.controls != null && self.controls.length > 0) 
			self.controls.each(function() {
				var control = $(this);

				if (control.data('page') == new_page)
					control.addClass('active'); else
					control.removeClass('active');
			});

		// set current page
		self.current_page = new_page;

		// update reached page
		if (self.current_page > self.reached_page)
			self.reached_page = self.current_page;
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
	 * Handle click comming from controls. This method is affected by the
	 * allow_forward option.
	 *
	 * @param object event
	 */
	self.handleControlClick = function(event) {
		var page = $(this).data('page');

		if (page != null)
			if ((!self.allow_forward && page <= self.reached_page) || self.allow_forward)
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
			self.controls.click(self.handleControlClick);
		}

		return self;
	};

	/**
	 * Attach form to page control. This function is used along with
	 * setSubmitOnEnd option. After reaching last page specified form
	 * will receive submit signal.
	 *
	 * @param string selector
	 */
	self.attachForm = function(selector) {
		self.form = $(selector);
		return self;
	};

	/**
	 * Make page control submit specified form when all pages have been flipped.
	 *
	 * @param boolean submit_on_end
	 */
	self.setSubmitOnEnd = function(submit_on_end) {
		self.submit_on_end = submit_on_end;
		return self;
	};

	/**
	 * Enable previously disabled page.
	 *
	 * @param integer page
	 */
	self.enablePage = function(page) {
		var index = self.disabled_pages.indexOf(page);

		// remove page from list of disabled pages
		if (index > -1) {
			self.disabled_pages.splice(index, 1);

			// update controls
			self.controls.each(function(index) {
				var control = $(this);

				if (control.data('page') == page)
					control.removeClass('disabled');
			});
		}

		return self;
	};

	/**
	 * Disable page.
	 *
	 * @param integer page
	 */
	self.disablePage = function(page) {
		// add page to the list of disabled pages
		if (self.disabled_pages.indexOf(page) == -1)
			self.disabled_pages.push(page);

		// update controls
		self.controls.each(function(index) {
			var control = $(this);

			if (control.data('page') == page)
				control.addClass('disabled');
		});

		return self;
	};

	/**
	 * Check if specified page is disabled.
	 *
	 * @param integer page
	 * @return boolean
	 */
	self.isPageDisabled = function(page) {
		return self.disabled_pages.indexOf(page) > -1;
	};

	/**
	 * Add page redirection.
	 *
	 * @param integer page
	 * @param string url
	 */
	self.addPageRedirection = function(page, url) {
		self.page_redirection[page] = url;
	};

	/**
	 * Remove page redirection.
	 *
	 * @param integer page
	 */
	self.removePageRedirection = function(page) {
		if (page in self.page_redirection)
			delete self.page_redirection[page];
	};

	/**
	 * Set the behavior of page control when clicking on step that hasn't been
	 * reached yet. This does not apply to next/previous buttons only on controls.
	 *
	 * @param boolean allow
	 */
	self.setAllowForward = function(allow) {
		self.allow_forward = allow;
		return self;
	};

	// finish object initialization
	self.init();
}
