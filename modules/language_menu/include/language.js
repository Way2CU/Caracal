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

var language_handler = null;

function LanguageHandler() {
	// language containers
	this.languages = [];
	this.rtl_languages = [];
	this.default_language = 'en';
	this.current_language = 'en';

	// base url for this site
	this.backend_url = $('meta[property=base-url]').attr('content') + '/index.php';

	// local language constant cache
	this.cache = {};

	this.init = function() {
		this.loadLanguages();
	};

	/**
	 * Get language list
	 *
	 * @return json object
	 */
	this.getLanguages = function() {
		return this.languages;
	};

	/**
	 * Get RTL language list
	 *
	 * @return array
	 */
	this.getRTL = function() {
		return this.rtl_languages;
	};

	/**
	 * Check if specified language is RTL
	 *
	 * @return boolean
	 */
	this.isRTL = function(language) {
		// in case language is not specified use current
		if (language == undefined || language == null)
			var language = this.current_language;

		// return boolean result
		return !(this.rtl_languages.indexOf(language) == -1);
	};

	/**
	 * Get language constant value for specified module and language
	 *
	 * @param string module
	 * @param string constant
	 * @param string language
	 * @return string
	 */
	this.getText = function(module, constant) {
		var id = (module == null ? '_global' : module) + '.' + constant;
		var data = {
					section: 'language_menu',
					action: 'json_get_text',
					constant: constant
				};

		if (module != null)
			data.from_module = module;

		// check local cache first
		if (this.cache[id] == undefined) {
			$.ajax({
				url: this.backend_url,
				method: 'GET',
				async: false,
				cache: true,
				data: data,
				dataType: 'json',
				context: this,
				success: function(data) {
					this.cache[id] = data.text;
				}
			});
		}

		return this.cache[id];
	};

	/**
	 * Get language constant and call specified function
	 *
	 * @param string module
	 * @param string constant
	 * @param object callback
	 */
	this.getTextAsync = function(module, constant, callback) {
		var id = (module == null ? '_global' : module) + '.' + constant;
		var data = {
					section: 'language_menu',
					action: 'json_get_text',
					constant: constant
				};

		if (module != null)
			data.from_module = module;

		// check local cache first
		if (this.cache[id] == undefined) {
			$.ajax({
				url: this.backend_url,
				method: 'GET',
				async: true,
				cache: true,
				data: data,
				dataType: 'json',
				context: this,
				success: function(data) {
					this.cache[id] = data.text;
					callback(constant, data.text);
				}
			});

		} else {
			// we have local cache, send that
			callback(constant, this.cache[id]);
		}
	};

	/**
	 * Get array of language constants from server
	 *
	 * @param string module
	 * @param array constants
	 * @return array
	 */
	this.getTextArray = function(module, constants) {
		var id = (module == null ? '_global' : module) + '.';
		var data = {
					section: 'language_menu',
					action: 'json_get_text_array',
				};
		var result = {};
		var request = [];

		if (module != null)
			data.from_module = module;

		// check for all constants if we have cache
		for (var i=0; i < constants.length; i++) {
			var key = id + constants[i];

			if (key in this.cache) {
				// add cached value to result
				result[constants[i]] = this.cache[key];

			} else {
				// add constant to requested list
				request.push(constants[i]);
			}
		}

		// check local cache first
		if (request.length > 0) {
			data.constants = request;

			$.ajax({
				url: this.backend_url,
				method: 'GET',
				async: false,
				cache: true,
				data: data,
				dataType: 'json',
				context: this,
				success: function(data) {
					for (var key in data.text) {
						this.cache[id + key] = data.text[key];
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
	this.getTextArrayAsync = function(module, constants, callback) {
		var id = (module == null ? '_global' : module) + '.';
		var data = {
					section: 'language_menu',
					action: 'json_get_text_array',
				};
		var result = {};
		var request = [];

		if (module != null)
			data.from_module = module;

		// check for all constants if we have cache
		for (var i=0; i < constants.length; i++) {
			var key = id + constants[i];

			if (key in this.cache) {
				// add cached value to result
				result[constants[i]] = this.cache[key];

			} else {
				// add constant to requested list
				request.push(constants[i]);
			}
		}

		// check local cache first
		if (request.length > 0) {
			data.constants = request;

			$.ajax({
				url: this.backend_url,
				method: 'GET',
				async: true,
				cache: true,
				data: data,
				dataType: 'json',
				context: this,
				success: function(data) {
					for (var key in data.text) {
						this.cache[id + key] = data.text[key];
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
	this.getCurrentLanguage = function() {
		$.ajax({
			url: this.backend_url,
			method: 'GET',
			async: false,
			cache: true,
			data: {
				section: 'language_menu',
				action: 'json_get_current_language',
			},
			dataType: 'json',
			context: this,
			success: function(data) {
				this.current_language = data;
			}
		});
	};

	/**
	 * Load language list from server
	 */
	this.loadLanguages = function() {
		// retireve languages from server
		$.ajax({
			url: this.backend_url,
			method: 'GET',
			async: false,
			cache: true,
			data: {
				section: 'language_menu',
				action: 'json',
			},
			dataType: 'json',
			context: this,
			success: this.loadLanguages_Complete
		});
	};

	/**
	 * Process server response
	 *
	 * @param object data
	 * @param string status
	 */
	this.loadLanguages_Complete = function(data, status) {
		this.languages = data.items;
		this.rtl_languages = data.rtl;
		this.default_language = data.default_language;
		this.current_language = data.current_language;
	};

	// initialize
	this.init();
}

$(document).ready(function() {
	language_handler = new LanguageHandler();
});
