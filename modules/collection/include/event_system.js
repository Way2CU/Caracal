/**
 * Event System JavaScript
 *
 * Copyright (c) 2016. by Way2CU, http://way2cu.com
 * Authors: Mladen Mijatov
 *
 * This event system provides easy framework to integrat signal
 * based communication between objects.
 */

var Caracal = Caracal || {};


Caracal.EventSystem = function() {
	var self = this;

	self.events = null;

	/**
	 * Object initialization.
	 */
	self._init = function() {
		self.events = new Object();
	};

	/**
	 * Register new event in the system.
	 *
	 * @param string event_name
	 * @return self
	 */
	self.register = function(event_name) {
		var real_name = event_name.replace(/-/g, '_');

		if (!(real_name in self.events))
			self.events[real_name] = new Array();

		return self;
	};

	/**
	 * Connect function to specified event name.
	 *
	 * @param string event name
	 * @param function callback
	 * @param boolean on_top
	 * @return self
	 */
	self.connect = function(event_name, callback, on_top) {
		var real_name = event_name.replace(/-/g, '_');
		var list = null;

		// check if event name is a valid one
		if (real_name in self.events)
			var list = self.events[real_name];

		// make sure we have list
		if (!list)
			return self;

		// add callback to list
		if (on_top)
			list.splice(0, 0, callback); else
			list.push(callback);

		return self;
	};

	/**
	 * Call all the functions connected to specified event
	 * synchronously in order they've been added.
	 *
	 * @param string event_name
	 * @param ...
	 */
	self.trigger = function(event_name) {
		var real_name = event_name.replace(/-/g, '_');
		var params = new Array();
		var list = null;

		// prepare arguments
		for (var index in arguments)
			params.push(arguments[index]);
		params = params.slice(1);

		// get list of functions to be called
		if (real_name in self.events)
			list = self.events[real_name];

		// make sure we have list
		if (!list)
			return;

		// call function
		for (var index in list) {
			callback = list[index];
			callback.apply(null, params);
		}
	};

	// finalize object
	self._init();
};
