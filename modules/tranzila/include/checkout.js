/**
 * Checkout JavaScript
 * Tranzila Payment Method
 *
 * Copyright (c) 2015. by Way2CU, http://way2cu.com
 * Authors: Mladen Mijatov
 */

var Site = Site || {};

/**
 * Create popup window and configure tranzila checkout.
 */
Site.configure_tranzila_checkout = function() {
	var form = document.querySelector('div#checkout form');
	var iframe = document.createElement('iframe');
	var dialog = new Caracal.Dialog();

	// configure iframe
	iframe.setAttribute('id', 'tranzila_checkout');
	iframe.setAttribute('name', 'tranzila_checkout');
	iframe.setAttribute('seamless', 'seamless');
	iframe.setAttribute('scrolling', 'no');

	// make form submit to iframe
	form.setAttribute('target', 'tranzila_checkout');

	// configure dialog
	dialog
		.set_title(Caracal.language.get_text('tranzila', 'payment_method_title'))
		.set_content(iframe);

	if (!Site.is_mobile())
		dialog.set_size(400, 250); else
		dialog.set_size('90vw', 250);

	// show dialog when form is submitted
	form.addEventListener('submit', function(event) {
		// show tranzila page
		dialog.open();
	});
};

/**
 * Configure checkout if tranzila is selected payment method.
 */
Site.initialize_tranzila_checkout = function() {
	var form = document.querySelector('div#checkout form');

	if (form !== null && form.dataset.method == 'tranzila')
		Site.configure_tranzila_checkout();
};

window.addEventListener('load', Site.initialize_tranzila_checkout);
