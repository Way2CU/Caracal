# Articles

This module provides storage and handling of articles in multiple languages. It can be used for anything from
blog posts to containers of small text blocks. Module, when used with default templates, uses [Markdown][] markup
language.


Summary:

1. Functions
	1.1. Public functions
	1.2. AJAX functions
2. Database structure
3. Contact


## 1. Functions

### 1.1. Public functions

#### 1.1.1. `show`

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
`id`           | `int`    | Unique article id.
`text_id`      | `string` | User specified textual id.
`timestamp`    | `int`    | Unix time stamp of article creation.
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


#### 1.1.2. `show_list`
#### 1.1.3. `show_group`
#### 1.1.4. `show_group_list`
#### 1.1.5. `get_rating_image`
#### 1.1.6. `show_rating_image`


### 1.2. AJAX functions

### 1.2.1. Function name

Function description.

**Param name** | **Type** | **Required** | **Description**
required-param | `int`    | +            | Param description
optional-param | `string` |              | Param description

Description of return type.


## 2. Database structure

### 2.1. Table name

Short description of what is stored in the table. If there are any foreign relations they should be
mentioned as well.

**Field name** | **Type**     | **Size** | **Required** | **Default value** | **Description**
example        | `varchar`    | 250      | +            |                   | Example field description
example        | `ml_varchar` | 250      | +            |                   | Example field description


## 3. Contact

This section is required only for third party developers. Make sure you provide at least one email address.


[Markdown]: http://daringfireball.net/projects/markdown/syntax
