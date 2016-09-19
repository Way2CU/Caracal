/**
 * Gallery Toolbar Extension
 *
 * Copyright (c) 2013. by Way2CU
 * Author: Mladen Mijatov
 *
 * This toolbar extension provides controls to be used in conjunction
 * with gallery module.
 *
 * Requires jQuery 1.4.2+
 */

function ToolbarExtension_Gallery() {
	var self = this;

	this.dialog = new Caracal.WindowSystem.Dialog();
	this.dialog.setTitle(language_handler.getText('gallery', 'title_insert_image'));

	// base url for this site
	this.backend_url = $('meta[property=base-url]').attr('content') + '/index.php';

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
	};

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
				self.dialog.show();
				self.dialog.setLoadingState();

				$.ajax({
					url: self.backend_url,
					type: 'GET',
					data: {
						section: 'gallery',
						action: 'json_image_list',
						thumbnail_size: 100,
						all_languages: 1
					},
					dataType: 'json',
					context: $component.get(0),
					success: self.loaded_ArticleImage
				});
			});

		$toolbar.append($button);
	};

	/**
	 * Process loaded image data
	 * @param json_object data
	 * @context $component
	 */
	this.loaded_ArticleImage = function(data) {
		var $component = $(this);
		var component_window = $component.closest('div.window');
		var language_selector = component_window.find('div.language_selector').data('selector');

		if (!data.error) {
			var $list = $('<div>');

			// no error, feed data into dialog
			var i = data.items.length;
			while (i--) {
				var image = data.items[i];

				// create elements
				var $item = $('<div>');
				var $container = $('<div>');
				var $image = $('<img>');
				var $label = $('<span>');

				// configure elements
				$container.addClass('image_holder');

				$image
					.attr('src', image.thumbnail)
					.attr('alt', image.title[language_selector.current_language]);

				$label
					.addClass('title')
					.html(image.title[language_selector.current_language]);

				$item
					.addClass('thumbnail')
					.data('image', image)
					.click(function() {
						var image = $(this).data('image');

						$component.insertAtCaret(
									'![' + image.title[language_selector.current_language] +
									'](' + image.id + ')'
								);

						self.dialog.hide();
					});


				// pack elements
				$container.append($image);

				$item.append($container);
				$item.append($label);

				$list.append($item);
			}

			// fix float
			$list.append($('<div>').css('clear', 'both'));

			self.dialog.setNormalState();
			self.dialog.setContent($list, 650, 500);
		} else {
			// report server-side error
			self.dialog.hide();
			alert(language_handler.getText(null, 'message_server_error') + ' ' + data.error_message);
		}
	};

	/**
	 * Form URL based on icon name
	 *
	 * @param string icon
	 * @return string
	 */
	this.getIconURL = function(icon) {
		var path = [];

		// add icon path
		path.push('modules/gallery/images');
		path.push(icon);

		// base url for this site
		var base_url = $('meta[property=base-url]').attr('content') + '/';

		return base_url + path.join('/');
	};
}

$(document).ready(function() {
	if (typeof(toolbar_api) != 'undefined')
		new ToolbarExtension_Gallery();
});
