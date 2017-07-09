# Include and parse template - `cms:template`

To help and keep complexity of the site low it's often required to keep commonly used tags and templates into separate files. This tag, `cms:template`, is then used to include templates in different places. Optionally included templates can have multiple behaviors defined through use of `$template` variable available inside of included template.

Tag recognizes the following attributes:

- `file` - Relative path to file for inclusion;
- `path` - Optional path where to look for template, if omitted defaults to `$template_path` defined in config.

Example which loads `parts/header.xml` from `site/templates` directory:

```xml
<cms:template file="parts/header.xml"/>
```


## Setting template parameters

Similarly to [`cms:module`](module.markdown) call, `cms:template` tag allows setting parameters to be used in template. This is done by creating child `param` tags. These parameters are later available in included template through `$template` variable.

Tag, recognizes the following attributes:

- `name` - Parameter name;
- `value` - Parameter value;

Example call:

```xml
<cms:template file="parts/header.xml">
	<param name="class" value="fixed"/>
</cms:template>
```

Example usage:

```xml
<document>
	<header
		class="$template['class']"
		cms:eval="class">

		<!-- Content -->
	</header>
</document>
```


## Transferring parameters

System doesn't parse or evaluate values inside of `cms:template` tag. It is up to function to handle these tags however large number of standard modules does nothing with these tags and will simply ignore them. Refer to individual module documentation for more information on this. There is a way, however, to pass individual values of `$template` or `$params` to function. Only inside of `cms:template` and `cms:module` call is `cms:transfer` recognized and handled.

This tag supports the following attributes:

- `name` - Optional name of parameter to transfer, equal to `$params['name']`;
- `template` - Optional name of template parameter to transfer, equal to `$template['name']`;
- `tag` - Optional resulting tag name, defaults to `param` if omitted;
- `target` - Optional target parameter name, defaults to either `name` or `template` value if specified.

For example:

```xml
<cms:transfer template="title" target="text"/>
```

Would generate tag inside of `cms:module` call that looks like this where `value_of_title` would be result of evaluating `$template['title']`:

```xml
<param name="text" value="value_of_title"/>
```
