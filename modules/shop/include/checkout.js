// implement page control
var page_control = new PageControl('div#input_details div.pages');

page_control
	.setAllowForward(false)
	.setSubmitOnEnd(true)
	.attachControls('div#checkout_stepps a')
	.attachForm('div#input_details form');

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

	// disable billing information page if payment method provides info about buyer
	if (method.data('provides-information') == 1)
		page_control.disablePage(3); else
		page_control.enablePage(3);
});

// implement sign in form
var sign_in_form = $('div#sign_in');
var next_button = sign_in_form.find('button.next');
var backend_url = $('base').attr('href') + '/index.php';
var email_field = sign_in_form.find('input[name=email]');
var password_field = sign_in_form.find('input[name=password]');

next_button
	.off('click')
	.click(function(event) {
		// prevent default button behavior
		event.preventDefault();

		// check which option is selected
		var selection = sign_in_form.find('input[name=existing_user]:checked').val();

		// load data from server if needed
		if (selection == 0) {
			var data = {
					section: 'shop',
					action: 'json_get_user_info',
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
					page_control._handleNext(event);
				}, 
				error: function(request, text_status, error) {
					alert(error);
				}
			});

		} else if (selection == 1) {
			// user has selected new account
			page_control._handleNext(event);

		} else {
			// remove password fields
			var container = $('div#input_details div.page').eq(1);
				
			container.find('hr').eq(1).remove();
			container.find('input[name=password], input[name=password_confirm]').parent().remove();

			// user has selected guest checkout
			page_control._handleNext(event);
		}
	});
