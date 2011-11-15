/**
 * Shopping Cart
 *
 * Copyright (c) 2011. by Mladen Mijatov
 * http://rcf-group.com
 *
 * Requires jQuery 1.4.2+
 */

var shopping_cart = null;

/**
 * Shopping Cart
 */
function ShoppingCart() {
	var self = this;  // used in embeded functions

	// create interface
	this.main_container = $('<div>');
	this.container = $('<div>');

	this.toggle_button = $('<a>');
	this.item_count = $('<span>');

	this.top_menu = $('<div>');
	this.checkout_button = $('<a>');
	this.clear_button = $('<a>');

	this.content = $('<div>');
	this.empty_cart = $('<div>');

	this.summary = $('<div>');

	// local variables
	this._visible = false;
	this._notification_active = false;
	this._width = 0;
	this._animation_time = 700;
	this._blink_time = 400;
	this._items = {};
	this._item_count = 0;
	this._default_currency = 'EUR';
	this._payment_methods = {};
	this._backend_url = window.location.protocol + '//' + window.location.host + window.location.pathname;

	/**
	 * Finish object initialization
	 */
	this.init = function() {
		// configure main container
		this.main_container
				.attr('id', 'shopping_cart')
				.append(this.container)
				.appendTo($('body'));

		// configure container
		this.container
				.addClass('container')
				.append(this.toggle_button)
				.append(this.item_count)
				.append(this.top_menu)
				.append(this.content)
				.append(this.summary);

		// configure toggle button
		this.toggle_button
				.attr('href', 'javascript: void(0);')
				.attr('title', language_handler.getText('shop', 'show_shopping_cart'))
				.click(this.toggleVisibility)
				.addClass('toggle_button');

		// configure item count label
		this.item_count
				.addClass('item_count')
				.click(this.toggleVisibility)

		// configure top menu
		this.top_menu
				.addClass('top_menu')
				.append(this.clear_button)
				.append(this.checkout_button);

		this.checkout_button
				.addClass('checkout')
				.html(language_handler.getText('shop', 'checkout'));

		this.clear_button
				.html(language_handler.getText('shop', 'clear'))
				.click(this._clearCart);

		// configure content container
		this.content
				.addClass('content')
				.append(this.empty_cart);

		this.empty_cart
				.addClass('empty')
				.html(language_handler.getText('shop', 'empty_shopping_cart'));

		// configure shopping cart summary container
		this.summary
				.addClass('summary');

		// connect events
		$(window).resize(this.__handleWindowResize);

		// get container width for later use
		this._width = this.main_container.width();

		// load cart items from cookies
		this._loadContent();
		this._loadDefaultCurrency();
		this._loadPaymentMethods();
		this._updateSummary();

		// manually call event handler to
		// calculate initial values
		this.__handleWindowResize();
	};

	/**
	 * Show shopping cart
	 */
	this.showCart = function() {
		if (!this._visible) {
			this.main_container.animate(
						{right: 0},
						this._animation_time,
						function() {
							self.toggle_button.attr(
											'title',
											language_handler.getText('shop', 'hide_shopping_cart')
										);
							self._visible = true;
						}
					);
		}
	};

	/**
	 * Hide shopping cart
	 */
	this.hideCart = function() {
		if (this._visible) {
			this.main_container.animate(
						{right: -this._width},
						this._animation_time,
						function() {
							self.toggle_button.attr(
									'title',
									language_handler.getText('shop', 'show_shopping_cart')
								);
							self._visible = false;
						}
					);
		}
	};

	/**
	 * Toggle cart visibility
	 */
	this.toggleVisibility = function() {
		if (self._visible)
			self.hideCart(); else
			self.showCart();
	};

	/**
	 * Blink shopping cart button
	 */
	this.notifyUser = function() {
		if (this._notification_active)
			return;

		var chain_cart = new AnimationChain(null, false, 3);

		chain_cart
			.addAnimation(
					this.toggle_button,
					{opacity: 0},
					this._blink_time
			)
			.addAnimation(
					this.toggle_button,
					{opacity: 1},
					this._blink_time
			)
			.callback(function() {
				self._notification_active = false;
			});

		this._notification_active = true;
		chain_cart.start();
	};

	/**
	 * Add item to shopping cart. If cart already
	 * contains specified item, increase its count.
	 * Return number of items in shopping cart with
	 * specified uid.
	 *
	 * @param string uid
	 * @return integer
	 */
	this.addItem = function(uid) {
		if (uid in this._items) {
			// increase number of existing items
			var item = this._items[uid];
			item.increaseCount();

		} else {
			// create new item in shopping cart
			this.empty_cart.animate(
				{opacity: 0}, 200,
				function() {
					var item = new ShopItem(uid, 1, self);

					// hide empty cart message
					self.empty_cart.css('display', 'none');

					// add new item to the list
					self._items[uid] = item;
					self._item_count++;
				});
		}

		// update total count of items
		this._updateSummary();

		// notify user
		this.notifyUser();
	};

	/**
	 * Remove specified number or all items from
	 * shopping cart. Return true if specified number
	 * of items was removed.
	 *
	 * @param string uid
	 * @param integer count
	 * @return boolean
	 */
	this.removeItem = function(uid, count) {
		if (!uid in this._items)
			return;
	};

	/**
	 * Load cart content from server
	 */
	this._loadContent = function() {
		// prepare data
		var data = {
					section: 'shop',
					action: 'json_get_shopping_cart',
				};

		// check local cache first
		$.ajax({
			url: this._getBackendURL(),
			type: 'GET',
			async: true,
			data: data,
			dataType: 'json',
			context: this,
			success: this.__handleContentLoad,
			error: this.__handleContentLoadError
		});
	};

	/**
	 * Load default currency from server
	 */
	this._loadDefaultCurrency = function() {
		// prepare data
		var data = {
					section: 'shop',
					action: 'json_get_currency',
				};

		// check local cache first
		$.ajax({
			url: this._getBackendURL(),
			type: 'GET',
			async: true,
			data: data,
			dataType: 'json',
			context: this,
			success: this.__handleCurrencyLoad,
			error: this.__handleCurrencyLoadError
		});
	};
	
	/**
	 * Load payment methods from server
	 */
	this._loadPaymentMethods = function() {
		// prepare data
		var data = {
					section: 'shop',
					action: 'json_get_payment_methods',
				};

		// check local cache first
		$.ajax({
			url: this._getBackendURL(),
			type: 'GET',
			async: true,
			data: data,
			dataType: 'json',
			context: this,
			success: this.__handlePaymentMethodsLoad,
			error: this.__handlePaymentMethodsLoadError
		});
	};

	/**
	 * Add child container to content list
	 *
	 * @param object container
	 */
	this._addChildContainer = function(container) {
		this.content.append(container);
	};

	/**
	 * Remove child object from local list
	 *
	 * @param string uid
	 */
	this._removeItemObject = function(uid) {
		if (!uid in this._items)
			return;

		delete this._items[uid];
	};

	/**
	 * Update cart summary
	 */
	this._updateSummary = function() {
		var text = language_handler.getText('shop', 'total_amount');
		var amount = 0;

		// calculate total price
		for (var uid in this._items)
			amount += this._items[uid]._total;

		// update shopping cart summary
		text = text.replace('%sum', amount);
		text = text.replace('%currency', this._default_currency);
		text = text.replace('%count', this._item_count);

		this.summary.html(text);

		// update item count
		this.item_count.html(this._item_count);
	};

	/**
	 * Get available payment methods from server
	 */
	this._getPaymentMethods = function() {

	};

	/**
	 * Return backend URL
	 * @return string
	 */
	this._getBackendURL = function() {
		return this._backend_url;
	};

	/**
	 * Clear shopping cart
	 */
	this._clearCart = function() {
		var text = language_handler.getText('shop', 'message_clear_cart');

		if (confirm(text)) {
			// clear shopping cart
			for (var uid in self._items) {
				var item = self._items[uid];

				item.remove();
				delete self._items[uid];
			}

			// reset item count
			self._item_count = 0;

			// update summary
			self._updateSummary();

			// show empty cart message
			self.empty_cart
					.css('display', 'block')
					.css('opacity', 0)
					.animate({opacity: 1}, 500);
		}
	};

	/**
	 * Update elements on window resize
	 *
	 * @param object event
	 * @return boolean
	 */
	this.__handleWindowResize = function(event) {
		var window_height = $(window).height();
		var content_height = window_height - 20 - self.top_menu.height() - self.summary.height() - 15;

		self.container.css('height', window_height - 20);  // 10px padding from CSS
		self.content.css('max-height', content_height);
	};

	/**
	 * Handle shopping cart content
	 * 
	 * @param object data
	 */
	this.__handleContentLoad = function(data) {
		self._items = {}
		self._item_count = 0;

		console.log(data);
		self._updateSummary();
	};

	/**
	 * Handle error from content loading procedure
	 *
	 * @param object xhr
	 * @param string status
	 * @param string error
	 */
	this.__handleContentLoadError = function(xhr, status, error) {
		alert('There was a problem while trying to load items in your shopping cart. Try refreshing page. If problem persists, please contact us.');
	};
	
	/**
	 * Load default currency from backend
	 *
	 * @param string data
	 */
	this.__handleCurrencyLoad = function(data) {
		self._default_currency = data;
		self._updateSummary();
	};

	/**
	 * Handle error from currency loading procedure
	 * 
	 * @param object xhr
	 * @param string status
	 * @param string error
	 */
	this.__handleCurrencyLoadError = function(xhr, status, error) {
	};

	/**
	 * Load payment methods from server
	 * 
	 * @param object data
	 */
	this.__handlePaymentMethodsLoad = function(data) {
		self._payment_methods = data;
	};

	/**
	 * Handle error from payment methods load procedure
	 *
	 * @param object xhr
	 * @param string status
	 * @param string error
	 */
	this.__handlePaymentMethodsLoadError = function(xhr, status, error) {
	};

	// initialize object
	this.init();
}

/**
 * Shop Item
 *
 * Shopping cart will create and add items automatically. This class
 * shouldn't be used by anyone except shopping cart.
 *
 * @param string uid	Shop item unique id
 * @param integer count	Initial number of items
 * @param object cart	Shopping cart
 */
function ShopItem(uid, count, cart) {
	var self = this;  // used internally in nested functions

	// create interface
	this.container = $('<div>');
	this.name = $('<div>');
	this.description = $('<div>');
	this.count = $('<span>');
	this.price = $('<span>');
	this.total = $('<span>');

	// local variables
	this._uid = uid;
	this._count = count;
	this._parent = cart;
	this._total = 0;  // total price
	this._price = 0;

	/**
	 * Complete object initialization
	 */
	this.init = function() {
		// configure container
		this.container
				.addClass('item')
				.addClass('loading')
				.append(this.name)
				.append(this.description)
				.append(this.count)
				.append(this.price)
				.append(this.total)
				.hide();

		this.name
				.addClass('name')
				.html(this._uid);

		this.description
				.addClass('description')
				.hide();

		this.count
				.addClass('count')
				.hide();

		this.price
				.addClass('price')
				.hide();

		this.total
				.addClass('total')
				.hide();

		// pack container
		this._parent._addChildContainer(this.container);

		// show container
		this._showContainer();

		// load data from server
		this._loadInformation();
	};

	/**
	 * Increase number of items
	 */
	this.increaseCount = function() {
		// update local variables
		this._count++;
		this._total = this._price * this._count;

		// update labels
		this._updateInformation();
		this._parent._updateSummary();
	};

	/**
	 * Decrease number of items
	 */
	this.decreaseCount = function() {
		this._count--;

		// if number of items is 0, call parent for removal
		if (this._count <= 0)
			this._parent._removeItemObject(this._uid);
	};

	/**
	 * Remove item from shopping cart
	 */
	this.remove = function() {
		this.container.remove();
	};

	/**
	 * Show item when parent adds it to the list
	 */
	this._showContainer = function() {
		this.container
				.show()
				.css('height', 0)
				.animate({height: 20}, 300);
	};

	/**
	 * Load information from server
	 */
	this._loadInformation = function() {
		// prepare data
		var data = {
					section: 'shop',
					action: 'json_get_item',
					uid: this._uid
				};

		// check local cache first
		$.ajax({
			url: this._parent._getBackendURL(),
			type: 'GET',
			async: true,
			data: data,
			dataType: 'json',
			context: this,
			success: this.__handleInformationLoad,
			error: this.__handleInformationLoadError
		});
	};

	/**
	 * Update item labels
	 */
	this._updateInformation = function() {
		this.price.html(language_handler.getText('shop', 'label_price') + ' ' + this._price);
		this.count.html(language_handler.getText('shop', 'label_count') + ' ' + this._count);
	};

	/**
	 * Show labels with animation
	 */
	this._showLabels = function() {
		this.price
				.css('display', 'block')
				.animate({opacity: 1}, 500);

		this.total
				.css('display', 'block')
				.animate({opacity: 1}, 500);

		this.count
				.css('display', 'block')
				.animate({opacity: 1}, 500);
	};

	/**
	 * Function called once information from server
	 * has been obtained.
	 *
	 * @param json data
	 */
	this.__handleInformationLoad = function(data) {
		// remove loading animation
		this.container.removeClass('loading');

		var item = data['item']
		var current_language = language_handler.current_language;

		// parse item
		if (!data['error']) {
			this.name.html(item['name'][current_language]);
			this.name.attr('title', this._uid);

			this.description.html(item['description'][current_language])

			this._price = item['price']
			this._total = this._price * this._count;

			// update labels
			this._updateInformation();

			// tell parent to update totals
			this._parent._updateSummary();

			// animate container
			this.container
					.animate({height: 50}, 300);

			// animate labels
			this._showLabels();

		} else {
			this.name.html(this._uid + ' <i><small>Error loading data!</small></i>');
		}
	};

	/**
	 * Handler errors occured during information load from server
	 *
	 * @param object xhr
	 * @param string status
	 * @param string error
	 */
	this.__handleInformationLoadError = function(xhr, status, error) {
		this.container.removeClass('loading');
		this.name.html(this._uid + ' <i><small>Error loading data!</small></i>');
	};

	// finish object initialization
	this.init();
}

// create single instance of shopping cart
$(document).ready(function() {
	shopping_cart = new ShoppingCart();
});
