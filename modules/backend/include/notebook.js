/**
 * Notebook Control
 *
 * This control is used to present complex forms into simpler and
 * easier to use interface.
 */

var Caracal = Caracal || new Object();
Caracal.WindowSystem = Caracal.WindowSystem || new Object();


Caracal.WindowSystem.Notebook = function(window) {
	var self = this;

	self.current = null;

	// container namespaces
	self.ui = new Object();
	self.handler = new Object();

	/**
	 * Complete object initialization
	 */
	self._init = function() {
		self.ui.window = window;

		// container for controls
		self.ui.controls = document.createElement('div');
		self.ui.controls.classList.add('notebook-controls');
		self.ui.window.ui.menu.append(self.ui.controls);

		// page container
		self.ui.container = self.ui.window.ui.content.querySelector('div.notebook');
		self.ui.pages = self.ui.container.querySelectorAll('div.page');

		// create controls for each of the pages
		self.ui.control_list = new Array();
		for (var i=0, count=self.ui.pages.length; i<count; i++) {
			var page = self.ui.pages[i];
			var control = document.createElement('a');

			if ('title' in page.dataset)
				control.text = page.dataset.title; else
				control.text = 'Page #' + i.toString();

			control.dataset.index = i;  // for addressing page later
			control.addEventListener('click', self.handler.control_click);
			self.ui.controls.append(control);
			self.ui.control_list.push(control);
		}

		// show first page
		self.set_active_page(0);
	};

	/**
	 * Handle clicking on page control.
	 *
	 * @param object event
	 */
	self.handler.control_click = function(event) {
		var control = event.target;
		self.set_active_page(control.dataset.index);
		event.preventDefault();
	};

	/**
	 * Set page with specified index as active.
	 *
	 * @param integer index
	 */
	self.set_active_page = function(index) {
		if (self.current == index)
			return;

		for (var i=0, count=self.ui.control_list.length; i<count; i++) {
			var control = self.ui.control_list[i];

			if (control.dataset.index == index) {
				control.classList.add('active');
				self.ui.pages[control.dataset.index].classList.add('active');
				self.current = index;

			} else {
				control.classList.remove('active');
				self.ui.pages[control.dataset.index].classList.remove('active');
			}
		}
	};

	/**
	 * Remove elements from the DOM and prepare object for deletion.
	 */
	self.cleanup = function() {
		self.ui.controls.remove();
	};

	// finalize object
	this._init();
}
