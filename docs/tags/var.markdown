# Include variable or parameter value - `cms:var`

This tag is replaced with value of specified rendering parameter, template transfer parameter or evaluated value.

Rendering parameters (`$params`) are only available when template is used for redndering specific entities from the database such as articles, shop items, groups, etc. These parameters are context aware and will change for each of the elements being rendered. In cases where multiple entities are being rendered these parameters are set to each of the values before template is parsed.

Template transfer parameters (`$template`) are set only once before initial parsing of the template and reused between rendering passes between different entities. These parameters can also be used to transfer data between each of the rendering passes.

Tag `cms:var` accepts the following attributes in order of preference:

- `name` - Raw PHP code to be evaluated whose resulting value will be rendered instead of tag itself;
- `param` - Rendering parameter name. For example `title` when rendering articles;
- `multilanguage` - Boolean value specifying whether to treat specified `param` name as multi-language field. Can be `yes` or `no`;
- `template` - Template transfer parameter name.

Example rendering article title with fixed content:

```xml
<article>
	<h1>
		<cms:var
			param="title"
			multilanguage="yes"
			/>
	</h1>

	<p>
		Placeat eius quisquam iste quibusdam ex non. Architecto optio magni similique sunt optio animi molestiae. Et odio ipsam accusantium in sit.
	</p>
</article>
```
