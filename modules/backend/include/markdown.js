/**
 * Markdown Toolbar Extension JavaScript
 * Caracal Backend
 *
 * Copyright (c) 2018. by Way2CU, http://way2cu.com
 * Authors: Mladen Mijatov
 */
var Caracal = Caracal || new Object();
Caracal.Toolbar = Caracal.Toolbar || new Object();


/**
 * Common toolbar extension.
 */
Caracal.Toolbar.Markdown = function(toolbar) {
	var self = this;

	self.toolbar = null;

	// namespaces
	self.ui = new Object();
	self.handler = new Object();

	/**
	 * Complete object initialization.
	 */
	self._init = function() {
		self.toolbar = toolbar;

		// create containers
		self.ui.format_container = document.createElement('div');
		self.ui.list_container = document.createElement('div');
		self.ui.embed_container = document.createElement('div');

		// create bold button
		self.ui.button_bold = self._create_control('icon-format-bold', self.handler.bold_click);
		self.ui.button_italic = self._create_control('icon-format-italic', self.handler.italic_click);
		self.ui.button_h1 = self._create_control('icon-format-h1', self.handler.header_click);
		self.ui.button_h2 = self._create_control('icon-format-h2', self.handler.header_click);
		self.ui.button_h3 = self._create_control('icon-format-h3', self.handler.header_click);
		self.ui.button_h4 = self._create_control('icon-format-h4', self.handler.header_click);
		self.ui.button_h5 = self._create_control('icon-format-h5', self.handler.header_click);
		self.ui.button_h6 = self._create_control('icon-format-h6', self.handler.header_click);
		self.ui.button_ordered_list = self._create_control('icon-format-ol', self.handler.list_click);
		self.ui.button_unordered_list = self._create_control('icon-format-ul', self.handler.list_click);
		self.ui.button_quote = self._create_control('icon-format-quote', self.handler.quote_click);
		self.ui.button_link = self._create_control('icon-format-link', self.handler.link_click);
		self.ui.button_code = self._create_control('icon-format-code', self.handler.code_click);

		// configure button variants
		self.ui.button_h1.dataset.variant = 1;
		self.ui.button_h2.dataset.variant = 2;
		self.ui.button_h3.dataset.variant = 3;
		self.ui.button_h4.dataset.variant = 4;
		self.ui.button_h5.dataset.variant = 5;
		self.ui.button_h6.dataset.variant = 6;
		self.ui.button_ordered_list.dataset.variant = 'ol';
		self.ui.button_unordered_list.dataset.variant = 'ul';

		// load language constants
		var constants = [
				'label_link',
				'toolbar_markdown_bold',
				'toolbar_markdown_italic',
				'toolbar_markdown_link',
				'toolbar_markdown_quote',
				'toolbar_markdown_code',
				'toolbar_markdown_header',
				'toolbar_markdown_ordered_list',
				'toolbar_markdown_unordered_list'
			];
		Caracal.language.getTextArrayAsync('backend', constants, self.handler.constants_load);

		// pack interface
		self.ui.format_container.append(self.ui.button_bold);
		self.ui.format_container.append(self.ui.button_italic);
		self.ui.format_container.append(self.ui.button_h1);
		self.ui.format_container.append(self.ui.button_h2);
		self.ui.format_container.append(self.ui.button_h3);
		self.ui.format_container.append(self.ui.button_h4);
		self.ui.format_container.append(self.ui.button_h5);
		self.ui.format_container.append(self.ui.button_h6);
		self.ui.list_container.append(self.ui.button_ordered_list);
		self.ui.list_container.append(self.ui.button_unordered_list);
		self.ui.embed_container.append(self.ui.button_quote);
		self.ui.embed_container.append(self.ui.button_link);
		self.ui.embed_container.append(self.ui.button_code);

		// add container to the toolbar
		self.toolbar.container.append(self.ui.format_container);
		self.toolbar.container.append(self.ui.list_container);
		self.toolbar.container.append(self.ui.embed_container);
	};

	/**
	 * Create control with specified icon and click handler.
	 *
	 * @param string icon_name
	 * @param callable handler
	 */
	self._create_control = function(icon_name, handler) {
		// create control
		var control = document.createElement('a');
		control.addEventListener('click', handler);

		// create icon
		var icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
		icon.innerHTML = '<use xlink:href="#' + icon_name + '"/>';
		control.append(icon);

		return control;
	};

	/**
	 * Handle language constants load.
	 *
	 * @param object data
	 */
	self.handler.constants_load = function(data) {
		self.ui.button_bold.title = data['toolbar_markdown_bold'];
		self.ui.button_italic.title = data['toolbar_markdown_italic'];
		self.ui.button_h1.title = data['toolbar_markdown_header'];
		self.ui.button_h2.title = data['toolbar_markdown_header'];
		self.ui.button_h3.title = data['toolbar_markdown_header'];
		self.ui.button_h4.title = data['toolbar_markdown_header'];
		self.ui.button_h5.title = data['toolbar_markdown_header'];
		self.ui.button_h6.title = data['toolbar_markdown_header'];
		self.ui.button_ordered_list.title = data['toolbar_markdown_ordered_list'];
		self.ui.button_unordered_list.title = data['toolbar_markdown_unordered_list'];
		self.ui.button_quote.title = data['toolbar_markdown_quote'];
		self.ui.button_link.title = data['toolbar_markdown_link'];
		self.ui.button_code.title = data['toolbar_markdown_code'];
	};

	/**
	 * Handle clicking on bold button.
	 *
	 * @param object event
	 */
	self.handler.bold_click = function(event) {
		event.preventDefault();

		// gather information about current selection
		var element = self.toolbar.element;
		var start = element.selectionStart;
		var end = element.selectionEnd;
		var new_value = '**' + element.value.substring(start, end) + '**';
		var cursor_position = start + new_value.length;

		// position selection inside of formatting when there's no selection
		if (start == end)
			cursor_position -= 2;

		// replace existing selection
		element.value = element.value.substr(0, start) + new_value + element.value.substr(end);
		element.focus();
		element.setSelectionRange(cursor_position, cursor_position);
	};

	/**
	 * Handle clicking on italic button.
	 *
	 * @param object event
	 */
	self.handler.italic_click = function(event) {
		event.preventDefault();

		// gather information about current selection
		var element = self.toolbar.element;
		var start = element.selectionStart;
		var end = element.selectionEnd;
		var new_value = '_' + element.value.substring(start, end) + '_';
		var cursor_position = start + new_value.length;

		// position selection inside of formatting when there's no selection
		if (start == end)
			cursor_position -= 1;

		// replace existing selection
		element.value = element.value.substr(0, start) + new_value + element.value.substr(end);
		element.focus();
		element.setSelectionRange(cursor_position, cursor_position);
	};

	/**
	 * Handle clicking on header button.
	 *
	 * @param object event
	 */
	self.handler.header_click = function(event) {
		event.preventDefault();

		// gather information about current selection
		var element = self.toolbar.element;
		var start = element.selectionStart;
		var end = element.selectionEnd;
		var prefix = (start == 0 ? '' : '\n\n') + '#'.repeat(event.currentTarget.dataset.variant) + ' ';
		var suffix = (start == end ? '' : '\n\n');
		var new_value = prefix + element.value.substring(start, end) + suffix;
		var cursor_position = start + new_value.length;

		// replace existing selection
		element.value = element.value.substr(0, start) + new_value + element.value.substr(end);
		element.focus();
		element.setSelectionRange(cursor_position, cursor_position);
	};

	/**
	 * Handle clicking on list button.
	 *
	 * @param object event
	 */
	self.handler.list_click = function(event) {
		event.preventDefault();

		// gather information about current selection
		var element = self.toolbar.element;
		var start = element.selectionStart;
		var end = element.selectionEnd;
		var selection = element.value.substring(start, end).split('\n');

		// generate list
		var prefix = (start == 0 ? '' : '\n');
		var new_value = '';
		var ul_match = /[\-\*]\s+/;
		var ol_match = /\d+\.\s+/;
		var block_match = /\s{3,}/;

		index = 0;
		for (var i=0, count=selection.length; i<count; i++) {
			var line = selection[i];

			// skip empty lines added to surround the list
			if (line == '')
				continue;
			
			// just reuse line with slight indentation
			if (block_match.test(line) || ul_match.test(line) || ol_match.test(line)) {
				new_value += '   ' + line + '\n';

			// add line with addon
			} else {
				var addon = event.currentTarget.dataset.variant == 'ol' ? (++index).toString() + '.' : '-';
				new_value += addon + ' ' + line + '\n';
			}
		}
		var new_value = prefix + new_value;
		var cursor_position = start + new_value.length;

		// replace existing selection
		element.value = element.value.substr(0, start) + new_value + element.value.substr(end);
		element.focus();
		element.setSelectionRange(cursor_position, cursor_position);
	};

	/**
	 * Handle clicking on quote button.
	 *
	 * @param object event
	 */
	self.handler.quote_click = function(event) {
		event.preventDefault();

		// gather information about current selection
		var element = self.toolbar.element;
		var start = element.selectionStart;
		var end = element.selectionEnd;
		var selection = element.value.substring(start, end).split('\n');

		// generate list
		var prefix = (start == 0 ? '' : '\n');
		var new_value = '';

		for (var i=0, count=selection.length; i<count; i++) {
			var line = selection[i];
			new_value += '> ' + line + '\n';
		}
		var new_value = prefix + new_value;
		var cursor_position = start + new_value.length;

		// replace existing selection
		element.value = element.value.substr(0, start) + new_value + element.value.substr(end);
		element.focus();
		element.setSelectionRange(cursor_position, cursor_position);
	};

	/**
	 * Handle clicking on link button.
	 *
	 * @param object event
	 */
	self.handler.link_click = function(event) {
		event.preventDefault();

		// gather information about current selection
		var element = self.toolbar.element;
		var start = element.selectionStart;
		var end = element.selectionEnd;

		// generate replacement text
		var url = prompt(Caracal.language.getText('backend', 'label_link'), 'http://');
		var new_value = '[' + element.value.substring(start, end) + '](' + url + ')';

		var cursor_position = start + new_value.length;
		if (start == end)
			cursor_position = start + 1;

		// replace existing selection
		element.value = element.value.substr(0, start) + new_value + element.value.substr(end);
		element.focus();
		element.setSelectionRange(cursor_position, cursor_position);
	};

	/**
	 * Handle clicking on code button.
	 *
	 * @param object event
	 */
	self.handler.code_click = function(event) {
		event.preventDefault();

		// gather information about current selection
		var element = self.toolbar.element;
		var start = element.selectionStart;
		var end = element.selectionEnd;

		// generate list
		var new_value = '\n```\n' + element.value.substring(start, end) + '\n```\n';
		var cursor_position = start + new_value.length;
		if (start == end)
			cursor_position = start + 5;

		// replace existing selection
		element.value = element.value.substr(0, start) + new_value + element.value.substr(end);
		element.focus();
		element.setSelectionRange(cursor_position, cursor_position);
	};

	// finalize object
	self._init();
}

// register extension
window.addEventListener('load', function() {
	Caracal.Toolbar.register_extension('markdown', Caracal.Toolbar.Markdown);
});
