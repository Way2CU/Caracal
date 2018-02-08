/**
 * Window Management System
 * Generic Dialog Class
 *
 * This class is used for displaying different choices in a modal dialog which
 * prevents users from interacting with the rest of the system while it's visible.
 */

var Caracal = Caracal || new Object();
Caracal.WindowSystem = Caracal.WindowSystem || new Object();


/**
 * Dialog Constructor
 */
Caracal.WindowSystem.Dialog = function() {
	var self = this;

	// container namespaces
	self.ui = new Object();
	self.handler = new Object();

	/**
	 * Finish object initialization
	 */
	self._init = function() {
		// create container element for dialog
		self.ui.background = document.createElement('div');
		self.ui.background.classList.add('dialog');

		self.ui.container = document.createElement('div');
		self.ui.container.classList.add('container');
		self.ui.background.append(self.ui.container);

		// create title bar and its namespace
		self.ui.title = new Object();
		self.ui.title.container = document.createElement('div');
		self.ui.title.container.classList.add('title');
		self.ui.container.append(self.ui.title.container);

		self.ui.title.icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
		self.ui.title.container.append(self.ui.title.icon);

		self.ui.title.content = document.createElement('span');
		self.ui.title.container.append(self.ui.title.content);

		self.ui.title.close_button = document.createElement('a');
		self.ui.title.container.append(self.ui.title.close_button);

		with (self.ui.title.close_button) {
			innerHTML = '<svg><use xlink:href="#icon-close"/></svg>';
			classList.add('button');
			classList.add('close');
			addEventListener('click', self.close)
		}

		// create content containter
		self.ui.content = document.createElement('div');
		self.ui.content.classList.add('content');
		self.ui.container.append(self.ui.content);

		// add dialog to the document
		document.querySelector('body').append(self.ui.background);
	};

	/**
	 * Log deprecation warning for function.
	 *
	 * @param string name
	 * @param string target
	 */
	self.__make_deprecated = function(name, target) {
		self[name] = function() {
			if (console)
				console.log(
					'Calling `' + name + '` is deprecated! ' +
					'Please use `' + target + '`.'
				);

			var params = Array.prototype.slice.call(arguments);
			var callable = self[target];
			return callable.apply(self, params);
		};
	};

	/**
	 * Open dialog.
	 */
	self.open = function() {
		self.ui.background.classList.add('visible');
	};

	self.__make_deprecated('show', 'open');

	/**
	 * Close dialog.
	 */
	self.close = function() {
		self.ui.background.classList.remove('visible');
	};

	self.__make_deprecated('hide', 'close');

	/**
	 * Destroy dialog and all the components.
	 */
	self.destroy = function() {
		self.ui.background.parentNode.removeChild(self.ui.background);
	};

	/**
	 * Set dialog content and make proper animation
	 *
	 * @param mixed content
	 * @param integer width
	 * @param integer height
	 */
	self.set_content = function(content) {
		if (typeof content == 'string') {
			self.ui.content.innerHTML = content;

		} else {
			while (self.ui.content.firstChild)
				self.ui.content.removeChild(self.ui.content.firstChild);
			self.ui.content.append(content);
		}
	};

	self.__make_deprecated('setContent', 'set_content');

	/**
	 * Set provided SVG content as icon for the dialog.
	 *
	 * @param string icon
	 */
	self.set_icon = function(icon) {
		self.ui.title.icon.outerHTML = icon;
	};

	/**
	 * Set dialog title
	 *
	 * @param string title
	 */
	self.set_title = function(title) {
		self.ui.title.content.innerHTML = title;
	};

	self.__make_deprecated('setTitle', 'set_title');

	/**
	 * Set dialog in loading state
	 */
	self.set_loading = function(in_progress) {
		if (in_progress)
			self.ui.background.classList.add('loading'); else
			self.ui.background.classList.remove('loading');
	};

	self.__make_deprecated('setLoadingState', 'set_loading');

	/**
	 * Set dialog in normal state
	 */
	self.setNormalState = function() {
		self.ui.container.removeClass('loading');
	};

	self.__make_deprecated('setNormalState', 'set_loading');

	// finish object initialization
	self._init();
};

