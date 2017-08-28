# Desktop conditional tag - `cms:desktop`

This tag provides an easy way of marking a specific block as desktop only. Tags contained within `cms:desktop` will be parsed only if user is requesting page as a desktop computer. This also includes "Request desktop site" option on mobile browsers.

In the following example, `H1` will be displayed only in desktop version.

```xml
<div class="article">
	<cms:desktop>
		<h1>Title</h1>
	</cms:desktop>

	<p>Article content</p>
</div>
```

System determines if request is made from desktop or mobile browser based on browser id. This tag is equivalent to `cms:if` with predefined `condition` parameter.

```xml
<cms:if condition="_DESKTOP_VERSION">
	<!-- Content -->
</cms:if>
```

If there's a need for more complex condition use of `cms:if` is recommended. For example:

```xml
<cms:if condition="_DESKTOP_VERSION and $action == 'show'">
	<!-- Content -->
</cms:if>
```

As [per advice][1] from Google, tablet computers are considered desktop and will be served desktop version of the pages.

[1]: http://googlewebmastercentral.blogspot.com/2012/11/giving-tablet-users-full-sized-web.html
