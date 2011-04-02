/**
 * News Display System
 *
 * Copyright (c) 2010. by MeanEYE.rcf
 * http://rcf-group.com
 *
 * This system provides developers with extensive array of options to display
 * news in eye-appealing and animated way. By specifying parameters in NewsSystem
 * constructor you can control additional behavior of news display system.
 *
 * Requires jQuery 1.4.2+
 */

function FadingNews($container, transition_time) {
	this.$container = $container;
	this.transition_time = transition_time;

	/**
	 * Show news container
	 */
	this.show = function() {
		this.$container
					.css({
						display: 'block',
						opacity: 0
					})
					.animate({opacity: 1}, this.transition_time);
	}

	/**
	 * Hide news container
	 */
	this.hide = function(next_news) {
		next_news.show();

		this.$container.animate(
						{opacity: 0},
						this.transition_time,
						function() {
							$(this).css('display', 'none');
						});
	}
}

function ScrollingNews($container, transition_time) {
	this.$container = $container;
	this.transition_time = transition_time;

	/**
	 * Show news container
	 */
	this.show = function() {
	}

	/**
	 * Hide news container
	 */
	this.hide = function(next_news) {
	}
}

/**
 * News System constructor function
 *
 * @param string container_id		id of element containing news items
 * @param integer animation_type	how new items are animated
 * @param integer display_time		time news item is displayed
 * @param integer transition_time	time required for transition between news
 */
function NewsSystem(container_id, animation_type, display_time, transition_time) {
	var self = this;  // internally used

	this.interval_id = null;
	this.display_time = display_time;
	this.transition_time = transition_time;
	this.news_list = [];
	this.active_item = 0;

	this.$container = $('#'+container_id);

	// get constructor method name
	switch (animation_type) {
		case 0:
			var News = FadingNews;
			break;

		case 1:
			var News = ScrollingNews;
			break;

		default:
			var News = FadingNews
	};

	// create news objects
	this.$container.find('.news').each(function() {
		$(this).addClass('animated');
		self.news_list.push(new News($(this), self.transition_time));
	});

	// callback method used for switching news items
	this.changeActiveItem = function() {
		var next_item = this.active_item + 1;

		if (next_item > this.news_list.length - 1)
			next_item = 0;

		this.news_list[this.active_item].hide(this.news_list[next_item]);

		this.active_item = next_item;
	};

	// stop animation interval
	this.stopNews = function() {
		if (this.interval_id != null)
			clearInterval(this.interval_id);
	};

	// show first news
	if (this.news_list.length > 0)
		this.news_list[this.active_item].show();

	// activate interval
	if (this.news_list.length > 1)
		setInterval(function() { self.changeActiveItem(); }, self.display_time);
}
