# Show localized content from text constants `cms:text`

Text constants are useful tool for providing localization option without ability to change its content from backend. Even on sites with single initally single language it's best to use language constats to enable easy addition of languages later down the line.

Tag recognizes the following attributes:

- `constant` - Name of language constant as defined in `data/language_xx.json` files;
- `module` - Optional module name which will get constant value from module language files instead of global language files.

Example:

```xml
<title>
	<cms:text constant="site_title"/>
</title>
```
