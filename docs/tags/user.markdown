# User Conditional Tag - `cms:user`

Tags contained within `cms:user` will only be parsed and displayed if user is logged in
regardless of users permissions.

In the following example, `H1` will be displayed only for user that is currently logged in.

	<div class="article">
		<cms:user>
			<h1>Title</h1>
		</cms:user>
		<p>Article content</p>
	</div>
