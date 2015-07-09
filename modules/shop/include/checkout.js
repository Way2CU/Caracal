/**
 * Checkout Form Implemenentation
 *
 * Copyright (c) 2013. by Way2CU
 * Author: Mladen Mijatov
 */

// create namespaces if needed
var Site = Site || {};
var Caracal = Caracal || {};
Caracal.Shop = Caracal.Shop || {};


Caracal.Shop.BuyerInformationForm = function() {
	var self = this;

	self.backend_url = $('base').attr('href') + '/index.php';

	self.sign_in_form = $('div#sign_in.page');
	self.shipping_information_form = $('div#shipping_information.page');
	self.billing_information_form = $('div#billing_information.page');
	self.payment_method_form = $('div#payment_method.page');
	self.methods = $('div.payment_methods span');
	self.method_field = $('input[name=payment_method]');
	self.page_control = new PageControl('div#input_details div.pages');
	self.password_dialog = new Dialog();
	self.cvv_dialog = new Dialog();

	// local namespaces
	self.handler = {};
	self.validator = {};

	/**
	 * Complete object initialization.
	 */
	self.init = function() {
		// implement page control
		self.page_control
			.setAllowForward(false)
			.setSubmitOnEnd(true)
			.attachControls('div#checkout_steps a')
			.attachForm('div#input_details form');

		// load dialog titles from server
		language_handler.getTextArrayAsync(
					'shop',
					['title_password_dialog', 'title_cvv_dialog'],
					self._configure_dialogs
				);

		// configure dialogs
		self.password_dialog
				.setSize(400, 300)
				.setScroll(false)
				.setClearOnClose(false);

		self.cvv_dialog
				.setSize(642, 265)
				.setScroll(false)
				.setClearOnClose(false)
				.setContentFromDOM('img#what_is_cvv');

		// set validators used by page control
		self.sign_in_form.data('validator', self.validator.sign_in_page);
		self.shipping_information_form.data('validator', self.validator.shipping_information_page);
		self.billing_information_form.data('validator', self.validator.billing_information_page);

		if (self.payment_method_form.length > 0) {
			// no payment method was preselected, we need validator
			self.payment_method_form.data('validator', self.validator.payment_method_page);

		} else {
			// payment method was preselected, prepare billing page
			var method = $('div#input_details input[name=payment_method]');
			var billing_page_index = self.billing_information_form.index();

			// set billing page state
			if (method.data('provides-information') == 1)
				self.page_control.disablePage(billing_page_index); else
				self.page_control.enablePage(billing_page_index);
		}

		// check if user is already logged
		if (self.shipping_information_form.find('select[name=presets]').data('autoload') == 1)
			self._load_account_information();

		// connect events
		self.sign_in_form.find('input[name=existing_user]').change(self.handler.account_type_change);
		self.shipping_information_form.find('select[name=presets]').change(self.handler.shipping_information_preset_change);
		self.sign_in_form.find('a.password_recovery').click(self._show_password_dialog);
		self.billing_information_form.find('a.what_is_cvv').click(self._show_cvv_dialog);
		self.methods.click(self.handler.payment_method_click);
	};

	/**
	 * Function called once async load of text variables is completed.
	 */
	self._configure_dialogs = function(data) {
		self.password_dialog.setTitle(data['title_password_dialog']);
		self.cvv_dialog.setTitle(data['title_cvv_dialog']);
	};

	/**
	 * Show password recovery dialog.
	 *
	 * @param object event
	 */
	self._show_password_dialog = function(event) {
		event.preventDefault();
		self.password_dialog.show();
	};

	/**
	 * Show CVV explanation dialog.
	 *
	 * @param object event
	 */
	self._show_cvv_dialog = function(event) {
		event.preventDefault();
		self.cvv_dialog.show();
	};

	/**
	 * Load account information from backend.
	 */
	self._load_account_information = function() {
		new Communicator('shop')
			.on_success(self.handler.account_load_success)
			.on_error(self.handler.account_load_error)
			.get('json_get_account_info', null);
	};

	/**
	 * Handle changing type of account for buyers information.
	 *
	 * @param object event
	 */
	self.handler.account_type_change = function(event) {
		var selection = self.sign_in_form.find('input[name=existing_user]:checked').val();

		switch (selection) {
			// existing account
			case 'log_in':
				self.sign_in_form.find('div.new_account').removeClass('visible');
				self.sign_in_form.find('div.existing_account').addClass('visible');
				self.sign_in_form.find('div.guest_checkout').removeClass('visible');
				break;

			// new account
			case 'sign_up':
				self.sign_in_form.find('div.new_account').addClass('visible');
				self.sign_in_form.find('div.existing_account').removeClass('visible');
				self.sign_in_form.find('div.guest_checkout').removeClass('visible');
				break;

			// checkout as guest
			case 'guest':
			default:
				self.sign_in_form.find('div.new_account').removeClass('visible');
				self.sign_in_form.find('div.existing_account').removeClass('visible');
				self.sign_in_form.find('div.guest_checkout').addClass('visible');
				break;
		}
	};

	/**
	 * Handle change in shipping information preset control.
	 *
	 * @param object event
	 */
	self.handler.shipping_information_preset_change = function(event) {
		var control = $(this);
		var option = control.find('option[value='+control.val()+']');

		self.shipping_information_form.find('input[name=name]').val(option.data('name'));
		self.shipping_information_form.find('input[name=phone]').val(option.data('phone'));
		self.shipping_information_form.find('input[name=street]').val(option.data('street'));
		self.shipping_information_form.find('input[name=street2]').val(option.data('street2'));
		self.shipping_information_form.find('input[name=city]').val(option.data('city'));
		self.shipping_information_form.find('input[name=zip]').val(option.data('zip'));
		self.shipping_information_form.find('select[name=country]').val(option.data('country'));
		self.shipping_information_form.find('input[name=state]').val(option.data('state'));
		self.shipping_information_form.find('input[name=access_code]').val(option.data('access_code'));
	};

	/**
	 * Handle clicking on payment method.
	 *
	 * @param object event
	 */
	self.handler.payment_method_click = function(event) {
		var method = $(this);

		// set payment method before processing
		self.method_field.val(method.data('name'));

		// add selection class to
		self.methods.not(method).removeClass('active');
		method.addClass('active');

		// remove bad class
		self.methods.removeClass('bad');

		// disable billing information page if payment method provides info about buyer
		var billing_page_index = self.billing_information_form.index();
		if (method.data('provides-information') == 1)
			self.page_control.disablePage(billing_page_index); else
			self.page_control.enablePage(billing_page_index);
	};

	/**
	 * Handle account data loading.
	 *
	 * @param object data
	 */
	self.handler.account_load_success = function(data) {
		var presets = self.shipping_information_form.find('select[name=presets]');
		var email_field = self.sign_in_form.find('input[name=sign_in_email]');
		var password_field = self.sign_in_form.find('input[name=sign_in_password]');

		// reset presets
		presets.html('');

		// clear bad state from fields
		email_field.removeClass('bad');
		password_field.removeClass('bad');

		// populate shipping information with data received from the server
		self.shipping_information_form.find('input[name=first_name]').val(data.information.first_name);
		self.shipping_information_form.find('input[name=last_name]').val(data.information.last_name);
		self.shipping_information_form.find('input[name=email]').val(data.information.email);

		// empty preset
		var empty_option = $('<option>');

		empty_option
			.html(language_handler.getText('shop', 'new_preset'))
			.attr('value', 0)
			.appendTo(presets);

		// add different presets of data
		for (var index in data.delivery_addresses) {
			var address = data.delivery_addresses[index];
			var option = $('<option>');

			option
				.html(address.name)
				.attr('value', address.id)
				.data('name', address.name)
				.data('street', address.street)
				.data('street2', address.street2)
				.data('phone', address.phone)
				.data('city', address.city)
				.data('zip', address.zip)
				.data('state', address.state)
				.data('country', address.country)
				.appendTo(presets);
		}

		// alter field visibility
		self.shipping_information_form.find('select[name=presets]').parent().show();
		self.shipping_information_form.find('input[name=name]').parent().show();
		self.shipping_information_form.find('input[name=email]').parent().hide();
		self.shipping_information_form.find('hr').eq(0).show();
	};

	/**
	 * Handle server side error during account load process.
	 *
	 * @param object xhr
	 * @param string transfer_status
	 * @param string description
	 */
	self.handler.account_load_error = function(xhr, transfer_status, description) {
		var email_field = self.sign_in_form.find('input[name=sign_in_email]');
		var password_field = self.sign_in_form.find('input[name=sign_in_password]');

		// add "bad" class to input fields
		email_field.addClass('bad');
		password_field.addClass('bad');

		// show error message to user
		alert(description);
	};

	/**
	* Validate sign in page.
	*
	* @return boolean
	*/
	self.validator.sign_in_page = function() {
		var result = false;
		var next_button = self.sign_in_form.find('button.next');

		// check which option is selected
		var selection = self.sign_in_form.find('input[name=existing_user]:checked').val();

		switch (selection) {
			case 'log_in':
				var email_field = self.sign_in_form.find('input[name=sign_in_email]');
				var password_field = self.sign_in_form.find('input[name=sign_in_password]');
				var captcha_field = self.sign_in_form.find('label.captcha');

				// prepare data
				var data = {
						username: email_field.val(),
						password: password_field.val(),
						captcha: captcha_field.find('input').val()
					};

				new Communicator('backend')
						.on_success(function(data) {
							// load account information
							if (data.logged_in) {
								self._load_account_information();

								// hide captcha field
								captcha_field.addClass('hidden');

							} else {
								// failed login
								email_field.addClass('bad');
								password_field.addClass('bad');

								// show captcha if required
								if (data.show_captcha)
									captcha_field.removeClass('hidden');
							}

							// allow page switch
							result = data.logged_in;
						})
						.on_error(function() {
							// don't allow page switch
							result = false;

							// mark fields as bad
							email_field.addClass('bad');
							password_field.addClass('bad');
						})
						.set_asynchronous(false)
						.get('json_login', data);
				break;

			case 'sign_up':
				// get new account section
				var container = self.sign_in_form.find('div.new_account');
				var fields = container.find('input');
				var first_name = container.find('input[name=first_name]');
				var last_name = container.find('input[name=last_name]');

				// ensure required fields are filled in
				fields.each(function(index) {
					var field = $(this);

					if (field.attr('type') != 'checkbox')
						value_is_good = field.val() != ''; else
						value_is_good = field.is(':checked');

					if (field.data('required') == 1 && !value_is_good)
						field.addClass('bad'); else
						field.removeClass('bad');
				});

				// make sure passwords match
				var password = container.find('input[name=new_password]');
				var password_confirm = container.find('input[name=new_password_confirm]');

				if (password.val() != password_confirm.val()) {
					password.addClass('bad');
					password_confirm.addClass('bad');
				}

				// check if account with specified email already exists
				var email_field = self.sign_in_form.find('input[name=new_email]');

				if (email_field.val() != '') {
					var data = {
							section: 'shop',
							action: 'json_get_account_exists',
							email: email_field.val()
						};

					$.ajax({
						url: self.backend_url,
						type: 'GET',
						async: false,
						data: data,
						dataType: 'json',
						context: this,
						success: function(data) {
							if (data.account_exists) {
								email_field.addClass('bad');
								alert(data.message);
							} else {
								email_field.removeClass('bad');
							}
						}
					});
				}

				// alter field visibility
				self.shipping_information_form.find('select[name=presets]').parent().hide();
				self.shipping_information_form.find('input[name=name]').val(first_name.val() + ' ' + last_name.val()).parent().show();
				self.shipping_information_form.find('input[name=email]').val(email_field.val()).parent().hide();
				self.shipping_information_form.find('hr').eq(0).hide();

				result = !(password.hasClass('bad') || password_confirm.hasClass('bad') || email_field.hasClass('bad'));
				break;

			case 'guest':
			default:
				// get agree checkbox
				var agree_to_terms = self.sign_in_form.find('input[name=agree_to_terms]');

				result = true;
				if (agree_to_terms.length > 0)
					result = agree_to_terms.is(':checked');

				// set class
				if (result)
					agree_to_terms.removeClass('bad'); else
					agree_to_terms.addClass('bad');

				// hide unneeded fields
				self.shipping_information_form.find('select[name=presets]').parent().hide();
				self.shipping_information_form.find('input[name=name]').parent().show();
				self.shipping_information_form.find('input[name=email]').parent().show();
				self.shipping_information_form.find('hr').eq(0).show();
		}

		return result;
	};

	/**
	* Validate shipping information page.
	*
	* @return boolean
	*/
	self.validator.shipping_information_page = function() {
		var fields = self.shipping_information_form.find('input,select');

		// add "bad" class to every required field which is empty
		fields.each(function(index) {
			var field = $(this);

			if (field.data('required') == 1 && field.is(':visible') && field.val() == '')
				field.addClass('bad'); else
				field.removeClass('bad');
		});

		return self.shipping_information_form.find('.bad').length == 0;
	};

	/**
	* Validate payment method page.
	*
	* @return boolean
	*/
	self.validator.payment_method_page = function() {
		var result = self.methods.filter('.active').length > 0;

		if (!result)
			self.methods.addClass('bad');

		return result;
	};

	/**
	* Validate billing information page.
	*
	* @return boolean
	*/
	self.validator.billing_information_page = function() {
		var fields = self.billing_information_form.find('input,select');
		var method = self.methods.filter('.active');

		fields.each(function(index) {
			var field = $(this);

			if (field.data('required') == 1 && field.val() == '')
				field.addClass('bad'); else
				field.removeClass('bad');
		});

		// if supported check data validity with method provided functions
		if (self.billing_information_form.find('.bad').length == 0)
			switch (self.method_field.val()) {
				case 'stripe':
					var card_number = fields.filter('input[name=billing_credit_card]');
					var card_expire_month = fields.filter('input[name=billing_expire_month]');
					var card_expire_year = fields.filter('input[name=billing_expire_year]');
					var card_cvv = fields.filter('input[name=billing_cvv]');

					if (!Stripe.card.validateCardNumber(card_number.val()))
						card_number.addClass('bad');

					if (!Stripe.card.validateExpiry(card_expire_month.val(), card_expire_year.val())) {
						card_expire_month.addClass('bad');
						card_expire_year.addClass('bad');
					}

					if (!Stripe.card.validateCVC(card_cvv.val()))
						card_cvv.addClass('bad');

					break;
			}

		return self.billing_information_form.find('.bad').length == 0;
	};

	// finalize object
	self.init();
}


/**
 * Checkout form implementation
 */
Caracal.Shop.CheckoutForm = function() {
	var self = this;

	// cached response from server
	self.cached_data = null;

	// backend URL used to get JSON data
	self.backend_url = $('base').attr('href') + '/index.php';

	self.checkout = $('div#checkout');
	self.checkout_details = self.checkout.find('table.checkout_details');
	self.delivery_provider_list = self.checkout.find('div.delivery_provider');
	self.delivery_method_list = self.checkout.find('div.delivery_method');
	self.overlay = self.delivery_provider_list.find('div.overlay');

	// handler functions namespace
	self.handler = {};

	/**
	 * Complete object initialization.
	 */
	self.init = function() {
		self.delivery_provider_list
				.find('input[name=delivery_provider]')
				.change(self.handler.delivery_provider_change);

		// disable checkout button
		if (self.delivery_provider_list.length > 0)
			self.checkout.find('div.checkout_controls button[type=submit]').attr('disabled', 'disabled');

		// connect events
		self.checkout.find('textarea[name=remarks]').on('blur', self.handler.remarks_focus_lost);
	};

	/**
	 * Save remarks when they loose focus.
	 *
	 * @param object event
	 */
	self.handler.remarks_focus_lost = function(event) {
		var textarea = $(this);

		// send data to server
		new Communicator('shop')
			.send('json_save_remark', {remark: textarea.val()});
	};

	/**
	 * Handle successful data load from server.
	 *
	 * @param object data
	 */
	self.handler.delivery_providers_load = function(data) {
		self.cached_data = data;
		self.checkout_details.find('.subtotal-value.shipping').html(parseFloat(data.shipping).toFixed(2));
		self.checkout_details.find('.subtotal-value.handling').html(parseFloat(data.handling).toFixed(2));
		self.checkout_details.find('.total-value').html(parseFloat(data.total).toFixed(2) + ' ' + data.currency);

		// add every delivery method to the container
		self.delivery_method_list.html('');

		if (data.delivery_prices.length > 0)
			for (var id in data.delivery_prices) {
				var method = data.delivery_prices[id];
				var entry = $('<label>');
				var name = $('<div>');
				var price = $('<span>');
				var time = $('<span>');
				var checkbox = $('<input>');

				// add method name to object
				method.push(data.delivery_method);

				// create interface
				checkbox
					.attr('type', 'radio')
					.attr('name', 'delivery_method')
					.attr('value', id)
					.data('method', method)
					.change(self.handler.delivery_method_click)
					.appendTo(entry);

				price
					.html(method[1])
					.attr('data-currency', method[2]);

				name
					.html(method[0])
					.append(price)
					.appendTo(entry);

				if (method[4] === null) {
					// no estimate available
					time.html(self.cached_data.label_no_estimate);

				} else {
					var start = method[3] != null ? method[5] + ' - ' : '';
					var end = method[6];
					time.html(self.cached_data.label_estimated_time + '<br>' + start + end);
				}

				time.appendTo(entry);

				entry
					.addClass('method')
					.appendTo(self.delivery_method_list);
			}

		// hide overlay
		self.overlay
			.stop(true, true)
			.animate({opacity: 0}, 500, function() {
				$(this).css('display', 'none');
			});

		// show list of delivery methods
		self.delivery_method_list.addClass('visible');
	};

	/**
	 * Handle error on server side while loading delivery methods.
	 *
	 * @param object error
	 */
	self.handler.delivery_providers_error = function(error) {
		// disable checkout button
		self.checkout.find('div.checkout_controls button[type=submit]').attr('disabled', 'disabled');

		// add every delivery method to the container
		self.delivery_method_list.html('');

		// hide overlay
		self.overlay
			.stop(true, true)
			.animate({opacity: 0}, 500, function() {
				$(this).css('display', 'none');
			});
	};

	/**
	 * Handle changing delivery provider.
	 *
	 * @param object event
	 */
	self.handler.delivery_provider_change = function(event) {
		var selected = self.delivery_provider_list.find('input[name=delivery_provider]:checked').val();

		var data = {
				section: 'shop',
				action: 'json_set_delivery_method',
				method: selected
			};

		// show loading overlay
		self.overlay
			.css({
				display: 'block',
				opacity: 0
			})
			.animate({opacity: 1}, 500);
		self.delivery_method_list.removeClass('visible');

		$.ajax({
			url: self.backend_url,
			type: 'GET',
			async: true,
			data: data,
			dataType: 'json',
			context: this,
			success: self.handler.delivery_providers_load,
			error: self.handler.delivery_providers_error
		});
	};

	/**
	 * Handle clicking on delivery method.
	 *
	 * @param object event
	 */
	self.handler.delivery_method_click = function(event) {
		var item = $(this);
		var method = item.data('method');
		var total = self.cached_data.total + self.cached_data.handling + parseFloat(method[1]);

		// update checkout table
		self.checkout_details.find('.subtotal-value.shipping').html(parseFloat(method[1]).toFixed(2));
		self.checkout_details.find('.total-value').html(parseFloat(total).toFixed(2) + ' ' + self.cached_data.currency);

		// enable checkout button
		self.checkout.find('div.checkout_controls button[type=submit]').removeAttr('disabled', 'disabled');

		// send selection to server
		var data = {
				section: 'shop',
				action: 'json_set_delivery_method',
				method: method[7],
				type: item.attr('value')
			};

		// send data to server
		$.ajax({
			url: self.backend_url,
			type: 'GET',
			async: true,
			data: data,
			dataType: 'json'
		});
	};

	// complete object initialization
	self.init();
}


$(function() {
	if ($('div#input_details').length > 0) {
		Site.buyer_information_form = new Caracal.Shop.BuyerInformationForm();

	} else if ($('div#checkout').length > 0) {
		Site.checkout_form = new Caracal.Shop.CheckoutForm();
	}
});
