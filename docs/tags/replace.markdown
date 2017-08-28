# Search and replace string with values in parameters - `cms:replace`

This tag will take data and replace `%param%` with appropriate value. This tag can be used only within context of object being displayed where templates have access to `$params`, for example when showing article, gallery image, etc.

Tag recognizes the following attribute:

- `param` - Optional comma separated list of parameters to replace. If omitted all of the parameters are searched and replaced. 

Example which replaces article `id` inside of JavaScript block with value of `$params['id']`:

```xml
<script type="text/javascript">
	<cms:replace param="id">
		window.location = '/articles/%id%';
	</cms:replace>
</script>
```

Output:

```xml
<script type="text/javascript">
	window.location = '/articles/15';
</script>
```
