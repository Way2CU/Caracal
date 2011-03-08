/**
 * LightBox System
 *
 * Copyright (c) 2011. by MeanEYE.rcf
 * http://rcf-group.com
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
 * @param boolean show_title		optionally show image title
 * @param bollean show_description	optionally show image description
 */
function LightBox(selector, show_title, show_description) {
	var self = this;  // used internally for nested functions
	this._selector = selector;
	this._visible = false;
	this._image_to_show = null;  // used when image loads faster than animation

	// create objects we'll use later
	this._images = null;
	this._background = $('<div>');
	this._container = $('<div>');
	this._title_container = $('<div>');
	this._content = $('<div>');
	this._title = show_title ? $('<div>') : null;
	this._description = show_description ? $('<div>') : null;
	this._close_button = $('<a>');

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

		// configure image description
		if (this._description != null)
			this._description
				.addClass('description')
				.html("Well, @NPRussell will look into it. Thing is, VR isn't interested in something unstable... The people on the hero want a stable ROM to use on their primary phone, pretty much. Froyd 1.7.2 does that for most people. While I agree it would be really nice to put GB onto it, I wouldn't do it at expense of performance or functionality or stability. But it's something that is potentially on the cards... We just need to keep an eye on developments upstream etc...")
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
		// add image to container
		this._content.html(image);

		// set image visibility
		$(image).css('opacity', 0);

		// set title
		if (this._title != null)
			this._title.html($(image).data('title'));

		// set description
		if (this._description != null)
			this._description.html($(image).data('description'));

		// calculate animation params
		var vertical_space = this._container.height() - this._content.height();
		var end_params = {
						width: image.width,
						height: image.height
					};
		var end_position = {
						top: Math.round(($(window).height() - image.height - vertical_space) / 2),
						left: Math.round(($(window).width() - image.width) / 2),
					};

		// create animation chain for images
		var content_chain = new AnimationChain();
		var details_chain = new AnimationChain();

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

		// start animation
		chain.start();
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
	 * Handle thumbnail click
	 * @param object event
	 */
	this.onThumbnailClick = function(event) {
		// stop default action
		event.preventDefault();

		var url = $(this).attr('href');
		var title = $(this).attr('title');
		var description = $(this).find('span.description').html();

		// if title is empty, try to find it elsewere
		if (title == '') {
			var tmp = $(this).find('img').one();
			if (tmp.length > 0)
				title = tmp.attr('alt');
		}

		if (title == '') {
			var tmp = $(this).find('span.title').one();
			if (tmp.length > 0)
				title = tmp.html();
		}

		// show container
		self.showContainer();

		// load image
		self.loadImage(url, title, description);
	};

	/**
	 * Handle window resize
	 * @param object event
	 */
	this.onWindowResize = function(event) {
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
