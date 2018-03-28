/**
 * Activity Tracker Beacon JavaScript
 * Caracal Development Framework
 *
 * Copyright (c) 2018. by Way2CU, http://way2cu.com
 * Author: Mladen Mijatov
 */


var Caracal = Caracal || new Object();
Caracal.ActivityTracker = Caracal.ActivityTracker || new Object();


/**
 * Object, once created, will send regular beacons back to the server. It
 * also provides an easy way to test whether some other activity is active
 * or not.
 *
 * @param string activity
 * @param string function_name
 */

Caracal.ActivityTracker.Beacon = function(activity, function_name) {
	var self = this;

	self._activity = null;
	self._function = null;
	self._interval = 900;
	self._interval_id = null;
	self._url = null;
	self._url_path = '/index.php';
	self._callback_interval = null;
	self._communicator = null;

	self.handler = new Object();

	/**
	 * Complete object initialization.
	 */
	self.init = function() {
		self._communicator = new Communicator('activity_tracker');

		self._activity = activity;
		self._function = function_name;

		self._url = document.querySelector("meta[property='base-url']").content + self._url_path;
	};

	/**
	 * Create new interval with specified delay.
	 */
	self._create_interval = function() {
		self._clear_interval();
		self._interval_id = setInterval(self.handler._handle_interval, self._interval * 1000);
	};

	/**
	 * Clear interval.
	 */
	self._clear_interval = function() {
		if (self._interval_id != null) {
			clearInterval(self._interval_id);
			self._interval_id = null;
		}
	};

	/**
	 * Handle interval.
	 */
	self.handler._handle_interval = function() {

	 	var data = {
		 		activity: self._activity,
		 		function: self._function
	 		};

		// assign callback
		if (self._callback_interval != null)
			self._communicator.on_success = self._callback_interval;

		self._communicator
			.use_cache(false)
			.set_asynchronous(true)
			.send('keep_alive', data);
	};

	/**
	 * Set interval to send keep alive notification to server.
	 *
	 * @param integer interval 	Seconds between each notification.
	 */
	self.set_interval = function(interval) {
		self._interval = interval;
		return self;
	};

	/**
	 * Set callback for handling intervals.
	 *
	 * @param function callback
	 */
	self.on_interval = function(callback) {
		self._callback_interval = callback;
		return self;
	};

	/**
	 * Check if function of the same activity is alive.
	 *
	 * @param string function_name
	 * @param function callback
	 * @return boolean
	 */
	self.is_alive = function(function_name, callback) {

	 	var result = false;

		// if callback is undefined
		var success = function(data) {
			result = data;
		};

		var data = {
				activity: self._activity,
				function: function_name
			};

		self._communicator.on_success(success);

		if (callback !== undefined)
			self._communicator.on_success(callback)

		// send notification
		self._communicator
			.use_cache(false)
			.set_asynchronous(true)
			.send('is_alive', data);

		return result;
	};

	/**
	 * Start the beacon.
	 */
	self.start = function() {
		self._create_interval();
		self.handler._handle_interval();
	};

	/**
	 * Stop the beacon.
	 */
	self.stop = function() {
		self._clear_interval();
	};

	// finalize object
	self.init();
}

