/**
 * Dynamic Contact Form Support JavaScript
 * Caracal Development Framework
 *
 * Copyright (c) 2016. by Way2CU, http://way2cu.com
 * Authors: Mladen Mijatov
 *
 * Supported events:
 * - Callback for `submit-success`:
 * 		funcion (response_data), returns boolean
 *
 * 		If result is `false` it will prevent default dialog from
 * 		showing, giving custom scripts opportunity to present success
 * 		message in a different way.
 *
 * - Callback for `submit-error`:
 *   	function (status, description), returns boolean
 *
 *   	If result is `false` it will prevent default dialog from
 *   	showing giving custom scripts opportunity to present error
 *   	message in a different way.
 */

var Caracal = Caracal || new Object();
Caracal.ContactForm = Caracal.ContactForm || new Object();


ContactForm.Form = function(form_object) {
	var self = this;

	self._form = null;
	self._fields = null;
	self._communicator = null;
	self._overlay = null;
	self._message = null;
	self._silent = false;

	self.events = null;

	/**
	 * Complete object initialization.
	 */
	self._init = function() {
		// create object for communicating with backend
		self._communicator = new Communicator('contact_form');

		self._communicator
				.on_success(self._handle_success)
				.on_error(self._handle_error);

		// find form and fields
		self._form = $(form_object);
		self._fields = self._form.find('input,textarea,select');

		// connect form events
		self._form.submit(self._handle_submit);

		// get overlay
		self._overlay = self._form.find('div.overlay');
		if (self._overlay.length == 0) {
			self._overlay = $('<div>');
			self._overlay
					.addClass('overlay')
					.appendTo(self._form);
		}

		// create dialog
		if (Caracal.ContactForm.dialog == null) {
			Caracal.ContactForm.dialog = new Dialog();
			Caracal.ContactForm.dialog.setTitle(language_handler.getText('contact_form', 'dialog_title'));
			Caracal.ContactForm.dialog.setSize(400, 100);
			Caracal.ContactForm.dialog.setScroll(false);
			Caracal.ContactForm.dialog.setClearOnClose(true);
		}

		// create message container
		self._message = $('<div>');
		self._message.css('padding', '20px');

		// create events handling system
		self.events = new Caracal.EventSystem();
		self.events
			.register('submit-error', 'boolean')
			.register('submit-success');
	};

	/**
	 * Get data from fields.
	 *
	 * @return object
	 */
	self._get_data = function() {
		var result = new Object();

		// collect data
		self._fields.each(function() {
			var field = $(this);
			var name = field.attr('name');
			var type = field.attr('type');

			switch (type) {
				case 'checkbox':
					if (field.attr('value')) {
						// ensure we have storage array
						if (result[name] == undefined) {
							result[name] = new Array();

						} else if (!(result[name] instanceof Array)) {
							var temp = result[name];
							result[name] = new Array();
							result[name].push(temp);
						}

						// add current value to the list
						if (this.checked)
							result[name].push(field.val());

					} else {
						// checkboxes without value are treated as on/off switches
						result[name] = this.checked ? 1 : 0;
					}
					break;

				case 'radio':
					if (result[name] == undefined) {
						var selected_radio = self._fields.filter('input:radio[name=' + name + ']:checked');
						if (selected_radio.length > 0)
							result[name] = selected_radio.val()
					}
					break;

				default:
					result[name] = field.val();
					break;
			}
		});

		// convert array values to string
		for (var index in result)
			if (result[index] instanceof Array)
				result[index] = result[index].join();

		return result;
	};

	/**
	 * Handle submitting a form.
	 *
	 * @param object event
	 * @return boolean
	 */
	self._handle_submit = function(event) {
		// prevent original form from submitting
		event.preventDefault();

		// collect data
		var data = self._get_data();

		// show overlay
		self._overlay.addClass('visible');

		// send data
		self._communicator.send('submit', data)
	};

	/**
	 * Handle successful data transmission.
	 *
	 * @param object data
	 */
	self._handle_success = function(data) {
		// hide overlay
		self._overlay.removeClass('visible');

		// configure and show dialog
		var response = self.events.trigger('submit-success', data);
		if (response) {
			self._message.html(data.message);
			Caracal.ContactForm.dialog.setError(data.error);
			Caracal.ContactForm.dialog.setContent(self._message);
			Caracal.ContactForm.dialog.show();
		}

		// clear form on success
		if (!data.error)
			self._form[0].reset();
	};

	/**
	 * Handle error in data transmission or on server side.
	 *
	 * @param object xhr
	 * @param string request_status
	 * @param string description
	 */
	self._handle_error = function(xhr, request_status, description) {
		// hide overlay
		self._overlay.removeClass('visible');

		// configure and show dialog
		var response = self.events.trigger('submit-error', request_status, description);
		if (response) {
			self._message.html(data.message);
			Caracal.ContactForm.dialog.setError(true);
			Caracal.ContactForm.dialog.setContent(self._message);
			Caracal.ContactForm.dialog.show();
		}
	};

	// finalize object
	self._init();
}

$(function() {
	Caracal.ContactForm.list = [];
	Caracal.ContactForm.dialog = null;

	$('form[data-dynamic]').each(function() {
		var form = new ContactForm(this);
		Caracal.ContactForm.list.push(form);
	});
});
