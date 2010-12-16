/**
 * Gallery Slideshow System
 *
 * Copyright (c) 2010. by MeanEYE.rcf
 * http://rcf-group.com
 *
 * This system provides developers with extensive array of options to display
 * gallery images in eye-appealing and animated way. By specifying parameters in
 * NewsSystem constructor you can control additional behavior.
 *
 * Requires jQuery 1.4.2+
 */

function FadingImage(image, transition_time) {
	this.$image = $(image);
	this.transition_time = transition_time;

	/**
	 * Show image
	 */
	this.show = function() {
		this.$image
					.css({
						opacity: 0,
						display: 'block'
					})
					.animate({opacity: 1}, this.transition_time);
	};

	/**
	 * Hide image
	 */
	this.hide = function(next_slide) {
		next_slide.show();

		this.$image.animate(
						{opacity: 0},
						this.transition_time,
						function() {
							$(this).css('display', 'none');
						});
	};
}

function SlidingImage(image, transition_time) {
	this.$image = $(image);
	this.transition_time = transition_time;

	/**
	 * Show image
	 */
	this.show = function() {
	};

	/**
	 * Hide image
	 */
	this.hide = function(next_slide) {
	};
}

/**
 * This funtion will create a new image slideshow with specified
 * parameters. Optionally you can provide gallery group to be displayed
 * instead of whole gallery.
 *
 * @param string container_id
 * @param integer animation_type
 * @param integer display_time
 * @param integer transition_time
 * @param string gallery_group
 * @param boolean scale_images
 */
function Slideshow(container_id, animation_type, display_time, transition_time, gallery_group, scale_images) {
	var self = this;  // used internally for nested functions

	this.interval_id = null;
	this.display_time = display_time;
	this.transition_time = transition_time;
	this.image_list = [];
	this.active_item = 0;
	this.group_name = gallery_group;
	this.animation_type = animation_type;
	this.scale_images = scale_images;

	this.$container = $('#' + container_id);

	/**
	 * Initialize slideshow
	 */
	this.init = function() {
		// load images from server
		$.ajax({
			url: this.getURL(),
			type: 'GET',
			data: {
					section: 'gallery',
					action: 'json_image_list',
					group: this.group_name
				},
			dataType: 'json',
			context: this,
			success: this.loadImages
		});
	};

	/**
	 * Event called once data fwas retrieved from server
	 */
	this.loadImages = function(data) {
		// get image animation function
		switch (this.animation_type) {
			case 0:
				var Slide = FadingImage;
				break;

			case 1:
				var Slide = SlidingImage;
				break;

			default:
				var Slide = FadingImage;
		}

		this.$container.css({
					display: 'block',
					position: 'relative',
					overflow: 'hidden'
				});

		if (!data.error) {
			// no server error occured, load images
			var i = data.items.length;
			var language = language_handler.current_language;

			while (i--) {
				var item = data.items[i];
				var image = new Image();
				var container_width = this.$container.width();
				var container_height = this.$container.height();

				if (this.scale_images) image.src = '';  // ensure image is not cashed
				image.src = item.image;
				image.alt = item.title[language];

				$(image)
					.css({
						display: 'none',
						position: 'absolute',
						top: '0px',
						left: '0px'
					})
					.appendTo(this.$container);

				if (this.scale_images)
					$(image).load(function() {
						var rate = 1;
						var image_width = this.width;
						var image_height = this.height;

						if (image_width > image_height) {
							rate = container_width / image_width;
						} else {
							if (image_width < image_height) {
								rate = container_height / image_height;
							} else {
								// image is square, so we fit in smaller dimension
								var size = container_height > container_width ? container_width : container_height;
								rate = size / image_width;
							}
						}

						image_width = Math.round(image_width * rate);
						image_height = Math.round(image_height * rate);

						$(this).css({
							top: Math.round((container_height - image_height) / 2),
							left: Math.round((container_width - image_width) / 2),
							width: image_width,
							height: image_height
						});
					});

				// create new slide using image and push it to the list
				this.image_list.push(new Slide(image, this.transition_time));
			}

			// show first image
			if (this.image_list.length > 0)
				this.image_list[this.active_item].show();

			// start animation
			if (this.image_list.length > 1)
				setInterval(function() { self.changeActiveItem(); }, self.display_time);

		} else {
			// server side error occured, report back to user
			alert(data.error_message);
		}
	};

	// callback method used for switching news items
	this.changeActiveItem = function() {
		var next_item = this.active_item + 1;

		if (next_item > this.image_list.length - 1)
			next_item = 0;

		this.image_list[this.active_item].hide(this.image_list[next_item]);

		this.active_item = next_item;
	};

	/**
	 * Form URL based on current location
	 */
	this.getURL = function() {
		return window.location.protocol + '//' + window.location.host + window.location.pathname;
	};

	// initialize
	this.init();
}
