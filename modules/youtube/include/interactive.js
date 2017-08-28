/**
 * Interactive YouTube JavaScript
 *
 * This script provides client-side functionality for loading YouTube videos.
 * In order to reduce the amount of data needed to load from the servers this
 * script will load embed code from the server and play the video only upon
 * clicking on the video link itself.
 *
 * Class defined here is indirectly used by the YouTube module itself and expects
 * XML tags in following format:
 *
 *	<a
 *		href="youtube.com/watch?v=XXXXXXXXX"
 *		data-embed-url="youtube.com/embed?v=XXXXXXXX&amp;theme=light&amp;autoplay=1"
 *		data-width="500"
 *		data-height="315"
 *		data-fullscreen="0"
 *		>
 *		<img src="img.youtube.com/...">
 *	</a>
 *
 * Even though additional tags can be used inside of `<a>` it's importan to
 * note that whole element is replaced with embeded video, effectively removing
 * content from the DOM tree.
 *
 * Copyright (c) 2017. by Way2CU, http://way2cu.com
 * Authors: Mladen Mijatov
 */

// create namespaces
var Caracal = Caracal || new Object();
Caracal.YouTube = Caracal.YouTube || new Object();


Caracal.YouTube.VideoLoader = function(selector) {
	var self = this;

	/**
	 * Complete object initialization.
	 */
	self._init = function() {
		var elements = document.querySelectorAll(selector);

		if (elements != null)
			for (var i=0, count=elements.length; i<count; i++)
				elements[i].addEventListener('click', self._handle_link_click);
	};

	/**
	 * Handle clicking on video link.
	 * @param object event
	 */
	self._handle_link_click = function(event) {
		// prevent browser from following the link
		event.preventDefault();

		// create new embedded container
		var fullscreen = this.dataset.fullscreen == '1';
		var container = document.createElement('iframe');

		// configure container
		container.width = this.dataset.width;
		container.height = this.dataset.height;
		container.src = this.dataset.embedUrl;
		container.style.border = '0';

		// replace original link with new iframe
		this.parentNode.replaceChild(container, this);
	};

	// finalize object
	self._init();
}


// automatically connect videos
window.addEventListener('load', function() {
	new Caracal.YouTube.VideoLoader('a.youtube.video.interactive');
});
