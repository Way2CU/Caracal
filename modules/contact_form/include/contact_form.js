/**
 * Dynamic Contact Form Support JavaScript
 * Caracal Development Framework
 *
 * Copyright (c) 2014. by Way2CU, http://way2cu.com
 * Authors: Mladen Mijatov
 */

var contact_form_dialog = null;

function ContactForm(form_object) {
	var self = this;

	self._form = null;
	self._fields = null;
	self._communicator = null;
	self._overlay = null;
	self._message = null;

	/**
	 * Complete object initialization.
	 */
	self._init = function() {
		// create object for communicating with backend
		self._communicator = new Communicator('contact_form');

		self._communicator
				.on_success(self._handle_success)
				.on_error(self._handle_error)

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
		if (contact_form_dialog == null) {
			contact_form_dialog = new Dialog();
			contact_form_dialog.setTitle(language_handler.getText('contact_form', 'dialog_title'));
			contact_form_dialog.setSize(400, 100);
			contact_form_dialog.setScroll(false);
			contact_form_dialog.setClearOnClose(true);
		}

		// create message container
		self._message = $('<div>');
		self._message.css('padding', '20px');
	}

	/**
	 * Get data from fields.
	 *
	 * @return object
	 */
	self._get_data = function() {
		var result = {};

		self._fields.each(function() {
			var field = $(this);
			var name = field.attr('name');
			var type = field.attr('type');

			switch (type) {
				case 'checkbox':
					result[name] = this.checked ? 1 : 0;
					break;

				case 'radio':
					if (result[name] == undefined) {
						var selected_radio = self._fields.find('input:radio[name=' + name + ']:checked');
						if (selected_radio.length > 0)
							result[name] = selected_radio.val()
					}
					break;

				default:
					result[name] = field.val();
					break;
			}

		});

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
		self._message.html(data.message);
		contact_form_dialog.setError(data.error);
		contact_form_dialog.setContent(self._message);
		contact_form_dialog.show();

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
		self._message.html(data.message);
		contact_form_dialog.setError(true);
		contact_form_dialog.setContent(self._message);
		contact_form_dialog.show();
	};

	// finalize object
	self._init();
}

$(function() {
	$('form[data-dynamic]').each(function() {
		new ContactForm(this);
	});
});
