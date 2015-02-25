/**
 * Downloads Toolbar Extension
 *
 * Copyright (c) 2013. by Way2CU
 * Author: Mladen Mijatov
 *
 * This toolbar extension provides controls to be used in conjunction
 * with downloads module.
 *
 * Requires jQuery 1.4.2+
 */

function ToolbarExtension_Downloads() {
	var self = this;

	this.dialog = new Dialog();
	this.dialog.setTitle(language_handler.getText('downloads', 'toolbar_add_download'));

	// register extension to mail API
	toolbar_api.registerModule('downloads', this);

	// base url for this site
	var base = $('base');
	this.backend_url = base.attr('href') + '/index.php'; 

	/**
	 * Function used to add control on specified toolbar
	 *
	 * @param object toolbar
	 * @param object component
	 * @param string control
	 */
	this.addControl = function(toolbar, component, control) {
		switch (control) {
			case 'link':
				this.control_DownloadLink(toolbar, component);
				break;
		}
	};

	/**
	 * Add control to specified toolbar
	 *
	 * @param object toolbar
	 * @param object component
	 */
	this.control_DownloadLink = function(toolbar, component) {
		var button = $('<a>');
		var image = $('<img>');

		// configure image
		image
			.attr('src', this.getIconURL('link.png'))
			.attr('alt', '');

		// configure button
		button
			.addClass('button')
			.attr('href', 'javascript: void(0);')
			.attr('title', language_handler.getText('downloads', 'toolbar_add_download'))
			.append(image)
			.click(function() {
				self.dialog.show();
				self.dialog.setLoadingState();

				$.ajax({
					url: self.backend_url,
					type: 'GET',
					data: {
						section: 'downloads',
						action: 'json_list'
					},
					dataType: 'json',
					context: component.get(0),
					success: self.loaded_DownloadsList
				});
			});

		toolbar.append(button);
	};

	/**
	 * Process loaded downloads data
	 * @param json_object data
	 * @context component
	 */
	this.loaded_DownloadsList = function(data) {
		var component = $(this);
		var component_window = component.closes('div.window');
		var language_selector = component_window.find('div.language_selector').data('selector');

		if (!data.error) {
			var container = $('<div>');
			var header = $('<div>');
			var list = $('<div>');

			// create header labels
			var header_name = $('<span>');
			var header_size = $('<span>');
			var header_count = $('<span>');
			var header_filename = $('<span>');

			header_name
				.addClass('column')
				.css('width', '150px')
				.html(language_handler.getText('downloads', 'column_name'));

			header_size
				.addClass('column')
				.css('width', '70px')
				.html(language_handler.getText('downloads', 'column_size'));

			header_count
				.addClass('column')
				.css('width', '70px')
				.html(language_handler.getText('downloads', 'column_downloads'));

			header_filename
				.addClass('column')
				.css('width', '200px')
				.html(language_handler.getText('downloads', 'column_filename'));

			// configure scrollable list
			list
				.addClass('list_content')
				.css('height', '375px');

			header
				.append(header_name)
				.append(header_size)
				.append(header_count)
				.append(header_filename)
				.addClass('list_header');

			container
				.append(header)
				.append(list)
				.addClass('scrollable_list');

			// no error, feed data into dialog
			var i = data.items.length;
			while (i--) {
				var item = data.items[i];

				// create elements
				var download = $('<a>');
				var title = $('<span>');
				var size = $('<span>');
				var count = $('<span>');
				var filename = $('<span>');

				// configure
				title
					.html(item.name[language_selector.current_language])
					.addClass('column')
					.css('width', '150px');

				size
					.html(item.size)
					.addClass('column')
					.css('width', '70px');

				count
					.html(item.count)
					.addClass('column')
					.css('width', '70px');


				filename
					.html(item.filename)
					.addClass('column')
					.css('width', '200px');

				download
					.append(title)
					.append(size)
					.append(count)
					.append(filename)
					.data('item', item)
					.addClass('list_item')
					.click(function() {
						var item = $(this).data('item');

						component.insertAtCaret(
									'[' + item.name[language_selector.current_language] +
									'](' + item.download_url + ')'
								);

						self.dialog.hide();
					});

				list.append(download);
			}

			self.dialog.setNormalState();
			self.dialog.setContent(container, 650, 400);

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
		path.push('modules/downloads/images');
		path.push(icon);

		// base url for this site
		var base = $('base');
		base_url = base.attr('href') + '/'; 

		return base_url + path.join('/');
	};
}

$(document).ready(function() {
	if (typeof(toolbar_api) != 'undefined')
		new ToolbarExtension_Downloads();
});
