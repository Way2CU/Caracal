/**
 * Multi-language Selector
 *
 * Copyright (c) 2010. by MeanEYE.rcf
 * http://rcf-group.com
 *
 * You need to create new language selector for each window. Id specified in
 * constructor function is container Id. Window Id can be used as well.
 *
 * Requires jQuery 1.4.2+
 */

var language_selector = null;

function LanguageSelector(id) {
	var self = this;  // used internally for events

	this.languages = null;
	this.rtl_languages = null;
	this.current_language = null;
	this.$objects = [];
	this.$parent = $('#'+id);

	this.$container = $('<div>').addClass('language_selector');

	/**
	 * Process result from server
	 *
	 * @param object data
	 */
	this.init = function() {
		this.$parent.prepend(this.$container);
		this.$container.addClass('loading');

		var field_data = {};

		// create options
		for(var i in language_handler.languages) {
			var language = language_handler.languages[i];
			var $button = $('<span>')

			$button
				.html(language.long)
				.data(language)
				.click(function() {
					self.setLanguage($(this).data().short);
				});

			this.$objects[language.short] = $button;
			this.$container.append($button);
		}

		// attach reset event if parent is form
		if (this.$parent.get(0).nodeName == 'FORM') {
			this.$parent.find(':reset').eq(0).click(function(event) {
				event.preventDefault();

				self.$parent.get(0).reset();
				self.resetFields(event);
			});
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

		// set language data and DOM references
		this.$parent.find('input.multi-language, textarea.multi-language').each(function() {
			var name = $(this).attr('name');
			var data = field_data[name];

			if (data == undefined) data = {};

			var original_data = $.extend({}, data);

			$(this).data('language', data);
			$(this).data('original_data', original_data);
			$(this).data('selector', self);

			// upon leaving input element, store data
			$(this).blur(function() {
				var data = $(this).data('language');
				data[self.current_language] = $(this).val()

				$(this).data('language', data);
			});
		});

		// select default language
		this.setLanguage(language_handler.default_language);

		// stop the loading animation
		this.$container.removeClass('loading');
	}

	/**
	 * Set active language
	 *
	 * @param string language
	 */
	this.setLanguage = function(language) {
		if (this.current_language != null)
			this.$objects[self.current_language].removeClass('active');

		this.$objects[language].addClass('active');

		this.$parent.find('input.multi-language, textarea.multi-language').each(function() {
			var data = $(this).data('language');

			// store data if current language is not null
			if (self.current_language != null)
				data[self.current_language] = $(this).val();

			// get data for new language from storage
			if (language in data)
				$(this).val(data[language]); else
				$(this).val('');

			// save old data once we switched everything
			$(this).data('language', data);

			// apply proper direction
			if (!language_handler.isRTL(language))
				$(this).css('direction', 'ltr'); else
				$(this).css('direction', 'rtl');
		});

		this.current_language = language;
	}

	/**
	 * Externaly called function on form reset event
	 */
	this.resetFields = function() {
		this.$parent.find('input.multi-language, textarea.multi-language').each(function() {
			var data = $.extend({}, $(this).data('original_data'));
			$(this).data('language', data);

			// get data for language from storage
			if (self.current_language in data)
				$(this).val(data[self.current_language]); else
				$(this).val('');
		});
	}

	// load languages and construct selector
	this.init();
}
