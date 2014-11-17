/**
 * Shopping Cart
 *
 * Copyright (c) 2013. by Way2CU
 * Author: Mladen Mijatov
 *
 * Requires jQuery 1.4.2+
 */

var Caracal = Caracal || {};
Caracal.Shop = Caracal.Shop || {};

/**
 * Shopping Cart
 */
Caracal.Shop.Cart = function() {
	var self = this;  // used in embeded functions

	// create interface
	self.main_container = $('<div>');
	self.container = $('<div>');

	self.toggle_button = $('<a>');
	self.item_count = $('<span>');

	self.top_menu = $('<div>');
	self.checkout_button = $('<a>');
	self.clear_button = $('<a>');

	self.content = $('<div>');
	self.empty_cart = $('<div>');

	self.summary = $('<div>');

	self.total = $('<div>');
	self.subtotal = $('<div>');
	self.handling = $('<div>');
	self.shipping = $('<div>');

	self.label_total = $('<span>');
	self.label_subtotal = $('<span>');
	self.label_handling = $('<span>');
	self.label_shipping = $('<span>');

	self.value_total = $('<span>');
	self.value_subtotal = $('<span>');
	self.value_handling = $('<span>');
	self.value_shipping = $('<span>');

	self.checkout_menu = $('<ul>');

	// local variables
	self._visible = false;
	self._notification_active = false;
	self._width = 0;
	self._animation_time = 700;
	self._blink_time = 400;
	self._items = {};
	self._item_count = 0;
	self._default_currency = 'EUR';
	self._payment_methods = {};
	self._default_method = null;
	self._selected_method = null;
	self._checkout_menu_visible = false;
	self._size_values = {};
	self._shipping = 0;
	self._handling = 0;

	// get checkout form if it exists
	self._checkout_form = $('div#checkout form');
	self._on_checkout_page = self._checkout_form.length > 0;

	// base url for this site
	var base = $('base');
	self._backend_url = base.attr('href') + '/index.php';

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
				'handling',
				'total_amount',
			];

	/**
	 * Finish object initialization
	 */
	self.init = function() {

		// configure main container
		self.main_container
				.attr('id', 'shopping_cart')
				.append(self.container)
				.appendTo($('body'));

		// parse initial options
		if (typeof Caracal.shop.config !== 'undefined') {
			if ('visible' in Caracal.shop.config)
				self.main_container.css('display', Caracal.shop.config.visible ? 'block' : 'none');

			if ('default_method' in Caracal.shop.config)
				self._default_method = Caracal.shop.config.default_method;
		}

		// configure container
		self.container
				.addClass('container')
				.append(self.toggle_button)
				.append(self.item_count)
				.append(self.top_menu)
				.append(self.checkout_menu)
				.append(self.content)
				.append(self.summary);

		// configure toggle button
		self.toggle_button
				.attr('href', 'javascript: void(0);')
				.attr('title', language_handler.getText('shop', 'show_shopping_cart'))
				.click(self.toggleVisibility)
				.addClass('toggle_button');

		// configure item count label
		self.item_count
				.addClass('item_count')
				.html('-')
				.click(self.toggleVisibility)

		// configure top menu
		self.top_menu
				.addClass('top_menu')
				.append(self.clear_button)
				.append(self.checkout_button);

		self.checkout_button
				.addClass('checkout')
				.html(language_handler.getText('shop', 'checkout'))
				.click(self.showCheckoutMenu);

		self.clear_button
				.html(language_handler.getText('shop', 'clear'))
				.click(self._clearCart);

		// configure checkout menu
		self.checkout_menu
				.addClass('checkout');

		// configure content container
		self.content
				.addClass('content')
				.append(self.empty_cart);

		self.empty_cart
				.addClass('empty')
				.html(language_handler.getText('shop', 'empty_shopping_cart'));

		// configure shopping cart summary container
		self.summary
				.addClass('summary')
				.append(self.subtotal)
				.append(self.handling)
				.append(self.shipping)
				.append(self.total);

		self.subtotal
				.addClass('subtotal')
				.append(self.label_subtotal)
				.append(self.value_subtotal);

		self.handling
				.addClass('handling')
				.append(self.label_handling)
				.append(self.value_handling);

		self.shipping
				.addClass('shipping')
				.append(self.label_shipping)
				.append(self.value_shipping);

		self.total
				.addClass('total')
				.append(self.label_total)
				.append(self.value_total);

		// configure values
		self.value_subtotal.addClass('value');
		self.value_handling.addClass('value');
		self.value_shipping.addClass('value');
		self.value_total.addClass('value');

		// load summary labels
		self.label_subtotal.html(language_handler.getText('shop', 'subtotal_amount'));
		self.label_handling.html(language_handler.getText('shop', 'handling'));
		self.label_shipping.html(language_handler.getText('shop', 'shipping'));
		self.label_total.html(language_handler.getText('shop', 'total_amount'));

		// connect events
		$(window).resize(self.__handleWindowResize);

		// get container width for later use
		self._width = self.main_container.width();

		// connect submit button on checkout form to our handler
		if (self._on_checkout_page)
			self._checkout_form.find('button[type=submit]').click(self._handleCheckout);

		// load cart items from cookies
		self._loadContent();
		self._loadPaymentMethods();

		// manually call event handler to
		// calculate initial values
		self.__handleWindowResize();
	};

	/**
	 * Show shopping cart
	 */
	self.showCart = function() {
		if (self._visible)
			return;

		self.main_container.animate(
					{right: 0},
					self._animation_time,
					function() {
						self.toggle_button.attr(
										'title',
										language_handler.getText('shop', 'hide_shopping_cart')
									);
						self._visible = true;
					}
				);
	};

	/**
	 * Show checkout menu
	 */
	self.showCheckoutMenu = function() {
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
	self.hideCart = function() {
		if (!self._visible)
			return;

		self.main_container.animate(
					{right: -self._width},
					self._animation_time,
					function() {
						self.toggle_button.attr(
								'title',
								language_handler.getText('shop', 'show_shopping_cart')
							);
						self._visible = false;
					}
				);
	};

	/**
	 * Toggle cart visibility
	 */
	self.toggleVisibility = function() {
		if (self._visible)
			self.hideCart(); else
			self.showCart();
	};

	/**
	 * Blink shopping cart button
	 */
	self.notifyUser = function() {
		if (self._notification_active)
			return;

		var chain_cart = new AnimationChain(null, false, 3);

		chain_cart
			.addAnimation(
					self.toggle_button,
					{opacity: 0},
					self._blink_time
			)
			.addAnimation(
					self.toggle_button,
					{opacity: 1},
					self._blink_time
			)
			.callback(function() {
				self._notification_active = false;
			});

		self._notification_active = true;
		chain_cart.start();
	};

	/**
	 * Open checkout page
	 */
	self.checkout = function() {
		var method_name = $(self).data('name');

		// use default payment method if specified
		if (!method_name && self._default_method)
			method_name = self._default_method;

		// store selected payment method
		self._selected_method = method_name;

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
	 * Change delivery method and reload the page
	 *
	 * @param integer delivery_method
	 */
	self.changeDeliveryMethod = function(delivery_method) {
		self._updateCheckoutForm(false, delivery_method);

		// enable or disable submit button on checkout form
		if (delivery_method > 0)
			self._checkout_form.find('button[type=submit]').removeAttr('disabled'); else
			self._checkout_form.find('button[type=submit]').attr('disabled', 'disabled'); 
	};

	/**
	 * Get value for specified size
	 *
	 * @param integer size
	 * @return string
	 */
	self.getSizeValue = function(size) {
		var result = '';

		if (size in self._size_values) 
			result = self._size_values[size]['value'][language_handler.current_language];

		return result;
	};

	/**
	 * Update checkout form items
	 *
	 * @param boolean update_items
	 * @param integer delivery_method
	 */
	self._updateCheckoutForm = function(update_items, delivery_method) {
		if (!self._on_checkout_page) 
			return;

		// load items table
		if (update_items) {
			var data = {
						section: 'shop',
						action: 'show_checkout_items',
					};

			$.ajax({
				url: self._getBackendURL(),
				type: 'POST',
				async: true,
				data: data,
				dataType: 'html',
				context: self,
				success: function(data) {
					self._checkout_form.find('tbody').html(data);
				}
			});
		}

		// load transaction totals
		var data = {
					section: 'shop',
					action: 'json_get_shopping_cart_summary',
				};

		if (delivery_method != undefined)
			data['delivery_method'] = delivery_method;

		$.ajax({
			url: self._getBackendURL(),
			type: 'POST',
			async: true,
			data: data,
			dataType: 'json',
			context: self,
			success: function(data) {
				self._shipping = parseFloat(data.shipping);
				self._handling = parseFloat(data.handling);

				// update shopping cart
				self._updateSummary();

				// update checkout form
				self._checkout_form.find('.subtotal').html(parseFloat(data.total).toFixed(2));
				self._checkout_form.find('.shipping').html(self._shipping.toFixed(2));
				self._checkout_form.find('.handling').html(self._handling.toFixed(2));
				self._checkout_form.find('.weight').html(parseFloat(data.weight).toFixed(2) + ' kg');

				// update total value
				var total = parseFloat(data.total) + parseFloat(data.shipping) + parseFloat(data.handling);
				self._checkout_form.find('.total-value').html(total.toFixed(2) + ' ' + self._default_currency);
			}
		});
	};

	/**
	 * Handle checout form submit
	 *
	 * @param object event
	 * @return boolean
	 */
	self._handleCheckout = function(event) {
		var remark = self._checkout_form.find('textarea[name=remarks]');

		// if there's a remark on self form, process that first
		if (remark.length > 0 && remark.val().length > 0) {
			// prevent browser from redirecting until we save remark
			event.preventDefault();

			// gather data and send them to server
			var data = {
						section: 'shop',
						action: 'json_save_remark',
						remark: remark.val()
					};

			$.ajax({
				url: self._getBackendURL(),
				type: 'POST',
				async: false,
				data: data,
				dataType: 'json',
				context: self,
				success: function(data) {
					// there was an error saving remark
					if (!data)
						alert('Error saving remark');
					
					self._checkout_form.submit();
				}
			});
		}
	};

	/**
	 * Load cart content from server
	 */
	self._loadContent = function() {
		var data = {
					section: 'shop',
					action: 'json_get_shopping_cart',
				};

		$.ajax({
			url: self._getBackendURL(),
			type: 'POST',
			async: true,
			data: data,
			dataType: 'json',
			context: self,
			success: self.__handleContentLoad,
			error: self.__handleContentLoadError
		});
	};

	/**
	 * Load default currency from server
	 */
	self._loadDefaultCurrency = function() {
		var data = {
					section: 'shop',
					action: 'json_get_currency',
				};

		$.ajax({
			url: self._getBackendURL(),
			type: 'GET',
			async: true,
			data: data,
			dataType: 'json',
			context: self,
			success: self.__handleCurrencyLoad,
			error: self.__handleCurrencyLoadError
		});
	};
	
	/**
	 * Load payment methods from server
	 */
	self._loadPaymentMethods = function() {
		var data = {
					section: 'shop',
					action: 'json_get_payment_methods',
				};

		$.ajax({
			url: self._getBackendURL(),
			type: 'GET',
			async: true,
			data: data,
			dataType: 'json',
			context: self,
			success: self.__handlePaymentMethodsLoad,
			error: self.__handlePaymentMethodsLoadError
		});
	};

	/**
	 * Method called by the shop item after initialization is completed.
	 * @param object item
	 */
	self._addItem = function(item) {
		var key = item._uid + '.' + item._variation_id;

		self._item_count++;
		self._items[key] = item;

		if (self._item_count == 1)
			self._hideEmptyMessage();

		self._updateSummary();
	};

	/**
	 * Add child container to content list
	 * @param object container
	 */
	self._addChildContainer = function(container) {
		self.content.append(container);
	};

	/**
	 * Remove child object from local list
	 * @param object item
	 */
	self._removeItem = function(item) {
		var key = item._uid + '.' + item._variation_id;

		if (!key in self._items)
			return;

		// animate item removal
		var container = self._items[key].getContainer();
		container.animate({opacity: 0, height: 0}, 300, function() {
			$(self).remove();
		});

		// remove item from the list
		delete self._items[key];
		self._item_count--;

		// notify the server about removal
		var data = {
					section: 'shop',
					action: 'json_remove_item_from_shopping_cart',
					uid: item._uid,
					variation_id: item._variation_id
				};

		$.ajax({
			url: self._getBackendURL(),
			type: 'POST',
			async: true,
			data: data,
			dataType: 'json',
			context: self,
			success: function(data) {
						if (!data)
							alert('There was a problem while removing item from cart.');
					}
		});

		// show messages if needed
		if (self._item_count <= 0)
			self._showEmptyMessage();

		// update shopping cart summary
		self._updateSummary();
	};

	/**
	 * Show empty cart message
	 */
	self._showEmptyMessage = function() {
		self.empty_cart
				.css('display', 'block')
				.css('opacity', 0)
				.animate({opacity: 1}, 500);
	};

	/**
	 * Hide empty cart message
	 */
	self._hideEmptyMessage = function() {
		self.empty_cart
				.animate({opacity: 0, height: 0}, 500, function() {
					$(self).css('display', 'none');
				});
	};

	/**
	 * Update cart summary
	 */
	self._updateSummary = function() {
		var text = language_handler.getText('shop', 'total_amount');
		var amount = 0;

		// calculate total price
		for (var uid in self._items) {
			var item = self._items[uid];

			amount += item._total;
		}

		// update shopping cart summary
		self.value_subtotal.html(amount.toFixed(2));
		self.value_handling.html(self._handling.toFixed(2));
		self.value_shipping.html(self._shipping.toFixed(2));

		this.value_total.html((amount + self._shipping + self._handling).toFixed(2) + ' ' + self._default_currency);

		// update item count
		self.item_count.html(self._item_count);
	};

	/**
	 * Return backend URL
	 * @return string
	 */
	self._getBackendURL = function() {
		return self._backend_url;
	};

	/**
	 * Clear shopping cart
	 */
	self._clearCart = function() {
		var text = language_handler.getText('shop', 'message_clear_cart');

		if (confirm(text)) {
			// clear shopping cart
			for (var key in self._items) 
				self._removeItem(self._items[key]);

			// let server know we cleared out cart
			var data = {
						section: 'shop',
						action: 'json_clear_shopping_cart',
					};

			$.ajax({
				url: self._getBackendURL(),
				type: 'POST',
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
	self.__handleWindowResize = function(event) {
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
	self.__handleContentLoad = function(data) {
		var items = data.cart;
		var total_items = data.count;

		self._size_values = data.size_values;
		self._default_currency = data.currency;
		self._shipping = parseFloat(data.shipping);
		self._handling = parseFloat(data.handling);

		for (var key in items) {
			var data = items[key];
			var uid = data['uid'];
			var item = new Caracal.Shop.Item(uid, self);

			// set item data
			item._setData(data);
			item._updateInterface();
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
	self.__handleContentLoadError = function(xhr, status, error) {
	};

	/**
	 * Load default currency from backend
	 *
	 * @param string data
	 */
	self.__handleCurrencyLoad = function(data) {
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
	self.__handleCurrencyLoadError = function(xhr, status, error) {
	};

	/**
	 * Load payment methods from server
	 * 
	 * @param object data
	 */
	self.__handlePaymentMethodsLoad = function(data) {
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
	self.__handlePaymentMethodsLoadError = function(xhr, status, error) {
	};

	// initialize object
	//self.init();
	language_handler.getTextArrayAsync('shop', constants, function() { self.init(); });
}

/**
 * Shop Item
 *
 * Shopping cart will create and add items automatically. This class
 * shouldn't be used by anyone except shopping cart.
 *
 * @param string uid	Shop item unique id
 * @param object cart	Shopping cart
 */
Caracal.Shop.Item = function(uid, cart) {
	var self = this;  // used internally in nested functions

	// create interface
	self.container = $('<div>');
	self.name = $('<div>');
	self.count = $('<div>');
	self.price = $('<div>');
	self.thumbnail = $('<div>');

	self.label_price = $('<span>');
	self.label_count = $('<span>');

	self.button_edit = $('<a>');
	self.button_delete = $('<a>');

	// local variables
	self._parent = cart;
	self._uid = uid;
	self._variation_id = null;
	self._properties = {};
	self._count = 0;
	self._total = 0;  // total price
	self._price = 0;
	self._tax = 0;
	self._name = '';
	self._image_url = '';

	/**
	 * Complete object initialization
	 */
	self.init = function() {
		// configure container
		self.container
				.addClass('item')
				.addClass('loading')
				.append(self.name)
				.append(self.thumbnail)
				.append(self.count)
				.append(self.price)
				.append(self.button_edit)
				.append(this.button_delete)
				.hide();

		self.name
				.addClass('name')
				.html(self._uid);

		self.count
				.addClass('count')
				.hide();

		self.price
				.addClass('price')
				.hide();

		self.thumbnail
				.addClass('thumbnail')
				.hide();

		self.label_price.html(language_handler.getText('shop', 'label_price'));
		self.label_count.html(language_handler.getText('shop', 'label_count'));

		// configure buttons
		self.button_edit
				.addClass('edit')
				.attr('href', 'javascript: void(0)')
				.attr('title', language_handler.getText('shop', 'edit_item'))
				.click(self.changeCount);

		self.button_delete
				.addClass('delete')
				.attr('href', 'javascript: void(0)')
				.attr('title', language_handler.getText('shop', 'delete_item'))
				.click(self.remove);

		// pack container
		self._parent._addChildContainer(self.container);

		// show container
		self._showContainer();
	};

	/**
	 * Completes object initialization and loads data from the server
	 */
	self.complete = function() {
		var data = {
					section: 'shop',
					action: 'json_add_item_to_shopping_cart',
					uid: self._uid,
					properties: self._properties
				};

		$.ajax({
			url: self._parent._getBackendURL(),
			type: 'POST',
			async: true,
			data: data,
			dataType: 'json',
			context: self,
			success: self.__handleInformationLoad,
			error: self.__handleInformationLoadError
		});
	};

	/**
	 * Decrease number of items
	 */
	self.changeCount = function() {
		var new_count = parseInt(prompt(
						language_handler.getText('shop', 'message_edit_item_in_cart'),
						self._count
					));

		if (new_count <= 0) {
			// if number of items is 0, call parent for removal
			self._parent._removeItem(self);

		} else {
			// change item value
			if (!isNaN(new_count))
				self._count = new_count;

			// notify server about count change
			self._notifyCount();

			// update information
			self._updateInformation();
			self._parent._updateSummary();
		}

		// if needed update checkout form
		self._parent._updateCheckoutForm(true);
	};

	/**
	 * Return item container
	 * @return object
	 */
	self.getContainer = function() {
		return self.container;
	};

	/**
	 * Remove item from shopping cart
	 * @return boolean
	 */
	self.remove = function() {
		var text = language_handler.getText('shop', 'message_remove_item_from_cart');
		var result = false;

		if (confirm(text))
			result = self._parent._removeItem(self);

		return result;
	};

	/**
	 * Set item property.
	 *
	 * These properties are combined to make a variation on server side
	 * and are later used when checking out.
	 *
	 * @param integer size
	 */
	self.setProperty = function(property, value) {
		self._properties[property] = value;
	};

	/**
	 * Return item property
	 *
	 * @param string property
	 * @return string
	 */
	self.getProperty = function(property) {
		return self._properties[property];
	};

	/**
	 * Method used to set item coun initially
	 * @param integer count
	 */
	self.setCount = function(count) {
		self._count = count;
	};

	/**
	 * Increment item count by one.
	 */
	self.incrementCount = function() {
		self._count++;
		self._notifyCount();

		self._updateInformation();
		self._parent._updateSummary();
		self._parent.notifyUser();
	};

	/**
	 * Update item labels
	 */
	self._updateInformation = function() {
		var current_language = language_handler.current_language;

		// update total price
		self._total = self._price * self._count;

		// set item name
		self.name.html(self._name[current_language]);

		if ('size' in self._properties) {
			var size_container = $('<small>');

			size_container
				.html('&nbsp;(' + self._parent.getSizeValue(self._properties['size']) + ')')
				.appendTo(self.name);
		}

		if ('color' in self._properties) {
			var color_container = $('<span>');

			color_container
				.addClass('color')
				.css('background-color', self._properties['color_value'])
				.attr('title', self._properties['color'])
				.prependTo(self.name);
		}

		// set other fields
		self.price.html(self._price);
		self.price.prepend(self.label_price);

		self.count.html(self._count);
		self.count.prepend(self.label_count);
	};

	/**
	 * Update interface after data is loaded
	 */
	self._updateInterface = function() {
		// remove loading animation
		self.container.removeClass('loading');

		// update labels
		self._updateInformation();

		// animate labels
		self._showLabels();
	};

	/**
	 * Notify server about changed item count
	 */
	self._notifyCount = function() {
		// notify server about quantity change
		var data = {
					section: 'shop',
					action: 'json_change_item_quantity',
					uid: self._uid,
					variation_id: self._variation_id,
					count: self._count
				};

		$.ajax({
			url: self._parent._getBackendURL(),
			type: 'POST',
			async: true,
			data: data,
			dataType: 'json',
			context: self,
			success: function(data) {
						if (!data)
							alert('There was a problem with changing item quantity.');
					}
		});
	};

	/**
	 * Show item when parent adds it to the list
	 */
	self._showContainer = function() {
		self.container
				.show()
				.css('height', 0)
				.animate({height: 20}, 300);
	};

	/**
	 * Show labels with animation
	 */
	self._showLabels = function() {
		// animate container
		self.container
				.animate({height: 50}, 300);

		// show other labels
		self.price
				.css('display', 'block')
				.animate({opacity: 1}, 500);

		self.thumbnail
				.css('display', 'block')
				.animate({opacity: 1}, 500);

		self.count
				.css('display', 'block')
				.animate({opacity: 1}, 500);
	};

	/**
	 * Set item data from specified object
	 * @param object data
	 */
	self._setData = function(data) {
		self._name = data['name'];
		self._price = parseFloat(data['price']).toFixed(2);
		self._tax = parseFloat(data['tax']).toFixed(2);
		self._weight = parseFloat(data['weight']).toFixed(2);
		self._image_url = data['image'];
		self._count = data['count'];
		self._variation_id = data['variation_id'];

		if ('properties' in data)
			self._properties = data['properties'];

		// add item to parent
		self._parent._addItem(self);
	};

	/**
	 * Function called once information from server
	 * has been obtained.
	 *
	 * @param json data
	 */
	self.__handleInformationLoad = function(data) {
		// assign data
		self._setData(data);

		// update interface
		self._updateInterface();

		// tell parent to update totals
		self._parent._updateSummary();

		// notify user
		self._parent.notifyUser();
	};

	/**
	 * Handler errors occured during information load from server
	 *
	 * @param object xhr
	 * @param string status
	 * @param string error
	 */
	self.__handleInformationLoadError = function(xhr, status, error) {
		self.container.removeClass('loading');
		self.name.html(self._uid + ' <i><small>Error loading data!</small></i>');
	};

	// finish object initialization
	self.init();
}

// create single instance of shopping cart
$(function() {
	Caracal.Shop.cart = new Caracal.Shop.Cart();
});
