/**
 * Language API
 *
 * Copyright (c) 2010. by MeanEYE.rcf
 * http://rcf-group.com
 *
 * Provides language services to client side of the backend.
 *
 * Requires jQuery 1.4.2+
 */

var language_handler = null;

function LanguageHandler() {
	// language containers
	this.languages = null;
	this.rtl_languages = null;
	this.default_language = null;
	this.current_language = null;

	// URL commonly used to comunicate with server
	this.backend_url = window.location.protocol + '//' + window.location.host + window.location.pathname;

	// local language constant cache
	this.cache = [];

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
		if (!language)
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
				type: 'GET',
				async: false,
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
	 * Get current language and store it localy
	 */
	this.getCurrentLanguage = function() {
		$.ajax({
			url: this.backend_url,
			type: 'GET',
			async: false,
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
			type: 'GET',
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
	};

	// initialize
	this.loadLanguages();
	this.getCurrentLanguage();
}

$(document).ready(function() {
	language_handler = new LanguageHandler();
});
