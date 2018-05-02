# Include style in head tag or link to other elements - `cms:link`

This tag is used instead of `<link>` in cases where style needs to be included on the page but taken into account when the code optimization occurs. Tag must be used before calling `show` function on the `head_tag` module.

Code optimization will automatically skip optimizing styles located on remote domains!

Tag attributes are the same as supported by the regular `<link>` tag.

Exmaple including regular page style:

```xml
<html>
	<head>
		<cms:link
			rel="stylesheet"
			type="text/css"
			href="//fonts.googleapis.com/css?family=Roboto:400|Roboto+Slab:400"
			/>
		<cms:module name="head_tag" action="show"/>
	</head>

	<body>
	</body>
</html>
```
