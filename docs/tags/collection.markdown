# Include script and styles from system collection - `cms:collection`

Collection module provides selection of predefined scripts and accompanying styles which can easily be included using this tag. As with [`cms:script`](script.markdown) tag, this one also has to be called before calling `show` function from `head_tag` module.

This tag accepts only one attribute (`include`) containing coma-separated values of scripts and styles to include. The following list of scripts is available for use:

- `animation_chain`      - Old and outdated animation chaining support using jQuery's `.animate` function;
- `dialog`               - Easy to customize and style general purpose dialog included by default on most configurations;
- `scrollbar`            - Cross-browser custom scrollbar which allows styling of every component;
- `page_control`         - Notebook-like widget allowing users to display more compact user interface;
- `mobile_menu`          - Mobile menu integration automatically included when page was requested from a mobile device;
- `communicator`         - Simplified object used for communication with backend services and modules;
- `dynamic_page_content` - Dynamic and lazy-loader component which allows sites to load faster and only requested content;
- `property_editor`      - Input control which allows arrays to be used as value;
- `event_system`         - Standalone event connecting and handling system used in all backend services. It implements mechanism for event-driven communication between components;
- `jquery`               - jQuery, currently at version 2.2.4;
- `jquery_event_drag`    - Drag and drop support for jQuery;
- `jquery_event_scroll`  - Scroll event support for jQuery;
- `jquery_event_outside` - Click-outside event support for jQuery;
- `jquery_extensions`    - Text editing extensions for jQuery. They provide `insertAtCaret`, `replaceSelection` and `selectRange` function on input elements;
- `less`                 - Real-time parser for LESS CSS. Automatically included if `head_tag` contains link tag to LESS file. When code optimization is turned off this script is no longer included as code optimization compiles LESS code on server side and serves it as CSS;
- `showdown`             - Markdown support;
- `prefix_free`          - Polyfill script which enables developers to skip using browser specific prefixes (`-moz`, `-webkit`, etc.) in CSS.

Example demonstrating inclusion of dialog scripts:

```xml
<html>
	<head>
		<cms:collection include="dialog,event_system"/>
		<cms:module name="head_tag" action="show"/>
	</head>

	<body>
	</body>
</html>
```
