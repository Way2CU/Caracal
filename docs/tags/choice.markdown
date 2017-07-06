# Multiple choice tag - `cms:choice`

This tag provides a way to do easy selection between multiple choices with support for default choice. Only matching option will be parsed. System will cache result of this tag. If this is not the desired behavior `cms:skip_cache` needs to be defined.

In the following example, value of request (`GET`, `POST`) parameter `version` will be compared to values in `option`. If matching `option` is found, it will be rendered. If no match was found `default` option will be rendered if specified, otherwise no rendering will occur.

Tag `cms:choice` supports following attributes:

- `param` - Parameter name whose value is compared to options;
- `value` - Manually set or [`cms:eval`-ed](eval.markdown) value to compare.

Tag `option` can be used in this form only inside of `cms:choice` with following attributes:

- `value` - Value to compare to;
- `default` - Optionally set to mark option as default is matching against values fails.

_Note: Due to way XML is parsed, `default` option can only be the last one._

Example:

```xml
<cms:choice param="version">
	<option value="one">
		<h1>One</h1>
	</option>

	<option value="two">
		<h1>Two</h1>
	</option>

	<option value="three" default="1">
		<h1>Three</h1>
	</option>
</cms:choice>
```
