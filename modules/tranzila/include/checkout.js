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

	// create submit button
	button = $('<button>');
	button
		.attr('type', 'button')
		.html(language_handler.getText(null, 'submit'))
		.on('click', function() {
			var iframe_doc = (iframe[0].contentWindow || iframe[0].contentDocument);
			if (iframe_doc.document) iframe_doc = iframe_doc.document;
			iframe_doc.getElementById('itranpayform').submit();
		});

	// configure dialog
	dialog
		.setTitle(language_handler.getText('tranzila', 'payment_method_title'))
		.setContent(iframe)
		.addControl(button);

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
