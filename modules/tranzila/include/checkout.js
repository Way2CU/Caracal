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
	var form = $('div#checkout form');
	var iframe = $('<iframe>');
	var dialog = new Dialog();

	// configure iframe
	iframe
		.attr('id', 'tranzila_checkout')
		.attr('name', 'tranzila_checkout')
		.attr('seamless', 'seamless')
		.attr('scrolling', 'no');

	// make form submit to iframe
	form.attr('target', 'tranzila_checkout');

	// configure dialog
	dialog
		.setTitle(language_handler.getText('tranzila', 'payment_method_title'))
		.setContent(iframe);

	if (!Site.is_mobile())
		dialog.setSize(400, 250); else
		dialog.setSize('90vw', 250);

	// show dialog when form is submitted
	form.on('submit', function(event) {
		// show tranzila page
		dialog.show();
	});
};

$(function() {
	if ($('div#checkout form').data('method') == 'tranzila')
		Site.configure_tranzila_checkout();
});
