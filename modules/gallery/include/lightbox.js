/**
 * LightBox System
 *
 * Copyright (c) 2013. by Way2CU
 * Author: Mladen Mijatov
 *
 * This system provides developers with simple picture zoom
 * optionally displayed with title and description.
 *
 * Example:
 * <a href="link/to/big/image.jpg" title="Image title">
 *    <img src="link/to/thumbnal.jpg" alt="Image title">
 *    <span class="title">Image title</span>
 *    <span class="description">Image description</span>
 * </a>
 *
 * Please note that only *ONE* method of including image title is
 * needed but you can use them all.
 *
 * Requires: jQuery 1.4.2+, Animation Chain 0.1+
 */


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
		if (this._images.length == 0) {
			if (window.console)	console.log('LightBox: No images found!', this._selector);
			return;
		}

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
