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

	self.is_open = false;
	self.show_title = true;
	self.show_controls = true;
	self.show_thumbnails = false;
	self.show_description = false;

	// local containers
	self.images = new Array();
	self.handlers = new Object();
	self.ui = new Object();

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
		with (self.ui.thumbnail_container) {
			classList.add('thumbnails');

			if (!self.show_thumbnails)
				classList.add('hidden');
		}

		// create main container
		self.ui.container = document.createElement('section');
		with (self.ui.container) {
			classList.add('lightbox');
			appendChild(self.ui.image_container);
			appendChild(self.ui.thumbnail_container);
		}

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
			self.ui.thumbnail_container.classList.remove('hidden'); else
			self.ui.thumbnail_container.classList.add('hidden');

		return self;
	};

	/**
	 * Switch to next image.
	 */
	self.next = function() {
	};

	/**
	 * Switch to previous image.
	 */
	self.previous = function() {
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
	self.images.add = function(params) {
		return self;
	};

	/**
	 * Clear images from list of handled by the lightbox.
	 *
	 * @return object
	 */
	self.images.clear = function() {
		return self;
	};

	/**
	 * Handle clicking on next control.
	 *
	 * @param object event
	 */
	self.handlers.next_click = function(event) {
		event.preventDefault();
		self.next();
	};

	/**
	 * Handle clicking on previous control.
	 *
	 * @param object event
	 */
	self.handlers.previous_click = function(event) {
		event.preventDefault();
		self.previous();
	};

	/**
	 * Handle clicking on close button.
	 *
	 * @param object event
	 */
	self.handlers.close_click = function(event) {
	};

	/**
	 * Handle clicking on background shade.
	 *
	 * @param object event
	 */
	self.handlers.background_click = function(event) {
	};

	/**
	 * Handle clicking on image thumbnail.
	 *
	 * @param object event
	 */
	self.handlers.thumbnail_click = function(event) {
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
		switch (key) {
			// escape
			case 27:
				self.close();
				break;

			// left arrow
			case 37:
				// don't allow keyboard switching
				if (!self.show_controls)
					break;

				// take into account page direction
				if (!self.is_rtl)
					self.previous(); else
					self.next();
				break;

			// right arrow
			case 39:
				// don't allow keyboard switching
				if (!self.show_controls)
					break;

				// take into account page direction
				if (!self.is_rtl)
					self.next(); else
					self.previous();
				break;
		}

		// prevent others from handling
		event.preventDefault();
		event.stopImmediatePropagation();
	};

	// finalize object
	self._init();
}

/**
 * LightBox constructor function
 *
 * @param string selector			jQuery selector for links containing images
 * @param boolean show_title		show image title
 * @param bollean show_description	show image description
 * @param boolean show_controls		show previous/next controls
 */
function LightBox(selector, show_title, show_description, show_controls) {
	var self = this;  // used internally for nested functions
	this._selector = selector;
	this._visible = false;
	this._image_to_show = null;  // used when image loads faster than animation
	this._current_index = null;  // represents index of currently displaying image

	// original image sizes
	this._original_image_width = null;
	this._original_image_height = null;

	// create objects we'll use later
	this._images = null;
	this._background = $('<div>');
	this._container = $('<div>');
	this._title_container = $('<div>');
	this._content = $('<div>');
	this._title = show_title ? $('<div>') : null;
	this._description = show_description ? $('<div>') : null;
	this._close_button = $('<a>');
	this._next_button = show_controls ? $('<a>') : null;
	this._previous_button = show_controls ? $('<a>') : null;

	/**
	 * Initialize object
	 */
	this.init = function() {
		// get all the image links
		this._images = $(this._selector);

		// make sure we have at least one image
		if (this._images.length == 0)
			return;

		// get element that will contain all the lightbox stuff
		var container = $('body');

		// configure background shade
		this._background
				.attr('id', 'lightbox-background')
				.bind('click', this.hideImage)
				.appendTo(container);

		// configure container
		this._container
				.attr('id', 'lightbox')
				.appendTo(container);

		// configure title bar
		this._title_container
				.addClass('title_bar')
				.appendTo(this._container);

		if (this._title != null)
			this._title
				.addClass('title')
				.html('Quick brown fox jumps over the lazy dog.')
				.appendTo(this._title_container);

		this._close_button
				.addClass('close_button')
				.bind('click', this.hideImage)
				.appendTo(this._title_container);

		// configure content
		this._content
				.addClass('content')
				.appendTo(this._container);

		// configure controls
		if (this._next_button != null) {
			this._next_button
					.addClass('button')
					.addClass('next')
					.appendTo(this._container)
					.click(function() {
							// we need delayed reaction to avoid racing
							// condition with language handler
							if (!language_handler.isRTL())
								self.nextImage(); else
								self.previousImage();
						});

			this._previous_button
					.addClass('button')
					.addClass('previous')
					.appendTo(this._container)
					.click(function() {
							// we need delayed reaction to avoid racing
							// condition with language handler
							if (!language_handler.isRTL())
								self.previousImage(); else
								self.nextImage();
						});
		}

		// configure image description
		if (this._description != null)
			this._description
				.addClass('description')
				.appendTo(this._container);

		// attach events
		$(window).bind('resize', this.onWindowResize);
		this._images.bind('click', this.onThumbnailClick);
	};

	/**
	 * Load image from specified URL
	 *
	 * @param string url
	 * @param string title
	 * @param string description
	 */
	this.loadImage = function(url, title, description) {
		// hide existing image
		this._content.addClass('loading');

		// connect events and load image
		var image = $('<img>');

		// make sure we have title and description defined
		if (title == undefined) title = '';
		if (description == undefined) description = '';

		image
			.data('title', title)
			.data('description', description)
			.bind('load', this.onImageLoad)
			.attr('src', url);
	};

	/**
	 * Show specified image
	 * @param object image
	 */
	this.showImage = function(image) {
		var image_width = image.width;
		var image_height = image.height;
		var window_width = $(window).width();
		var window_height = $(window).height();

		// set original image sizes
		this._original_image_width = image_width;
		this._original_image_height = image_height;

		// set title
		if (this._title != null)
			this._title.html($(image).data('title'));

		// set description
		if (this._description != null)
			this._description.html($(image).data('description'));

		// calculate vertical space taken by title and description
		var vertical_space = this._container.height() - this._content.height();

		// resize image if needed
		var new_size = this.getImageSize(image_width, image_height, vertical_space);
		image_width = new_size[0];
		image_height = new_size[1];

		// add image to container
		this._content.html(image);

		// set image visibility
		$(image).css({
				opacity: 0,
				width: image_width,
				height: image_height
			});

		// calculate animation params
		var end_params = {
						width: image_width,
						height: image_height
					};
		var end_position = {
						top: Math.round((window_height - image_height - vertical_space) / 2),
						left: Math.round((window_width - image_width) / 2),
					};

		// create animation chain for images
		var content_chain = new AnimationChain();
		var details_chain = new AnimationChain();
		var control_chain = new AnimationChain(null, true);

		content_chain
				.addAnimation(
						this._content,
						end_params,
						500
					)
				.addAnimation(
						$(image),
						{opacity: 1},
						300
					)
				.callback(function() {
					// show hidden objects
					if (self._title != null) self._title.show();
					if (self._description != null) self._description.show();

					// resize elements
					self.adjustSize();

					// start animation
					details_chain.start();
					control_chain.start();
				});

		// add title to animation chain
		if (this._title != null) {
			// hide element to protect animation
			this._title.hide();

			// add object to chain
			details_chain.addAnimation(this._title, {opacity: 1}, 300);
		}

		// add description to animation chain
		if (this._description != null) {
			// hide element to protect animation
			this._description.hide();

			// add object to chain
			details_chain.addAnimation(this._description, {opacity: 1}, 200);
		}

		// add controls to animation chain
		if (this._next_button != null) {
			control_chain.addAnimation(this._next_button, {opacity: 1}, 200);
			control_chain.addAnimation(this._previous_button, {opacity: 1}, 200);
		}

		// animate containers
		this._container.animate(end_position, 500);
		content_chain.start();
	};

	/**
	 * Show container with loading animation in it
	 */
	this.showContainer = function() {
		// if container is already visible we have nothing to do
		if (this._visible) return;

		// create animation chain
		var chain = new AnimationChain();
		chain
			.addAnimation(
					this._background,
					{opacity: 0.8},
					300
				)
			.addAnimation(
					this._container,
					{opacity: 1},
					300
				)
			.callback(function() {
				self._visible = true;

				// check if we need to show image initially
				if (self._image_to_show != null) {
					self.showImage(self._image_to_show);
					self._image_to_show = null;
				}
			});

		// set initial values
		this._background.css({
						display: 'block',
						opacity: 0
					});

		this._container.css({
						display: 'block',
						opacity: 0
					});

		this._content.css({
						width: 45,
						height: 24
					});

		if (this._title != null)
			this._title.css('opacity', 0).html('');

		if (this._description != null)
			this._description.css('opacity', 0).html('');

		if (this._next_button != null) {
			this._next_button.css('opacity', 0);
			this._previous_button.css('opacity', 0);
		}

		this.adjustSize();
		this.adjustPosition();

		// start animation
		chain.start();
	};

	/**
	 * Hide image container
	 */
	this.hideImage = function() {
		// create animation chain
		var chain = new AnimationChain();
		chain
			.addAnimation(
					self._container,
					{opacity: 0},
					300
				)
			.addAnimation(
					self._background,
					{opacity: 0},
					300
				)
			.callback(function() {
				// when animation finishes, hide all containers
				self._background.css('display', 'none');
				self._container.css('display', 'none');

				// remove content
				self._content.html('');

				self._visible = false;
			});

		// clear current index
		self._current_index = null;

		// start animation
		chain.start();
	};

	/**
	 * Show next image in list
	 */
	this.nextImage = function() {
		// get next image in line
		var next_index = self._current_index + 1;

		// make sure we don't run out of bounds
		if (next_index > self._images.length -1)
			next_index = 0;

		// start loading image
		self.startLoading(next_index);
	};

	/**
	 * Show previouse image in list
	 */
	this.previousImage = function() {
		// get previous image in line
		var previous_index = self._current_index - 1;

		// make sure we don't run out of bounds
		if (previous_index < 0)
			previous_index = self._images.length - 1;

		// start loading image
		self.startLoading(previous_index);
	};

	/**
	 * Adjust container position based on its size
	 */
	this.adjustPosition = function() {
		var width = this._container.width();
		var window_width = $(window).width();
		var height = this._container.height();
		var window_height = $(window).height();

		// calculate container position
		var top = Math.round((window_height - height) / 2);
		var left = Math.round((window_width - width) / 2);

		// move container to specified position
		this._container.css({
						top: top,
						left: left
					});
	};

	/**
	 * Adjust container sizes
	 */
	this.adjustSize = function() {
		var width = this._content.width();

		if (this._title != null)
			this._title.css('width', width - this._close_button.width() - 5);

		if (this._description != null)
			this._description.css('width', width);
	};

	/**
	 * Calculate image size to fit the screen
	 *
	 * @param integer width
	 * @param integer height
	 * @param integer vertical_space
	 */
	this.getImageSize = function(width, height, vertical_space) {
		var image_width = width;
		var image_height = height;
		var window_width = $(window).width();
		var window_height = $(window).height();

		// maximum alowable image dimensions
		var max_width = window_width - 100;
		var max_height = window_height - vertical_space - 100;

		// check if image fits in current window
		if ((image_width > max_width) || (image_height > max_height)) {
			// calculate ratio
			var image_ratio = image_height / image_width;
			var container_ratio = max_height / max_width;

			// calculate scale
			var scale = 1;
			if (image_ratio < container_ratio)
				scale = max_width / image_width; else
				scale = max_height / image_height;

			// modify values
			image_width = image_width * scale;
			image_height = image_height * scale;
		}

		return [image_width, image_height];
	};

	/**
	 * Start loading image with specified index number
	 * @param integer index
	 */
	this.startLoading = function(index) {
		// store current index
		this._current_index = index;

		// get image
		var image = this._images.eq(index);

		// get image variables
		var url = image.attr('href');
		var title = image.attr('title');
		var description = image.find('span.description').html();

		// if title is empty, try to find it elsewere
		if (title == '' || title == undefined) {
			var tmp = image.find('img');
			if (tmp.length > 0)
				title = tmp.eq(0).attr('alt');
		}

		if (title == '' || title == undefined) {
			var tmp = image.find('span.title');
			if (tmp.length > 0)
				title = tmp.eq(0).html();
		}

		if (this._visible) {
			// container is visible, we need to hide details
			var chain = new AnimationChain();

			// hide image
			chain.addAnimation(this._content.find('img'), {opacity: 0}, 200);

			// add title to animation chain
			if (this._title != null)
				chain.addAnimation(this._title, {opacity: 0}, 200);

			// add description to animation chain
			if (this._description != null)
				chain.addAnimation(this._description, {opacity: 0}, 200);

			chain.callback(function() {
					// delayed load image
					self.loadImage(url, title, description);
				});

			chain.start();

		} else {
			// container is not visible
			this.showContainer();

			// load image
			this.loadImage(url, title, description);
		}
	};

	/**
	 * Handle thumbnail click
	 * @param object event
	 */
	this.onThumbnailClick = function(event) {
		// stop default action
		event.preventDefault();

		// store current image index
		index = self._images.index(this);

		// start loading image
		self.startLoading(index);
	};

	/**
	 * Handle window resize
	 * @param object event
	 */
	this.onWindowResize = function(event) {
		// calculate vertical space taken by title and description
		var vertical_space = self._container.height() - self._content.height();

		// calculate new size
		var new_size = self.getImageSize(
								self._original_image_width,
								self._original_image_height,
								vertical_space
							);

		// resize image and container
		self._content
				.css({
					width: new_size[0],
					height: new_size[1]
				})
				.children('img').css({
					width: new_size[0],
					height: new_size[1]
				});

		self.adjustSize();
		self.adjustPosition();
	};

	/**
	 * Handle loaded event for image
	 * @param object event
	 */
	this.onImageLoad = function(event) {
		// we need to do this due to IE being stupid
		var image = new Image();
		image.src = this.src;

		$(image).data($(this).data());

		// remove loading animation
		self._content.removeClass('loading');

		// show image
		if (self._visible)
			self.showImage(image); else
			self._image_to_show = image;
	};

	// initialize object
	this.init();
}
