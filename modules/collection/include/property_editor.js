/**
 * Property editor function which converts regular input field into
 * complex field allowing entry of predefined or new properties.
 *
 * Author: Mladen Mijatov
 */

var Caracal = Caracal || {};


/**
 * Construct new property editor and replace input element found by `selector`.
 *
 * @param string selector
 */
Caracal.PropertyEditor = function(selector) {
	var self = this;

	self._container = null;
	self._component = null;
	self._handlers = {};

	/**
	 * Object finalization.
	 */
	self._init = function() {
		// connect event handlers for input field
		self._component = $(selector);

		// create and configure container
		self._container = $('<div class="property-editor">');
		self._container
				.insertAfter(self._component)
				.on('click', self._handlers.container_click);

		// create input field
		self._input = $('<input>');
		self._input
				.attr('type', self._component.attr('type'))
				.on('focus', self._handlers.input_focus_gained)
				.on('blur', self._handlers.input_focus_lost)
				.on('keypress', self._handlers.input_key_press)
				.on('click', self._handlers.input_click)
				.appendTo(self._container);

		// configure component and move it
		self._component
				.on('change', self._handlers.component_value_changed)
				.detach().appendTo(self._container);
	};

	/**
	 * Add specified property to the list.
	 *
	 * @param mixed property
	 * @return boolean
	 */
	self.add_property = function(value) {
		var result = false;

		// we only allow unique properties
		if (self._property_exists(value))
			return result;

		// create property container
		self._create_property(value);

		// add property to the list
		try {
			var properties = JSON.parse(self._component.val());
			properties.push(value);
			self._component.val(JSON.stringify(properties));

		} catch (e) {
			// ignore parse errors
			var properties = new Array();
			properties.push(value);
			self._component.val(JSON.stringify(properties));
		}
	};

	/**
	 * Remove specified property from the list.
	 *
	 * @param mixed property
	 * @return boolean
	 */
	self.remove_property = function(value) {
		var result = false;

		// remove property
		if (self._property_exists(value)) {
			result = properties.splice(index, 1).length == 1;
			if (result)
				self._component.val(JSON.stringify(properties));
		}

		return result;
	};

	/**
	 * Check if property exists.
	 *
	 * @param string value
	 * @return boolean
	 */
	self._property_exists = function(value) {
		try {
			// parse properties
			var properties = JSON.parse(self._component.val());
		} catch (e) {
			// ignore decoding error
			var properties = new Array();
		}

		return properties.indexOf(value) > -1;
	};

	/**
	 * Create property for specified value.
	 *
	 * @param string value
	 */
	self._create_property = function(value) {
		// create and add property to container
		var property = $('<span>');
		var remove = $('<a>');

		property
			.html(value)
			.append(remove)
			.data('value', value)
			.addClass('property')
			.insertBefore(self._input);

		remove
			.on('click', self._handlers.property_remove_click)
			.attr('href', 'javascript: void(0);');

		return property;
	};

	/**
	 * Handle clicking on container.
	 *
	 * @param object event
	 */
	self._handlers.container_click = function(event) {
		self._component.focus();
	};

	/**
	 * Handle removing specific property from the list.
	 *
	 * @param object event
	 */
	self._handlers.property_remove_click = function(event) {
		// prevent default tag behavior
		event.preventDefault();

		// remove property tag
		var property = $(this).closest('.property');
		if (self.remove_property(property.data('value')))
			property.remove();
	};

	/**
	 * Handle component value change.
	 *
	 * @param object event
	 */
	self._handlers.component_value_changed = function(event) {
		// remove old properties
		self._container.find('.property').remove();

		// get properties from changed value
		try {
			var properties = JSON.parse(self._component.val());
		} catch (e) {
			var properties = new Array();
		}

		// create new properties container
		for (var index in properties)
			self._create_property(properties[index]);
	};

	/**
	 * Handle pressing specific keys.
	 *
	 * @param object event
	 */
	self._handlers.input_key_press = function(event) {
		if (event.keyCode == 13) {
			// prevent default tag behavior
			event.preventDefault();
			event.stopPropagation();

			// add value to the list
			var value = self._input.val();
			self.add_property(value);

			self._input.val('');
		}
	};

	/**
	 * Handle gaining foucs on input field.
	 *
	 * @param object event
	 */
	self._handlers.input_focus_gained = function(event) {
		// make container look focused
		self._container.addClass('focused');
	};

	/**
	 * Handle loosing focus on input field.
	 *
	 * @param object event
	 */
	self._handlers.input_focus_lost = function(event) {
		// make container look normal
		self._container.removeClass('focused');
	};

	/**
	 * Handle clicking on input field.
	 *
	 * @param object event
	 */
	self._handlers.input_click = function(event) {
		// prevent container from catching click event
		event.stopPropagation();
	};

	// finalize object
	self._init();
}
