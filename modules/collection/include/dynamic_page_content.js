/**
 * Dynamic Page Content JavaScript
 *
 * Control allowing on-page loading of content from
 * other pages sharing the same container id.
 *
 * Copyright (c) 2014. by Way2CU, http://way2cu.com
 * Authors: Mladen Mijatov
 */

function DynamicPageContent(container_id, link_selector, switch_delay) {
	var self = this;

	self._container = null;
	self._container_id = null;
	self._links = null;
	self._switch_delay = null;

	/**
	 * Complete object initialization.
	 */
	self._init = function() {
		self._switch_delay = switch_delay != undefined ? switch_delay : 300;
		self._container = $('#' + container_id);
		self._container_id = container_id;
		self._links = $(link_selector);

		// connect link events
		self._links.click(self._handle_link_click);
	}

	/**
	 * Load content from specified URL.
	 *
	 * @param object activated_link
	 * @param string url
	 */
	self._load_content = function(activated_link, url) {
		self._container
			.data('activated-link', activated_link)
			.animate({opacity: 0}, self._switch_delay, function() {
				$.get(url, self._handle_content_load).fail(self._handle_content_load_error);
			});
	};

	/**
	 * Handle event after content has been loaded.
	 * 
	 * @param string data
	 */
	self._handle_content_load = function(data) {
		// replace content
		var content = $(data).find('#' + container_id).children();
		self._container.html(content);

		// show container
		self._container.animate({opacity: 1}, self._switch_delay);

		// highlight link
		var activated_link = self._container.data('activated-link');
		activated_link.addClass('active');
		self._links.not(activated_link).removeClass('active');
	};

	/**
	 * Handle error during content load.
	 */
	self._handle_content_load_error = function() {
		self._container.animate({opacity: 1}, 500);
	};

	/**
	 * Handle clicking on link.
	 *
	 * @param object event
	 */
	self._handle_link_click = function(event) {
		// prevent page redirection
		event.preventDefault();

		// load content from link URL
		var item = $(this);
		var url = item.attr('href');

		if (url)
			self._load_content(item, url);
	};

	// finalize object
	self._init();
}
