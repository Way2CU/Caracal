/**
 * Backend JavaScript
 *
 * Copyright (c) 2014. by Way2CU
 * Author: Mladen Mijatov
 */

var backend_container = null;
var backend_element_size = null;

function update_backend_container_height(event) {
	if (backend_element_size == null) {
		// get header and footer
		var header = $('header').height() + 20;
		var footer = $('footer').height() + 2;

		backend_element_size = header + footer;

		// get container element
		backend_container = $('div#container');
	}

	var window_height = $(window).height();
	backend_container.css('height', window_height - backend_element_size);
}

$(function() {
	// connect window resize event
	$(window).resize(update_backend_container_height);

	// apply height initially
	update_backend_container_height();
});
