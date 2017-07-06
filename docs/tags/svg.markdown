# Include or use SVG sprite - `cms:svg`

System supports and encourages heavy use of SVG sprites. These reduce site loading time, provide greater flexibility and better rendering on high DPI screen devices. System ensures near perfect cross-browser compatibility when using sprites through this tag.

Tag recognizes the following attributes:

- `file` - File name to embed or to use as reference for sprite;
- `symbol` - Optional symbol or sprite name to use from SVG file. When omitted SVG file is included in rendered page.

SVG file will be loaded from `$image_path` defined in configuration file. Included files can later styled and animated using CSS/LESS as they are part of the DOM tree.

Example which first includes then uses example symbol from `images/sprite.svg`:

```xml
<cms:svg file="sprite.svg"/>

<a href="/">
	<cms:svg file="sprite.svg" symbol="logo">
</a>
```
