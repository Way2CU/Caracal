# Attribute-level directives

Unlike the regular `cms:*` tags, the directives described here are not standalone
tags with children. They are special attributes added to *any* tag (framework tag
or plain HTML) that modify how the parser processes that tag's other attributes, or
how the tag interacts with the page cache.

All directives are resolved before the tag itself is rendered and are removed from
the output. When more than one is present on the same tag they are applied in the
following order: `cms:eval`, `cms:optional`, `cms:tooltip`, `cms:constant`,
`cms:skip_cache`, `cms:flush`.

Several of these directives evaluate their target attributes as PHP expressions. The
evaluation context exposes the local template parameters as `$params`, the template
parameters as `$template`, the current module settings as `$settings`, and the
globals `$section`, `$language` and `$language_rtl`.


## `cms:eval`

Comma-separated list of attribute names on the same tag whose values are evaluated as
PHP expressions. Each named attribute is replaced with the result of its evaluation.
If evaluation fails (returns `false`) the original value is kept and a warning is
emitted.

```xml
<a cms:eval="href" href="'/article/' . $params['text_id']">
	<cms:text constant="read_more"/>
</a>
```


## `cms:optional`

Comma-separated list of attribute names that are evaluated as PHP expressions in the
same way as `cms:eval`, with one difference: when the result is falsy the attribute is
removed from the tag entirely instead of being emitted with an empty value. Useful for
attributes that should only be present when they carry a value.

```xml
<input type="text" name="title" cms:optional="value" value="$params['title']"/>
```


## `cms:tooltip`

Takes the name of a language constant, resolves it (as a module constant when the
template is mapped to a module, otherwise as a global constant), and, if a non-empty
value is found, injects it as a `data-tooltip` attribute on the tag.

```xml
<a href="/help" cms:tooltip="tooltip_help">?</a>
```


## `cms:constant`

Comma-separated list of attribute names whose values are treated as language constant
names and replaced with the resolved localized text. Resolution follows the same
module/global rule as `cms:tooltip`. This is effectively an inline `cms:text` for
arbitrary attributes.

```xml
<input type="submit" cms:constant="value" value="button_save"/>
```


## `cms:skip_cache`

Marks the tag as a dynamic region that must be rendered on every request rather than
being stored in the page cache. When page caching is active the tag is turned into a
"dirty area" and re-rendered each time the cached page is served. The `cms:privacy`
and `cms:test` tags receive this treatment automatically.

```xml
<span cms:skip_cache="1">
	<cms:var param="csrf_token"/>
</span>
```


## `cms:flush`

Forces the output buffer to be flushed once the tag is closed. Setting this directive
also cancels any `cms:skip_cache` present on the same tag.

```xml
<div cms:flush="1">
	<cms:module name="slow_module" action="render"/>
</div>
```
