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

	this.total = $('<div>');
	this.subtotal = $('<div>');
	this.tax = $('<div>');
	this.shipping = $('<div>');

	this.label_total = $('<span>');
	this.label_subtotal = $('<span>');
	this.label_tax = $('<span>');
	this.label_shipping = $('<span>');

	this.value_total = $('<span>');
	this.value_subtotal = $('<span>');
	this.value_tax = $('<span>');
	this.value_shipping = $('<span>');

	this.checkout_menu = $('<ul>');

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
	this._default_method = null;
	this._checkout_menu_visible = false;
	this._size_values = {};

	// base url for this site
	var base = $('base');
	this._backend_url = base.attr('href') + '/index.php';

	var constants = [
				'checkout',
				'clear',
				'delete_item',
				'edit_item',
				'empty_shopping_cart',
				'hide_shopping_cart',
				'label_count',
				'label_price',
				'message_clear_cart',
				'message_edit_item_in_cart',
				'message_no_items_in_cart',
				'message_remove_item_from_cart',
				'shipping',
				'show_shopping_cart',
				'subtotal_amount',
				'tax',
				'total_amount',
			];


	/**
	 * Finish object initialization
	 */
	this.init = function() {

		// configure main container
		this.main_container
				.attr('id', 'shopping_cart')
				.append(this.container)
				.appendTo($('body'));

		// parse initial options
		if (typeof shop_init_options !== 'undefined') {
			if ('visible' in shop_init_options)
				this.main_container.css('display', shop_init_options.visible ? 'block' : 'none');
	
			if ('default_method' in shop_init_options)
				this._default_method = shop_init_options.default_method;
		}

		// configure container
		this.container
				.addClass('container')
				.append(this.toggle_button)
				.append(this.item_count)
				.append(this.top_menu)
				.append(this.checkout_menu)
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
				.html(language_handler.getText('shop', 'checkout'))
				.click(this.showCheckoutMenu);

		this.clear_button
				.html(language_handler.getText('shop', 'clear'))
				.click(this._clearCart);

		// configure checkout menu
		this.checkout_menu
				.addClass('checkout');

		// configure content container
		this.content
				.addClass('content')
				.append(this.empty_cart);

		this.empty_cart
				.addClass('empty')
				.html(language_handler.getText('shop', 'empty_shopping_cart'));

		// configure shopping cart summary container
		this.summary
				.addClass('summary')
				.append(this.subtotal)
				.append(this.tax)
				.append(this.shipping)
				.append(this.total);

		this.subtotal
				.addClass('subtotal')
				.append(this.label_subtotal)
				.append(this.value_subtotal);

		this.tax
				.addClass('tax')
				.append(this.label_tax)
				.append(this.value_tax);

		this.shipping
				.addClass('shipping')
				.append(this.label_shipping)
				.append(this.value_shipping);

		this.total
				.addClass('total')
				.append(this.label_total)
				.append(this.value_total);

		// configure values
		this.value_subtotal.addClass('value');
		this.value_tax.addClass('value');
		this.value_shipping.addClass('value');
		this.value_total.addClass('value');

		// load summary labels
		this.label_subtotal.html(language_handler.getText('shop', 'subtotal_amount'));
		this.label_tax.html(language_handler.getText('shop', 'tax'));
		this.label_shipping.html(language_handler.getText('shop', 'shipping'));
		this.label_total.html(language_handler.getText('shop', 'total_amount'));

		// connect events
		$(window).resize(this.__handleWindowResize);

		// get container width for later use
		this._width = this.main_container.width();

		// load cart items from cookies
		this._loadContent();
		this._loadPaymentMethods();

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
	 * Show checkout menu
	 */
	this.showCheckoutMenu = function() {
		var y_pos = self.checkout_button.offset().top + self.top_menu.height(); 

		if (self._checkout_menu_visible) {
			// hide checkout menu
			self.checkout_menu
					.stop(true, true)
					.animate({opacity: 0}, 200, function() {
						self._checkout_menu_visible = false;
						self.checkout_menu.css('display', 'none');
					});

		} else {
			// show checkout menu
			self.checkout_menu
					.css({
						display: 'block',
						opacity: 0,
						top: y_pos
					})
					.stop(true, true)
					.animate({opacity: 1}, 200, function() {
						self._checkout_menu_visible = true;
					});
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
	 * @param integer size
	 * @param object data
	 * @param boolean skip_notify
	 * @param boolean skip_update
	 * @return integer
	 */
	this.addItem = function(uid, size, data, skip_notify, skip_update) {
		var key = size ? uid + '_' + size : uid;

		if (key in this._items) {
			// increase number of existing items
			var item = this._items[key];
			item.addItemToCart();

			// update total count of items
			if (!skip_update)
				this._updateSummary();

		} else {
			// create new item in shopping cart
			var createShopItem = function() {
					var item = new ShopItem(uid, size, self, data);

					// hide empty cart message
					self.empty_cart.css('display', 'none');

					// add new item to the list
					self._items[key] = item;
					self._item_count++;

					// update total count of items only if we are
					// loading new item from the server
					if (!skip_update)
						self._updateSummary();
				};

			if (this._item_count > 0)
				createShopItem(); else
				this.empty_cart.animate({opacity: 0}, 200, createShopItem);  // animate hide only once
		}

		// notify user
		if (!skip_notify)
			this.notifyUser();
	};

	/**
	 * Remove specified number or all items from
	 * shopping cart. Return true if specified number
	 * of items was removed.
	 *
	 * @param string uid
	 * @param integer size
	 * @return boolean
	 */
	this.removeItem = function(uid, size) {
		var key = size ? uid + '_' + size : uid;

		if (!key in this._items)
			return;

		var text = language_handler.getText('shop', 'message_remove_item_from_cart');
		var item_object = this._items[key].getContainer();
		var result = false;

		if (confirm(text)) {
			item_object.animate({opacity: 0, height: 0}, 300, function() {
				// remove item container from list
				$(this).remove();

				// kill item object
				self._removeItemObject(uid, size);

				// notify server we removed an item
				var data = {
							section: 'shop',
							action: 'json_remove_item_from_shopping_cart',
							uid: uid
						};

				if (size)
					data['size'] = size;

				$.ajax({
					url: self._getBackendURL(),
					type: 'GET',
					async: true,
					data: data,
					dataType: 'json',
					context: self,
					success: function(data) {
								if (!data)
									alert('There was a problem while removing item from cart.');
							}
				});

				// show empty cart message
				if (self._item_count <= 0)
					self.empty_cart
							.css('display', 'block')
							.css('opacity', 0)
							.animate({opacity: 1}, 500);

				self._updateSummary();
			});

			result = true;
		}

		return result;
	};
	
	/**
	 * Open checkout page
	 */
	this.checkout = function() {
		var method_name = $(this).data('name');

		// use default payment method if specified
		if (!method_name && self._default_method)
			method_name = self._default_method;

		if (self._item_count > 0) {
			var url = self._getBackendURL();
			var params = {
					section: 'shop',
					action: 'checkout',
					method: method_name
				}

			// append additional parameters to base URL
			url += '?' + $.param(params);

			// navigate to checkout
			window.location = url;

		} else {
			// show warning to user
			alert(language_handler.getText('shop', 'message_no_items_in_cart'));
		}
	};

	/**
	 * Get value for specified size
	 *
	 * @param integer size
	 * @return string
	 */
	this.getSizeValue = function(size) {
		var result = '';

		if (size in this._size_values) 
			result = this._size_values[size]['value'][language_handler.current_language];

		return result;
	};

	/**
	 * Load cart content from server
	 */
	this._loadContent = function() {
		var data = {
					section: 'shop',
					action: 'json_get_shopping_cart',
				};

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
		var data = {
					section: 'shop',
					action: 'json_get_currency',
				};

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
		var data = {
					section: 'shop',
					action: 'json_get_payment_methods',
				};

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
	this._removeItemObject = function(uid, size) {
		var key = size ? uid + '_' + size : uid;

		if (!key in this._items)
			return;

		delete this._items[key];
		this._item_count--;
	};

	/**
	 * Update cart summary
	 */
	this._updateSummary = function() {
		var text = language_handler.getText('shop', 'total_amount');
		var amount = 0;
		var shipping = 0;
		var tax = 0;

		// calculate total price
		for (var uid in this._items) {
			var item = this._items[uid];

			amount += item._total;
			tax += item._total * (item._tax / 100);
		}

		// update shopping cart summary
		this.value_subtotal.html(amount.toFixed(2));
		this.value_tax.html(tax.toFixed(2));
		this.value_shipping.html(shipping.toFixed(2));

		this.value_total.html((amount + shipping + tax).toFixed(2) + ' ' + this._default_currency);

		// update item count
		this.item_count.html(this._item_count);
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
			for (var key in self._items) {
				var item = self._items[key];

				item.getContainer().slideUp(200, function() {
					$(this).remove();
				});
				delete self._items[key];
			}

			// let server know we cleared out cart
			var data = {
						section: 'shop',
						action: 'json_clear_shopping_cart',
					};

			$.ajax({
				url: self._getBackendURL(),
				type: 'GET',
				async: true,
				data: data,
				dataType: 'json',
				context: self,
				success: function(data) {
						if (!data)
							alert('There was a problem while clearing shopping cart.');
					},
			});

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
		var current_item = 0;
		var items = data.cart;
		var total_items = data.count;
		var items_added = 0;

		self._size_values = data.size_values;

		for (var key in items) {
			var item = items[key];

			// increase item counter
			current_item++;
			
			// try adding item from sizes list
			items_added = 0;
			for (var i in item.sizes) {
				self.addItem(key, i, item, true, current_item < total_items);
				items_added++;
			}

			// add single size item to shopping cart
			if (items_added == 0)
				self.addItem(key, null, item, true, current_item < total_items);
		}

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

		for (var i in data){
			var method = data[i];
			var menu_item = $('<li>');

			menu_item
				.html(method.title)
				.css('background-image', 'url(' + method.icon + ')')
				.data('name', method.name)
				.click(self.checkout)
				.appendTo(self.checkout_menu);
		}
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
	//this.init();
	language_handler.getTextArrayAsync('shop', constants, function() { self.init(); });
}

/**
 * Shop Item
 *
 * Shopping cart will create and add items automatically. This class
 * shouldn't be used by anyone except shopping cart.
 *
 * @param string uid	Shop item unique id
 * @param integer size	Index number of size definition
 * @param integer count	Initial number of items
 * @param object cart	Shopping cart
 */
function ShopItem(uid, size, cart, data) {
	var self = this;  // used internally in nested functions

	// create interface
	this.container = $('<div>');
	this.name = $('<div>');
	this.count = $('<div>');
	this.price = $('<div>');
	this.thumbnail = $('<div>');

	this.label_price = $('<span>');
	this.label_count = $('<span>');

	this.button_edit = $('<a>');
	this.button_delete = $('<a>');

	// local variables
	this._parent = cart;
	this._uid = uid;
	this._size = size;
	this._count = 0;
	this._total = 0;  // total price
	this._price = 0;
	this._tax = 0;
	this._name = '';
	this._image_url = '';

	/**
	 * Complete object initialization
	 */
	this.init = function() {
		// configure container
		this.container
				.addClass('item')
				.addClass('loading')
				.append(this.name)
				.append(this.thumbnail)
				.append(this.count)
				.append(this.price)
				.append(this.button_edit)
				.append(this.button_delete)
				.hide();

		this.name
				.addClass('name')
				.html(this._uid);

		this.count
				.addClass('count')
				.hide();

		this.price
				.addClass('price')
				.hide();
				
		this.thumbnail
				.addClass('thumbnail')
				.hide();

		this.label_price.html(language_handler.getText('shop', 'label_price'));
		this.label_count.html(language_handler.getText('shop', 'label_count'));

		// configure buttons
		this.button_edit
				.addClass('edit')
				.attr('href', 'javascript: void(0)')
				.attr('title', language_handler.getText('shop', 'edit_item'))
				.click(this.changeCount);

		this.button_delete
				.addClass('delete')
				.attr('href', 'javascript: void(0)')
				.attr('title', language_handler.getText('shop', 'delete_item'))
				.click(this.remove);

		// pack container
		this._parent._addChildContainer(this.container);

		// show container
		this._showContainer();

		// load data 
		if (data) {
			// load specified data
			this.__handleInformationLoad(data);

		} else {
			// load data from server
			this.addItemToCart();
		}
	};

	/**
	 * Decrease number of items
	 */
	this.changeCount = function() {
		var new_count = parseInt(prompt(
						language_handler.getText('shop', 'message_edit_item_in_cart'),
						self._count
					));

		if (new_count <= 0) {
			// if number of items is 0, call parent for removal
			self._parent.removeItem(self._uid); 

		} else {
			// change item value
			if (!isNaN(new_count))
				self._count = new_count;

			// notify server about quantity change
			var data = {
						section: 'shop',
						action: 'json_change_item_quantity',
						uid: self._uid,
						quantity: self._count
					};

			if (self._size)
				data['size'] = self._size;

			$.ajax({
				url: self._parent._getBackendURL(),
				type: 'GET',
				async: true,
				data: data,
				dataType: 'json',
				context: self,
				success: function(data) {
							if (!data)
								alert('There was a problem with changing item quantity.');
						}
			});

			// update information
			self._updateInformation();
			self._parent._updateSummary();
		}

	};

	/**
	 * Return item container
	 * @return object
	 */
	this.getContainer = function() {
		return this.container;
	};

	/**
	 * Remove item from shopping cart
	 */
	this.remove = function() {
		return self._parent.removeItem(self._uid, self._size);
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
	this.addItemToCart = function() {
		var data = {
					section: 'shop',
					action: 'json_add_item_to_shopping_cart',
					uid: this._uid
				};

		if (this._size)
			data['size'] = this._size;

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
		var current_language = language_handler.current_language;

		// update total price
		this._total = this._price * this._count;

		// set item name
		this.name.html(this._name[current_language]); 

		if (this._size) {
			var size_container = $('<small>');

			size_container
				.html('&nbsp;(' + this._parent.getSizeValue(this._size) + ')')
				.appendTo(this.name);
		}

		this.name.attr('title', this._uid);

		// set other fields
		this.price.html(this._price);
		this.price.prepend(this.label_price);

		this.count.html(this._count);
		this.count.prepend(this.label_count);
	};

	/**
	 * Show labels with animation
	 */
	this._showLabels = function() {
		// animate container
		this.container
				.animate({height: 50}, 300);

		// show other labels
		this.price
				.css('display', 'block')
				.animate({opacity: 1}, 500);

		this.thumbnail
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

		// assign data
		this._name = data['name']
		this._price = parseFloat(data['price']).toFixed(2)
		this._tax = parseFloat(data['tax']).toFixed(2)
		this._weight = parseFloat(data['weight']).toFixed(2)
		this._image_url = data['image']
		
		if (this._size)
			this._count = data['sizes'][this._size]; else
			this._count = data['quantity'];


		// update labels
		this._updateInformation();

		// tell parent to update totals
		this._parent._updateSummary();

		// animate labels
		this._showLabels();
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
