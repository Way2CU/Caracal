# Head Tag - Events

Events are registered under the `head-tag` namespace (note the dash, unlike the module name `head_tag`).

```php
Events::connect('head-tag', 'before-print', 'add_tags', $this);
```

Summary:

1. Before title print
2. Before print

Both events fire while the `<head>` section is being rendered through `cms:module name="head_tag"`, so they
run for every page that uses the tag, including backend pages. Because the output is already being generated,
listeners must add content through the module API (`add_tag()`, `add_title_part()` and similar) rather than
printing directly.


## 1. Before title print: `before-title-print`

Gives modules a chance to replace the page title. The module collects all responses and uses the one with the
highest priority; when no listener returns a positive priority the title is generated from the site title and
the fragments added by templates.

Triggered from: head tag rendering, before any tag is printed.

Parameters passed to callback function: none.

Return value: an array of two elements, `array(string $title, int $priority)`. Return a negative priority to
abstain. A response with priority `0` is never selected since the comparison requires a priority higher than
the current best, which starts at `0`.

Known listeners: `page_description` (sets title and description for pages matching the request path).


## 2. Before print: `before-print`

Gives modules a chance to add their own elements (scripts, styles, meta tags, links) to the page head. It fires
after the title has been decided and immediately before tags are printed.

Triggered from: head tag rendering.

Parameters passed to callback function: none.

Return value: ignored.

Known listeners: the template handler core (`units/template.php`) and most modules that ship frontend
JavaScript or CSS, e.g. `gallery`, `captcha`, `contact_form`, `news`, `youtube`, `language_menu`,
`page_info`, `downloads`.
