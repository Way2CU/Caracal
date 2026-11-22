# Module Title - Events

Events are registered by a module in its constructor with `Events::register()` and other modules subscribe to
them with `Events::connect()`. Callbacks receive the parameters listed for each event and may return a value;
`Events::trigger()` returns the list of all callback return values to the code that raised the event.

```php
Events::connect('module-name', 'event-name', 'callback_method', $this);
```

Note that the first argument is the *event namespace* under which the module registered its events. It is
usually the module name but does not have to be (for example `head_tag` registers under `head-tag`).

Summary:

1. Event name


## 1. Event name: `event-name-in-module`

Short description of event and when it happens. Mention anything a listener needs to know about the state of
the system at that point, such as whether data has already been stored or whether the response has already been
sent.

Triggered from: short description of code paths that raise the event (backend action, AJAX call, frontend
tag, etc.).

Parameters passed to callback function:

**Param name**   | **Type** | **Description**
-----------------|----------|----------------
`required-param` | `int`    | Param description
`optional-param` | `string` | Param description. Mention when it can be `null`.

Return value: description of return type, or `ignored` when the module does not use callback results.

Known listeners: optional list of modules in this repository that connect to the event. Useful as reference
implementations.
