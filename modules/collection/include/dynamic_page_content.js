/**
 * Dynamic Page Content JavaScript
 *
 * Control allowing on-page loading of content from
 * other pages sharing the same container id.
 *
 * Copyright (c) 2014. by Way2CU, http://way2cu.com
 * Authors: Mladen Mijatov
 */

/**
 * Constructor function.
 *
 * @param string container_id	ID parameter of page container.
 * @param string link_selector	Selector for anchors with page links.
 * @param string switch_delay	Transition time between switching.
 * @param string change_url		Change URL in browser's address bar on content change.
 */
function DynamicPageContent(container_id, link_selector, switch_delay, change_url) {
	var self = this;

	self._container = null;
	self._container_id = null;
	self._links = null;
	self._switch_delay = null;
	self._change_url = null;
	self._starting_url = null;
	self._starting_link = null;

	/**
	 * Complete object initialization.
	 */
	self._init = function() {
		self._switch_delay = switch_delay != undefined ? switch_delay : 300;
		self._container = $('#' + container_id);
		self._container_id = container_id;
		self._links = $(link_selector);
		self._change_url = change_url == undefined ? true : change_url;

		// connect link events
		self._links.click(self._handle_link_click);

		// get the starting url
		self._starting_link = self._links.find('.active');
		self._starting_url = self._starting_link.attr('href');

		// connect popstate event
		if (self._change_url && window.history.pushState != undefined)
			$(window).bind('popstate', self._handle_pop_state);
	}

	/**
	 * Load content from specified URL.
	 *
	 * @param object activated_link
	 * @param string url
	 */
	self._load_content = function(activated_link, url) {
		self._container
			.data('activated-url', url)
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
		var title = $(data).find('title').text();
		var content = $(data).find('#' + container_id).children();
		self._container.html(content);

		// change title
		document.title = title;

		// show container
		self._container.animate({opacity: 1}, self._switch_delay);

		// highlight link
		var activated_url = self._container.data('activated-url');
		var activated_link = self._container.data('activated-link');

		activated_link.addClass('active');
		self._links.not(activated_link).removeClass('active');

		// change url
		if (self._change_url && window.history.pushState != undefined) {
			var state = {};

			// populate state with data
			state.url = activated_url;
			state.title = title;
			state.content = $(data).find('#' + container_id).html();

			// push new state
			window.history.pushState(state, title, activated_url);
		}
	};

	/**
	 * Handle navigating through history.
	 *
	 * @param object event
	 */
	self._handle_pop_state = function(event) {
		// initial page doesn't have a state
		if (!event.originalEvent.state) {
			self._load_content(self._starting_link, self._starting_url);
			return;
		}

		// get variables
		var state = event.originalEvent.state;
		var activated_link = self._links.filter('[href="' + state.url + '"]');

		// restore state
		self._container
			.data('activated-url', state.url)
			.data('activated-link', activated_link)
			.animate({opacity: 0}, self._switch_delay, function() {
				// set content
				document.title = state.title;
				self._container.html(state.content);

				// highlight link
				activated_link.addClass('active');
				self._links.not(activated_link).removeClass('active');

				// show container
				self._container.animate({opacity: 1}, self._switch_delay);
			});
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
