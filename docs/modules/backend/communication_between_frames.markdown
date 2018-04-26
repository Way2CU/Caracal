# Communication between frames using `postMessage`

Backend window system supports sending and receiving messages using `postMessage` mechanism. These messages can be used to extend functionality across different domains.

Message system is only available when `enclosed` parameter is provided in URL used to load window content. This parameter contains URL of the page embedding the window.

Communication is done through message objects with predefined required properties.

Example message object:

```json
{
	name: "window.resize",
	type: "notify",
	id: "shop_new_item",
	size: [100, 100]
}
```

Required properties:

- `name` - Message name usually formed by combining message class with its function;
- `type` - Type of message used for differentiation between requests, notifications and responses. The following types are recognized: `notify`, `request`, `response`;

Refer to individual module documentation for list of individually supported messages.
