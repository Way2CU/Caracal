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
					<div class="page active">
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
					</div>

					<!-- General page -->
					<div class="page">
						<h1>General information</h1>

						<label>
							<span>Site title:</span>
							<input type="text" name="site_title">
						</label>

						<label>
							<span>Page description:</span>
							<textarea name="site_description"></textarea>
						</label>

						<label>
							<span>Copyright:</span>
							<input type="text" name="site_copyright">
						</label>
					</div>

					<!-- Administrator page -->
					<div class="page">
						<h1>Administrator account</h1>

						<label>
							<span>Full name:</span>
							<input type="text" name="admin_name">
						</label>

						<label>
							<span>Email:</span>
							<input type="text" name="admin_email">
						</label>

						<label>
							<span>Username:</span>
							<input type="text" name="admin_username">
						</label>

						<label>
							<span>Password:</span>
							<input type="text" name="admin_password">
						</label>

						<label>
							<span>Repeat password:</span>
							<input type="text" name="admin_password_repeat">
						</label>
					</div>

					<!-- Database page -->
					<div class="page">
						<h1>Database information</h1>
					</div>

					<!-- Languages page -->
					<div class="page">
						<h1>Language selection</h1>
					</div>
					
					<!-- Template page -->
					<div class="page">
						<h1>Starting template</h1>
					</div>

					<!-- Summary page -->
					<div class="page">
						<h1>Summary</h1>
					</div>
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
