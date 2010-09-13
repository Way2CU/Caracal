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
	}
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
	}

	/**
	 * Check if specified module exists
	 *
	 * @return boolean
	 */
	this.moduleExists = function(module) {
		return module in this.module_list;
	}

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
	}

	/**
	 * Create toolbar for specified componend in current window.
	 *
	 * @param string parent_id
	 * @param string component
	 */
	this.createToolbar = function(parent_id, component) {
		return new Toolbar(parent_id, component);
	}
}


/**
 * COMMON TOOLBAR CONTROLS
 */
function ToolbarExtension_Common() {

	/**
	 * Function used to add control on specified toolbar
	 *
	 * @param object $toolbar
	 * @param object $component
	 * @param string control
	 */
	function addControl($toolbar, $component, control) {
		switch (control) {
			case 'markdown':
				break;
		}
	}
}

$(document).ready(function() {
	toolbar_api = new ToolbarAPI();

	var common = new ToolbarExtension_Common();
});
