/**
 * Notebook Control
 * 
 * Copyright (c) 2012. by Way2CU
 * Author: Mladen Mijatov
 *
 * This control is used to transform DOM elements into tabbed control
 * and unify controls in a way that is easier to use without taking excessive
 * screen space.
 *
 * Requires jQuery 1.4.2+
 */

function Notebook(selector) {
	var self = this;

	this._container = $('#' + selector).eq(0);
	this._tabs = $('<ul>');
	this._page_container = $('<div>');
	this._pages = this._container.children('div');

	this.active_page = -1;

	/**
	 * Finalize object initialization
	 */
	this.init = function() {
		// replace pages
		this._pages
				.detach()
				.appendTo(this._page_container);

		// configure containers
		this._tabs.appendTo(this._container);
		this._page_container
				.addClass('container')
				.appendTo(this._container);

		// configure notebook pages
		var page_max_height = 0;

		this._pages.each(function(current_index) {
			// get maximum page height
			var page = $(this);
			var page_height = page.height();

			if (page_height > page_max_height)
				page_max_height = page_height;

			// add new item to tab control
			$('<li>')
				.data('index', current_index)
				.click(function() {
					self.setActivePage($(this).data('index'));
				})
				.appendTo(self._tabs);
		});

		this._pages
				.css('height', page_max_height)
				.addClass('page');

		// configure container
		this._container
				.addClass('notebook');

		// activate first page
		if (this._pages.length > 0)
			this.setActivePage(0);
	};

	/**
	 * Set tab label for specified tab index
	 *
	 * @param integer index
	 * @param string title
	 * @return object
	 */
	this.setPageTitle = function(index, title) {
		this._tabs.children().eq(index).html(title);

		return this;
	};

	/**
	 * Make tab specified by index active
	 *
	 * @param integer index
	 * @return object
	 */
	this.setActivePage = function(index) {
		if (index == this.active_page)
			return;

		var page = this._pages.eq(index);
		var tab = this._tabs.children().eq(index);

		// set active page
		this._pages
				.removeClass('active')
				.eq(index)
					.addClass('active');

		// set active tab
		this._tabs.children()
				.removeClass('active')
				.eq(index)
					.addClass('active');

		// update active page index
		this.active_page = index;

		return this;
	};

	this.init();
}
