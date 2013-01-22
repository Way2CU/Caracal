/**
 * Support for multi-part form
 *
 * Copyright (c) 2013. by Way2CU
 * Author: Mladen Mijatov
 */

function FormSteps(form_selector) {
	var self = this;

	self.steps = null;
	self.current_step = null;
	self.form = null;

	/**
	 * Finalize object initalization.
	 */
	self.init = function() {
		// get all steps
		var selector = (form_selector != undefined) ? form_selector : 'form';
		self.form = $(selector);

		// get all steps
		self.steps = self.form.find('div.step');
		self.steps.each(self._connectButtonEvents);

		self._switchContainer(0);
	};

	/**
	 * Connect button events for each step.
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

		if (index + 1 < self.steps.length) {
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
	 * Change active container.
	 *
	 * @param integer step
	 */
	self._switchContainer = function(step) {
		if (self.current_step != null) {
			var to_hide = self.steps.eq(self.current_step);
			var to_show = self.steps.eq(step);

			if (to_hide != to_show)
				// swap step containers
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
			self.steps.each(function(index) {
				$(this).css('display', index == step ? 'block' : 'none');
			});
		}

		self.current_step = step;
	};

	// finish object initialization
	self.init();
}
