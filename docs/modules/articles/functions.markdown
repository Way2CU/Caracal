# Articles - Functions

Summary:

1. Public functions
2. AJAX functions

## 1. Public functions

### 1.1. `show`

Shows a single article for different conditions specified. Output template can be specified. All of the
parameters are sanitized by the module. This function accepts the following input parameters:

	<cms:module
		name="articles"
		action="show"
		id="0"
		text_id=""
		order_by="field,another"
		order_asc="1"
		random="0"
		group="first,second"
		template="file.xml"
		template_path="path/"
		local="0"
	/>

**Param name**  | **Type**          | **Required** | **Default**             | **Description**
----------------|-------------------|--------------|-------------------------|----------------
`id`            | `int`             |              |                         | Unique article id.
`text_id`       | `string`          |              |                         | Textual id specified by the user.
`order_by`      | `string`          |              | 'id'                    | One or more field names to sort by. Comma-separated _without_ spaces!
`order_asc`     | `int`             |              | 1                       | Sort direction (0 - descending, 1 - ascending)
`random`        | `int`             |              | 0                       | If article selection should be randomized. Can not be used with `order_by` or `id`.
`group`         | `string` or `int` |              |                         | `text_id` or `id` of one or more groups to pick article from.
`template`      | `string`          |              | 'article.xml'           | Template to be used for outputting data.
`template_path` | `string`          |              | Defined in 'config.php' | Path where to look for template.
`local`         | `int`             |              | 0                       |  | If `template` is located in module's default path. Overrides `template_path`.


Available template parameters:

**Param name** | **Type** | **Description**
---------------|----------|----------------
`id`           | `int`    | Unique article id.
`text_id`      | `string` | User specified textual id.
`timestamp`    | `string` | Article creation time as stored in the database (SQL datetime string).
`date`         | `string` | Localized date based on `timestamp`.
`time`         | `string` | Localized time based on `timestamp`.
`title`        | `array`  | Titles in every language.
`content`      | `array`  | Raw content in every language. See `cms:markdown` tag.
`author`       | `string` | Full name of author.
`visible`      | `int`    | Visibility of article.
`views`        | `int`    | Number of views for this particular article. Currently not used.
`votes_up`     | `int`    | Number of up votes.
`votes_down`   | `int`    | Number of down votes.
`rating`       | `float`  | Rating based on `votes_up` and `votes_down` in range of 0 to 5.


### 1.2. `show_list`

Shows a list of articles matching the specified conditions. Each article is rendered with the item template
(`list_item.xml` by default) which has access to the `cms:article` and `cms:article_rating_image` tags. This
function accepts the following input parameters:

	<cms:module
		name="articles"
		action="show_list"
		limit="10"
		id="0"
		text_id=""
		order_by="field,another"
		order_asc="1"
		random="0"
		only_visible="1"
		selected="0"
		group="first,second"
		without_group="0"
		generate_sprite="0"
		template="file.xml"
		template_path="path/"
		local="0"
	/>

**Param name**    | **Type**          | **Default** | **Description**
------------------|-------------------|-------------|----------------
`limit`           | `int`             |             | Maximum number of articles to display.
`id`              | `int`             |             | Unique article id.
`text_id`         | `string`          |             | One or more textual ids. Comma-separated _without_ spaces!
`order_by`        | `string`          | 'id'        | One or more field names to sort by. Comma-separated _without_ spaces!
`order_asc`       | `int`             | 1           | Sort direction (0 - descending, 1 - ascending).
`random`          | `int`             | 0           | Randomize selection order. Overrides `order_by`.
`only_visible`    | `int`             | 0           | When 1, only visible articles are listed.
`selected`        | `int`             | -1          | Id of the article to mark as selected in the template.
`group`           | `string` or `int` |             | One or more group `text_id` or `id` values to pick articles from. Comma-separated.
`without_group`   | `int`             | 0           | When 1, only articles that belong to no group are listed.
`generate_sprite` | `int`             | 0           | Generate an image sprite for the listed articles. Requires the `gallery` module and accepts `image_size`, `image_constraint` and `image_crop`.
`template`        | `string`          | 'list_item.xml' | Item template used for each article.
`template_path`   | `string`          | Defined in 'config.php' | Path where to look for template.
`local`           | `int`             | 0           | If `template` is located in module's default path. Overrides `template_path`.

Each article is rendered with the same template parameters as `show` (see section 1.1).


### 1.3. `show_group`

Shows a single article group. Either `id` or `text_id` must be provided. The group template (`group.xml` by
default) has access to the `cms:article_list` tag for listing the group's articles.

	<cms:module
		name="articles"
		action="show_group"
		id="0"
		text_id=""
		template="file.xml"
		template_path="path/"
		local="0"
	/>

**Param name**  | **Type** | **Description**
----------------|----------|----------------
`id`            | `int`    | Unique group id.
`text_id`       | `string` | Textual id of the group.
`template`      | `string` | Template to be used for outputting data. Defaults to 'group.xml'.
`template_path` | `string` | Path where to look for template. Defined in 'config.php'.
`local`         | `int`    | If `template` is located in module's default path. Overrides `template_path`.

Available template parameters:

**Param name** | **Type** | **Description**
---------------|----------|----------------
`id`           | `int`    | Unique group id.
`text_id`      | `string` | User specified textual id.
`title`        | `array`  | Titles in every language.
`description`  | `array`  | Descriptions in every language.


### 1.4. `show_group_list`

Shows a list of article groups. Each group is rendered with the item template (`group_list_item.xml` by default)
which has access to the `cms:article_list` tag.

	<cms:module
		name="articles"
		action="show_group_list"
		only_visible="yes"
		limit="10"
		selected="0"
		template="file.xml"
		template_path="path/"
		local="0"
	/>

**Param name**  | **Type** | **Default** | **Description**
----------------|----------|-------------|----------------
`only_visible`  | `string` |             | When set to `yes`, only visible groups are listed.
`limit`         | `int`    |             | Maximum number of groups to display.
`selected`      | `int`    | -1          | Id of the group to mark as selected in the template.
`template`      | `string` | 'group_list_item.xml' | Item template used for each group.
`template_path` | `string` | Defined in 'config.php' | Path where to look for template.
`local`         | `int`    | 0           | If `template` is located in module's default path. Overrides `template_path`.

Available template parameters per group:

**Param name** | **Type** | **Description**
---------------|----------|----------------
`id`           | `int`    | Unique group id.
`text_id`      | `string` | User specified textual id.
`title`        | `array`  | Titles in every language.
`description`  | `array`  | Descriptions in every language.
`selected`     | `int`    | Id of the currently selected group (or -1).
`item_change`  | `string` | Backend hyperlink for editing the group.
`item_delete`  | `string` | Backend hyperlink for deleting the group.


### 1.5. `get_rating_image`

Outputs a rating image (PNG) for an article directly. This action is meant to be used as the `src` of an
`<img>` tag (the URL is produced by `show_rating_image`), not embedded in a template. It reads its parameters
from the request:

**Param name** | **Type** | **Default** | **Description**
---------------|----------|-------------|----------------
`id`           | `int`    |             | Unique article id.
`type`         | `int`    | 1           | Image style: `1` - stars, `2` - circles.


### 1.6. `show_rating_image`

Embeds a rating image for an article using the `rating_image.xml` template. The template receives the URL of the
generated image (pointing at `get_rating_image`) and the numeric rating.

	<cms:module
		name="articles"
		action="show_rating_image"
		id="0"
		type="1"
	/>

**Param name** | **Type** | **Default** | **Description**
---------------|----------|-------------|----------------
`id`           | `int`    |             | Unique article id.
`type`         | `int`    | 1           | Image style: `1` - stars, `2` - circles.

Available template parameters:

**Param name** | **Type** | **Description**
---------------|----------|----------------
`url`          | `string` | URL of the generated rating image.
`rating`       | `float`  | Rating in range of 0 to 5, rounded to two decimals.


## 2. AJAX functions

All AJAX functions are invoked through the front controller (`?section=articles&action=...`) and return a JSON
encoded object. Every response contains `error` (boolean) and `error_message` (string) fields.

### 2.1. `json_article`

Returns a single article matching the given conditions.

**Param name**  | **Type** | **Default** | **Description**
----------------|----------|-------------|----------------
`id`            | `int`    |             | Unique article id.
`text_id`       | `string` |             | One or more textual ids, comma-separated.
`order_by`      | `string` | 'id'        | Field names to sort by, comma-separated.
`order_asc`     | `int`    | 1           | Sort direction (0 - descending, 1 - ascending).
`type`          | `int`    | 1           | Rating image style used to build `rating_image` (1 - stars, 2 - circles).
`all_languages` | `int`    | 0           | When 1, `title` and `content` are returned for all languages; otherwise only the current language is returned and `content` is parsed from Markdown to HTML.

The response holds the article under the `item` key with the fields `id`, `text_id`, `timestamp`, `date`,
`time`, `title`, `content`, `author`, `visible`, `views`, `votes_up`, `votes_down`, `rating` (0 to 10) and
`rating_image`.

### 2.2. `json_article_list`

Returns a list of articles. Accepts the same selection parameters as `show_list` (`limit`, `id`, `text_id`,
`order_by`, `random`, `order_asc`, `only_visible`, `group`) plus `all_languages` as described above. Articles are
returned under the `items` key.

### 2.3. `json_group`

Returns a single group. Requires either `id` or `text_id`; the group is returned under the `group` key.

### 2.4. `json_group_list`

Returns all groups (optionally filtered by the `visible` parameter) under the `items` key, each with `id`,
`text_id`, `title`, `description` and `visible`.

### 2.5. `json_vote`

Records a vote for an article. Only one vote per client address per article is allowed; a repeated vote returns
an error.

**Param name** | **Type** | **Description**
---------------|----------|----------------
`id`           | `int`    | Unique article id.
`value`        | `int`    | Vote value: `1` for an up vote, `-1` for a down vote.

On success the response includes the recomputed `rating` (0 to 10).
