/**
 * Shop Module
 * Multiple images upload
 * 
 * Author: MeanEYE.rcf
 */

function MultipleImagesUpload(id) {
	this._id = id;
	this._container = null;
	
	/**
	 * Finalize object initialization
	 */
	this.init = function() {
		this._container = $('#'+id);
	};
	
	this.addImage = function() {
		var upload_image = $('<input>');
		var number = this._container.children('input').length;
		
		upload_image
				.attr('type', 'file')
				.attr('name', this._name_base+'_'+number);
		
		this._container.append(upload_image);
	};
	
	// finish initialization
	this.init();
}