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
		page_control.disablePage(2); else
		page_control.enablePage(2);
});
