# Gallery - Events

Events are registered under the `gallery` namespace and are raised by backend management actions. They allow
other modules to keep derived data (caches, sprites, references) in sync with gallery content.

```php
Events::connect('gallery', 'image-deleted', 'handle_image_deleted', $this);
```

Summary:

1. Image uploaded
2. Image changed
3. Image deleted
4. Group created
5. Group changed
6. Group deleted

All `data` parameters are the arrays that were passed to the database manager, so multi-language fields
(`title`, `description`) are arrays keyed by language code.


## 1. Image uploaded: `image-uploaded`

Fired after one or more images were uploaded through the backend and stored in the database.

Triggered from: backend upload action (`upload_image_save()`), after files are written to disk and database
rows are updated.

Parameters passed to callback function:

**Param name** | **Type**     | **Description**
---------------|--------------|----------------
`id_list`      | `array`      | List of newly created image ids.
`data`         | `array|null` | For single uploads, the stored data: `group`, `text_id`, `title`, `description`, `visible`, `slideshow`. For bulk uploads this is `null` and only the group is set.
`group`        | `int|null`   | Id of the group images were assigned to, or `null`.

Return value: ignored.


## 2. Image changed: `image-changed`

Fired after an existing image's properties were saved from the backend. Only metadata changes raise this event,
the image file itself is not replaced.

Triggered from: backend save action (`save_image()`).

Parameters passed to callback function:

**Param name** | **Type** | **Description**
---------------|----------|----------------
`id`           | `int`    | Image id.
`data`         | `array`  | Stored data: `text_id`, `title`, `group`, `description`, `visible`, `slideshow`.

Return value: ignored.


## 3. Image deleted: `image-deleted`

Fired after an image row was removed from the database.

Triggered from: backend delete confirmation (`delete_image_commit()`).

Parameters passed to callback function:

**Param name** | **Type** | **Description**
---------------|----------|----------------
`id`           | `int`    | Id of the removed image. The row no longer exists when the event fires.

Return value: ignored.


## 4. Group created: `group-created`

Fired after a new image group was inserted.

Triggered from: backend group save action (`save_group()`) when no id was supplied.

Parameters passed to callback function:

**Param name** | **Type** | **Description**
---------------|----------|----------------
`id`           | `int`    | Id of the new group.
`data`         | `array`  | Stored data: `text_id`, `name`, `description` and, when supplied, `thumbnail`.

Return value: ignored.


## 5. Group changed: `group-changed`

Fired after an existing group was updated.

Triggered from: backend group save action (`save_group()`) when an id was supplied.

Parameters passed to callback function:

**Param name** | **Type** | **Description**
---------------|----------|----------------
`id`           | `int`    | Group id.
`data`         | `array`  | Stored data, same keys as `group-created`.

Return value: ignored.


## 6. Group deleted: `group-deleted`

Fired after a group and **all images belonging to it** were removed from the database. No `image-deleted`
events are raised for the individual images.

Triggered from: backend delete confirmation (`delete_group_commit()`).

Parameters passed to callback function:

**Param name** | **Type** | **Description**
---------------|----------|----------------
`id`           | `int`    | Id of the removed group.

Return value: ignored.
