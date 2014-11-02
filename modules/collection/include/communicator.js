/**
 * Caracal Communicator
 *
 * Copyright (c) 2014. by Way2CU
 * Author: Mladen Mijatov
 *
 * This function creates helper object used for communication with backend.
 */

function Communicator(module_name) {
	var self = this;

	self._url = null;
	self._url_path = '/index.php';
	self._module = module_name;
	self._headers = {};
	self._callback_success = null;
	self._callback_error = null;
	self._cache_response = false;
	self._asynchronous = true;

	/**
	 * Complete object initialization.
	 */
	self._init = function() {
		self._url = $('base').attr('href') + self._url_path;
	};

	/**
	 * Handle error during send/receive.
	 *
	 * @param object xhr
	 * @param string transfer_status
	 * @param string error_description
	 */
	self._handle_error = function(xhr, transfer_status, error_description) {
		if (self._callback_error !== null)
			self._callback_error(xhr, transfer_status, error_description);
	};

	/**
	 * Handle successful transaction.
	 *
	 * @param mixed data
	 */
	self._handle_success = function(data) {
		if (self._callback_success !== null)
			self._callback_success(data);
	};

	/**
	 * Assign success handler.
	 *
	 * @param function callback
	 */
	self.on_success = function(callback) {
		self._callback_success = callback;
		return self;
	};

	/**
	 * Assign error handler.
	 *
	 * @param function callback
	 */
	self.on_error = function(callback) {
		self._callback_error = callback;
		return self;
	};

	/**
	 * If we should use cached version of response.
	 *
	 * @param boolean use_cache
	 */
	self.use_cache = function(use_cache) {
		self._cache_response = use_cache;
		return self;
	};

	/**
	 * Add additional headers to HTTP request. X-Requested-With is added by
	 * default but its default XMLHttpRequest value can be changed here. Please
	 * note that by changing this value CMS will not recognize request as AJAX!
	 *
	 * @param object headers
	 */
	self.add_headers = function(headers) {
		self._headers = headers;
		return self;
	};

	/**
	 * Should request be sent in asynchronous manner.
	 *
	 * @param boolean async
	 */
	self.set_asynchronous = function(async) {
		self._asynchronous = async;
		return self;
	};

	/**
	 * Send data to server with specified parameters.
	 *
	 * @param string action
	 * @param object data
	 * @param string response_type	Optional [json, html, xml, script]
	 * @return boolean
	 */
	self.send = function(action, data, response_type) {
		// prepare general parameters
		var params = {
				url: self._url,
				type: 'POST',
				context: this,
				data: {
					section: self._module,
					action: action
				},
				cache: self._cache_response,
				async: self._asynchronous,
				success: self._handle_success,
				error: self._handle_error
			};

		// add optional parameters
		if (data !== undefined)
			$.extend(params.data, data);

		if (response_type === undefined)
			params.dataType = 'json'; else
			params.dataType = response_type;

		if (self._headers !== null)
			params.headers = self._headers;

		// send request to server
		$.ajax(params);
	};

	/**
	 * Request data from server with specified parameters.
	 *
	 * @param string action
	 * @param object data
	 * @param string response_type	Optional [json, html, xml, script]
	 * @return boolean
	 */
	self.get = function(action, data, response_type) {
		// prepare general parameters
		var params = {
				url: self._url,
				type: 'GET',
				context: this,
				data: {
					section: self._module,
					action: action
				},
				cache: self._cache_response,
				async: self._asynchronous,
				success: self._handle_success,
				error: self._handle_error
			};

		// add optional parameters
		if (data !== undefined)
			$.extend(params.data, data);

		if (response_type === undefined)
			params.dataType = 'json'; else
			params.dataType = response_type;

		if (self._headers !== null)
			params.headers = self._headers;

		// send request to server
		$.ajax(params);
	};

	// finalize object
	self._init();
}
