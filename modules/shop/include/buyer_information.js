/**
 * Buyers Information JavaScript Implementation
 *
 * Copyright (c) 2013. by Way2CU
 * Author: Mladen Mijatov
 */


/**
 * Validate sign in page.
 *
 * @return boolean
 */
function validateSignInPage() {
	var result = false;
	var sign_in_form = $('div#sign_in.page');
	var shipping_information_form = $('div#shipping_information.page');
	var next_button = sign_in_form.find('button.next');
	var backend_url = $('base').attr('href') + '/index.php';

	// check which option is selected
	var selection = sign_in_form.find('input[name=existing_user]:checked').val();
	var selection = parseInt(selection);

	switch (selection) {
		case 0:
			var presets = shipping_information_form.find('select[name=presets]');
			var email_field = sign_in_form.find('input[name=sign_in_email]');
			var password_field = sign_in_form.find('input[name=sign_in_password]');

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
				url: backend_url,
				type: 'GET',
				async: false,
				data: data,
				dataType: 'json',
				context: this,
				success: function(data) {
					email_field.removeClass('bad');
					password_field.removeClass('bad');

					// populate shipping information with data received from the server
					shipping_information_form.find('input[name=first_name]').val(data.information.first_name);
					shipping_information_form.find('input[name=last_name]').val(data.information.last_name);
					shipping_information_form.find('input[name=email]').val(data.information.email);

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
			shipping_information_form.find('select[name=presets]').parent().show();
			shipping_information_form.find('input[name=name]').parent().show();
			shipping_information_form.find('input[name=email]').parent().hide();
			shipping_information_form.find('hr').eq(0).show();

			result = !(email_field.hasClass('bad') || password_field.hasClass('bad'));
			break;

		case 1:
			// get new account section
			var container = sign_in_form.find('div.new_account');
			var fields = container.find('input');

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
			var email_field = sign_in_form.find('input[name=new_email]');

			if (email_field.val() != '') {
				var data = {
						section: 'shop',
						action: 'json_get_account_exists',
						email: email_field.val()
					};

				$.ajax({
					url: backend_url,
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
			shipping_information_form.find('select[name=presets]').parent().hide();
			shipping_information_form.find('input[name=name]').parent().hide();
			shipping_information_form.find('input[name=email]').parent().hide();
			shipping_information_form.find('hr').eq(0).hide();

			result = !(password.hasClass('bad') || password_confirm.hasClass('bad') || email_field.hasClass('bad'));
			break;

		case 2:
		default:
			// hide unneeded fields
			shipping_information_form.find('select[name=presets]').parent().hide();
			shipping_information_form.find('input[name=name]').parent().show();
			shipping_information_form.find('input[name=email]').parent().show();
			shipping_information_form.find('hr').eq(0).show();

			result = true;
	}

	return result;
}

/**
 * Validate shipping information page.
 *
 * @return boolean
 */
function validateShippingInformationPage() {
	var sign_in_form = $('div#sign_in.page');
	var container = $('div#shipping_information.page');
	var fields = container.find('input,select');
	var backend_url = $('base').attr('href') + '/index.php';

	// add "bad" class to every required field which is empty
	fields.each(function(index) {
		var field = $(this);

		if (field.data('required') == 1 && field.val() == '') 
			field.addClass('bad'); else
			field.removeClass('bad');
	});

	return container.find('.bad').length == 0;
}

/**
 * Validate payment method page.
 *
 * @return boolean
 */
function validatePaymentMethodPage() {
	var methods = $('div.payment_methods');
	var result = methods.find('span.active').length > 0;

	if (!result)
		methods.find('span').addClass('bad');

	return result;
}

/**
 * Validate billing information page.
 *
 * @return boolean
 */
function validateBillingInformationPage() {
	var container = $('div#billing_information');
	var fields = container.find('input,select');

	fields.each(function(index) {
		var field = $(this);

		if (field.data('required') == 1 && field.val() == '') 
			field.addClass('bad'); else
			field.removeClass('bad');
	});

	return container.find('.bad').length == 0;
}

/**
 * Validate form pages.
 *
 * @param integer current_page
 * @param integer new_page
 * @return boolean
 */
function validatePage(current_page, new_page) {
	var result = true;

	if (new_page > current_page)
		switch(current_page) {
			case 0:
				// validate sign in page
				result = validateSignInPage();
				break;

			case 1:
				// validate shipping information page
				result = validateShippingInformationPage();
				break;

			case 2:
				// validate payment method page
				result = validatePaymentMethodPage();
				break;

			case 3:
				// validate billing information
				result = validateBillingInformationPage();
				break;
		}

	return result;
}

$(function() {
	// implement page control
	var page_control = new PageControl('div#input_details div.pages');

	page_control
		.setAllowForward(false)
		.setSubmitOnEnd(true)
		.attachControls('div#checkout_stepps a')
		.attachForm('div#input_details form')
		.connect('page-flip', validatePage);

	// implement sign in page functionality
	var sign_in_form = $('div#sign_in.page');

	sign_in_form.find('input[name=existing_user]').change(function(event) {
		var selection = sign_in_form.find('input[name=existing_user]:checked').val();

		switch (parseInt(selection)) {
			// existing account
			case 0:
				sign_in_form.find('div.new_account').removeClass('visible');
				sign_in_form.find('div.existing_account').addClass('visible');
				break;

			// new account
			case 1:
				sign_in_form.find('div.new_account').addClass('visible');
				sign_in_form.find('div.existing_account').removeClass('visible');
				break;

			// checkout as guest
			case 2:
			default:
				sign_in_form.find('div.new_account').removeClass('visible');
				sign_in_form.find('div.existing_account').removeClass('visible');
				break;
		}
	});

	// implement preset switching
	var shipping_information_form = $('div#shipping_information.page');

	shipping_information_form.find('select[name=presets]').change(function(event) {
		var control = $(this);
		var option = control.find('option[value='+control.val()+']');

		shipping_information_form.find('input[name=name]').val(option.data('name'));
		shipping_information_form.find('input[name=phone]').val(option.data('phone'));
		shipping_information_form.find('input[name=street]').val(option.data('street'));
		shipping_information_form.find('input[name=street2]').val(option.data('street2'));
		shipping_information_form.find('input[name=city]').val(option.data('city'));
		shipping_information_form.find('input[name=zip]').val(option.data('zip'));
		shipping_information_form.find('select[name=country]').val(option.data('country'));
		shipping_information_form.find('input[name=state]').val(option.data('state'));
	});

	// implement payment method selection
	var methods = $('div.payment_methods span');
	var method_field = $('input[name=payment_method]');

	methods.click(function(event) {
		var method = $(this);

		// set payment method before processing
		method_field.val(method.data('name'));
			
		// add selection class to 
		methods.not(method).removeClass('active');
		method.addClass('active');

		// remove bad class
		methods.removeClass('bad');

		// disable billing information page if payment method provides info about buyer
		if (method.data('provides-information') == 1)
			page_control.disablePage(3); else
			page_control.enablePage(3);
	});
});
