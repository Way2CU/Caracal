/**
 * Captcha Module JavaScript
 *
 * Copyright (c) 2026. by Way2CU, http://way2cu.com
 * Authors: Mladen Mijatov
 */

var Caracal = Caracal || new Object();
var Caracal.Captcha = Caracal.Captcha || new Object();


/**
 * Handle clicking on CAPTCH image, and refresh the image.
 *
 * @param object event
 */
Caracal.Captcha.handle_image_click = function(event) {
	event.stopPropagation();
	event.preventDefault();

	var url = new URL(event.target.getAttribute('src'));
	var params = new URLSearchParams(url.search);

	params.set('_', Date.now().toString());
	url.search = params;

	event.target.src = url.toString();
}


// connect events
window.addEventListener('load', function() {
	var images = document.querySelectAll('img.captcha');
	images.forEach((image) => image.addEventListener('click', Caracal.Captcha.handle_image_click));
});
