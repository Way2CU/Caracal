<!DOCTYPE html>
<html lang="en">
	<head>
		<title>Caracal - Installation</title>
		<link rel="icon" sizes="16x16" type="image/png" href="%-embed: ../images/default_icon/16.png%">
		<link rel="stylesheet" type="text/css" href="%-embed: styles/main.css%">
		<link rel="stylesheet" type="text/css" href="http://fonts.googleapis.com/css?family=Ubuntu+Mono|Ubuntu:300,400,500|Ubuntu+Condensed">
		<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/2.1.0/jquery.min.js"></script>
		<script type="text/javascript" src="%-embed: scripts/steps.js%"></script>
		<script type="text/javascript" src="%-embed: scripts/main.js%"></script>
	</head>

	<body>
		<form action="" method="post">
			<div id="main">
				<header>
					<div class="logo">Development Framework</div>

					<nav id="steps">
						<a class="general">General</a>
						<a class="admin">Administrator</a>
						<a class="database">Database</a>
						<a class="languages">Languages</a>
						<a class="template">Template</a>
					</nav>
				</header>

				<div id="pages">
					<!-- Main page -->
					<section class="active">
						<h1>Welcome to installation script!</h1>
						<p>
						During this process we will download, install and configure
						latest <em>stable</em> version of development framework. Configuration
						will consist of following steps:

						<ul>
							<li>General questions related to your site such as title;</li>
							<li>Setting up an administrative user account;</li>
							<li>Configuring database which framework will use to store content;</li>
							<li>Language selection your site will have and support;</li>
							<li>Optionally selecting starting template from our gallery.</li>
						</ul>

						It's important to note that this script will <u>not</u>
						make any changes to your server until you click <em>Finish</em> button.
						If you wish to abort installation at any point just close this window/tab.
						</p>
					</section>

					<!-- General page -->
					<section>
						<h1>General information</h1>

						<label>
							<span>Site title:</span>
							<input type="text" name="site_title">
							<dfn data-tooltip="Main part of title that will always be included."></dfn>
						</label>

						<label>
							<span>Page description:</span>
							<textarea name="site_description"></textarea>
							<dfn data-tooltip="Page description for home page. This description will be shown in search results."></dfn>
						</label>

						<label>
							<span>Copyright:</span>
							<input type="text" name="site_copyright">
							<dfn data-tooltip="Text displayed at the bottom of page."></dfn>
						</label>
					</section>

					<!-- Administrator page -->
					<section>
						<h1>Administrator account</h1>

						<label>
							<span>Full name:</span>
							<input type="text" name="admin_name">
							<dfn data-tooltip="Optional name for use in backend.">?</dfn>
						</label>

						<label>
							<span>Email:</span>
							<input type="text" name="admin_email">
							<dfn data-tooltip="Address to which future notifications will be sent."></dfn>
						</label>

						<label>
							<span>Username:</span>
							<input type="text" name="admin_username">
						</label>

						<label>
							<span>Password:</span>
							<input type="text" name="admin_password">
							<dfn data-tooltip="There are no limits or requirements for password. We suggest forming a sentence of 3-5 unrelated words ending with few interpunction signs or numbers."></dfn>
						</label>

						<label>
							<span>Repeat password:</span>
							<input type="text" name="admin_password_repeat">
						</label>
					</section>

					<!-- Database page -->
					<section>
						<h1>Database information</h1>
					</section>

					<!-- Languages page -->
					<section>
						<h1>Language selection</h1>
					</section>

					<!-- Template page -->
					<section>
						<h1>Starting template</h1>
					</section>

					<!-- Summary page -->
					<section>
						<h1>Summary</h1>
					</section>
				</div>

				<footer>
					<button type="button" class="previous">Previous</button>
					<button type="button" class="next">Next</button>
					<button type="button" class="install">Install</button>
				</footer>
			</div>
		</form>
	</body>
</html>
