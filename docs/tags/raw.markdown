# Add raw text or include raw text file - `cms:raw`

System is programmed in such a way to render child tags first then render tag data regardless of order of elemens inside of template. To place raw text in specific `cms:raw` tag is used either by specifying `text` attribute or simply wrapping some text with `cms:raw`.

Tag recognizes the following attributes:
- `text` - Optional text to add to rendered template;
- `file` - Optional file to include raw;
- `path` - Optional path where to look for `file`. If omitted defaults to `$template_path` set in config;

Example for adding raw text:

```xml
<cms:raw>Sample text</cms:raw>
<hr/>
<cms:raw text="Extra text"/>
```

This tag can also be used to include raw text files. This is useful when integrating with services such as Facebook and Google Analytics which require developers to put code snippets in specific areas of their site. 

Example which includes `gtm.txt` file from templates directory:

```xml
<cms:raw file="gtm.txt"/>
```
