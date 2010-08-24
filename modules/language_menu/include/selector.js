/**
 * Multi-language Selector
 *
 * Copyright (c) 2010. by MeanEYE.rcf
 * http://rcf-group.com
 *
 * You need to create new language selector
 *
 * Requires jQuery 1.4.2+
 */

var language_selector = null;

function LanguageSelector(id) {
	var self = this;  // used internally for events

	this.languages = null;
	this.current_anguage = null;
	this.$objects = [];
	this.$parent = $('#'+id);

	this.$container = $('<div>').addClass('language_selector');

	/**
	 * Load languages from server
	 */
	this.loadLanguages = function() {
		this.$parent.prepend(this.$container);
		this.$container.addClass('loading');

		// retireve languages from server
		$.ajax({
			url: window.location.protocol + '//' + window.location.hostname + ':' + window.location.port + window.location.pathname,
			type: 'GET',
			data: {
				section: 'language_menu',
				action: 'json',
			},
			dataType: 'json',
			context: this,
			success: this.processResponse
		});
	}

	/**
	 * Process result from server
	 *
	 * @param object data
	 */
	this.processResponse = function(data, status) {
		// exit if there are no languages (possibly an error)
		if (data.items.length == 0) return;

		var field_data = {};

		// create options
		for(var i in data.items) {
			var language = data.items[i];
			var $button = $('<span>').html(language.long).data(language);

			if (language.default)
				this.current_language = language.short;

			$button.click(function() {
				self.setLanguage($(this).data().short);
			});

			this.$objects[language.short] = $button;
			this.$container.append($button);
		}

		// collect multi-language data
		this.$parent.find('data').each(function() {
			var field = $(this).attr('field');
			var language = $(this).attr('language');

			if (field_data[field] == undefined)
				field_data[field] = {};

			field_data[field][language] = $(this).html();
			$(this).remove();
		});

		// replace existing and load data along the way
		this.$parent.find('input.multi-language, textarea.multi-language').each(function() {
			for(var i in data.items) {
				var name = $(this).attr('name');
				var language = data.items[i];

				var $new = $(this).clone()
							.insertAfter($(this))
							.attr('name', $(this).attr('name') + '_' + language.short)
							.css({display: 'none'});

				if (field_data[name] != undefined && field_data[name][language.short] != undefined)
					$new.val(field_data[name][language.short]);
			}

			$(this).remove();
		});

		// select default language
		if (this.current_language != null)
			this.setLanguage(this.current_language); else
			this.setLanguage(data.items[0].short);

		// stop the loading animation
		this.$container.removeClass('loading');
	}

	/**
	 * Set active language
	 *
	 * @param string language
	 */
	this.setLanguage = function(language) {
		this.$objects[this.current_language].removeClass('active');
		this.$objects[language].addClass('active');

		this.current_language = language;

		this.$parent.find('input.multi-language, textarea.multi-language').each(function() {
			if ($(this).attr('name').substr(-2) == language)
				$(this).show(); else
				$(this).hide();
		});
	}

	// load languages and construct selector
	this.loadLanguages();
}
