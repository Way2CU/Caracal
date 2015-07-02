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

	// configure iframe
	iframe
		.attr('id', 'tranzila_checkout')
		.appendTo(form);

	form.on('submit', function() {
	});
});
