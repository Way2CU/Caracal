# Include script in head tag - `cms:script`

This tag is used instead of `<script>` in cases where script needs to be included on the page but taken into account when the code optimization occurs. Tag must be used before calling `show` function on the `head_tag` module.

If script relies or others from the system it must be included with this tag as function and variable names are modified during code optimization process.

Tag additionally supports `local` attribute which can be used by backend templates to include scripts relative to their module paths.

Attributes recognized by the tag:

- `local` - Include script from the module include directory. Value represents file name;
- Others supported by the regular `<script>` tag.

Example including module level script:

```xml
<cms:script local="toolbar.js"/>
```

This code snipped would include `toolbar.js` from modules `include/` directory. For example if this code was called from template located in articles module script would be loaded from `modules/articles/include/toolbar.js`.

Exmaple including regular page script:

```xml
<html>
	<head>
		<cms:script
			type="text/javascript"
			src="_BASEURL.'/site/scripts/test.js'"
			cms:eval="src"
			/>
		<cms:module name="head_tag" action="show"/>
	</head>

	<body>
	</body>
</html>
```
