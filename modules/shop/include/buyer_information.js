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
	var sign_in_form = $('div#sign_in');
	var next_button = sign_in_form.find('button.next');
	var backend_url = $('base').attr('href') + '/index.php';
	var email_field = sign_in_form.find('input[name=email]');
	var password_field = sign_in_form.find('input[name=password]');

	// check which option is selected
	var selection = sign_in_form.find('input[name=existing_user]:checked').val();

	// load data from server if needed
	if (selection == 0) {
		var data = {
				section: 'shop',
				action: 'json_get_account_info',
				email: email_field.val(),
				password: password_field.val()
			};

		$.ajax({
			url: backend_url,
			type: 'GET',
			async: false,
			data: data,
			dataType: 'json',
			context: this,
			success: function(data) {
				result = true;
			}, 
			error: function(request, text_status, error) {
				alert(error);
			}
		});

	} else if (selection == 1) {
		// user has selected new account
		result = true;

	} else {
		// remove password fields
		var container = $('div#input_details div.page').eq(1);
			
		container.find('hr').eq(1).remove();
		container.find('input[name=password], input[name=password_confirm]').parent().remove();

		// user has selected guest checkout
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
	var sign_in_form = $('div#sign_in');
	var container = $('div#shipping_information');
	var fields = container.find('input,select');
	var backend_url = $('base').attr('href') + '/index.php';

	// add "bad" class to every required field which is empty
	fields.each(function(index) {
		var field = $(this);

		if (field.data('required') == 1 && field.val() == '') 
			field.addClass('bad'); else
			field.removeClass('bad');
	});

	// make sure passwords match
	var password = container.find('input[name=password]');
	var password_confirm = container.find('input[name=password_confirm]');

	if (password.length > 0 && password_confirm.length > 0 && password.val() != password_confirm.val()) {
		password.addClass('bad');
		password_confirm.addClass('bad');
	}

	// check if account with specified email already exists
	var email_field = container.find('input[name=email]');
	var existing_user = sign_in_form.find('input[name=existing_user]:checked').val();

	if (existing_user == 1) {
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
					email_field.addClass('bad')
					alert(data.message);
				}
			}
		});
	}

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
