/**
 * Activity Tracker Beacon
 *
 * Object, once created, will send regular beacons back to the server. It
 * also provides an easy way to test whether some other activity is active
 * or not.
 *
 * Author: Mladen Mijatov
 */

function Beacon(activity, function_name, license) {
	var self = this;

	self._activity = activity;
	self._function = function_name;
	self._license = license;
	self._interval = 900;
	self._interval_id = null;

	self._url = null;
	self._url_path = '/index.php';

	self._callback_interval = null;

	/**
	 * Complete object initialization.
	 */
	self.init = function() {
		var base = document.querySelector('meta[property=base-url]');

		self._url = (base !== null ? base.getAttribute('content') : '') + self._url_path;
	};

	/**
	 * Create new interval with specified delay.
	 */
	self._create_interval = function() {
		self._clear_interval();
		self._interval_id = setInterval(self._handle_interval, self._interval * 1000);
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
	self._handle_interval = function() {
		var communicator = new Communicator(self._url);
		var data = {
				section: 'activity_tracker',
				activity: self._activity,
				function: self._function,
				license: self._license
			};

		// assign callback
		if (self._callback_interval != null)
			communicator.on_success(self._callback_interval);

		communicator.send('keep_alive', data);
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
		var communicator = new Communicator(self._url);
		var data = {
				section: 'activity_tracker',
				activity: self._activity,
				function: function_name,
				license: self._license
			};

		// allow asynchronous callback
		if (callback !== undefined)
			communicator.on_success(callback); else
			communicator
				.set_asynchronous(false)
				.on_success(function(data) {
					result = data;
				});

		// send notification
		communicator.send('is_alive', data);

		return result;
	};

	/**
	 * Start the beacon.
	 */
	self.start = function() {
		self._create_interval();
		self._handle_interval();
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

window['Beacon'] = Beacon;
