/**
 * Language API
 *
 * Copyright (c) 2013. by Way2CU
 * Author: Mladen Mijatov
 *
 * Provides language services to browser side of the framework.
 *
 * Requires jQuery 1.4.2+
 */

var Caracal = Caracal || new Object();
var language_handler = null;


function LanguageHandler() {
	var self = this;

	// language containers
	self.languages = [];
	self.rtl_languages = [];
	self.default_language = 'en';
	self.current_language = 'en';

	// base url for self site
	self.backend_url = $('meta[property=base-url]').attr('content') + '/index.php';

	// local language constant cache
	self.cache = {};

	/**
	 * Finalize object initialization.
	 */
	self._init = function() {
		self.loadLanguages();
	};

	/**
	 * Get language list
	 *
	 * @return json object
	 */
	self.getLanguages = function() {
		return self.languages;
	};

	/**
	 * Get RTL language list
	 *
	 * @return array
	 */
	self.getRTL = function() {
		return self.rtl_languages;
	};

	/**
	 * Check if specified language is RTL
	 *
	 * @return boolean
	 */
	self.isRTL = function(language) {
		// in case language is not specified use current
		if (language == undefined || language == null)
			var language = self.current_language;

		// return boolean result
		return !(self.rtl_languages.indexOf(language) == -1);
	};

	/**
	 * Get language constant value for specified module and language
	 *
	 * @param string module
	 * @param string constant
	 * @param string language
	 * @return string
	 */
	self.getText = function(module, constant) {
		var id = (module == null ? '_global' : module) + '.' + constant;
		var data = {
					section: 'language_menu',
					action: 'json_get_text',
					language: self.current_language,
					constant: constant
				};

		if (module != null)
			data.from_module = module;

		// check local cache first
		if (self.cache[id] == undefined) {
			$.ajax({
				url: self.backend_url,
				method: 'GET',
				async: false,
				cache: true,
				data: data,
				dataType: 'json',
				context: self,
				success: function(data) {
					self.cache[id] = data.text;
				}
			});
		}

		return self.cache[id];
	};

	/**
	 * Get language constant and call specified function
	 *
	 * @param string module
	 * @param string constant
	 * @param object callback
	 */
	self.getTextAsync = function(module, constant, callback) {
		var id = (module == null ? '_global' : module) + '.' + constant;
		var data = {
					section: 'language_menu',
					action: 'json_get_text',
					language: self.current_language,
					constant: constant
				};

		if (module != null)
			data.from_module = module;

		// check local cache first
		if (self.cache[id] == undefined) {
			$.ajax({
				url: self.backend_url,
				method: 'GET',
				async: true,
				cache: true,
				data: data,
				dataType: 'json',
				context: self,
				success: function(data) {
					self.cache[id] = data.text;
					callback(constant, data.text);
				}
			});

		} else {
			// we have local cache, send that
			callback(constant, self.cache[id]);
		}
	};

	/**
	 * Get array of language constants from server
	 *
	 * @param string module
	 * @param array constants
	 * @return array
	 */
	self.getTextArray = function(module, constants) {
		var id = (module == null ? '_global' : module) + '.';
		var data = {
					section: 'language_menu',
					action: 'json_get_text_array',
					language: self.current_language,
				};
		var result = {};
		var request = [];

		if (module != null)
			data.from_module = module;

		// check for all constants if we have cache
		for (var i=0; i < constants.length; i++) {
			var key = id + constants[i];

			if (key in self.cache) {
				// add cached value to result
				result[constants[i]] = self.cache[key];

			} else {
				// add constant to requested list
				request.push(constants[i]);
			}
		}

		// check local cache first
		if (request.length > 0) {
			data.constants = request;

			$.ajax({
				url: self.backend_url,
				method: 'GET',
				async: false,
				cache: true,
				data: data,
				dataType: 'json',
				context: self,
				success: function(data) {
					for (var key in data.text) {
						self.cache[id + key] = data.text[key];
						result[key] = data.text[key];
					}
				}
			});
		}

		return result;
	};

	/**
	 * Get array of language constants and call specified function when completed
	 *
	 * @param string module
	 * @param array constants
	 * @param object callback
	 */
	self.getTextArrayAsync = function(module, constants, callback) {
		var id = (module == null ? '_global' : module) + '.';
		var data = {
					section: 'language_menu',
					action: 'json_get_text_array',
					language: self.current_language,
				};
		var result = {};
		var request = [];

		if (module != null)
			data.from_module = module;

		// check for all constants if we have cache
		for (var i=0; i < constants.length; i++) {
			var key = id + constants[i];

			if (key in self.cache) {
				// add cached value to result
				result[constants[i]] = self.cache[key];

			} else {
				// add constant to requested list
				request.push(constants[i]);
			}
		}

		// check local cache first
		if (request.length > 0) {
			data.constants = request;

			$.ajax({
				url: self.backend_url,
				method: 'GET',
				async: true,
				cache: true,
				data: data,
				dataType: 'json',
				context: self,
				success: function(data) {
					for (var key in data.text) {
						self.cache[id + key] = data.text[key];
						result[key] = data.text[key];
					}

					callback(result);
				}
			});
		} else {
			// we have all the data cached, send them right away
			callback(result);
		}
	};

	/**
	 * Get current language and store it localy
	 */
	self.getCurrentLanguage = function() {
		$.ajax({
			url: self.backend_url,
			method: 'GET',
			async: false,
			cache: true,
			data: {
				section: 'language_menu',
				action: 'json_get_current_language',
			},
			dataType: 'json',
			context: self,
			success: function(data) {
				self.current_language = data;
			}
		});
	};

	/**
	 * Load language list from server
	 */
	self.loadLanguages = function() {
		// retireve languages from server
		$.ajax({
			url: self.backend_url,
			method: 'GET',
			async: false,
			cache: true,
			data: {
				section: 'language_menu',
				action: 'json',
			},
			dataType: 'json',
			context: self,
			success: self.loadLanguages_Complete
		});
	};

	/**
	 * Process server response
	 *
	 * @param object data
	 * @param string status
	 */
	self.loadLanguages_Complete = function(data, status) {
		self.languages = data.items;
		self.rtl_languages = data.rtl;
		self.default_language = data.default_language;
		self.current_language = data.current_language;
	};

	// initialize
	self._init();
}

$(document).ready(function() {
	language_handler = new LanguageHandler();
	Caracal.language_handler = language_handler;
});
