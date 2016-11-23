/**
 * Generic table options.
 *
 * This object will allow sorting and filtering through by various methods.
 * It is primarily intended for use in backend but its use outside of it is
 * also possible.
 *
 * Author: Mladen Mijatov
 */

var Caracal = Caracal || new Object();


Caracal.TableOptions = function(table, options) {
	var self = this;

	// structure variables
	self.table = table;
	self.body = null;
	self.head = null;
	self.column_titles = null;
	self.rows = null;

	// detailed options structure
	self.details_button = null;
	self.details_container = null;

	// sorting configuration
	self.sort_title = null;
	self.sort_column = 0;
	self.sort_ascending = true;

	// generic containers
	self.handlers = new Object();
	self.options = {
			sort: true,
			filter: true,
			detailed: true
		};

	/**
	 * Complete object initialization.
	 */
	self._init = function() {
	};

	/**
	 * Set specified DOM element as table heading. These will be
	 * used to add ordering functionality to the table. If not specified
	 * object will attempt to use default `th` or `thead td` tags.
	 *
	 * @param object head
	 */
	self.set_table_head = function(head) {
	};

	/**
	 * Set specified DOM element as table body to be used in sorting
	 * and filtering operations. If not specified object will attempt
	 * to find rows on its own by looking for `tr` and `tbody` tags.
	 *
	 * @param object body
	 */
	self.set_table_body = function(body) {
	};

	/**
	 * Set attribute name to use for table sorting. If not
	 * specified or attribute is not present sorting will be done
	 * based on cell data.
	 *
	 * @param string attribute
	 */
	self.set_data_attribute = function(attribute) {
	};

	/**
	 * Handle clicking on column title.
	 *
	 * @param object event
	 */
	self.handlers.column_click = function(event) {
	};

	/**
	 * Handle hovering on details button. This button will
	 * show detailed user interface allowing for greater control
	 * over displayed data.
	 *
	 * @param object event
	 */
	self.handlers.details_hover = function(event) {
	};

	// finalize object
	self._init();
}
