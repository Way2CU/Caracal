# Caracal

Fast, lightweight, developer oriented framework.


## Documentation

Contents:

1. Framework tags
	1. [Set session variable - `cms:session`](docs/tags/session.markdown)
	2. [Call module function - `cms:module`](docs/tags/module.markdown)
	3. Include and parse template - `cms:template`
	4. Add raw text or include raw text file - `cms:raw`
	5. Include or use SVG sprite - `cms:svg`
	6. Show localized content from text constants - `cms:text`
	7. Parse parameter value as Markdown text - `cms:markdown`
	8. Include data from all languages for backend use - `cms:language_data`
	9. Search and replace string with values in parameters - `cms:replace`
	10. Conditional template parsing - `cms:if`
	11. [Multiple choice conditional parsing - `cms:choice`](docs/tags/choice.markdown)
	12. [Parse child nodes only on desktop version - `cms:desktop`](docs/tags/desktop.markdown)
	13. [Parse child nodes only on mobile version - `cms:mobile`](docs/tags/mobile.markdown)
	14. [Parse child nodes for logged in users - `cms:user`](docs/tags/user.markdown)
	15. [Parse child nodes for guests only - `cms:guest`](docs/tags/guest.markdown)
	16. Include variable or parameter value - `cms:var`
	17. Include script in head tag - `cms:script`
	18. Include script from system collection - `cms:collection`
	19. Include style in head tag - `cms:link`
	20. [Automated testing - `cms:test`](docs/tags/test.markdown)
2. Framework attributes
	1. Evaluating attribute - `cms:eval`
	2. Optional attribute evaluation - `cms:eval`
	3. Language constant tooltip - `cms:tooltip`
	4. Treating attribute value as language constant name - `cms:constant`
	5. Marking dirty block for cache handling - `cms:skip_cache`
3. Modules
	1. Articles
	2. Gallery
	3. Shop
	4. Downloads
	5. Links
	6. News
	7. Language menu
	8. Contact form
4. Path handling and template loading
	1. Error pages
5. Globally available functions and constants in evaluation
6. [Coding style guidelines](docs/coding_style.markdown)
7. [License](docs/COPYING)
8. Release notes
	- [v0.1](docs/release_notes/v0.1.markdown)
	- [v0.2](docs/release_notes/v0.2.markdown)
	- [v0.2.1](docs/release_notes/v0.2.1.markdown)
	- [v0.3](docs/release_notes/v0.3.markdown)
	- [v0.4](docs/release_notes/v0.4.markdown)
	- [v0.4.1](docs/release_notes/v0.4.1.markdown)
9. Version specific SQL changes
