/**
 * Backend Toolbar JavaScript
 * Caracal Administration
 *
 * This script implements support for globally shared toolbar used in all rich text
 * editing entries in backend. It provides an easy way to implement new functionality
 * and features.
 *
 * Upon opening a window, system will try to integrate toolbar on all of the input
 * elements containing `data-toolbar` attribute whose value is comma separated
 * extension names.
 *
 * Example:
 *
 *	<textarea name="message" data-toolbar="videos,downloads"/>
 *
 * Extensions are simple constructor functions which receive a single parameter,
 * instance of `Caracal.Toolbar.Toolbar`. This instance contains reference to the
 * component (`element`) against which toolbar is implemented, list of other
 * `extensions`, parent `target_window` of the component and other data.
 *
 * Copyright (c) 2018. by Way2CU, http://way2cu.com
 * Authors: Mladen Mijatov
 */

var Caracal = Caracal || new Object();
Caracal.Toolbar = Caracal.Toolbar || new Object();
Caracal.Toolbar.extensions = new Object();
Caracal.Toolbar.priority = ['markdown'];


/**
 * Register extension constructor under specified name.
 *
 * @param string name
 * @param callable constructor
 */
Caracal.Toolbar.register_extension = function(name, constructor) {
	if (name in Caracal.Toolbar.extensions && window.console) {
		console.log('Extension "' + name + '" already exists in the system!');
		return;
	}

	// store for later use
	Caracal.Toolbar.extensions[name] = constructor;
};

/**
 * Implement toolbar on all elements of specified Caracal backend window.
 *
 * @param object target_window
 */
Caracal.Toolbar.implement = function(target_window) {
	var elements = target_window.ui.container.querySelectorAll('textarea');

	for (var index=0, element_count=elements.length; index<element_count; index++) {
		var element = elements[index];

		// check if element is requesting toolbar
		if (!element.hasAttribute('data-toolbar'))
			continue;

		// get list of extensions to integrate
		var list_to_load = new Array();

		if (element.dataset.toolbar == 'all') {
			list_to_load = Object.keys(Caracal.Toolbar.extensions);

		} else {
			var list_requested = element.dataset.toolbar.split(',');

			for (var i=0, count=list_requested.length; i<count; i++) {
				var name = list_requested[i].trim();
				if (name in Caracal.Toolbar.extensions)
					list_to_load.push(name);
			}
		}

		// sort extension list to prioritize certain extensions
		var temp = Caracal.Toolbar.priority;

		for (var i=0, count=list_to_load.length; i<count; i++) {
			var name = list_to_load[i];

			if (temp.indexOf(name) == -1)
				temp.push(name); else
				continue;
		}

		list_to_load = temp;

		// create new toolbar with extensions
		var toolbar = new Caracal.Toolbar.Toolbar(target_window, element, list_to_load);
	}
};


/**
 * Toolbar class used as container for extensions and event handler
 * for user interface updates. For each element which requested toolbar
 * system will create one object from this constructor.
 */
Caracal.Toolbar.Toolbar = function(target_window, element, list_to_load) {
	var self = this;

	self.target_window = null;
	self.element = null;
	self.container = null;

	// namespaces
	self.handler = new Object();
	self.extensions = new Object();

	/**
	 * Complete object initialization.
	 */
	self._init = function() {
		self.target_window = target_window;

		// configure target element
		self.element = element;
		self.element.addEventListener('focus', self.handler.element_focus);
		self.element.addEventListener('blur', self.handler.element_blur);

		// create container and attach it
		self.container = document.createElement('div');
		self.container.classList.add('toolbar');
		self.element.parentNode.insertBefore(self.container, self.element);

		// create requested extensions
		for (var i=0, count=list_to_load.length; i<count; i++) {
			var name = list_to_load[i];
			var Extension = Caracal.Toolbar.extensions[name];
			self.extensions[name] = new Extension(self);
		}
	};

	/**
	 * Handle target element gaining focus.
	 *
	 * @param object event
	 */
	self.handler.element_focus = function(event) {
		self.container.classList.add('active');
	};

	/**
	 * Handle target element loosing focus.
	 *
	 * @param object event
	 */
	self.handler.element_blur = function(event) {
		self.container.classList.remove('active');
	};

	// finalize object
	self._init();
}
