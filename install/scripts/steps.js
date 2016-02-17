/**
 * Steps JavaScript
 * Caracal Installation Script
 *
 * Copyright (c) 2014. by Way2CU, http://way2cu.com
 * Authors: Mladen Mijatov
 */

function Steps() {
	var self = this;

	self._step_container = $('nav#steps');
	self._page_container = $('div#pages');
	self._steps = self._step_container.find('a');
	self._pages = self._page_container.find('section');
	self._buttons = $('footer button');

	self._current_step = 0;

	/**
	 * Complete object initialization.
	 */
	self._init = function() {
		// connect step button events
		self._steps
				.each(function(index) {
					$(this).data('index', index+1);
				})
				.click(self._handle_step_click);

		// connect footer buttons
		self._buttons.filter('.next').click(self._handle_next_click);
		self._buttons.filter('.previous').click(self._handle_previous_click);
	};

	/**
	 * Handle clicking on step in steps navigation.
	 *
	 * @param object event
	 */
	self._handle_step_click = function(event) {
		// prevent default behavior
		event.preventDefault();

		// change step
		var index = $(this).data('index');
		self.set_current(index);
	};

	self._handle_next_click = function(event) {
		// prevent default behavior
		event.preventDefault();

		// show steps if needed
		if (!self._step_container.hasClass('visible'))
			self._step_container.addClass('visible');

		// move to next step
		self.set_current(self._current_step + 1);
	};

	self._handle_previous_click = function(event) {
		// prevent default behavior
		event.preventDefault();

		// move to next step
		self.set_current(self._current_step - 1);
	};

	/**
	 * Show steps.
	 */
	self.show_steps = function() {
		self._step_container.addClass('visible');
	};

	/**
	 * Hide steps.
	 */
	self.hide_steps = function() {
		self._step_container.removeClass('visible');
	};

	/**
	 * Mark step as completed.
	 */
	self.mark_completed = function(step, completed) {
		// mark of unmark specified step as completed
		if (completed)
			self._steps.eq(step).addClass('checked'); else
			self._steps.eq(step).removeClass('checked');

		// show install button once all the steps are completed
		if (self._steps.filter('.checked').length == self._steps.length)
			self._buttons.filter('.install').show(); else
			self._buttons.filter('.install').hide();
	};

	/**
	 * Set current step.
	 */
	self.set_current = function(step) {
		// update steps navigation
		if (step > 0) {
			var current_step = self._steps.eq(step-1);
			current_step.addClass('active');
			self._steps.not(current_step).removeClass('active');
		}

		// update pages
		var current_page = self._pages.eq(step);
		current_page.addClass('active');
		self._pages.not(current_page).removeClass('active');

		// update buttons
		if (step > 1)
			self._buttons.filter('.previous').show(); else
			self._buttons.filter('.previous').hide();

		// store current step
		self._current_step = step;
	};

	// finalize object
	self._init();
}
