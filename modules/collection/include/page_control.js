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
	self.wrap_around = false;
	self.pause_on_hover = false;
	self.interval_id = null;
	self.interval_time = 0;

	// signal handlers
	self.on_page_flip = new Array();
	self.on_submit = new Array();

	/**
	 * Finalize object initalization.
	 */
	self.init = function() {
		self.container = $(selector || 'div.pages');
		self.container.hover(
				self._handleContainerMouseEnter,
				self._handleContainerMouseLeave
			);

		// get all pages
		self.pages = self.container.find(page_selector || 'div.page');
		self.pages.each(self._connectButtonEvents);

		self.showPage(0);
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
			button_next.on('click', self._handleNext);

		if (button_previous.length > 0)
			button_previous.on('click', self._handlePrevious);
	};

	/**
	 * Handle clicking on submit button.
	 *
	 * @param object event
	 * @return boolean
	 */
	self._handleNext = function(event) {
		self.nextPage();
		event.preventDefault();
	};

	/**
	 * Handle clicking on back button.
	 *
	 * @param object event
	 * @return boolean
	 */
	self._handlePrevious = function(event) {
		self.previousPage();
		event.preventDefault();
	};

	/**
	 * Handle periodic switches.
	 */
	self._handleInterval = function() {
		// show next page
		self.showPage(self.current_page + 1);
	};

	/**
	 * Handle mouse entering container.
	 */
	self._handleContainerMouseEnter = function(event) {
		if (!self.pause_on_hover)
			return;

		if (self.interval_id != null)
			clearInterval(self.interval_id);

		self.interval_id = null;
	};

	/**
	 * Handle mouse leaving container.
	 */
	self._handleContainerMouseLeave = function(event) {
		if (!self.pause_on_hover)
			return;

		if (self.interval_id == null)
			self.interval_id = setInterval(self._handleInterval, self.interval_time);
	};

	/**
	 * Change active page.
	 *
	 * @param integer page
	 */
	self.showPage = function(page) {
		var new_page = page;

		// check if validator is set
		var validator = self.pages.eq(self.current_page).data('validator');
		if ((validator !== undefined && new_page > self.current_page) && !validator(self.current_page))
			return;

		// skip page if specified one is disabled
		if (self.isPageDisabled(page))
			new_page += page > self.current_page ? 1 : -1;

		// submit on last page
		if (new_page >= self.pages.length - 1 && self.submit_on_end) {
			// page-flip signal should be emitted before submit
			if (!self._emitSignal('page-flip', self.current_page, new_page))
				return;

			// emit submit signal
			if (!self._emitSignal('submit', current_page))
				return;

			// submit form
			self.form.submit()
		}

		// support wrapping pages
		if (self.wrap_around)
			if (new_page > self.pages.length - 1) {
				new_page = 0;

			} else if (new_page < 0) {
				new_page = self.pages.length - 1;
			}

		// redirect if needed
		if (new_page in self.page_redirection) {
			window.navigate(self.page_redirection[new_page]);
			return;
		}

		// make sure we don't show animation if page is already visible
		if (new_page == self.current_page)
			return;

		// emit signal and exit unless all of the handlers approve
		if (!self._emitSignal('page-flip', self.current_page, new_page))
			return;

		// switch page
		if (self.current_page != null) {
			var to_hide = self.pages.eq(self.current_page);
			var to_show = self.pages.eq(new_page);

			// swap pages containers
			to_hide.removeClass('visible');
			to_show.addClass('visible');
		} else {
			self.pages.eq(new_page).addClass('visible');
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
	 * Emit signal with specified parameters. This function accepts more than one
	 * parameter. All except first parameter will be passed to callback function.
	 *
	 * @param string signal_name
	 * @param ...
	 * @return boolean
	 */
	self._emitSignal = function(signal_name) {
		var result = true;
		var params = new Array();
		var list = null;

		// prepare arguments
		for (var index in arguments)
			params.push(arguments[index]);
		params = params.slice(1);

		// get list of functions to call
		switch(signal_name) {
			case 'page-flip':
				list = self.on_page_flip;
				break;

			case 'submit':
				list = self.on_submit;
				break;
		}

		// emit signal
		if (list != null && list.length > 0)
			for (var i=0, length=list.length; i < length; i++) {
				var callback = list[i];

				if (!callback.apply(this, params)) {
					result = false;
					break;
				}
			}

		return result;
	};

	/**
	 * Switch to next page.
	 */
	self.nextPage = function() {
		self.showPage(self.current_page + 1);
	};

	/**
	 * Switch to previos page.
	 */
	self.previousPage = function() {
		self.showPage(self.current_page - 1);
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
			self.showPage(page);

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
				self.showPage(page);

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
			self.controls.on('click', self.handleControlClick);

			// highlight selected control
			self.controls.eq(self.current_page).addClass('active');
		}

		return self;
	};

	/**
	 * Attach click event for previous page control.
	 *
	 * @param object control
	 */
	self.attachPreviousControl = function(control) {
		control.on('click', self._handlePrevious);
		return self;
	};

	/**
	 * Attach click event for next page control.
	 *
	 * @param object control
	 */
	self.attachNextControl = function(control) {
		control.on('click', self._handleNext);
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
	 * @param object page
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
	 * @param object page
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

	/**
	 * Set automatic switch interval.
	 *
	 * @param integer interval
	 */
	self.setInterval = function(interval) {
		// store interval timeout
		self.interval_time = interval;

		// clear existing interval
		if (self.interval_id != null)
			clearInterval(self.interval_id);

		// create interval
		self.interval_id = setInterval(self._handleInterval, self.interval_time);

		return self;
	};

	/**
	 * Configure page control to start pages from beginning once last is reached.
	 *
	 * @param boolean wrap
	 */
	self.setWrapAround = function(wrap) {
		self.wrap_around = wrap;

		return self;
	};

	/**
	 * Connect function to be called when specified signal is emitted.
	 *
	 * Callback for page-flip:
	 * 		function (current_page, new_page), returns boolean
	 *
	 * Callback for submit:
	 * 		function (current_page), returns boolean
	 *
	 * @param string signal_name
	 * @param function callback
	 * @param boolean top
	 */
	self.connect = function(signal_name, callback, top) {
		switch (signal_name) {
			case 'page-flip':
				if (!top)
					self.on_page_flip.push(callback); else
					self.on_page_flip.splice(0, 0, callback);

				break;

			case 'submit':
				if (!top)
					self.on_submit.push(callback); else
					self.on_submit.splice(0, 0, callback);

				break;

			default:
				break;
		}

		return self;
	};

	/**
	 * Make specified function act as validator for specified page.
	 * If function returns false page will not be switched.
	 *
	 * Example callback:
	 *
	 * function check(current_page) {
	 * 		result = true;
	 *
	 * 		if (something)
	 * 			result = false;
	 *
	 * 		return result;
	 * }
	 *
	 * @param integer page
	 * @param callable validator
	 */
	self.setValidatorFunction = function(page, validator) {
		self.pages.eq(page).data('validator', validator);
		return self;
	};

	/**
	 * Enable pausing interval based sliders when mouse hovers over container.
	 *
	 * @param boolean pause_on_hover
	 */
	self.setPauseOnHover = function(pause_on_hover) {
		self.pause_on_hover = pause_on_hover;
		return self;
	};

	// finish object initialization
	self.init();
}
