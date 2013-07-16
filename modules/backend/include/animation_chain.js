/**
 * jQuery Animation Chain
 *
 * Version: 1.0
 *
 * Copyright (c) 2010. by Mladen Mijatov
 * http://way2cu.com
 *
 * This object provides you with ability to make complex chained
 * animations using jQuery.
 *
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.*
 *
 */

/**
 * Constructor function
 *
 * @param function callback		Optional user defined callback.
 * @param boolean async			Asynchronous chain starts all animation at the same time. Default false
 * @param integer repeat		Number of times to repeat animation. Default 0
 * @return AnimationChain
 */
function AnimationChain(callback, async, repeat) {
	var self = this;  // used internally for nested functions

	this.callback = callback;
	this.current_step = -1;
	this.playing = false;
	this.can_continue = true;
	this.async = async;
	this.repeat = (repeat != undefined) ? repeat : 1;
	this.current_cycle = this.repeat;

	this.objects = [];
	this.params = [];
	this.durations = [];
	this.delays = [];

	/**
	 * Internally used function to animate next object in chain
	 */
	this.animateNext = function() {
		// in case animation is interupted return
		if (!self.can_continue) return;

		var step = ++self.current_step;

		// exit animation loop and execute callback if set
		if (step > self.objects.length - 1) {
			// modify variables
			self.current_step = 0;
			self.current_cycle--;

			// reset current index
			step = 0;

			// exit only if target number of cycles is reached
			if (self.current_cycle == 0) {
				self.current_cycle = self.repeat;
				if (self.callback != undefined || self.callback != null) self.callback();
				return;
			}
		}

		// delay animation if specified
		if (self.delays[step] > 0)
			self.objects[step].delay(self.delays[step]);

		// queue animation
		self.objects[step].animate(
							self.params[step],
							self.durations[step],
							self.animateNext
						);
	};

	/**
	 * Internally used function to animate all elements in chain at once
	 * @note: this function does *NOT* trigger callback!
	 */
	this.animateAll = function() {
		var objects = this.objects;
		var params = this.params;
		var durations = this.durations;
		var delays = this.delays;

		var len = objects.length;

		for (var i=0; i<len; i++) {
			var delay = delays[i];
			var duration = durations[i];
			var object = objects[i];

			if (delay > 0)  // delay if needed
				object.delay(delay);

			if ((self.callback != undefined || self.callback != null) && i == len-1)
				object.animate(params[i], duration, self.callback); else
				object.animate(params[i], duration);
		}

		this.playing = false;
	};

	/**
	 * Adds item to animation chain.
	 *
	 * @param resource object	jQuery object being animated
	 * @param map parameters 	Map of CSS properties that the animation will move toward
	 * @param integer duration	Number of milliseconds animation will run
	 * @param integer delay		Number of milliseconds animation will be delayed [optional]
	 * @return AnimationChain
	 */
	this.addAnimation = function(object, parameters, duration, delay) {
		this.objects.push(object);
		this.params.push(parameters);
		this.durations.push(duration);
		this.delays.push(delay == undefined ? 0 : delay);

		return this;  // enable chaining
	};

	/**
	 * Start animation chain
	 */
	this.start = function() {
		this.playing = true;
		this.can_continue = true;

		if (!this.async)
			this.animateNext(); else
			this.animateAll();

		return this;
	};

	/**
	 * Stop animation chain
	 *
	 * @param boolean wait		Should we wait for last animation to stop. Defaults to false
	 */
	this.stop = function(wait) {
		if (wait == undefined || !wait) {
			// don't wait for animation to finish
			this.objects[this.current_step].stop(true, true);

		} else {
			// wait for current animation to finish
			this.can_continue = false;
		}

		this.playing = false;

		return this;
	};

	/**
	 * Reset chain state
	 */
	this.reset = function() {
		if (this.playing)
			this.stop();

		this.current_step = -1;

		return this;
	};

	/**
	 * Set animation callback
	 */
	this.callback = function(method) {
		this.callback = method;
		return this;
	};

	/**
	 * Reverse order of animations
	 */
	this.reverse = function() {
		this.objects.reverse();
		this.params.reverse();
		this.durations.reverse();
		this.delays.reverse();
	};
}
