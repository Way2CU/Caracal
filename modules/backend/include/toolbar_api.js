/**
 * Backend Toolbar API
 *
 * Copyright (c) 2010. by MeanEYE.rcf
 * http://rcf-group.com
 *
 * This API was designed to be used with RCF WebEngine
 * administration system and has very little use outside of it.
 * Is you are going to take parts from this file leave credits intact.
 *
 * Requires jQuery 1.4.2+
 *
 * Object used in registerModule funciton needs to have the following method.
 *
 * function addControl($toolbar, $component, control) {
 * }
 */

var toolbar_api = null;

/**
 * Toolbar class. Acts as a glue between DOM objects and Toolbar API object.
 * This object needs to be created for *each* input element separately.
 */
function Toolbar(parent_id, component) {
	var self = this;  // for use in internal events
	var $parent = $('#' + parent_id);

	this.$toolbar = $('<div>');
	this.$component = $parent.find(component);

	// component focus/blur events
	this.$component.focus(function() {
		self.$toolbar.addClass('active');
	});

	this.$component.blur(function() {
		self.$toolbar.removeClass('active');
	});

	this.$toolbar
		.insertBefore(this.$component)
		.addClass('toolbar')
		.css({
			width: this.$component.width() + 2
		});

	this.$component.css({
					'borderTop': '0px',
					'resize': 'none'
				});

	/**
	 * Add control from module.
	 *
	 * @return object
	 */
	this.addControl = function(module, control) {
		var module = toolbar_api.getModule(module);

		if (module != null)
			module.addControl(this.$toolbar, this.$component, control);

		return this;
	};
}

/**
 * Global Toolbar API object
 */
function ToolbarAPI() {
	this.module_list = [];

	/**
	 * Register toolbar extension for specified module
	 *
	 * @param string name
	 * @param resource object
	 */
	this.registerModule = function(name, object) {
		if (name in this.module_list) return;

		this.module_list[name] = object;
	};

	/**
	 * Check if specified module exists
	 *
	 * @return boolean
	 */
	this.moduleExists = function(module) {
		return module in this.module_list;
	};

	/**
	 * Get module object based on its name
	 *
	 * @return object
	 */
	this.getModule = function(module) {
		var result = null;

		if (this.moduleExists(module))
			result = this.module_list[module]; else
			result = null;

		return result;
	};

	/**
	 * Create toolbar for specified componend in current window.
	 *
	 * @param string parent_id
	 * @param string component
	 */
	this.createToolbar = function(parent_id, component) {
		return new Toolbar(parent_id, component);
	};
}


/**
 * COMMON TOOLBAR CONTROLS
 */
function ToolbarExtension_Common() {
	// register extension to mail API
	toolbar_api.registerModule('common', this);

	constants = [
			'toolbar_markdown_bold',
			'toolbar_markdown_italic',
			'toolbar_markdown_link',
			'toolbar_markdown_quote',
			'toolbar_markdown_code',
			'toolbar_markdown_header',
			'toolbar_markdown_ordered_list',
			'toolbar_markdown_unordered_list'
		];

	setTimeout(
		function() {
			language_handler.getTextArrayAsync('backend', constants, function() {});
		}, 1000);

	/**
	 * Function used to add control on specified toolbar
	 *
	 * @param object toolbar
	 * @param object component
	 * @param string control
	 */
	this.addControl = function(toolbar, component, control) {
		switch (control) {
			case 'markdown':
				this.control_Style(toolbar, component);
				this.control_Separator(toolbar, component);
				this.control_Header(toolbar, component);
				this.control_Separator(toolbar, component);
				this.control_List(toolbar, component);
				this.control_Separator(toolbar, component);
				this.control_Quote(toolbar, component);
				this.control_Link(toolbar, component);
				this.control_Code(toolbar, component);
				break;
		}
	};

	/**
	 * Create control for markdown styles to the specified toolbar
	 * 
	 * @param object toolbar
	 * @param object component
	 */
	this.control_Style = function(toolbar, component) {
		var button_bold = $('<a>');
		var button_italic = $('<a>');

		var image_bold = $('<img>');
		var image_italic = $('<img>');

		// configure images
		image_bold
				.attr('src', this.getIconURL('markdown_bold.png'))
				.attr('alt', '');
		image_italic
				.attr('src', this.getIconURL('markdown_italic.png'))
				.attr('alt', '');

		// configure buttons
		button_bold
				.addClass('button')
				.attr('href', 'javascript: void(0);')
				.attr('title', language_handler.getText('backend', 'toolbar_markdown_bold'))
				.append(image_bold)
				.data('control', component)
				.click(this.__handleClick_Bold);
		button_italic
				.addClass('button')
				.attr('href', 'javascript: void(0);')
				.attr('title', language_handler.getText('backend', 'toolbar_markdown_italic'))
				.append(image_italic)
				.data('control', component)
				.click(this.__handleClick_Italic);

		// pack buttons
		toolbar
			.append(button_bold)
			.append(button_italic);
	};

	/**
	 * Create link button for toolbar
	 *
	 * @param object toolbar
	 * @param object component
	 */
	this.control_Link = function(toolbar, component) {
		var button_link = $('<a>');
		var image_link = $('<img>');

		// configure images
		image_link
				.attr('src', this.getIconURL('markdown_link.png'))
				.attr('alt', '');

		// configure buttons
		button_link
				.addClass('button')
				.attr('href', 'javascript: void(0);')
				.attr('title', language_handler.getText('backend', 'toolbar_markdown_link'))
				.append(image_link)
				.data('control', component)
				.click(this.__handleClick_Link);

		// pack buttons
		toolbar
			.append(button_link);
	};

	/**
	 * Create quote button for toolbar
	 *
	 * @param object toolbar
	 * @param object component
	 */
	this.control_Quote = function(toolbar, component) {
		var button_quote = $('<a>');
		var image_quote = $('<img>');

		// configure images
		image_quote
				.attr('src', this.getIconURL('markdown_quote.png'))
				.attr('alt', '');

		// configure buttons
		button_quote
				.addClass('button')
				.attr('href', 'javascript: void(0);')
				.attr('title', language_handler.getText('backend', 'toolbar_markdown_quote'))
				.append(image_quote)
				.data('control', component)
				.click(this.__handleClick_Quote);

		// pack buttons
		toolbar
			.append(button_quote);
	};

	/**
	 * Create code button for toolbar
	 *
	 * @param object toolbar
	 * @param object component
	 */
	this.control_Code = function(toolbar, component) {
		var button_code = $('<a>');
		var image_code = $('<img>');

		// configure images
		image_code
				.attr('src', this.getIconURL('markdown_code.png'))
				.attr('alt', '');

		// configure buttons
		button_code
				.addClass('button')
				.attr('href', 'javascript: void(0);')
				.attr('title', language_handler.getText('backend', 'toolbar_markdown_code'))
				.append(image_code)
				.data('control', component)
				.click(this.__handleClick_Code);

		// pack buttons
		toolbar
			.append(button_code);
	};

	/**
	 * Create heading buttons for toolbar
	 *
	 * @param object toolbar
	 * @param object component
	 */
	this.control_Header = function(toolbar, component) {
		for (var i=1; i<=6; i++) {
			var button_header = $('<a>');
			var image_header = $('<img>');

			// configure images
			image_header
					.attr('src', this.getIconURL('markdown_heading_' + i + '.png'))
					.attr('alt', '');

			// configure buttons
			button_header
					.addClass('button')
					.attr('href', 'javascript: void(0);')
					.attr('title', language_handler.getText('backend', 'toolbar_markdown_header'))
					.append(image_header)
					.data('control', component)
					.data('number', i)
					.click(this.__handleClick_Header);

			// pack buttons
			toolbar
				.append(button_header);
		}
	};

	/**
	 * Create list buttons for toolbar
	 *
	 * @param object toolbar
	 * @param object component
	 */
	this.control_List = function(toolbar, component) {
		var button_ordered_list = $('<a>');
		var button_unordered_list = $('<a>');

		var image_ordered_list = $('<img>');
		var image_unordered_list = $('<img>');

		// configure images
		image_ordered_list
				.attr('src', this.getIconURL('markdown_list_numbers.png'))
				.attr('alt', '');
		image_unordered_list
				.attr('src', this.getIconURL('markdown_list_bullets.png'))
				.attr('alt', '');

		// configure buttons
		button_ordered_list
				.addClass('button')
				.attr('href', 'javascript: void(0);')
				.attr('title', language_handler.getText('backend', 'toolbar_markdown_ordered_list'))
				.append(image_ordered_list)
				.data('control', component)
				.data('ordered', true)
				.click(this.__handleClick_List);
		button_unordered_list
				.addClass('button')
				.attr('href', 'javascript: void(0);')
				.attr('title', language_handler.getText('backend', 'toolbar_markdown_unordered_list'))
				.append(image_unordered_list)
				.data('control', component)
				.data('ordered', false)
				.click(this.__handleClick_List);

		// pack buttons
		toolbar
			.append(button_ordered_list)
			.append(button_unordered_list);
	};

	/**
	 * Create separator for toolbar
	 *
	 * @param object toolbar
	 * @param object component
	 */
	this.control_Separator = function(toolbar, component) {
		var separator = $('<span>');

		separator.addClass('separator');
		toolbar.append(separator);
	};

	/**
	 * Form URL based on icon name
	 *
	 * @param string icon
	 * @return string
	 */
	this.getIconURL = function(icon) {
		var path = [];

		// add icon path
		path.push('modules/backend/images/icons/16');
		path.push(icon);

		// base url for this site
		var base = $('base');
		base_url = base.attr('href') + '/';

		return base_url + path.join('/');
	};

	/**
	 * Handle clicking on bold button
	 * @param object event
	 */
	this.__handleClick_Bold = function(event) {
		var control = $(this).data('control')[0];
		var sel_start = control.selectionStart;
		var sel_end = control.selectionEnd;
		var value = control.value;
		var new_value = '**' + value.substring(sel_start, sel_end) + '**';

		$(control).replaceSelection(new_value, 2);
		event.preventDefault();
	};

	/**
	 * Handle clicking on italic button
	 * @param object event
	 */
	this.__handleClick_Italic = function(event) {
		var control = $(this).data('control')[0];
		var sel_start = control.selectionStart;
		var sel_end = control.selectionEnd;
		var value = control.value;
		var new_value = '_' + value.substring(sel_start, sel_end) + '_';

		$(control).replaceSelection(new_value, 1);
		event.preventDefault();
	};

	/**
	 * Handle clicking on bold button
	 * @param object event
	 */
	this.__handleClick_Link = function(event) {
		var control = $(this).data('control')[0];
		var sel_start = control.selectionStart;
		var sel_end = control.selectionEnd;
		var new_value = '[' + control.value.substring(sel_start, sel_end) + ']';

		language_handler.getTextAsync('backend', 'label_link', function(constant, text) {
			var url = prompt(text, 'http://');

			if (url != 'http://' && url != '' && url != null) {
				new_value = new_value + '(' + url + ')';
				$(control).replaceSelection(new_value, 1);
			}
		});

		event.preventDefault();
	};

	/**
	 * Handle clicking on heading button
	 * @param object event
	 */
	this.__handleClick_Header = function(event) {
		var control = $(this).data('control')[0];
		var number = $(this).data('number');
		var sel_start = control.selectionStart;
		var sel_end = control.selectionEnd;
		var chars = ['#', '##', '###', '####', '#####', '######'][number - 1];
		var new_value = '\n' + chars + ' ' + control.value.substring(sel_start, sel_end);

		$(control).replaceSelection(new_value, chars.length + 2);
		event.preventDefault();
	};

	/**
	 * Handle clicking on list button
	 * @param object event
	 */
	this.__handleClick_List = function(event) {
		var control = $(this).data('control')[0];
		var ordered = $(this).data('ordered');
		var sel_start = control.selectionStart;
		var sel_end = control.selectionEnd;
		var selection = control.value.substring(sel_start, sel_end).split('\n');

		for (var i=0; i<selection.length; i++) 
			selection[i] = ' ' + (ordered ? i + 1 + '.' : '-') + ' ' + selection[i];

		$(control).replaceSelection('\n' + selection.join('\n') + '\n', ordered ? 5 : 4);
		event.preventDefault();
	};

	/**
	 * Handle clicking on code button
	 * @param object event
	 */
	this.__handleClick_Code = function(event) {
		var control = $(this).data('control')[0];
		var sel_start = control.selectionStart;
		var sel_end = control.selectionEnd;
		var selection = control.value.substring(sel_start, sel_end).split('\n');

		for (var i=0; i<selection.length; i++) 
			selection[i] = '    ' + selection[i];

		$(control).replaceSelection('\n' + selection.join('\n') + '\n', 5);
		event.preventDefault();
	};

	/**
	 * Handle clicking on quote button
	 * @param object event
	 */
	this.__handleClick_Quote = function(event) {
		var control = $(this).data('control')[0];
		var sel_start = control.selectionStart;
		var sel_end = control.selectionEnd;
		var selection = control.value.substring(sel_start, sel_end).split('\n');

		for (var i=0; i<selection.length; i++) 
			selection[i] = '> ' + selection[i];

		$(control).replaceSelection('\n' + selection.join('\n') + '\n', 3);
		event.preventDefault();
	};
}

$(document).ready(function() {
	toolbar_api = new ToolbarAPI();

	var common = new ToolbarExtension_Common();
});
