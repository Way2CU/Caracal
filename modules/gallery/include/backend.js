/**
 * Gallery Backend Functions
 */
var Caracal = Caracal || {};


function gallery_update_image_list() {
	var list = $('select#gallery_images_group');
	var gallery_window = Caracal.window_system.get_window('gallery_images');

	if (gallery_window.original_url == undefined)
		gallery_window.original_url = gallery_window.url;

	gallery_window.load_content(gallery_window.original_url + '&group=' + list.val());
}
