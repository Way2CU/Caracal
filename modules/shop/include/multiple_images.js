/**
 * Shop Module
 * Multiple images upload
 *
 * Author: Mladen Mijatov
 */

function MultipleImagesUpload(id, name_base) {
	var self = this;

	this._id = id;
	this._container = null;
	this._name_base = null;

	/**
	 * Finalize object initialization
	 */
	this.init = function() {
		this._container = $('#'+id);
		this._name_base = name_base;

		// add one image container initially
		this.addImage();

		// add button
		this.addButton();
	};

	/**
	 * Add button for adding more images
	 */
	this.addButton = function() {
		var button = $('<button type="button">');

		// configure button
		button
			.click(this.__handle_add_click)
			.html(Caracal.language.get_text('shop', 'add_another'));

		this._container.append(button);
	};

	/**
	 * Add new image for upload
	 */
	this.addImage = function() {
		var container = $('<div>');
		var remove_button = $('<button type="button">');
		var upload_image = $('<input type="file">');
		var number = this._container.children('div').length;

		// configure container
		container
			.append(upload_image)
			.append(remove_button);

		// configure button
		remove_button
			.data('container', container)
			.click(this.__handle_remove_click)
			.html(Caracal.language.get_text('shop', 'remove'));

		// configure file upload field
		upload_image
			.attr('name', this._name_base+'_'+number);

		// add image upload to the main container
		this._container.prepend(container);
	};

	/**
	 * Handle clicking on add image button
	 * @param object event
	 */
	this.__handle_add_click = function(event) {
		self.addImage();
		event.preventDefault();
	};

	/**
	 * Handle clicking on remove image button
	 * @param object event
	 */
	this.__handle_remove_click = function(event) {
		var container = $(this).data('container');

		// remove image from container
		container.remove();

		event.preventDefault();
	};

	// finish initialization
	this.init();
}
