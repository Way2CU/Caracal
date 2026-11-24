ALTCHA
======

Module `altcha` protects forms from automated submissions with
[ALTCHA](https://altcha.org/) proof-of-work. Visitor's browser has to spend
a few seconds solving a challenge before form can be submitted, no images
or puzzles are involved.

Everything is provided by the module itself: the widget, its script and
styles, challenges and their verification. Nothing is loaded from or sent to
external services and no account is needed. Official ALTCHA widget is not
used, module only follows its challenge format.


Usage
-----

Widget is shown with `show` action and has to be placed inside of a form:

```xml
<cms:module name="altcha" action="show"/>
```

Supported parameters:

- `field_name` - name of form field widget stores its result in, `altcha`
  by default;
- `auto` - when to start verification: `off` (visitor clicks the widget),
  `onfocus`, `onload` or `onsubmit`;
- `display` - `standard` widget is shown in the form, `invisible` one works
  in the background and starts verification on submit when `auto` is `off`.

Submitted value is verified on server side with:

```php
$altcha = altcha::get_instance();
$valid = $altcha->is_valid($_REQUEST['altcha']);
```

Value must be passed as submitted, without sanitizing it first. Each challenge
can be verified only once, regardless of the outcome, and only from session
it was issued to.

Widget takes care of the form it is in. Submission is held back until form
is verified and continues on its own afterwards. Widget returns to its initial
state when form is reset. Forms submitted through JavaScript should trigger
`submit-rejected` event on form element when server doesn't accept the data,
so visitor can verify again.

Contact form module has dedicated field type for the widget and takes care
of all of the above. Field is shown in `div` with class `altcha-field`
instead of `label`. Field label is shown only with `standard` widget and
whole field is hidden when widget is `invisible`.


Styling
-------

Widget is rendered from `templates/widget.xml` and styled by
`include/altcha.css`. It takes font and text color from the form it is in,
so it fits light and dark forms without configuration. Texts come from
module's language files.

Container `div.altcha-widget` has current state in `data-state` attribute:
`unverified`, `verifying`, `verified`, `error` or `expired`. Control inside
is made of `div` and `span` elements on purpose, as site styles for labels
and inputs would otherwise apply to it.

Forms which use grid areas need one for `div.altcha-field` as well, otherwise
field ends up after the last defined area.


Settings
--------

Settings are available in backend under _ALTCHA_ menu item:

- **Iterations per attempt** - number of PBKDF2 iterations for each attempt,
  5000 by default;
- **Minimum** and **maximum number of attempts** - range from which the
  number of attempts browser has to make is randomly selected, 5000 to 10000
  by default. Larger numbers make verification last longer;
- **Challenge lifetime** - number of seconds challenge can be used for,
  600 by default;
- **Start verification** - default for `auto` parameter;
- **Widget display** - default for `display` parameter;
- **Generate new signing key** - replaces random key used for signing
  challenges, which is created when module is initialized. This invalidates
  challenges which were not used yet.

Parameters specified in `cms:module` tag take priority over widget options
set in backend.


How it works
------------

1. Widget requests challenge from `?section=altcha&action=json_challenge`.
   Module picks secret counter, derives key from it with PBKDF2/SHA-256 and
   returns first half of that key together with signed challenge parameters.
   Challenge is remembered in visitor's session. Response is never stored in
   page cache.
2. Workers started from `include/worker.js` derive keys for counters starting
   from zero until one starts with received key prefix. Widget then stores
   challenge and solution in its hidden field.
3. On submission `is_valid` checks signature of challenge parameters, makes
   sure challenge was issued to the same session, is not expired or already
   used, and derives the key once to confirm the solution.

Browser needs Web Crypto, which is available only on pages served through
HTTPS or from `localhost`.
