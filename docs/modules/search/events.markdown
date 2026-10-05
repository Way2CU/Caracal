# Search - Events

Events are registered under the `search` namespace. The search module itself does not search anything; it
collects results from modules listening to the `get-results` event, merges them, sorts by score and renders them.

```php
Events::connect('search', 'get-results', 'get_search_results', $this);
```

Summary:

1. Get results


## 1. Get results: `get-results`

Fired every time search results are requested, once per request. Each listener is expected to decide whether it
is being asked to participate (its name is in `module_list`) and return a list of matching items.

Triggered from: the `show_results` tag of the module.

Parameters passed to callback function:

**Param name** | **Type**   | **Description**
---------------|------------|----------------
`module_list`  | `array`    | Names of modules that should take part in the search, taken from the `module_list` tag parameter. May be empty; listeners should return an empty array when their name is not present.
`query`        | `string`   | Lower-cased, sanitized search query.
`threshold`    | `int`      | Minimum score (0–100) a result must reach to be included. Defaults to 25.

Return value: array of result arrays. Returning an empty array is required when the module has nothing to add;
returning `null` breaks the merge. Each result must contain at least:

**Key**    | **Type** | **Description**
-----------|----------|----------------
`score`    | `float`  | Relevance between 0 and 100. Results are sorted by this value in descending order across all modules.
`id`       | `int`    | Item id within the originating module.
`title`    | `string` | Title to display.
`content`  | `string` | Excerpt to display.
`type`     | `string` | Item type, e.g. `article`.
`module`   | `string` | Name of the module that produced the result.

Any additional keys are passed through to the result template as local parameters.

Known listeners: `articles`, `news`, `shop`.
