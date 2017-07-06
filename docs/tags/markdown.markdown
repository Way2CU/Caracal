# Parse parameter value as Markdown text - `cms:markdown`

[Markdown][markdown] is a useful format for writing content without implicit style attached. It provides people who are not well-versed in HTML to write content and still get proper structure and formatting in place. This tag in allows treating any parameter value as Markdown notation and during rendering output is as HTML.

Tag recognizes the following attributes:

- `chars` - Optional number of characters to limit output to;
- `end_with` - Optional text to add at the end of limited output. If omitted it defaults to "...";
- `param` - Name of parameter whose value is to be used for rendering to HTML;
- `multilanguage` - Optional flag which indicates whether parameter value contains more than one language. If omitted defaults to `no`;
- `clear_text` - Optional flag which indicates whether tags should be stripped from resulting HTML.

Example which shows article and its content:

```xml
<document>
	<h1><cms:var param="title" multilanguage="yes"/></h1>

	<!-- Content -->
	<cms:markdown param="content" multilanguage="yes"/>
</document>
```
