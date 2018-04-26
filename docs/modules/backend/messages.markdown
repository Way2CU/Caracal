# Supported messages

Table of contents:

1. Windows;
	- Setting window properties;
	- Getting window properties;
	- Notification of window content size;
2. Styles;
	- Injecting CSS.


## Window related mesages

Window components in Caracal are all non-modal interface containers. These containers in regular backend operations are movable and have number of properties associated with them. Messages listed in this section are used to work with these properties.


### Setting window properties

This message allows sender to set different properties of the window with specified `id`. All properties are optional. Window system reserves right to reject setting certain properties in cases where such action would produce undesireable effect. Response message will contain list of properties changed. In cases where window with specified `id` is not loaded empty set of properties is returned.

Request message:
```json
{
	name: "window:set-properties",
	type: "request",
	id: "window-id",
	properties: {
		size: [300, 100],
		title: "Some title"
	}
}
```

Properties:
- `size` - Array containing new width and height for the window;
- `title` - Window title to set.

Response message:
```json
{
	name: "window:set-properties",
	type: "response",
	id: "window-id",
	properties: ["size", "title"]
}
```

Response `properties` array contains list of strings indicating which properties were set.


### Getting window properties

External applications can get properties for window with specified `id`. Response message will contain only requested properties. In cases where window with specified `id` doesn't exist or is not loaded empty set of properties is returned.

Request message:
```json
{
	name: "window:get-properties",
	type: "request",
	id: "window-id",
	properties: ["size", "title"]
}
```

Properties:
- `size` - Array containing new width and height for the window;
- `title` - Window title to set.

Response message:
```json
{
	name: "window:get-properties",
	type: "response",
	id: "window-id",
	properties: {
		size: [300, 100],
		title: "Some title"
	}
}
```


### Notification of window content size

Notification sent to other side when message system is active and there has been change in window content size. Reported size is the size of _content_ not window itself. These messages can not be requested.

Message:
```json
{
	name: "window:content-size",
	type: "notification",
	id: "window-id",
	content_size: [100, 100]
}
```


## System related messages


### Injecting CSS

When showing enclosed window content, all elements of the backend will be loaded apart from styles. This is to provide developers with ability to style Caracal backend windows to their liking and provide seamless experience to users. This message allows adding styles from same domain as specified in `enclosed` parameter when displaying window content. Each file however needs to be accompanied with its integrity code to ensure security is up to par. Files without integrity code are silently ignored.

Request message:
```json
{
	name: "system:inject-styles",
	type: "request",
	styles: [
		[
			"http://somedomain.com/styles/additional.css",
			"sha384-xxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
		], 
		[
			"http://somedomain.com/styles/style.css",
			"sha384-xxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
		]
	]
}
```

Response message:
```json
{
	name: "system:inject-styles",
	type: "response",
	styles: ["http://somedomain.com/styles/style.css"]
}
```
