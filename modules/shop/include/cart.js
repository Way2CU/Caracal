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
 * 	item-added
 * 	item-removed
 * 	item-amount-change
 * 	before-checkout
 * 	checkout
 *
 * @return object
 */
Caracal.Shop.Cart = function() {
	var self = this;

	self.items = {};
	self.default_currency = 'EUR';
	self.currency = self.default_currency;
	self.handling = 0;
	self.shipping = 0;
	self.ui = {};
	self.events = {};
	self.handlers = {};
	self.item_views = new Array();

	/**
	 * Complete object initialization.
	 */
	self._init = function() {
		self._communicator = new Communicator('shop');

		// create user interface containers
		self.ui.item_list = $();
		self.ui.total_count = $();
		self.ui.total_cost = $();
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
			.on_error(self.handlers.cart_load_error)
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
		var cid = item.get_cid();
		if (self.events.emit_signal('item-added', self, cid)) {
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
		if (cid in self.items) {
			// item already exists, increase count
			var item = self.items[cid];
			item.alter_count(1);

		} else if (self.events.emit_signal('item-added', self, cid)) {
			// load data from server
			var cid_data = cid.split('/', 2);
			var data = {
				uid: cid_data[0],
				variation_id: cid_data[1]
			};

			new Communicator('shop')
				.on_error(self.handlers.item_add_error)
				.on_success(self.handlers.item_add_success)
				.send('json_add_item_to_shopping_cart', data);
		}
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

		// check if item exists
		if (!(cid in self.items))
			return result;

		// find item and remove it
		var item = self.items[cid];
		result = item.set_count(count);

		// update totals
		self.ui.update_totals();

		return result;
	};

	/**
	 * Alter item count by certain value.
	 *
	 * @param string cid
	 * @param integer difference
	 */
	self.alter_item_count_by_cid = function(cid, difference) {
		if (!(cid in self.items) && difference > 0) {
			// create new item
			self.add_item_by_cid(cid);

		} else if (cid in self.items) {
			// alter amount of existing item
			var item = self.items[cid];
			item.alter_count(difference);

			// update totals
			self.ui.update_totals();
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
	 * Remove all items from shopping cart.
	 */
	self.clear_cart = function() {
	};

	/**
	 * Select currency to be displayed in shopping cart.
	 *
	 * @param string currency
	 */
	self.set_currency = function(currency) {
	};

	/**
	 * Go to checkout page and optionally pre-select payment method.
	 *
	 * @param string payment_method
	 */
	self.checkout = function(payment_method) {
	};

	/**
	 * Add constructor function for item view. This function
	 * needs to be descendant from `Caracal.Shop.ItemView` function.
	 *
	 * @param callable constructor
	 */
	self.add_item_view = function(constructor) {
		self.item_views.push(constructor);
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
	 * Handle shopping cart load event.
	 *
	 * @param object data
	 */
	self.handlers.cart_load_success = function(data) {
		// apply data
		self.handling = data.handling || self.handling;
		self.shipping = data.shipping || self.shipping;
		self.currency = data.currency || self.currency;

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
	 * Handle shopping cart load error.
	 *
	 * @param object xhr
	 * @param string transfer_status
	 * @param string description
	 */
	self.handlers.cart_load_error = function(xhr, transfer_status, description) {
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
	 */
	self.handlers.item_add_success = function(data) {
		var item = new Caracal.Shop.Item(self);

		// configure item
		item.apply_data(data);

		// add item to the list
		self.items[item.get_cid()] = item;

		// update totals
		self.ui.update_totals();
	};

	/**
	 * Handle server or communication error during item add.
	 *
	 * @param object xhr
	 * @param string transfer_status
	 * @param string description
	 */
	self.handlers.item_add_error = function(xhr, transfer_status, description) {
	};

	/**
	 * Add item list to shopping cart.
	 *
	 * @param object item_list
	 * @return object
	 */
	self.ui.add_item_list = function(item_list) {
		$.extend(self.ui.item_list, item_list);
		return self;
	};

	/**
	 * Add total item count label to shopping cart.
	 *
	 * @param object label
	 * @return object
	 */
	self.ui.add_total_count_label = function(label) {
		$.extend(self.ui.total_count, label);
		return self;
	};

	/**
	 * Add total item cost label to shopping cart.
	 *
	 * @param object label
	 * @return object
	 */
	self.ui.add_total_cost_label = function(label) {
		$.extend(self.ui.total_cost, label);
		return self;
	};

	/**
	 * Connect checkout button for shopping cart.
	 *
	 * @param object button
	 * @return object
	 */
	self.ui.connect_checkout_button = function(button) {
		$.extend(self.ui.checkout_button, button);
		// TODO: Re-apply event handlers.
		return self;
	};

	/**
	 * Recalculate total values.
	 */
	self.ui.update_totals = function() {
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

		console.log(signal_name);

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
	self.tax = 0;
	self.weight = 0;
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
				.on_error(self.handlers.remove_error)
				.on_success(self.handlers.remove_success)
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

		if (self.cart.events.emit_signal('item-amount-change', self.cart, self, count)) {
			// update count
			self.count = Math.abs(count);

			// update views
			for (var i=0, count=self.views.length; i<count; i++)
				self.views[i].handle_change();

			// remove item if count is zero
			if (self.count == 0)
				self.remove();

			result = true;
		}

		return result;
	};

	/**
	 * Change item count by specified difference and return new
	 * item count.
	 *
	 * @param integer difference
	 * @return integer
	 */
	self.alter_count = function(difference) {
		if (self.cart.events.emit_signal('item-amount-change', self.cart, self, count)) {
			self.count += difference;

			// make sure number is not negative
			if (self.count < 0)
				self.count = 0;

			// update views
			for (var i=0, count=self.views.length; i<count; i++)
				self.views[i].handle_change();

			// remove item if count is zero
			if (self.count == 0)
				self.remove();
		}

		return self.count;
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
		self.count = data.count || self.count;
		self.price = data.price || self.price;
		self.tax = data.tax || self.tax;
		self.weight = data.weight || self.weight;
		self.properties = data.properties || self.properties;
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

		// create labels
		self.label_name = $('<span>');
		self.label_name.addClass('name');

		self.label_count = $('<span>');
		self.label_count.addClass('count');

		self.label_total = $('<span>');
		self.label_total
				.addClass('total')
				.attr('data-currency', self.cart.currency);

		// create options
		self.option_remove = $('<a>');
		self.option_remove
				.attr('href', 'javascript: void(0);')
				.on('click', self._handle_remove)
				.html(language_handler.getText('shop', 'remove'));

		// create container
		self.container = $('<li>');
		self.container
				.addClass('item')
				.append(self.label_name)
				.append(self.label_count)
				.append(self.label_total)
				.append(self.option_remove)
				.appendTo(item_list);
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
		self.label_name.html(self.item.name[language_handler.current_language]);
		self.label_count.html(self.item.count);
		self.label_total.html(self.item.count * self.item.price);
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
