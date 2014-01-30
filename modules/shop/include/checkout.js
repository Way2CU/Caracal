/**
 * Checkout Form JavaScript Implemenentation
 *
 * Copyright (c) 2013. by Way2CU
 * Author: Mladen Mijatov
 */

function BuyerInformationForm() {
	var self = this;

	self.backend_url = $('base').attr('href') + '/index.php';

	self.sign_in_form = $('div#sign_in.page');
	self.shipping_information_form = $('div#shipping_information.page');
	self.billing_information_form = $('div#billing_information.page');
	self.methods = $('div.payment_methods span');
	self.method_field = $('input[name=payment_method]');
	self.page_control = new PageControl('div#input_details div.pages');
	self.password_dialog = new Dialog();
	self.cvv_dialog = new Dialog();

	// pages
	if (self.sign_in_form.length > 0) {
		self.pages = {
				SIGN_IN: 0,
				SHIPPING_INFORMATION: 1,
				PAYMENT_METHOD: 2,
				BILLING_INFORMATION: 3,
			};

	} else {
		self.pages = {
				SIGN_IN: -2,
				SHIPPING_INFORMATION: -1,
				PAYMENT_METHOD: 0,
				BILLING_INFORMATION: 1,
			};
	}

	/**
	 * Complete object initialization.
	 */
	self.init = function() {
		// implement page control
		self.page_control
			.setAllowForward(false)
			.setSubmitOnEnd(true)
			.attachControls('div#checkout_stepps a')
			.attachForm('div#input_details form')
			.connect('page-flip', self.validate_page);

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

		// connect events
		self.sign_in_form.find('input[name=existing_user]').change(self._handle_account_type_change);
		self.shipping_information_form.find('select[name=presets]').change(self._handle_shipping_information_preset_change);
		self.methods.click(self._handle_payment_method_click);
		self.sign_in_form.find('a.password_recovery').click(self._show_password_dialog);
		self.billing_information_form.find('a.what_is_cvv').click(self._show_cvv_dialog);
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
	 * Handle changing type of account for buyers information.
	 *
	 * @param object event
	 */
	self._handle_account_type_change = function(event) {
		var selection = self.sign_in_form.find('input[name=existing_user]:checked').val();

		switch (parseInt(selection)) {
			// existing account
			case 0:
				self.sign_in_form.find('div.new_account').removeClass('visible');
				self.sign_in_form.find('div.existing_account').addClass('visible');
				break;

			// new account
			case 1:
				self.sign_in_form.find('div.new_account').addClass('visible');
				self.sign_in_form.find('div.existing_account').removeClass('visible');
				break;

			// checkout as guest
			case 2:
			default:
				self.sign_in_form.find('div.new_account').removeClass('visible');
				self.sign_in_form.find('div.existing_account').removeClass('visible');
				break;
		}
	};

	/**
	 * Handle change in shipping information preset control.
	 *
	 * @param object event
	 */
	self._handle_shipping_information_preset_change = function(event) {
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
	};

	/**
	 * Handle clicking on payment method.
	 *
	 * @param object event
	 */
	self._handle_payment_method_click = function(event) {
		var method = $(this);

		// set payment method before processing
		self.method_field.val(method.data('name'));
			
		// add selection class to 
		self.methods.not(method).removeClass('active');
		method.addClass('active');

		// remove bad class
		self.methods.removeClass('bad');

		// disable billing information page if payment method provides info about buyer
		if (method.data('provides-information') == 1)
			self.page_control.disablePage(self.pages.BILLING_INFORMATION); else
			self.page_control.enablePage(self.pages.BILLING_INFORMATION);
	};
	/**
	* Validate sign in page.
	*
	* @return boolean
	*/
	self._validate_sign_in_page = function () {
		var result = false;
		var next_button = self.sign_in_form.find('button.next');

		// check which option is selected
		var selection = parseInt(self.sign_in_form.find('input[name=existing_user]:checked').val());

		switch (selection) {
			case 0:
				var presets = self.shipping_information_form.find('select[name=presets]');
				var email_field = self.sign_in_form.find('input[name=sign_in_email]');
				var password_field = self.sign_in_form.find('input[name=sign_in_password]');

				// reset presets
				presets.html('');

				// prepare data
				var data = {
						section: 'shop',
						action: 'json_get_account_info',
						email: email_field.val(),
						password: password_field.val()
					};

				// check with server if provided information is correct
				$.ajax({
					url: self.backend_url,
					type: 'GET',
					async: false,
					data: data,
					dataType: 'json',
					context: this,
					success: function(data) {
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
					}, 
					error: function(request, text_status, error) {
						// add "bad" class to input fields
						email_field.addClass('bad');
						password_field.addClass('bad');

						// show error message to user
						alert(error);
					}
				});

				// alter field visibility
				self.shipping_information_form.find('select[name=presets]').parent().show();
				self.shipping_information_form.find('input[name=name]').parent().show();
				self.shipping_information_form.find('input[name=email]').parent().hide();
				self.shipping_information_form.find('hr').eq(0).show();

				result = !(email_field.hasClass('bad') || password_field.hasClass('bad'));
				break;

			case 1:
				// get new account section
				var container = self.sign_in_form.find('div.new_account');
				var fields = container.find('input');
				var first_name = container.find('input[name=first_name]');
				var last_name = container.find('input[name=last_name]');

				// ensure required fields are filled in
				fields.each(function(index) {
					var field = $(this);

					if (field.data('required') == 1 && field.val() == '') 
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
				self.shipping_information_form.find('input[name=name]').val(first_name.val() + ' ' + last_name.val()).parent().hide();
				self.shipping_information_form.find('input[name=email]').val(email_field.val()).parent().hide();
				self.shipping_information_form.find('hr').eq(0).hide();

				result = !(password.hasClass('bad') || password_confirm.hasClass('bad') || email_field.hasClass('bad'));
				break;

			case 2:
			default:
				// hide unneeded fields
				self.shipping_information_form.find('select[name=presets]').parent().hide();
				self.shipping_information_form.find('input[name=name]').parent().show();
				self.shipping_information_form.find('input[name=email]').parent().show();
				self.shipping_information_form.find('hr').eq(0).show();

				result = true;
		}

		return result;
	};

	/**
	* Validate shipping information page.
	*
	* @return boolean
	*/
	self._validate_shipping_information_page = function () {
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
	self._validate_payment_method_page = function () {
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
	self._validate_billing_information_page = function () {
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

	/**
	* Validate form pages.
	*
	* @param integer current_page
	* @param integer new_page
	* @return boolean
	*/
	self.validate_page = function(current_page, new_page) {
		var result = true;

		if (new_page > current_page)
			switch(current_page) {
				case self.pages.SIGN_IN:
					// validate sign in page
					result = self._validate_sign_in_page();
					break;

				case self.pages.SHIPPING_INFORMATION:
					// validate shipping information page
					result = self._validate_shipping_information_page();
					break;

				case self.pages.PAYMENT_METHOD:
					// validate payment method page
					result = self._validate_payment_method_page();
					break;

				case self.pages.BILLING_INFORMATION:
					// validate billing information
					result = self._validate_billing_information_page();
					break;
			}

		return result;
	};

	// finalize object
	self.init();
}


/**
 * Checkout form implementation
 */
function CheckoutForm() {
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

	/**
	 * Complete object initialization.
	 */
	self.init = function() {
		self.delivery_provider_list
				.find('input[name=delivery_provider]')
				.change(self._handle_delivery_provider_change);
	};

	/**
	 * Handle successful data load from server.
	 *
	 * @param object data
	 */
	self._handle_delivery_providers_load = function(data) {
		self.cached_data = data;
		self.checkout_details.find('.subtotal-value.shipping').html(parseFloat(data.shipping).toFixed(2));
		self.checkout_details.find('.subtotal-value.handling').html(parseFloat(data.handling).toFixed(2));
		self.checkout_details.find('.total-value').html(parseFloat(data.total).toFixed(2) + ' ' + data.currency);

		// add every delivery method to the container
		self.delivery_method_list.html('');

		for (var i=0, count=data.delivery_prices.length; i<count; i++) {
			var method = data.delivery_prices[i];
			var entry = $('<label>');
			var name = $('<div>');
			var price = $('<span>');
			var time = $('<span>');
			var checkbox = $('<input>');

			checkbox
				.attr('type', 'radio')
				.attr('name', 'delivery_method')
				.attr('value', i)
				.data('method', method)
				.change(self._handle_delivery_method_click)
				.appendTo(entry);

			price.html(method[1] + ' ' + method[2]);

			name
				.html(method[0])
				.append(price)
				.appendTo(entry);

			if (method[4] === null)
				time.html(self.cached_data.label_no_estimate); else
				time.html(self.cached_data.label_estimated_time + ' ' + (method[3] == null ? method[4] : method[3] + ' - ' + method[4]));

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
	self._handle_delivery_providers_error = function(error) {
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
	self._handle_delivery_provider_change = function(event) {
		var selected = self.delivery_provider_list.find('input[name=delivery_provider]:checked').val();

		var data = {
				section: 'shop',
				action: 'json_get_shopping_cart_summary',
				delivery_method: selected
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
			success: self._handle_delivery_providers_load,
			error: self._handle_delivery_providers_error
		});
	};

	/**
	 * Handle clicking on delivery method.
	 *
	 * @param object event
	 */
	self._handle_delivery_method_click = function(event) {
		var method = $(this).data('method');
		var total = self.cached_data.total + self.cached_data.handling + parseFloat(method[1]);

		// update checkout table
		self.checkout_details.find('.subtotal-value.shipping').html(parseFloat(method[1]).toFixed(2));
		self.checkout_details.find('.total-value').html(parseFloat(total).toFixed(2) + ' ' + self.cached_data.currency);

		// enable checkout button
		self.checkout.find('div.checkout_controls button[type=submit]').removeAttr('disabled', 'disabled');
	};

	// complete object initialization
	self.init();
}


$(function() {
	if ($('div#input_details').length > 0) {
		new BuyerInformationForm();

	} else if ($('div#checkout').length > 0) {
		new CheckoutForm();
	}
});
