# Articles - Database structure

The module uses three tables, created from the SQL files in `modules/articles/queries/`.

Columns of type `ml_varchar` / `ml_text` are multi-language fields. The framework expands each of them into one
real column per configured language (for example `title_en`, `title_de`, ...), so from templates and managers
they are accessed as a single field returning an array keyed by language.


## 1. `articles`

Main storage table for articles.

**Column**    | **Type**          | **Default**         | **Description**
--------------|-------------------|---------------------|----------------
`id`          | `int`             | auto increment      | Unique article id (primary key).
`group`       | `int`             | `NULL`              | Id of the `article_groups` row this article belongs to, or `NULL` when ungrouped.
`text_id`     | `varchar(32)`     | `NULL`              | Optional human-friendly textual id.
`timestamp`   | `timestamp`       | `CURRENT_TIMESTAMP` | Article creation time.
`title`       | `ml_varchar(255)` | `''`                | Multi-language article title.
`content`     | `ml_text`         |                     | Multi-language article body (Markdown).
`author`      | `int`             |                     | Id of the user (system access) who created the article.
`gallery`     | `int`             |                     | Id of an associated gallery entry.
`visible`     | `boolean`         | `0`                 | Whether the article is publicly visible.
`views`       | `int`             | `0`                 | View counter (reserved, currently not used).
`votes_up`    | `int`             | `0`                 | Number of positive votes.
`votes_down`  | `int`             | `0`                 | Number of negative votes.

Indexes: `author`, `group`, `text_id`.


## 2. `article_groups`

Containers grouping multiple articles.

**Column**    | **Type**          | **Default**    | **Description**
--------------|-------------------|----------------|----------------
`id`          | `int`             | auto increment | Unique group id (primary key).
`text_id`     | `varchar(32)`     | `NULL`         | Optional human-friendly textual id.
`title`       | `ml_varchar(255)` | `''`           | Multi-language group title.
`description` | `ml_text`         |                | Multi-language group description.
`visible`     | `boolean`         | `1`            | Whether the group is publicly visible.

Indexes: `text_id`.


## 3. `article_votes`

Records individual votes so a single client address can vote only once per article.

**Column**  | **Type**      | **Description**
------------|---------------|----------------
`id`        | `int`         | Unique vote id (primary key).
`address`   | `varchar(64)` | Client address (IP) that cast the vote.
`article`   | `int`         | Id of the voted article.

Indexes: (`address`, `article`).
