/**
 * Window Functions
 *
 * Copyright (c) 2009. by MeanEYE , RCF Group
 * http://rcf-group.com
 */

var window_List = new Object();

/**
 * Open new window with content derived from specified URL
 *
 * @param string id
 * @param integer width
 * @param string title
 * @param boolean can_close
 * @param boolean can_minimize
 * @param string url
 */
function window_Open(id, width, title, can_close, can_minimize, url) {
	if (window_Exists(id)) {
		// window already exist so we set focus
		window_SetFocus(id);
		window_LoadContent(id, url);
		window_SetTitle(id, title);
		window_Resize(id, width);
	} else {
		// window does not exist so we create one
		var wnd = document.createElement('div');
		var pSize = page_GetSize();
		var request = new XMLHttpRequest();

		wnd.setAttribute('id', id);

		wnd.style.width = width + 'px';
		wnd.style.left = Math.round((pSize[0] - width) / 2) + 'px';
		wnd.style.top = (Math.round((pSize[1] - 100) / 2) - 10) + 'px';

		wnd.innerHTML = '\
			<div class="title_bar">\
				<div class="title_bar_right">\
					<div class="title_bar_content">\
						<h6 onMouseDown="window_MoveStart(\'' + id + '\', this, event);" id="'+id+'_title">\
							' + title + '\
						</h6>\
					</div>\
				</div>\
			</div>\
			<div class="content_holder">\
				<div class="content_holder_right">\
					<div class="content" id="'+id+'_content">\
					</div>\
				</div>\
			</div>\
			<div class="bottom">\
				<div class="bottom_right">\
					<div class="bottom_content">\
					</div>\
				</div>\
			</div>';

		document.body.appendChild(wnd);
		window_Init(id);
		window_SetFocus(id);
		window_LoadContent(id, url);
	}
}

/**
 * Close window with specified ID
 *
 * @param string id
 */
function window_Close(id) {
	var $wnd = $('#'+id);

	if ($wnd != undefined)
		$wnd.animate({opacity: 0}, 300, function() {
			$wnd.remove();
			delete window_List[id];
			window_SetFocus(window_GetTopLevel());
		});
}

/**
 * Load content in specified window
 *
 * @param string id
 * @param string url
 */
function window_LoadContent(id, url) {
	var wnd = window_GetById(id);
	var request = new XMLHttpRequest();

	if (wnd != undefined) {
		wnd.url = url;
		wnd._content.className = 'content loading';

		request.open('GET', url, true);
		request.onreadystatechange = function() {
							window_ProcessResult(request, wnd);
						};
		request.send(null);
	}
}

/**
 * Reload content on specified window
 *
 * @param string id
 */
function window_ReloadContent(id) {
	var wnd = window_GetById(id);
	var request = new XMLHttpRequest();

	if (wnd != undefined && wnd.url != undefined) {
		wnd._content.className = 'content loading';

		request.open('GET', wnd.url, true);
		request.onreadystatechange = function() {
							window_ProcessResult(request, wnd);
						};
		request.send(null);
	}
}

/**
 * Set window content
 *
 * @param object wnd
 * @param string content
 */
function window_SetContent(wnd, content) {
	// data vas retrieved correctly, handle
	original_size = window_GetSize(wnd);
	wnd._content.innerHTML = content;
	new_size = window_GetSize(wnd);

	// implement newly received objects
	window_ImplementContentEvents(wnd);
	window_MoveBy(wnd, (original_size[0] - new_size[0]) / 2, (original_size[1] - new_size[1]) / 2);

	// execute scripts if there are any
	var script_list = wnd._content.getElementsByTagName('script');

	for (var i=0; i<script_list.length; i++) {
		var script = script_list[i];
		if (script.type == 'text/javascript')
			eval(script.innerHTML);
	}
}

/**
 * Adds window to window list and prepares window for usage
 * @param id Main tag ID
 */
function window_Init(id) {
	var mElement = document.getElementById(id);
	var container = document.getElementById(id+'_content');
	var title = document.getElementById(id+'_title');

	mElement.className = 'window_inactive';
	mElement.onclick = function () { window_SetFocus(id); };
	mElement._content = container;
	mElement._title = title;
	document.element_previous = mElement;

	window_List[id] = mElement;
}

/**
 * Event function called onMouseDown
 * @param id Id of element being moved
 * @param eElement Calling element
 * @param Event
 */
function window_MoveStart(id, eElement, Event) {
	if (Event.preventDefault) Event.preventDefault();
	Event.cancelBubble = true;

	document.onmousemove_Old = document.onmousemove;
	document.onmouseup_Old = document.onmouseup;
	document.onmousemove = window_MoveUpdate;
	document.onmouseup = window_MoveStop;

	window_SetFocus(id);

	mElement = window.document.getElementById(id);
	document.element_moved = mElement;
	document.element_calling = eElement;

	mElement.dragX = Event.clientX;
	mElement.dragY = Event.clientY;
}

/**
 * Function which updates position of moved element
 * @param Event
 */
function window_MoveUpdate(Event) {
	if (!Event) Event = event;

	mElement = window.document.element_moved;
	mElement.style.left = (mElement.offsetLeft + Event.clientX - mElement.dragX) + "px";
	mElement.style.top = (mElement.offsetTop + Event.clientY - mElement.dragY) + "px";
	mElement.dragX = Event.clientX;
	mElement.dragY = Event.clientY;

	return false;
}

/**
 * Restore old events when dragging is completed
 */
function window_MoveStop() {
	document.element_previous = document.element_moved;
	document.onmousemove = document.onmousemove_Old;
	document.onmouseup = document.onmouseup_Old;
}

/**
 * Set focus on selected window
 *
 * @param id
 */
function window_SetFocus(id) {
	for (var name in window_List) {
		var wnd = window_List[name];

		if (wnd.id == id) {
			wnd.className = 'window';
			wnd.style.zIndex = 1000;
		} else {
			wnd.className = 'window_inactive';
			wnd.style.zIndex--;
		}
	}
}

/**
 * Set window title
 *
 * @param string id
 * @param string title
 */
function window_SetTitle(id, title) {
	var wnd = window_GetById(id);

	if (wnd != undefined)
		wnd._title.innerText = title;
}

/**
 * Check if selected window exists
 *
 * @param string id
 * @return boolean
 */
function window_Exists(id) {
	return window_List.hasOwnProperty(id);
}

/**
 * Resize window
 *
 * @param string id
 * @param integer width
 */
function window_Resize(id, new_width) {
	var $wnd = $('#'+id)
	var new_position = $wnd.position().left + Math.floor(new_width / 2);

	$wnd.animate({
					left: new_position,
					width: new_width
				},
				500);
}

/**
 * Handle onSubmit form event
 * @param object wnd
 * @param object form
 */
function window_SubmitContent(wnd, form) {
	var request = new XMLHttpRequest();
	var params = new Array();
	var tag_list = new Array('input', 'select', 'textarea');

	// process INPUT elements
	for (var i=0; i<tag_list.length; i++) {
		var node_list = form.getElementsByTagName(tag_list[i]);

		for (var j=0; j<node_list.length; j++) {
			var node = node_list[j];
			var value = '';

			switch(node.type) {
				case 'checkbox':
					value = node.checked ? 1 : 0;
					break;

				default:
					value = escape(node.value.replace(/\+/g, '%2B'));
					break;
			}
			params.push(node.name + '=' + value);
		}
	}

	wnd._content.className = 'content loading';

	var action = form.attributes['action'].value;
	request.open('POST', action, true);

	request.setRequestHeader('X-Requested-With', 'AJAX');
	request.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
	request.setRequestHeader('Content-length', params.length);
	request.setRequestHeader('Connection', 'close');

	request.onreadystatechange = function() {
						window_ProcessResult(request, wnd);
					};

	request.send(params.join('&'));
}

/**
 * Handle async content update response
 *
 * @param object request
 * @param object wnd
 */
function window_ProcessResult(request, wnd) {
	if (request.readyState == 4)
		if (request.status == 200) {
			window_SetContent(wnd, request.responseText);

			// reset loading class
			wnd._content.className = 'content';
		} else {
			// error loading data
			error_log = window.open("", "error_log", "width=640px, height=480px, scrollbars");

			error_log.document.open();
			error_log.document.writeln('<pre>'+request.responseText+'</pre>');
			error_log.document.close();

			// reset loading class
			container.className = '';
		}
}

/**
 * Implement AJAX function into newly inserted content
 *
 * @param object wnd
 */
function window_ImplementContentEvents(wnd) {
	var form_list = wnd._content.getElementsByTagName('form');

	// implement submit events
	for (var i=0; i<form_list.length; i++) {
		var form = form_list[i];

		// attach onSubmit event
		if (form.onsubmit == undefined)
			form.onsubmit = function() {
				window_SubmitContent(wnd, form);
				return false;
			};
	}
}

/**
 * Move window to selepcted location
 *
 * @param object window
 * @param integer x
 * @param integer y
 */
function window_MoveTo(window, x, y) {
	window.style.left = x.toString() + 'px';
	window.style.top = y.toString() + 'px';
}

/**
 * Move window by given amount
 *
 * @param object window
 * @param integer x
 * @param integer y
 */
function window_MoveBy(window, x, y) {
	var pos = window_GetPosition(window);

	pos[0] += x;
	pos[1] += y;
	window_MoveTo(window, pos[0], pos[1]);
}

/**
 * Get window size
 *
 * @param object window
 * @return array
 */
function window_GetSize(window) {
   if (window.clientWidth)
      return [window.clientWidth, window.clientHeight]; else
      return [window.width, window.height];
}

/**
 * Get window position
 *
 * @param object window
 * @return array
 */
function window_GetPosition(window) {
	var x = parseInt(window.style.left.substring(0, window.style.left.length-2));
	var y = parseInt(window.style.top.substring(0, window.style.top.length-2));
	return [x, y];
}

/**
 * Get window object by ID
 * @param string id
 * @return object
 */
function window_GetById(id) {
	return window_Exists(id) ? window_List[id] : undefined;
}

/**
 * Get top level window ID
 *
 * @return string
 */
function window_GetTopLevel() {
	var result = undefined;
	var highest = 0;

	for (var id in window_List)
		if (highest < parseInt(window_List[id].style.zIndex)) {
			highest = parseInt(window_List[id].style.zIndex);
			result = id;
		}

	return result;
}
