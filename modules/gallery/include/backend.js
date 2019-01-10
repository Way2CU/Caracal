/**
 * Gallery Backend Functions
 */
var Caracal = Caracal || {};


function gallery_update_image_list() {
	var gallery_window = Caracal.window_system.get_window('gallery_images');
	var list = gallery_window.ui.container.querySelector('select#gallery_images_group');
	var raw_url = gallery_window.url;

	// make sure we have URL to operate on
	if (raw_url === undefined)
		raw_url = window.location.toString();

	// create params object for easier modification
	var url = new URL(raw_url);
	var params = new URLSearchParams(url.search);

	// enclose parameter is needed only initially
	if (params.has('enclose'))
		params.delete('enclose');

	// modify selected group
	params.set('group', list.value)
	url.search = params.toString();

	// reload window content
	gallery_window.load_content(url.toString());
}
