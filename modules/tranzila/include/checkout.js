/**
 * Checkout JavaScript
 * Tranzila Payment Method
 *
 * Copyright (c) 2015. by Way2CU, http://way2cu.com
 * Authors: Mladen Mijatov
 */

$(function() {
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
		.setSize(400, 250)
		.setTitle(language_handler.getText('tranzila', 'payment_method_title'))
		.setContent(iframe);

	// show dialog when form is submitted
	form.on('submit', function() {
		dialog.show();
	});
});
