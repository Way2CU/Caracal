/**
 * Gallery Backend Functions
 */

function gallery_update_image_list() {
	var list = $('select#gallery_images_group');
	var gallery_window = window_system.getWindow('gallery_images');

	if (gallery_window.original_url == undefined)
		gallery_window.original_url = gallery_window.url;

	gallery_window.loadContent(gallery_window.original_url + '&group=' + list.val());
}
