# Call module function - `cms:module`

This tag calls module to render content in its place. Optionally user can specify custom template and attributes to be passed to function being called. Only `name` and `action` attributes are required.

Tag recognizes the following attributes:

- `name` - Name of the module where function resides;
- `action` - Function name to call;
- `template` - Optional template file to use for rendering. If omitted system will use its own;
- `template_path` - Optional location where to look for template file. If omitted it defaults to `$template_path`;
- `local` - Optional flag which indicates where template file should be loaded from.

If `local` attribute is set to `1` system will ignore `template_path` attribute and look for template file inside of module specific template directory. This is meant for internal use and when developing modules and is rarely used when developing sites. Omitting this attribute or setting it `0` indicates that template should be loaded from `template_path` if specified, or from default `$template_path` specified in configuration file. Most of the times user will just specify `template` and not worry about other attributes.

Example:

```xml
<cms:module
	name="articles"
	action="show"
	text_id="test"
	template="custom_article.xml"
	/>
```


## Custom templates and use of `$params`

Within `custom_article.xml`, from example above, variable `$params` is available in context of object being rendered. That is `$params` would hold specific article's `id`, `text_id`, `title`, etc. Refer to individual module documentation for detailed information on parameters of each individual object. This variable is _contextual_ and will hold different values for different templates as well as different rendering passes for same template, when showing a list for example.

Usage of `$params` variable allows users to render data stored in any way they prefer. Article list can be, for example, used to form a menu of sorts just by showing titles, while content would be displayed on individual pages.

Framework tag [`cms:var`](var.markdown) can be used to render value of parameter or `$params` can be directly used in conjunction with [`cms:eval`](eval.markdown). Content can also be treated as Markdown through use of [`cms:markdown`](markdown.markdown) tag.

The following example will show article title as link while rendering its content as Markdown and producing HTML from it.

```xml
<document>
	<a href="/">
		<cms:var param="title" multilanguage="yes"/>
	</a>

	<!-- Article content -->
	<cms:markdown param="content" multilanguage="yes"/>
</document>
```


## Nesting module calls

System will allow making another `cms:module` call from within template. Recursion problems can happen and developers are advised to pay close attention when making nested calls.

Nested calls can be used to show information related to current context. In example above, we are showing article with specified `text_id`. Articles have galleries associated with them under `gallery` parameter. To show all the images associated with this article we would make template look like the following.

```xml
<document>
	<a href="/">
		<cms:var param="title" multilanguage="yes"/>
	</a>

	<!-- Article content -->
	<cms:markdown param="content" multilanguage="yes"/>

	<!-- List of images -->
	<cms:module
		name="gallery"
		action="show_image_list"
		group_id="$params['gallery']"
		cms:eval="group_id"
		/>
</document>
```


## Setting template parameters

Similarly to [`cms:template`](template.markdown) call, `cms:module` tag allows setting parameters to be used in template. This is done by creating child `param` tags. These parameters are later available in included template through `$template` variable.

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

System doesn't parse or evaluate values inside of `cms:module` tag. It is up to function to handle these tags however large number of standard modules does nothing with these tags and will simply ignore them. Refer to individual module documentation for more information on this. There is a way, however, to pass individual values of `$template` or `$params` to function. Only inside of `cms:module` and `cms:template` call is `cms:transfer` recognized and handled.

This tag supports the following attributes:

- `name` - Optional name of parameter to transfer, equal to `$params['name']`;
- `template` - Optional name of template parameter to transfer, equal to `$template['name']`;
- `tag` - Optional resulting tag name, defaults to `param` if omitted;
- `target` - Optional target parameter name, defaults to either `name` or `template` value if specified.

For example:

```xml
<cms:transfer name="title" target="text"/>
```

Would generate tag inside of `cms:module` call that looks like this where `value_of_title` would be result of evaluating `$params['title']`:

```xml
<param name="text" value="value_of_title"/>
```
