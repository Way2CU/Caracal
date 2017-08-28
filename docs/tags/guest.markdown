# Guest conditional tag - `cms:guest`

Tags contained within `cms:guest` will only be parsed and displayed if there is no user currently logged in on the system.

In the following example, `H1` will be displayed only for guests.

```xml
<div class="article">
	<cms:guest>
		<h1>Title</h1>
	</cms:guest>

	<p>Article content</p>
</div>
```
