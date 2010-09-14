/**
 * Gallery Toolbar Extension
 *
 * Copyright (c) 2010. by MeanEYE.rcf
 * http://rcf-group.com
 *
 * This toolbar extension provides controls to be used in conjunction
 * with gallery module.
 *
 * Requires jQuery 1.4.2+
 */

function ToolbarExtension_Gallery() {

	// register extension to mail API
	toolbar_api.registerModule('gallery', this);

	/**
	 * Function used to add control on specified toolbar
	 *
	 * @param object $toolbar
	 * @param object $component
	 * @param string control
	 */
	this.addControl = function($toolbar, $component, control) {
		switch (control) {
			case 'article_image':
				this.control_ArticleImage($toolbar, $component);
				break;
		}
	}

	/**
	 * Create button for inserting image into article using markdown
	 *
	 * @param object $toolbar
	 * @param object $component
	 */
	this.control_ArticleImage = function($toolbar, $component) {
		var $button = $('<a>');
		var $image = $('<img>');

		// configure image
		$image
			.attr('src', this.getIconURL('image_add.png'))
			.attr('alt', '');

		// configure button
		$button
			.addClass('button')
			.attr('href', 'javascript: void(0);')
			.attr('title', language_handler.getText('gallery', 'toolbar_insert_image'))
			.append($image)
			.click(function() {
				var $dialog = window_system.showModalDialog();
				
				
			});

		$toolbar.append($button);
	}

	/**
	 * Form URL based on icon name
	 *
	 * @param string icon
	 * @return string
	 */
	this.getIconURL = function(icon) {
		var path = window.location.pathname.split('/');

		// remove index.php
		if (path[path.length-1] == 'index.php')
			delete path[path.length-1];

		// add icon path
		path.push('modules/gallery/images');
		path.push(icon);

		return window.location.protocol + '//' + window.location.host +	path.join('/')
	}
}

$(document).ready(function() {
	new ToolbarExtension_Gallery();
});
