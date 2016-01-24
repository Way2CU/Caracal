/**
 * Multi-language Selector
 *
 * Copyright (c) 2014. by Way2CU
 * Author: Mladen Mijatov
 *
 * You need to create new language selector for each window.
 *
 * Requires jQuery 1.4.2+
 */

var language_selector = null;

function LanguageSelector(id) {
	var self = this;

	self.current_language = null;
	self.container = $('#' + id);
	self.fields = null;

	self.button_container = $('<div>').addClass('language_selector');

	/**
	 * Process result from server
	 *
	 * @param object data
	 */
	self.init = function() {
		self.container.prepend(self.button_container);
		self.button_container
				.addClass('loading')
				.data('selector', self);

		// find fields
		self.fields = self.container.find('input.multi-language, textarea.multi-language');

		// create options
		var languages = language_handler.languages;
		var default_supported = false;
		var default_language = null;

		for(var i in languages) {
			var language = languages[i];
			var button = $('<span>');

			// configure button
			button
				.html(language.long)
				.attr('data-short', language.short)
				.click(self._handle_button_click);

			// add button to container
			self.button_container.append(button);

			// check if language matches default one
			if (language.short == language_handler.default_language) {
				default_supported = true;
				default_language = language.short;
			}
		}

		// make sure we have default language to set
		if (default_language === null)
			default_language = languages[0].short;

		// attach reset event to forms in container
		self.container.find('form').on('reset', self._handle_form_reset);

		// collect multi-language data
		var field_data = {};

		self.container.find('data').each(function() {
			var data_tag = $(this);
			var field = data_tag.attr('field');
			var language = data_tag.attr('language');

			if (field_data[field] == undefined)
				field_data[field] = {};

			field_data[field][language] = data_tag.html();
			data_tag.remove();
		});

		// set language data
		self.fields.each(function() {
			var field = $(this);
			var name = field.attr('name');
			var data = field_data[name];

			if (data == undefined)
				data = {};

			var original_data = $.extend({}, data);

			field.data('language', data);
			field.data('original_data', original_data);

			// upon leaving input element, store data
			field.blur(self._handle_field_lost_focus);
		});

		// select default language
		self.set_language(default_language);

		// stop the loading animation
		self.button_container.removeClass('loading');
	};

	/**
	 * Handle clicking on language button.
	 *
	 * @param object event
	 */
	self._handle_button_click = function(event) {
		// get data
		var button = $(this);
		var language = button.data('short');

		// prevent default behavior
		event.preventDefault();

		// change language
		self.set_language(language);
	};

	/**
	 * Handle field loosing focus.
	 *
	 * @param object event
	 */
	self._handle_field_lost_focus = function(event) {
		var field = $(this);
		var data = field.data('language');

		// update field data for current language
		data[self.current_language] = field.val();
		field.data('language', data);

		// unset focused field
		self.focused_field = null;
	};

	/**
	 * Handle reseting the form.
	 *
	 * @param object event
	 */
	self._handle_form_reset = function(event) {
		// reset fields
		self.reset_fields(event);
	};

	/**
	 * Set active language
	 *
	 * @param string language
	 */
	self.set_language = function(language) {
		if (self.current_language == language)
			return;

		// change active button
		var new_button = self.button_container.find('[data-short=\'' + language + '\']');
		self.button_container.children().not(new_button).removeClass('active');
		new_button.addClass('active');

		// switch language for each field
		self.fields.each(function() {
			var field = $(this);
			var data = field.data('language');

			// store data if current language is not null
			if (self.current_language != null)
				data[self.current_language] = field.val();

			// get data for new language from storage
			if (language in data)
				field.val(data[language]); else
				field.val('');

			// save old data once we switched everything
			field.data('language', data);

			// emit signal to let other scripts know value has been changed
			field.trigger('change');

			// apply proper direction
			if (!language_handler.isRTL(language))
				field.css('direction', 'ltr'); else
				field.css('direction', 'rtl');
		});

		// store new language selection
		self.current_language = language;
	};

	/**
	 * Function used to restore original language data on form reset event
	 */
	self.reset_fields = function() {
		self.fields.each(function() {
			var field = $(this);
			var data = $.extend({}, field.data('original_data'));
			field.data('language', data);

			// get data for language from storage
			if (self.current_language in data)
				field.val(data[self.current_language]); else
				field.val('');
		});
	};

	// load languages and construct selector
	self.init();
}
