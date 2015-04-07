/**
 * Shopping Cart JavaScript
 *
 * Copyright (c) 2015. by Way2CU, http://way2cu.com
 * Authors: Mladen Mijatov
 */

var Caracal = Caracal || {};
Caracal.Shop = Caracal.Shop || {};


/**
 * Constructor function for Shopping Cart integration.
 *
 * Note: When dealing with items, CID refers to combination id which is item ID
 * and variation id combined.
 *
 * Signals fired by this object:
 * 	item-added (cart, uid, variation id, properties)
 * 	item-removed (cart, item)
 * 	item-amount-change (cart, item, new amount)
 * 	before-checkout
 * 	checkout
 *
 * @return object
 */
Caracal.Shop.Cart = function() {
	var self = this;

	self.items = {};
	self.reservations = [];
	self.default_currency = '';
	self.currency = self.default_currency;
	self.exchange_rate = 1;
	self.handling = 0;
	self.shipping = 0;
	self.ui = {};
	self.events = {};
	self.handlers = {};
	self.item_views = new Array();
	self.checkout_url = '/shop/checkout';

	/**
	 * Complete object initialization.
	 */
	self._init = function() {
		self._communicator = new Communicator('shop');

		// create user interface containers
		self.ui.item_list = $();
		self.ui.total_count = $();
		self.ui.total_cost = $();
		self.ui.total_weight = $();
		self.ui.checkout_button = $();

		// create event containers
		self.events.on_item_added = new Array();
		self.events.on_item_removed = new Array();
		self.events.on_item_amount_change = new Array();
		self.events.on_ui_update = new Array();
		self.events.on_before_checkout = new Array();
		self.events.on_checkout = new Array();

		// load shopping cart from server
		new Communicator('shop')
			.on_success(self.handlers.cart_load_success)
			.get('json_get_shopping_cart');
	};

	/**
	 * Add new item to the shopping cart.
	 *
	 * @param object item
	 * @return boolean
	 */
	self.add_item = function(item) {
		var result = false;

		// make sure item is of right type
		if (!(item instanceof Caracal.Shop.Item))
			return result;

		// add item if all signal handlers permit it
		if (self.events.emit_signal('item-added', self, item.uid, item.variation_id, item.properties)) {
			var cid = item.get_cid();
			self.items[cid] = item;
			result = true;
		}

		return result;
	};

	/**
	 * Add new item with specified CID to the shopping cart.
	 *
	 * @param string cid
	 */
	self.add_item_by_cid = function(cid) {
		// prepare server data
		var cid_data = cid.split('/', 2);
		var data = {
			uid: cid_data[0],
			variation_id: cid_data[1]
		};

		// check if item is reserved
		if (self.reservations.indexOf(data.uid) > -1)
			return;

		if (cid in self.items) {
			// item already exists, increase count
			var item = self.items[cid];
			if (item != null)
				item.alter_count(1);

		} else if (self.events.emit_signal('item-added', self, data.uid, data.variation_id, null)) {
			// temporarily prevent modifications of this item
			self.reservations.push(data.uid);

			// add item through server
			new Communicator('shop')
				.on_success(self.handlers.item_add_success)
				.on_error(self.handlers.item_add_error)
				.set_callback_data(cid)
				.send('json_add_item_to_shopping_cart', data);
		}
	};

	/**
	 * Add new item with specified unique id and a set of properties. Properties will
	 * be used to generate variation id.
	 *
	 * @param string uid
	 * @param object properties
	 */
	self.add_item_by_uid = function(uid, properties) {
		// check if item is reserved
		if (self.reservations.indexOf(uid) > -1)
			return;

		// check if signal handlers allow adding this item
		if (!self.events.emit_signal('item-added', self, uid, null, properties))
			return;

		// temporarily prevent modifications of this item
		self.reservations.push(uid);

		// add item through server
		var data = {
				'uid': uid,
				'properties': properties
			};

		new Communicator('shop')
			.on_success(self.handlers.item_add_success)
			.on_error(self.handlers.item_add_error)
			.set_callback_data(uid + '/')
			.send('json_add_item_to_shopping_cart', data);
	};

	/**
	 * Set number of items specified by CID in shopping cart.
	 *
	 * @param string cid
	 * @param integer count
	 * @return boolean
	 */
	self.set_item_count_by_cid = function(cid, count) {
		var result = false;
		var cid_data = cid.split('/', 2);

		// check if item is reserved
		if (self.reservations.indexOf(cid_data[0]) > -1)
			return result;

		// check if item exists
		if (!(cid in self.items))
			return result;

		// find item and remove it
		var item = self.items[cid];
		result = item.set_count(count);

		return result;
	};

	/**
	 * Alter item count by certain value.
	 *
	 * @param string cid
	 * @param integer difference
	 */
	self.alter_item_count_by_cid = function(cid, difference) {
		var cid_data = cid.split('/', 2);

		if (!(cid in self.items) && difference > 0 && self.reservations.indexOf(cid_data[0]) == -1) {
			// create new item
			self.add_item_by_cid(cid);

		} else if (cid in self.items) {
			// alter amount of existing item
			var item = self.items[cid];

			if (item != null)
				item.alter_count(difference);
		}
	};

	/**
	 * Remove item with specified CID from shopping cart.
	 *
	 * @param string cid
	 * @return boolean
	 */
	self.remove_item_by_cid = function(cid) {
		var result = false;

		// check if item exists
		if (!(cid in self.items))
			return result;

		// get item and remove it
		var item = self.items[cid];
		result = item.remove();

		return result;
	};

	/**
	 * Remove all items with matching unique id. Function returns
	 * number of items removed. If no items were removed, result is zero.
	 *
	 * @param string uid
	 * @return integer
	 */
	self.remove_item_by_uid = function(uid) {
		var result = 0;

		for (var cid in self.items) {
			var cid_data = cid.split('/', 2);

			if (cid_data[0] == uid) {
				self.items[cid].remove();
				result++;
			}
		}

		return result;
	};

	/**
	 * Get item object with specified CID.
	 *
	 * @param string cid
	 * @return object
	 */
	self.get_item_by_cid = function(cid) {
		var result = null;

		if (cid in self.items)
			result = self.items[cid];

		return result;
	};

	/**
	 * Get first item with matching unique id. This function does not take
	 * into account any properties set. If there's no item with specified
	 * id, null is returned.
	 *
	 * @param string uid
	 * @return object
	 */
	self.get_item_by_uid = function(uid) {
		var result = null;

		for (var cid in self.items) {
			var cid_data = cid.split('/', 2);

			if (cid_data[0] == uid) {
				result = self.items[cid];
				break;
			}
		}

		return result;
	};

	/**
	 * Get all item variations in cart. Result is an array with items matching
	 * specified unique id. If no items match specified id empty list is returned.
	 *
	 * @param string uid
	 * @return array
	 */
	self.get_item_list_by_uid = function(uid) {
		var result = new Array();

		for (var cid in self.items) {
			var cid_data = cid.split('/', 2);

			if (cid_data[0] == uid)
				result.push(self.items[cid]);
		}

		return result;
	};

	/**
	 * Remove all items from shopping cart.
	 */
	self.clear_cart = function() {
		new Communicator('shop')
				.on_success(self.handlers.cart_clear_success)
				.send('json_clear_shopping_cart', null);
	};

	/**
	 * Select currency to be displayed in shopping cart.
	 *
	 * @param string currency
	 */
	self.set_currency = function(currency) {
		if (currency == self.currency)
			return;

		if (currency == self.default_currency) {
			// set default currency
			self.currency = self.default_currency;
			self.exchange_rate = 1;

			// update items
			for (var cid in self.items) {
				var item = self.items[cid];
				item.handlers.currency_change(self.currency, self.exchange_rate);
			}

			// update totals
			self.ui.update_totals();

		} else {
			// foreign currency, request exchange rate
			data = {
					from: self.default_currency,
					to: currency
				};

			new Communicator('shop')
					.on_success(self.handlers.currency_change_success)
					.set_callback_data(currency)
					.use_cache(true)
					.get('json_get_conversion_rate', data);
		}
	};

	/**
	 * Set checkout URL or path. If URL does not contain 'http' or 'https' schema
	 * in the beginning system will assume it's relative path and will use base tag
	 * to determine absolute URL.
	 *
	 * @param string url
	 * @return object
	 */
	self.set_checkout_url = function(url) {
		self.checkout_url = url;
		return self;
	};

	/**
	 * Go to checkout page and optionally pre-select payment method.
	 *
	 * @param string payment_method
	 */
	self.checkout = function(payment_method) {
		// ensure there are items for checkout
		if (Object.keys(self.items).length == 0)
			return;

		// make sure we have absolute path to checkout
		var url = self.checkout_url;

		if (url.indexOf('http://') == -1 && url.indexOf('https://') == -1) {
			if (url[0] != '/')
				url = '/' + url;

			url = $('base').attr('href') + url;
		}

		// add payment method if specified
		if (payment_method) {
			var glue = url.indexOf('?') > -1 ? '&' : '?';
			url = url + glue + 'payment_method=' + escape(payment_method);
		}

		// redirect to checkout
		window.location.href = url;
	};

	/**
	 * Add constructor function for item view. This function
	 * needs to be descendant from `Caracal.Shop.ItemView` function.
	 *
	 * @param callable constructor
	 */
	self.add_item_view = function(constructor) {
		self.item_views.push(constructor);
		return self;
	};

	/**
	 * Get all item view constructors.
	 *
	 * @return array
	 */
	self.get_item_views = function() {
		return self.item_views;
	};

	/**
	 * Retrieve jQuery set of list container objects.
	 *
	 * @return object
	 */
	self.get_list_container = function() {
		return self.ui.item_list;
	};

	/**
	 * Handle successful currency change.
	 *
	 * @param object data
	 */
	self.handlers.currency_change_success = function(data, new_currency) {
		// store new data
		self.currency = new_currency;
		self.exchange_rate = data;

		// update items
		for (var cid in self.items) {
			var item = self.items[cid];
			item.handlers.currency_change(new_currency, data);
		}

		// update totals
		self.ui.update_totals();
	};

	/**
	 * Handle successful cart clearing process.
	 *
	 * @param object success
	 */
	self.handlers.cart_clear_success = function(success) {
		if (!success)
			return;

		// tell items to clean up
		for (var cid in self.items) {
			var item = self.items[cid];
			item.handlers.remove_success(true);
		}
	};

	/**
	 * Handle shopping cart load event.
	 *
	 * @param object data
	 */
	self.handlers.cart_load_success = function(data) {
		// apply data
		self.handling = data.handling || self.handling;
		self.shipping = data.shipping || self.shipping;
		self.default_currency = data.currency || self.default_currency;
		self.currency = self.default_currency;
		self.exchange_rate = 1;

		// create items handlers
		for (var i=0, count=data.cart.length; i<count; i++) {
			var item_data = data.cart[i];
			var item = new Caracal.Shop.Item(self);

			// configure item
			item.apply_data(item_data);

			// add item to the list
			self.items[item.get_cid()] = item;
		}

		// update totals
		self.ui.update_totals();
	};

	/**
	 * Handle change in item numbers.
	 *
	 * @param object item
	 */
	self.handlers.item_count_changed = function(item) {
		self.ui.update_totals();
	};

	/**
	 * Handle item removal from shopping cart.
	 *
	 * @param object item
	 */
	self.handlers.item_removed = function(item) {
		var cid = item.get_cid();

		// remove item from the list
		if (cid in self.items)
			delete self.items[cid];

		// update totals
		self.ui.update_totals();
	};

	/**
	 * Handle item added to shopping cart.
	 *
	 * @param object data
	 * @param string original_cid
	 */
	self.handlers.item_add_success = function(data, original_cid) {
		var item = new Caracal.Shop.Item(self);

		// configure item
		item.apply_data(data);

		// add item to the list
		var cid = item.get_cid();
		self.items[cid] = item;

		// remove reserved space if cid is different
		if (cid != original_cid)
			delete self.items[original_cid];

		// clear item reservation
		var index = self.reservations.indexOf(data.uid);
		if (index > -1)
			self.reservations.splice(index, 1);

		// update totals
		self.ui.update_totals();
	};

	/**
	 * Handle server or communication error during item add.
	 *
	 * @param object xhr
	 * @param string transfer_status
	 * @param string description
	 * @param string cid
	 */
	self.handlers.item_add_error = function(xhr, transfer_status, description, cid) {
		var cid_data = cid.split('/', 2);
		var index = self.reservations.indexOf(cid_data[0]);

		// remove item reservation
		if (index > -1)
			self.reservations.splice(index, 1);
	};

	/**
	 * Handle clicking on checkout button.
	 *
	 * @param object event
	 */
	self.handlers.checkout_click = function(event) {
		event.preventDefault();
		self.checkout();
	};

	/**
	 * Add item list to shopping cart.
	 *
	 * @param object item_list
	 * @return object
	 */
	self.ui.add_item_list = function(item_list) {
		self.ui.item_list = self.ui.item_list.add(item_list);
		return self;
	};

	/**
	 * Add total item count label to shopping cart.
	 *
	 * @param object label
	 * @return object
	 */
	self.ui.add_total_count_label = function(label) {
		self.ui.total_count = self.ui.total_count.add(label);
		return self;
	};

	/**
	 * Add total item cost label to shopping cart.
	 *
	 * @param object label
	 * @return object
	 */
	self.ui.add_total_cost_label = function(label) {
		// add label to list
		self.ui.total_cost = self.ui.total_cost.add(label);

		// create attribute with currency
		self.ui.total_cost.attr('data-currency', self.currency);

		return self;
	};

	/**
	 * Add total weight label to shopping cart.
	 *
	 * @param object label
	 * @return object
	 */
	self.ui.add_total_weight_label = function(label) {
		self.ui.total_weight = self.ui.total_weight.add(label);
		return self;
	};

	/**
	 * Connect checkout button for shopping cart.
	 *
	 * @param object button
	 * @return object
	 */
	self.ui.connect_checkout_button = function(button) {
		// extend set
		self.ui.checkout_button = self.ui.checkout_button.add(button);

		// re-attach handlers
		self.ui.checkout_button.off('click', self.handlers.checkout_click);
		self.ui.checkout_button.on('click', self.handlers.checkout_click);

		return self;
	};

	/**
	 * Recalculate total values.
	 */
	self.ui.update_totals = function() {
		var total_weight = 0;
		var total_cost = 0;
		var total_count = 0;

		// summarize information
		for (var cid in self.items) {
			var item = self.items[cid];

			total_weight += item.get_total_weight();
			total_cost += item.get_total_cost();
			total_count += item.count;
		}

		// update labels
		self.ui.total_count.text(total_count);
		self.ui.total_cost
			.text(total_cost.toFixed(2))
			.attr('data-currency', self.currency);
		self.ui.total_weight.text(total_weight.toFixed(2));
	};

	/**
	 * Connect function to be called when specified signal is emitted.
	 *
	 * @param string signal_name
	 * @param function callback
	 * @param boolean top
	 */
	self.events.connect = function(signal_name, callback, top) {
		var list_name = 'on_' + signal_name.replace('-', '_');

		// make sure event list exists
		if (!(list_name in self.events))
			return self;

		// add call back to the list
		if (!top)
			self.events[list_name].push(callback); else
			self.events[list_name].splice(0, 0, callback);

		return self;
	};

	/**
	 * Emit signal with specified parameters. This function accepts more than one
	 * parameter. All except first parameter will be passed to callback function.
	 *
	 * @param string signal_name
	 * @param ...
	 * @return boolean
	 */
	self.events.emit_signal = function(signal_name) {
		var result = true;
		var params = new Array();
		var list = null;

		// prepare arguments
		for (var index in arguments)
			params.push(arguments[index]);
		params = params.slice(1);

		// get list of functions to call
		var list_name = 'on_' + signal_name.replace('-', '_');
		if (list_name in self.events)
			list = self.events[list_name];

		// emit signal
		if (list != null && list.length > 0)
			for (var i=0, length=list.length; i < length; i++) {
				var callback = list[i];

				if (!callback.apply(this, params)) {
					result = false;
					break;
				}
			}

		return result;
	};

	// finalize object
	self._init();
}


/**
 * Constructor function for shopping cart item object.
 *
 * @param object cart
 */
Caracal.Shop.Item = function(cart) {
	var self = this;

	self.cart = cart;
	self.name = '';
	self.count = 0;
	self.price = 0;
	self.exchange_rate = 1;
	self.tax = 0;
	self.weight = 0;
	self.image = '';
	self.uid = '';
	self.variation_id = '';
	self.properties = {};
	self.handlers = {};
	self.views = new Array();

	/**
	 * Complete object initialization.
	 */
	self._init = function() {
		// create item views
		var constructors = self.cart.get_item_views();

		for (var i=0, count=constructors.length; i<count; i++) {
			var ItemView = constructors[i];
			self.views.push(new ItemView(self));
		}
	};

	/**
	 * Notify server about item count change.
	 *
	 * @param integer new_count
	 * @return boolean
	 */
	self._notify_count_change = function(new_count) {
		var result = false;
		var old_count = self.count;

		// check if signal handlers permit the change
		if (!self.cart.events.emit_signal('item-amount-change', self.cart, self, new_count))
			return result;

		// update count
		self.count = new_count;

		// update views
		for (var i=0, count=self.views.length; i<count; i++)
			self.views[i].handle_change();

		if (new_count > 0) {
			// notify server about the change
			var data = {
					uid: self.uid,
					variation_id: self.variation_id,
					count: new_count
				};

			new Communicator('shop')
				.set_callback_data(old_count)
				.on_success(self.handlers.change_success)
				.on_error(self.handlers.change_error)
				.send('json_change_item_quantity', data);

		} else {
			// if amount is zero of lower remove item
			self.remove();
		}
	};

	/**
	 * Remove item from cart.
	 */
	self.remove = function() {
		var result = false;

		if (self.cart.events.emit_signal('item-removed', self.cart, self)) {
			var data = {
				uid: self.uid,
				variation_id: self.variation_id
			};

			new Communicator('shop')
				.on_success(self.handlers.remove_success)
				.on_error(self.handlers.remove_error)
				.get('json_remove_item_from_shopping_cart', data);

			result = true;
		}

		return result;
	};

	/**
	 * Set number of items.
	 *
	 * @param integer count
	 * @return boolean
	 */
	self.set_count = function(count) {
		var result = false;
		var new_count = Math.abs(count);

		// notify server
		result = self._notify_count_change(new_count);

		return result;
	};

	/**
	 * Change item count by specified difference and return new
	 * item count.
	 *
	 * @param integer difference
	 * @return boolean
	 */
	self.alter_count = function(difference) {
		var result = false;
		var new_count = self.count + difference;

		// make sure number is not negative
		if (new_count < 0)
			new_count = 0;

		// notify server
		result = self._notify_count_change(new_count);

		return result;
	};

	/**
	 * Apply item configuration from data on shopping cart load.
	 *
	 * @param object data
	 */
	self.apply_data = function(data) {
		self.uid = data.uid || self.uid;
		self.variation_id = data.variation_id || self.variation_id;
		self.name = data.name || self.name;
		self.count = parseFloat(data.count) || self.count;
		self.price = parseFloat(data.price) || self.price;
		self.tax = parseFloat(data.tax) || self.tax;
		self.weight = parseFloat(data.weight) || self.weight;
		self.properties = data.properties || self.properties;
		self.image = data.image || self.image;
		self.uid = data.uid || self.uid;
		self.variation_id = data.variation_id || self.variation_id;

		// update views
		for (var i=0, count=self.views.length; i<count; i++)
			self.views[i].handle_change();
	};

	/**
	 * Generate CID from UID and variation id.
	 *
	 * @return string
	 */
	self.get_cid = function() {
		return self.uid + '/' + self.variation_id;
	};

	/**
	 * Set unique item id and variation id from CID.
	 *
	 * @param string cid
	 */
	self.set_cid = function(cid) {
		// store combination id
		var cid_data = cid.split('/', 2);
		self.uid = cid_data[0];
		self.variation_id = cid_data[1] || '';
	};

	/**
	 * Return total cost of this item.
	 *
	 * @return float
	 */
	self.get_total_cost = function() {
		return self.count * self.price * self.exchange_rate;
	};

	/**
	 * Return total weight of this item.
	 *
	 * @return float
	 */
	self.get_total_weight = function() {
		return self.count * self.weight;
	};

	/**
	 * Handle currency change in shopping cart.
	 *
	 * @param string currency
	 * @param float rate
	 */
	self.handlers.currency_change = function(currency, rate) {
		// store rate for later calculation
		self.exchange_rate = rate;

		// update views
		for (var i=0, count=self.views.length; i<count; i++)
			self.views[i].handle_currency_change(currency, rate);
	};

	/**
	 * Handle successful change in item count.
	 *
	 * @param boolean success
	 * @param integer old_count
	 */
	self.handlers.change_success = function(success, old_count) {
		if (success) {
			// notify cart about successful change
			self.cart.handlers.item_count_changed(self);

		} else {
			// revert count to old value
			self.count = old_count;

			// update views
			for (var i=0, count=self.views.length; i<count; i++)
				self.views[i].handle_change();
		}
	};

	/**
	 * Handle server side error during item count change.
	 *
	 * @param object xhr
	 * @param string transfer_status
	 * @param string description
	 * @param integer old_count
	 */
	self.handlers.change_error = function(xhr, transfer_status, description, old_count) {
		// revert count to old value
		self.count = old_count;

		// update views
		for (var i=0, count=self.views.length; i<count; i++)
			self.views[i].handle_change();
	};

	/**
	 * Handle successful call for removal of item from shopping cart.
	 *
	 * @param boolean success
	 */
	self.handlers.remove_success = function(success) {
		if (success) {
			// update item views
			for (var i=0, count=self.views.length; i<count; i++)
				self.views[i].handle_remove();

			// notify shopping cart
			self.cart.handlers.item_removed(self);
		}
	};

	/**
	 * Handle error when removing item from shopping cart.
	 *
	 * @param object xhr
	 * @param string transfer_status
	 * @param string description
	 */
	self.handlers.remove_error = function(xhr, transfer_status, description) {
	};

	// finalize object
	self._init();
}


/**
 * Default item view adapter. This adapter will show elementary information
 * about item in shopping cart such as name, price, amount and some related
 * functions.
 *
 * @param object item
 */
Caracal.Shop.ItemView = function(item) {
	var self = this;

	self.item = item;
	self.cart = item.cart;
	self.currency = null;
	self.exchange_rate = 1;

	self.container = null;
	self.label_name = null;
	self.label_count = null;
	self.label_total = null;
	self.option_remove = null;

	/**
	 * Complete object initialization.
	 */
	self._init = function() {
		// get list containers
		var item_list = self.cart.get_list_container();
		self.currency = self.cart.default_currency;

		// create container
		self.container = $('<li>').appendTo(item_list);
		self.container.addClass('item');

		// create labels
		self.label_name = $('<span>').appendTo(self.container);
		self.label_name.addClass('name');

		self.label_count = $('<span>').appendTo(self.container);
		self.label_count.addClass('count');

		self.label_total = $('<span>').appendTo(self.container);
		self.label_total
				.addClass('total')
				.attr('data-currency', self.currency);

		// create options
		self.option_remove = $('<a>').appendTo(self.container);
		self.option_remove
				.attr('href', 'javascript: void(0);')
				.on('click', self._handle_remove)
				.html(language_handler.getText('shop', 'remove'));
	};

	/**
	 * Handle clicking on remove item.
	 *
	 * @param object event
	 */
	self._handle_remove = function(event) {
		event.preventDefault();
		self.item.remove();
	};

	/**
	 * Handler externally called when item count has changed.
	 */
	self.handle_change = function() {
		self.label_name.text(self.item.name[language_handler.current_language]);
		self.label_count.text(self.item.count);
		self.label_total
				.text((self.item.count * self.item.price * self.exchange_rate).toFixed(2))
				.attr('data-currency', self.currency);
	};

	/**
	 * Handle shopping cart currency change.
	 *
	 * @param string currency
	 * @param float rate
	 */
	self.handle_currency_change = function(currency, rate) {
		// store values
		self.currency = currency;
		self.exchange_rate = rate;

		// update labels
		self.handle_change();
	};

	/**
	 * Handler externally called before item removal.
	 */
	self.handle_remove = function() {
		self.container.remove();
	};

	// finalize object
	self._init();
}
