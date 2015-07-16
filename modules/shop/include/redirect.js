/**
 * IFrame Redirection JavaScript
 * Caracal Framework
 *
 * This script ensures page is not running inside of IFrame or any other frame. Usually
 * script would not be necessary but some payment providers are yet to understand concept
 * of bug reports.
 *
 * Copyright (c) 2015. by Way2CU, http://way2cu.com
 * Authors: Mladen Mijatov
 */

(function(window) {
	if (window.location !== window.top.location)
		window.top.location = window.location;
})(this);
