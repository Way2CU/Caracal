/**
 * Gallery Slideshow System
 *
 * Copyright (c) 2010. by Way2CU
 * Author: Mladen Mijatov
 *
 * This system provides developers with extensive array of options to display
 * gallery images in eye-appealing and animated way. By specifying parameters in
 * NewsSystem constructor you can control additional behavior.
 *
 * Requires jQuery 1.4.2+
 */

function FadingImage(image, transition_time) {
	this.image = $(image);
	this.transition_time = transition_time;

	/**
	 * Show image
	 */
	this.show = function() {
		this.image
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

		this.image.animate(
					{opacity: 0},
					this.transition_time,
					function() {
						$(this).css('display', 'none');
					});
	};
}

function SlidingImage(image, transition_time) {
	this.image = $(image);
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
	var self = this;

	self.interval_id = null;
	self.display_time = display_time;
	self.transition_time = transition_time;
	self.image_list = [];
	self.active_item = 0;
	self.group_name = gallery_group;
	self.animation_type = animation_type;
	self.scale_images = scale_images;
	self.container = $('#' + container_id);

	// commonly used backend URL
	var base = $('base');
	self.backend_url = result = base.attr('href') + '/index.php'; 

	/**
	 * Initialize slideshow
	 */
	self.init = function() {
		var data = {
				section: 'gallery',
				action: 'json_image_list',
				slideshow: 1
			};

		if (self.group_name != undefined && self.group_name != null)
			data['group'] = self.group_name;

		// load images from server
		$.ajax({
			url: self.backend_url,
			type: 'GET',
			data: data,
			dataType: 'json',
			context: self,
			success: self.loadImages
		});
	};

	/**
	 * Event called once data fwas retrieved from server
	 */
	self.loadImages = function(data) {
		// get image animation function
		switch (self.animation_type) {
			case 0:
				var Slide = FadingImage;
				break;

			case 1:
				var Slide = SlidingImage;
				break;

			default:
				var Slide = FadingImage;
		}

		self.container.css({
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
				var container_width = self.container.width();
				var container_height = self.container.height();

				if (self.scale_images) image.src = '';  // ensure image is not cashed
				image.src = item.image;
				image.alt = item.title[language];

				$(image)
					.css({
						display: 'none',
						position: 'absolute',
						top: '0px',
						left: '0px'
					})
					.appendTo(self.container);

				if (self.scale_images)
					$(image).load(function() {
						var rate = 1;
						var image_width = self.width;
						var image_height = self.height;

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

						$(self).css({
							top: Math.round((container_height - image_height) / 2),
							left: Math.round((container_width - image_width) / 2),
							width: image_width,
							height: image_height
						});
					});

				// create new slide using image and push it to the list
				self.image_list.push(new Slide(image, self.transition_time));
			}

			// show first image
			if (self.image_list.length > 0)
				self.image_list[self.active_item].show();

			// start animation
			if (self.image_list.length > 1)
				setInterval(function() { self.changeActiveItem(); }, self.display_time);

		} else {
			// server side error occured, report back to user
			if (console)
				console.log(data.error_message);
		}
	};

	// callback method used for switching news items
	self.changeActiveItem = function() {
		var next_item = self.active_item + 1;

		if (next_item > self.image_list.length - 1)
			next_item = 0;

		self.image_list[self.active_item].hide(self.image_list[next_item]);

		self.active_item = next_item;
	};

	// initialize
	self.init();
}
