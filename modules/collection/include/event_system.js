/**
 * Event System JavaScript
 *
 * Copyright (c) 2016. by Way2CU, http://way2cu.com
 * Authors: Mladen Mijatov
 *
 * This event system provides easy framework to integrat signal
 * based communication between objects.
 */

var Caracal = Caracal || new Object();


Caracal.EventSystem = function() {
	var self = this;

	self.events = null;
	self.response_types = new Object();

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
	 * @param string response_type
	 * @return self
	 */
	self.register = function(event_name, response_type) {
		var real_name = event_name.replace(/-/g, '_');

		// register new event
		if (!(real_name in self.events))
			self.events[real_name] = new Array();

		// store response type
		self.response_types[real_name] = response_type;

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
	 * @return mixed
	 */
	self.trigger = function(event_name) {
		var result = null;
		var real_name = event_name.replace(/-/g, '_');

		// make sure event exists
		if (!(real_name in self.events))
			return result;

		// prepare for execution
		var params = Array.prototype.slice.call(arguments);
		var response_type = self.response_types[real_name];
		var list = self.events[real_name];

		// prepare result
		switch (response_type) {
			case 'array':
				result = new Array();
				break;

			case 'boolean':
			default:
				result = true;
				break;
		}

		// call functions
		for (var index in list) {
			callback = list[index];
			response = callback.apply(null, params);

			if (response_type)
				switch (response_type) {
					case 'array':
						result.push(response);
						break;

					case 'boolean':
					default:
						result &= response;
						if (!result) break;
						break;
				}
		}

		return result;
	};

	// finalize object
	self._init();
};
