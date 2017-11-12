/**
 * Editor Language Selector
 *
 * Language selector is used to add support for multiple-languages in backend
 * while editing content. Window system will automatically create new language
 * selector for each window if it detects multi-language input fields.
 */

var Caracal = Caracal || new Object();
Caracal.WindowSystem = Caracal.WindowSystem || new Object();


Caracal.WindowSystem.LanguageSelector = function(window) {
	var self = this;

	self.fields = null;
	self.language = null;

	// container namespaces
	self.ui = new Object();
	self.handler = new Object();
	self.data = new Object();

	/**
	 * Complete object initialization.
	 */
	self._init = function() {
		self.ui.window = window;

		// create button container and configure it
		self.ui.container = document.createElement('div');
		self.ui.container.classList.add('language-selector');
		self.ui.window.ui.window_menu.append(self.ui.container);

		// find fields to integrate with
		self.fields = self.ui.window.ui.content.querySelectorAll('input.multi-language, textarea.multi-language');

		// create language controls
		var default_language = null;
		self.ui.controls = new Array();

		for (var i=0, count=language_handler.languages.length; i<count; i++) {
			var language = language_handler.languages[i];

			// create controls
			var control = document.createElement('a');
			control.text = language.long;
			control.dataset.short = language.short;
			control.addEventListener('click', self.handler.control_click);
			self.ui.container.append(control);
			self.ui.controls.push(control);
		}

		// create data storage for each field
		self.data.initial = new Object();
		self.data.current = new Object();

		for (var i=0, count=self.fields.length; i<count; i++) {
			var field = self.fields[i];

			self.data.initial[field.name] = new Object();
			self.data.current[field.name] = new Object();
		}

		// collect language data associated with fields
		var data_tags = self.ui.window.ui.content.querySelectorAll('language-data');

		for (var i=0, count=data_tags.length; i<count; i++) {
			var data_tag = data_tags[i];
			var field = data_tag.getAttribute('field');
			var language = data_tag.getAttribute('language');

			if (self.data.initial[field] == undefined)
				self.data.initial[field] = new Object();

			if (self.data.current[field] == undefined)
				self.data.current[field] = new Object();

			self.data.initial[field][language] = data_tag.innerText;
			self.data.current[field][language] = data_tag.innerText;
			data_tag.remove();
		}

		// connect events
		for (var i=0, count=self.fields.lenght; i<count; i++)
			self.fields[i].addEventListener('blur', self.handler.field_lost_focus);
		self.ui.window.ui.content.querySelector('form').addEventListener('reset', self.handler.form_reset);

		// select default language
		self.set_language();
	};

	/**
	 * Handle clicking on control.
	 *
	 * @param object event
	 */
	self.handler.control_click = function(event) {
		// change language
		var language = event.target.dataset.short;
		self.set_language(language);

		// stop default handler
		event.preventDefault();
	};

	/**
	 * Handle resetting of the form.
	 *
	 * @param object event
	 */
	self.handler.form_reset = function(event) {
		self.reset_values();
	};

	/**
	 * Handle multi-language field loosing focus.
	 *
	 * @param object event
	 */
	self.handler.field_lost_focus = function(event) {
		var field = event.target;
		self.data.current[field.name][self.language] = field.value;
	};

	/**
	 * Switch multi-language fields to specified language. If no language
	 * was specified, set to default.
	 *
	 * @param string new_language
	 */
	self.set_language = function(new_language) {
		// if omitted we are switching to default language
		if (new_language == undefined)
			var new_language = language_handler.default_language;

		// make sure we are not switching to same language
		if (self.language == new_language)
			return;

		console.log(new_language, self.language);
		var new_language_is_rtl = language_handler.isRTL(new_language);

		// highlight active control
		for (var i=0, count=self.ui.controls.length; i<count; i++) {
			var control = self.ui.controls[i];

			if (control.dataset.short == new_language)
				control.classList.add('active'); else
				control.classList.remove('active');
		}

		// change input field values
		for (var i=0, count=self.fields.length; i<count; i++) {
			var field = self.fields[i];

			// store current language data
			if (self.language != null)
				self.data.current[field.name][self.language] = field.value;

			// load new language data from the storage
			if (new_language in self.data.current[field.name])
				field.value = self.data.current[field.name][new_language]; else
				field.value = '';

			// apply language direction
			if (new_language_is_rtl)
				field.style.direction = 'rtl'; else
				field.style.direction = 'ltr';
		}

		// store new language selection
		self.language = new_language;
	};

	/**
	 * Restore initial field values.
	 */
	self.reset_values = function() {
		if (self.language == null)
			return;

		for (var i=0, count=self.fields.length; i<count; i++) {
			var field = self.fields[i];

			if (self.language in seld.data.initial[field.name])
				field.value = self.data.initial[field.name][self.language]; else
				field.value = '';
		}
	};

	// finalize object
	self._init();
}
