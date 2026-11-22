# Backend - Events

Events are registered under the `backend` namespace. They fall into two groups: *integration* events that let
modules plug their UI into the administration interface, and *user account* events raised when system users are
created, changed or removed.

```php
Events::connect('backend', 'add-menu-items', 'add_menu_items', $this);
```

Summary:

1. Add menu items
2. Add tags
3. Sprite include
4. User create
5. User change
6. User password change
7. User delete

Account events pass a user object as returned by the user manager. Its properties match the columns of the
`system_access` table: `id`, `username`, `fullname`, `first_name`, `last_name`, `email`, `level`, `verified`,
`agreed`, plus `password` (hash) and `salt`. Listeners must not log or forward the latter two.


## 1. Add menu items: `add-menu-items`

Fired when the backend main menu is rendered. Listeners register their entries by creating `backend_MenuItem`
objects and adding them with `backend::addMenu()`.

Triggered from: the `cms:menu_items` tag of the backend template (`tag_MainMenu()`), once per backend page load.

Parameters passed to callback function: none.

Return value: ignored.

Known listeners: nearly every module with a backend UI, e.g. `articles`, `gallery`, `shop`, `links`, `news`.


## 2. Add tags: `add-tags`

Fired before the backend page head is rendered, giving modules a chance to include backend-only scripts and
styles through the `head_tag` module. It is raised in two situations: when the main backend interface is shown,
and when a module's content is rendered *enclosed* in a standalone template (the `enclose` request parameter,
used to embed a backend window in an external page).

The event is raised only when the `head_tag` module is loaded.

Triggered from: `showBackend()` and the enclosed-content branch of `transfer_control()`.

Parameters passed to callback function: none. Listeners call `head_tag::add_tag()`.

Return value: ignored.

Known listeners: `articles`, `gallery`, `shop`, `contact_form`, `youtube`, `language_menu` and the backend
module itself.


## 3. Sprite include: `sprite-include`

Fired while the backend's inline SVG sprite sheets are printed. Listeners print their own SVG sprite file so
that their icons can be referenced by id from backend templates.

Triggered from: the `cms:sprites` tag of the backend template (`tag_Sprites()`), after the backend's own sprites were printed.

Parameters passed to callback function: none. Listeners output SVG directly with `print`.

Return value: ignored.

Known listeners: `gallery`, `downloads`, `youtube`.


## 4. User create: `user-create`

Fired after a new system user was inserted, both when created by an administrator from the backend and when a
visitor registers through the unprivileged (frontend) registration flow. In the second case the account may
still be unverified. The password has already been stored when the event fires.

Triggered from: backend user save action (`saveUser()`) and the frontend registration action
(`saveUnpriviledgedUser()`).

Parameters passed to callback function:

**Param name** | **Type** | **Description**
---------------|----------|----------------
`user`         | `object` | Freshly loaded user record.

Return value: ignored.

Known listeners: `shop` (creates a matching buyer record).


## 5. User change: `user-change`

Fired after an administrator saved changes to an existing user from the backend.

**Note:** the user object passed to listeners is loaded *before* the update is written, so it holds the
previous values. Reload the record by `id` if the new data is needed.

Triggered from: backend user save action (`saveUser()`).

Parameters passed to callback function:

**Param name** | **Type** | **Description**
---------------|----------|----------------
`user`         | `object` | User record as it was before the change.

Return value: ignored.


## 6. User password change: `user-password-change`

Fired after a user's password was changed by any of the available flows: the logged-in user changing their own
password in the backend, the frontend "change password" action, or completing the password recovery process
(which also marks the account as verified).

Triggered from: `savePassword()`, `saveUnpriviledgedPassword()` and `saveRecoveredPassword()`.

Parameters passed to callback function:

**Param name** | **Type** | **Description**
---------------|----------|----------------
`user`         | `object` | User record. In the recovery flow it is the record loaded before the change; otherwise it is reloaded after the change.

Return value: ignored.


## 7. User delete: `user-delete`

Fired **before** the user row is removed from the database, so listeners can still read the record and clean up
related data.

Triggered from: backend user delete confirmation (`deleteUser_Commit()`).

Parameters passed to callback function:

**Param name** | **Type** | **Description**
---------------|----------|----------------
`user`         | `object` | User record about to be deleted.

Return value: ignored.
