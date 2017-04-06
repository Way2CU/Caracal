# Mobile conditional tag - `cms:mobile`

This tag provides an easy way of marking a specific block as _mobile_ only. Tags contained within `cms:mobile` will be parsed only if user is requesting page from a mobile phone.

In the following example, `H1` will be displayed only in mobile version.

```xml
<div class="article">
	<cms:mobile>
		<h1>Title</h1>
	</cms:mobile>
	<p>Article content</p>
</div>
```

System determines if request is made from desktop or mobile browser based on browser id. This
tag is equivalent to `cms:if` with predefined `condition` parameter.

```xml
<cms:if condition="_MOBILE_VERSION">
</cms:if>
```

If there's a need for more complex condition use of `cms:if` is recommended. For example:

```xml
<cms:if condition="_MOBILE_VERSION and $action == 'show'">
</cms:if>
```

_Note: Empty `cms:if` statements might lead to problems._

As [per advice][1] from Google, tablet computers are considered desktop and will be served desktop
version of the pages.

[1]: http://googlewebmastercentral.blogspot.com/2012/11/giving-tablet-users-full-sized-web.html
