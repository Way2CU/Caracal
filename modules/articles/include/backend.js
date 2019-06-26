/**
 * Preview JavaScript
 * Articles Module
 *
 * Copyright (c) 2019. by Way2CU, http://way2cu.com
 * Authors: Mladen Mijatov
 */

var Caracal = Caracal || new Object();
Caracal.Articles = Caracal.Articles || new Object();

/**
 * Handle window opening in Caracal
 * @param object affected_window
 */
Caracal.Articles.create_markdown_preview = function(affected_window) {
	if (affected_window.id != 'articles_new' && affected_window.id != 'articles_change')
		return true;

	var converter = new Showdown.converter();
	var content_input = affected_window.ui.content.querySelector('textarea[name=content]');
	var content_preview = affected_window.ui.content.querySelector('div#article_preview');

	if (!content_input)
		return true;

	// update preview on blur
	content_input.addEventListener('blur', function() {
		content_preview.innerHTML = converter.makeHtml(content_input.value);
	});
};


window.addEventListener('load', function() {
	Caracal.window_system.events.connect('window-content-load', Caracal.Articles.create_markdown_preview);
});
