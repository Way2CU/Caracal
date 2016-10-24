/**
 * Order Editor
 *
 * Authors: Mladen Mijatov
 */

var Caracal = Caracal || new Object();
Caracal.Backend = Caracal.Backend || new Object();


/**
 * Constructor function for order editor.
 *
 * @return object
 */
Caracal.Backend.OrderEditor = function() {
	var self = this;

	self.list = null;
	self.dragged_item = null;
	self.hovered_item = null;
	self.order_element = null;
	self.handlers = new Object();

	/**
	 * Complete object initialization.
	 */
	self._init = function() {
		var current_script = document.scripts[document.scripts.length - 1];
		var backend_window = current_script.parentNode;

		self.list = backend_window.querySelector('div.scrollable_list');
		self.order_element = backend_window.querySelector('input[name=order]');

		// find and configure items
		var items = self.list.querySelectorAll('div.list_item');
		for (var i=0, count=items.length; i<count; i++) {
			var item = items[i];
			item.draggable = true;
			item.classList.add('draggable');
			item.addEventListener('dragstart', self.handlers.drag_start);
			item.addEventListener('dragover', self.handlers.drag_over);
			item.addEventListener('dragleave', self.handlers.drag_leave);
			item.addEventListener('dragend', self.handlers.drag_end);
		}
	};

	/**
 	 * Handle user starting to drag element.
	 *
	 * @param object event
	 */
	self.handlers.drag_start = function(event) {
		event.dataTransfer.effectAllowed = 'move';
		self.dragged_item = event.target;
		self.dragged_item.classList.add('dragging');
	};

	/**
 	 * Handle hovering over different element while dragging.
	 *
	 * @param object event
	 */
	self.handlers.drag_over = function(event) {
		// make sure hovered item is a different one
		if (self.dragged_item === event.target)
			return;

		// store current item for moving later
		self.hovered_item = event.target;

		// update looks
		event.target.classList.add('drag-hover');
	};

	/**
 	 * Handle leaving element while dragging.
	 *
	 * @param object event
	 */
	self.handlers.drag_leave = function(event) {
		event.target.classList.remove('drag-hover');
	};

	/**
 	 * Handle end of the dragging.
	 *
	 * @param object event
	 */
	self.handlers.drag_end = function(event) {
		// remove special looks of the dragged item
		self.dragged_item.classList.remove('dragging');

		// swap two items
		var list_content = self.list.querySelector('div.list_content');
		list_content.insertBefore(self.dragged_item, self.hovered_item);

		// reset variables
		self.dragged_item = null;
		self.hovered_item = null;

		// update order field
		var order = new Array();
		var items = self.list.querySelectorAll('div.list_item');

		for (var i=0, count=items.length; i<count; i++)
			order.push(items[i].dataset.id)

		self.order_element.value = order.join(',');
	};

	// finalize object
	self._init();
}
