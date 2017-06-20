/**
 * LightBox System
 *
 * Copyright (c) 2017. by Way2CU
 * Author: Mladen Mijatov
 *
 * This system provides developers with simple picture zoom
 * optionally displayed with title and description.
 */

// create namespace
var Caracal = Caracal || new Object();
Caracal.Gallery = Caracal.Gallery || new Object();


Caracal.Gallery.Lightbox = function() {
	var self = this;

	self.is_rtl = document.querySelector('html').getAttribute('dir') == 'rtl';
	self.is_open = false;
	self.show_title = true;
	self.show_controls = true;
	self.show_thumbnails = false;
	self.show_description = false;

	// local containers
	self.link_list = new Array();
	self.images = new Object();  // image functions
	self.handlers = new Object();  // event handlers
	self.ui = new Object();  // user interface elements
	self.current_index = null;

	/**
	 * Complete object initialization.
	 */
	self._init = function() {
		// create controls
		self.ui.previous_control = document.createElement('a');
		self.ui.next_control = document.createElement('a');
		self.ui.close_control = document.createElement('a');

		with (self.ui.previous_control) {
			classList.add('control', 'previous');
			addEventListener('click', self.handlers.previous_click);

			if (!self.show_controls)
				classList.add('hidden');
		}

		with (self.ui.next_control) {
			classList.add('control', 'next');
			addEventListener('click', self.handlers.next_click);

			if (!self.show_controls)
				classList.add('hidden');
		}

		with (self.ui.close_control) {
			classList.add('control', 'close');
			addEventListener('click', self.handlers.close_click);
		}

		// create image and its caption
		self.ui.title = document.createElement('h4');
		self.ui.description = document.createElement('div');

		if (!self.show_description)
			self.ui.description.classList.add('hidden');

		self.ui.caption_container = document.createElement('figcaption');
		with (self.ui.caption_container) {
			appendChild(self.ui.title);
			appendChild(self.ui.description);

			if (!self.show_title)
				classList.add('hidden');
		}

		// create figure container
		self.ui.image = document.createElement('figure');
		with (self.ui.image) {
			appendChild(self.ui.caption_container);
			appendChild(self.ui.close_control);
		}

		// create image container
		self.ui.image_container = document.createElement('div');
		with (self.ui.image_container) {
			classList.add('image');
			appendChild(self.ui.previous_control);
			appendChild(self.ui.image);
			appendChild(self.ui.next_control);
		}

		// create thumbnail container
		self.ui.thumbnail_container = document.createElement('div');
		self.ui.thumbnail_container.classList.add('thumbnails');

		// create main container
		self.ui.container = document.createElement('section');
		with (self.ui.container) {
			classList.add('lightbox');
			appendChild(self.ui.image_container);
			appendChild(self.ui.thumbnail_container);

			if (self.show_thumbnails)
				classList.add('with_thumbnails');
		}

		document.querySelector('body').appendChild(self.ui.container);

		// connect window events
		window.addEventListener('keypress', self.handlers.key_press);
	};

	/**
	 * Configure lightbox to show or hide image title. System will use `alt` attribute
	 * of the added image as default source for title. You can also provide `title` attribute
	 * to the link added or element with class `title` inside of the specified link.
	 *
	 * Example:
	 *	<a href="link/to/big/image.jpg" title="Image title">
	 *	   <img src="link/to/thumbnal.jpg" alt="Image title">
	 *	   <span class="title">Image title</span>
	 *	</a>
	 *
	 * @param boolean show_title
	 * @return object
	 */
	self.set_show_title = function(show_title) {
		self.show_title = show_title;

		// apply change
		if (self.show_title)
			self.ui.caption_container.classList.remove('hidden'); else
			self.ui.caption_container.classList.add('hidden');

		return self;
	};

	/**
	 * Configure lightbox to show or hide controls for next/previous image. Note that
	 * keyboard shortcuts will also not work when controls are hidden.
	 *
	 * @param boolean show_controls
	 * @return object
	 */
	self.set_show_controls = function(show_controls) {
		self.show_controls = show_controls;

		// apply change
		if (self.show_title) {
			self.ui.previous_control.classList.remove('hidden');
			self.ui.next_control.classList.remove('hidden');

		} else {
			self.ui.previous_control.classList.add('hidden');
			self.ui.next_control.classList.add('hidden');
		}

		return self;
	};

	/**
	 * Configure lightbox to show or hide image description. System will look for
	 * attribute `data-description` in added `img` to the list, or for element with
	 * class `description` and use its content.
	 *
	 * @param boolean show_description
	 * @return object
	 */
	self.set_show_description = function(show_description) {
		self.show_description = show_description;

		// apply change
		if (self.show_description)
			self.ui.description.classList.remove('hidden'); else
			self.ui.description.classList.add('hidden');

		return self;
	};

	/**
	 * Configure lightbox to show or hide thumbnails. System will use all the images
	 * added to the lightbox object to show thumbnails. No additiona network activity
	 * will be generated.
	 *
	 * @param boolean show_thumbnails
	 * @return object
	 */
	self.set_show_thumbnails = function(show_thumbnails) {
		self.show_thumbnails = show_thumbnails;

		// apply change
		if (self.show_thumbnails)
			self.ui.container.classList.add('with_thumbnails'); else
			self.ui.container.classList.remove('with_thumbnails');

		return self;
	};

	/**
	 * Open lightbox and set specified image as focused one. Please note that
	 * upon adding images to lightbox `click` event will be connected to local
	 * handler.
	 *
	 * @param object focused_image
	 */
	self.open = function(focused_image) {
		// show background and container
		self.ui.container.classList.add('visible');

		// toggle flag to enable keyboard controls
		self.is_open = true;
	};

	/**
	 * Close lightbox.
	 */
	self.close = function() {
		// show background and container
		self.ui.container.classList.remove('visible');

		// toggle flag to enable keyboard controls
		self.is_open = false;
	};

	/**
	 * Add specified images by selector or set of previously queried nodes to the
	 * image list handled by the lightbox. Pressing on next/previous button will cycle
	 * images in order they are added.
	 *
	 * @param mixed images
	 * @return object
	 */
	self.images.add = function(images) {
		var links = new Array();

		if (typeof images == 'string') {
			// query document with specified selector
			var list = document.querySelectorAll(images);
			for (var i=0, count=list.length; i<count; i++)
				if (list[i] instanceof HTMLAnchorElement)
					links.push(list[i]);

		} else if (images instanceof HTMLAnchorElement) {
			// add specified image to the list
			links.push(images);

		} else if (images instanceof NodeList) {
			// get images from the node list
			for (var i=0, count=list.length; i<count; i++)
				if (images[i] instanceof HTMLAnchorElement)
					links.push(images[i]);
		}

		// add links to the current list
		for (var i=0, count=links.length; i < count; i++) {
			var link = links[i];
			var image = link.querySelector('img');
			var title = null;

			// create new object with image data
			var data = {
					link: link,
					image: image,
					thumbnail: null,
					title: null,
					source: link.getAttribute('href'),
					thumbnail_source: image.getAttribute('src'),
					loaded: false
				};

			// get title from one of the multiple sources
			if (link.hasAttribute('title'))
				data.title = link.getAttribute('title'); else
			if (title = link.querySelector('span.title'))
				data.title = title.innerHTML; else
				data.title = image.getAttribute('alt');  // image always must have `alt`

			// create thumbnail
			data.thumbnail = new Image();
			data.thumbnail.setAttribute('src', data.thumbnail_source);
			data.thumbnail.setAttribute('alt', data.title);
			data.thumbnail.dataset.index = self.link_list.length;
			self.ui.thumbnail_container.appendChild(data.thumbnail);

			// add image object to list
			self.link_list.push(data);

			// connect events
			link.addEventListener('click', self.handlers.image_click);
			data.thumbnail.addEventListener('click', self.handlers.thumbnail_click);
		}

		// make sure index is set
		if (self.current_index == null)
			self.current_index = 0;

		return self;
	};

	/**
	 * Clear images from list of handled by the lightbox.
	 *
	 * @return object
	 */
	self.images.clear = function() {
		self.images.length = 0;
		return self;
	};

	/**
	 * Show image with specific index.
	 *
	 * @param integer index
	 */
	self.images.show = function(index) {
		// check if index is within bounds
		if (index < 0 && index >= self.link_list.length)
			return;

		// open lightbox if it's not visible
		if (!self.is_open)
			self.open();

		// remove active class from old thumbnail
		if (self.current_index != null)
			self.link_list[self.current_index].thumbnail.classList.remove('active');

		// store new index as current
		self.current_index = index;

		// get image information to display
		var data = self.link_list[index];

		// apply title and description
		if (data.title != undefined)
			self.ui.title.innerText = data.title; else
			self.ui.title.innerText = '';

		if (data.description != undefined)
			self.ui.description.innerHTML = data.description; else
			self.ui.description.innerHTML = '';

		// highlight thumbnail as selected
		data.thumbnail.classList.add('active');

		// load image from the server
		if (!data.loaded) {
			var temp = new Image();

			// create load handler
			temp.addEventListener('load', function(event) {
				data.loaded = true;
				self.ui.image.classList.remove('loading');
				self.ui.image.style.backgroundImage = 'url(' + data.source + ')';
			});

			// start loading image
			self.ui.image.classList.add('loading');
			temp.src = data.source;

		} else {
			// used cached image
			self.ui.image.style.backgroundImage = 'url(' + data.source + ')';
		}
	};

	/**
	 * Switch to next image.
	 */
	self.images.next = function() {
		var index = self.current_index;

		console.log(index);

		// calculate new index
		if (index + 1 < self.link_list.length)
			index++; else
			index = 0;

		// show image with specified index
		self.images.show(index);
	};

	/**
	 * Switch to previous image.
	 */
	self.images.previous = function() {
		var index = self.current_index;

		// calculate new index
		if (index - 1 >= 0)
			index--; else
			index = self.link_list.length - 1;

		// show image with specified index
		self.images.show(index);
	};

	/**
	 * Handle clicking on next control.
	 *
	 * @param object event
	 */
	self.handlers.next_click = function(event) {
		event.preventDefault();
		self.images.next();
	};

	/**
	 * Handle clicking on previous control.
	 *
	 * @param object event
	 */
	self.handlers.previous_click = function(event) {
		event.preventDefault();
		self.images.previous();
	};

	/**
	 * Handle clicking on close button.
	 *
	 * @param object event
	 */
	self.handlers.close_click = function(event) {
		event.preventDefault();
		self.close();
	};

	/**
	 * Handle clicking on background shade.
	 *
	 * @param object event
	 */
	self.handlers.background_click = function(event) {
		self.close();
	};

	/**
	 * Handle clicking on image thumbnail.
	 *
	 * @param object event
	 */
	self.handlers.thumbnail_click = function(event) {
		var index = Number(event.target.dataset.index);
		self.images.show(index);
	};

	/**
	 * Handle clicking on image.
	 *
	 * @param object event
	 */
	self.handlers.image_click = function(event) {
		// prevent default click handler
		event.preventDefault();

		// find image index
		var index = null;
		var clicked_image = event.target;

		for (var i=0, count=self.link_list.length; i<count; i++) {
			var data = self.link_list[i];

			if (data.image == clicked_image) {
				index = i;
				break;
			}
		}

		// show selected image
		if (index != null)
			self.images.show(index);
	};

	/**
	 * Handle keyboard shortcuts.
	 *
	 * @param object event
	 */
	self.handlers.key_press = function(event) {
		// skip handling when closed
		if (!self.is_open)
			return;

		// handle key press
		var key = event.which || event.keyCode;
		var handled = false;
		switch (key) {
			// escape
			case 27:
				self.close();

				handled = true;
				break;

			// left arrow
			case 37:
				// take into account page direction
				if (!self.is_rtl)
					self.images.previous(); else
					self.images.next();

				handled = true;
				break;

			// right arrow
			case 39:
				// take into account page direction
				if (!self.is_rtl)
					self.images.next(); else
					self.images.previous();

				handled = true;
				break;
		}

		// prevent others from handling
		if (handled) {
			event.preventDefault();
			event.stopImmediatePropagation();
		}
	};

	// finalize object
	self._init();
}
