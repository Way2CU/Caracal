/**
 * Magnifier for Gallery
 *
 * Copyright (c) 2024. by Way2CU
 * Author: Mladen Mijatov
 *
 * Simple class which allows zooming in on particular parts of the image.
 * It takes selector string as construction parameter. Tag being selected is
 * expected to have `data-full-image` attribute containing URL to zoomed image.
 */

// create namespace
var Caracal = Caracal || new Object();
Caracal.Gallery = Caracal.Gallery || new Object();


Caracal.Gallery.Magnifier = function(container) {
	var self = this;

	self.ui = new Object();
	self.handler = new Object();
	self.box = [100, 100];
	self.image = [0, 0];
	self.scale_w = 1;
	self.scale_h = 1;
	self.is_circle = false;

	self._init = function() {
		if (typeof container == 'string')
			self.ui.container = document.querySelector(container); else
		if (container instanceof Element)
			self.ui.container = container; else
			self.ui.container = null;

		if (!self.ui.container) {
			console.warn('Missing container for gallery magnifier.');
			return;
		}

		self.ui.container.addEventListener('mouseenter', self.handler.mouse_enter);
		self.ui.container.addEventListener('mouseleave', self.handler.mouse_leave);
		self.ui.container.addEventListener('mousemove', self.handler.mouse_move);
		self.ui.container.style.cursor = 'zoom-in';

		self.ui.viewport = document.createElement('div');
		self.ui.viewport.classList.add('gallery-magnifier-viewport');
		self.set_viewport_size();

		self.ui.full_image = document.createElement('img');
		self.ui.full_image.addEventListener('load', self.handler.image_load);
		self.ui.full_image.src = self.ui.container.dataset['fullImage'];
		self.ui.viewport.append(self.ui.full_image);

		document.querySelector('body').append(self.ui.viewport);
	};

	/**
	 * Update position of viewport and its zoomed image. Full size
	 * image is offset by half of the viewport's size to make currently
	 * hovered pixels look centered.
	 *
	 * @param int x
	 * @param int y
	 * @param int vx
	 * @param int vy
	 */
	self._update_viewport_position = function(x, y, vx, vy) {
		let offset = self.is_circle ? 0 : 20;
		self.ui.viewport.style.left = (x + offset).toString() + 'px';
		self.ui.viewport.style.top = (y + offset).toString() + 'px';
		self.ui.full_image.style.left = ((self.box[0] / 2) - vx).toString() + 'px';
		self.ui.full_image.style.top = ((self.box[1] / 2) - vy).toString() + 'px';
	};

	/**
	 * Handle full image load.
	 *
	 * @param object event
	 */
	self.handler.image_load = function(event) {
		self.image = [
			self.ui.full_image.offsetWidth,
			self.ui.full_image.offsetHeight,
			];

		self.scale_w = self.image[0] / self.ui.container.offsetWidth;
		self.scale_h = self.image[1] / self.ui.container.offsetHeight;
	};

	/**
	 * Handle mouse entering container. At this point we show
	 * viewport with original image scrolled.
	 *
	 * @param object event
	 */
	self.handler.mouse_enter = function(event) {
		self.ui.viewport.classList.add('visible');
		event.stopPropagation();
		event.preventDefault();
	};

	/**
	 * Handle mouse leaving container. At this point we hide
	 * viewport with original image scrolled.
	 *
	 * @param object event
	 */
	self.handler.mouse_leave = function(event) {
		self.ui.viewport.classList.remove('visible');
		event.stopPropagation();
		event.preventDefault();
	};

	/**
	 * Handle mouse moving within container. We just continually
	 * update viewport's position and original image inside.
	 * @param object event
	 */
	self.handler.mouse_move = function(event) {
		let mx = event.clientX;
		let my = event.clientY;
		let vx = event.offsetX * self.scale_w;
		let vy = event.offsetY * self.scale_h;

		self._update_viewport_position(mx, my, vx, vy);

		event.stopPropagation();
		event.preventDefault();
	};

	/**
	 * Set size of viewport.
	 *
	 * @param int width
	 * @param int height
	 */
	self.set_viewport_size = function(width, height) {
		self.box = [
			width | self.box[0],
			height | self.box[1]
			];

		self.ui.viewport.style.width = self.box[0].toString() + 'px';
		self.ui.viewport.style.height = self.box[1].toString() + 'px';
	};

	/**
	 * Set shape of viewport.
	 *
	 * @param boolean circular
	 */
	self.set_circular_viewport = function(circular) {
		if (circular)
			self.ui.viewport.classList.add('circle'); else
			self.ui.viewport.classList.remove('circle');

		self.is_circle = circular;
	};

	// finalize object
	self._init();
}
