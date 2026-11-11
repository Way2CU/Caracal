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
	self._callback_data = null;
	self._cache_response = false;
	self._asynchronous = true;
	self._accept_types = {
			json: 'application/json, text/javascript',
			html: 'text/html',
			xml: 'application/xml, text/xml',
			script: 'text/javascript, application/javascript, application/ecmascript, application/x-ecmascript',
			text: 'text/plain'
		};

	/**
	 * Complete object initialization.
	 */
	self._init = function() {
		var base = document.querySelector('meta[property=base-url]');
		var base_url = base !== null ? base.getAttribute('content') : '';

		self._url = base_url + self._url_path;
	};

	/**
	 * Add key and value pair to the list of encoded parameters.
	 *
	 * @param array result
	 * @param string key
	 * @param mixed value
	 */
	self._add_parameter = function(result, key, value) {
		if (typeof value == 'function')
			value = value();

		if (value === null || value === undefined)
			value = '';

		result.push(encodeURIComponent(key) + '=' + encodeURIComponent(value));
	};

	/**
	 * Recursively encode value, using bracket notation for arrays and objects
	 * the same way PHP expects them.
	 *
	 * @param array result
	 * @param string prefix
	 * @param mixed value
	 */
	self._build_parameters = function(result, prefix, value) {
		if (Array.isArray(value)) {
			for (var i = 0; i < value.length; i++) {
				var item = value[i];

				if (/\[\]$/.test(prefix))
					self._add_parameter(result, prefix, item); else
					self._build_parameters(
						result,
						prefix + '[' + (typeof item == 'object' && item !== null ? i : '') + ']',
						item
					);
			}

		} else if (Object.prototype.toString.call(value) == '[object Object]') {
			for (var name in value)
				self._build_parameters(result, prefix + '[' + name + ']', value[name]);

		} else {
			self._add_parameter(result, prefix, value);
		}
	};

	/**
	 * Encode data object as URL encoded string.
	 *
	 * @param object data
	 * @return string
	 */
	self._encode_data = function(data) {
		var result = [];

		for (var key in data)
			self._build_parameters(result, key, data[key]);

		return result.join('&').replace(/%20/g, '+');
	};

	/**
	 * Convert response to specified type. Exception is thrown
	 * if response can not be converted.
	 *
	 * @param object xhr
	 * @param string response_type
	 * @return mixed
	 */
	self._convert_response = function(xhr, response_type) {
		var result = xhr.responseText;

		switch (response_type) {
			case 'json':
				result = JSON.parse(xhr.responseText);
				break;

			case 'xml':
				result = xhr.responseXML;
				if (result === null)
					throw new Error('Invalid XML');
				break;

			case 'script':
				var script = document.createElement('script');
				script.text = xhr.responseText;
				document.head.appendChild(script);
				document.head.removeChild(script);
				break;
		}

		return result;
	};

	/**
	 * Handle completed request and call appropriate handler.
	 *
	 * @param object xhr
	 * @param string response_type
	 */
	self._handle_load = function(xhr, response_type) {
		var data;
		var successful = (xhr.status >= 200 && xhr.status < 300) || xhr.status == 304;
		var parsed = true;

		// convert response, unless there is no content
		if (successful && xhr.status != 204 && xhr.status != 304)
			try {
				data = self._convert_response(xhr, response_type);

			} catch (error) {
				parsed = false;
				self._handle_error(xhr, 'parsererror', error);
			}

		if (successful && parsed)
			self._handle_success(data); else
		if (!successful)
			self._handle_error(xhr, 'error', xhr.statusText);
	};

	/**
	 * Send request to the server.
	 *
	 * @param string method
	 * @param string action
	 * @param object data
	 * @param string response_type
	 */
	self._request = function(method, action, data, response_type) {
		var xhr = new XMLHttpRequest();
		var url = self._url;
		var body = null;
		var completed = false;
		var params = {
				section: self._module,
				action: action,
				language: Caracal.language.current_language
			};
		var headers = {
				'accept': ['Accept', '*/*'],
				'x-requested-with': ['X-Requested-With', 'XMLHttpRequest']
			};

		if (response_type === undefined)
			response_type = 'json';

		if (response_type in self._accept_types)
			headers['accept'][1] = self._accept_types[response_type] + ', */*; q=0.01';

		// add optional parameters
		if (data !== undefined && data !== null)
			for (var key in data)
				if (data[key] !== undefined)
					params[key] = data[key];

		// prepare data for sending
		if (method == 'GET') {
			if (!self._cache_response)
				params._ = Date.now();
			url += (url.indexOf('?') == -1 ? '?' : '&') + self._encode_data(params);

		} else {
			body = self._encode_data(params);
			headers['content-type'] = ['Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8'];
		}

		// custom headers override default ones
		if (self._headers !== null)
			for (var name in self._headers)
				headers[name.toLowerCase()] = [name, self._headers[name]];

		// configure handlers
		xhr.onload = function() {
			completed = true;
			self._handle_load(xhr, response_type);
		};

		xhr.onerror = function() {
			completed = true;
			self._handle_error(xhr, 'error', xhr.statusText);
		};

		xhr.onabort = function() {
			completed = true;
			self._handle_error(xhr, 'abort', 'abort');
		};

		xhr.ontimeout = function() {
			completed = true;
			self._handle_error(xhr, 'timeout', 'timeout');
		};

		// send request to server
		try {
			xhr.open(method, url, self._asynchronous);

			for (var index in headers)
				xhr.setRequestHeader(headers[index][0], headers[index][1]);

			xhr.send(body);

		} catch (error) {
			// synchronous requests throw on network errors
			if (!completed)
				self._handle_error(xhr, 'error', error);
		}
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
			self._callback_error(xhr, transfer_status, error_description, self._callback_data);
	};

	/**
	 * Handle successful transaction.
	 *
	 * @param mixed data
	 */
	self._handle_success = function(data) {
		if (self._callback_success !== null)
			self._callback_success(data, self._callback_data);
	};

	/**
	 * Assign success handler.
	 *
	 * @param function callback
	 * @return self
	 */
	self.on_success = function(callback) {
		self._callback_success = callback;
		return self;
	};

	/**
	 * Assign error handler.
	 *
	 * @param function callback
	 * @return self
	 */
	self.on_error = function(callback) {
		self._callback_error = callback;
		return self;
	};

	/**
	 * If we should use cached version of response.
	 *
	 * @param boolean use_cache
	 * @return self
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
	 * @return self
	 */
	self.add_headers = function(headers) {
		self._headers = headers;
		return self;
	};

	/**
	 * Should request be sent in asynchronous manner.
	 *
	 * @param boolean async
	 * @return self
	 */
	self.set_asynchronous = function(async) {
		self._asynchronous = async;
		return self;
	};

	/**
	 * Set data to be passed in callback.
	 *
	 * @param mixed
	 * @return self
	 */
	self.set_callback_data = function(data) {
		self._callback_data = data;
		return self;
	};

	/**
	 * Send data to server with specified parameters.
	 *
	 * @param string action
	 * @param object data
	 * @param string response_type	Optional [json, html, xml, script]
	 */
	self.send = function(action, data, response_type) {
		self._request('POST', action, data, response_type);
	};

	/**
	 * Request data from server with specified parameters.
	 *
	 * @param string action
	 * @param object data
	 * @param string response_type	Optional [json, html, xml, script]
	 */
	self.get = function(action, data, response_type) {
		self._request('GET', action, data, response_type);
	};

	// finalize object
	self._init();
}
