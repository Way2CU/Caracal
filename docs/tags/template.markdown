# Include and parse template - `cms:template`

To help and keep complexity of the site low it's often required to keep commonly used tags and templates into separate files. This tag, `cms:template`, is then used to include templates in different places. Optionally included templates can have multiple behaviors defined through use of `$template` variable available inside of included template.

Tag recognizes the following attributes:

- `file` - Relative path to file for inclusion;
- `path` - Optional path where to look for template, if omitted defaults to `$template_path` defined in config.

Example which loads `parts/header.xml` from `site/templates` directory:

```xml
<cms:template file="parts/header.xml"/>
```


## Transfering parameters

Similarly to [`cms:module`](module.markdown) call, `cms:template` tag allows transfering parameters to the new template. This is done by creating child `param` tags. These parameters are later available in included template through `$template` variable.

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
