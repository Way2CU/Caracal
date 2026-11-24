ALTCHA
======

Module `altcha` protects forms from automated submissions with
[ALTCHA](https://altcha.org/) proof-of-work. Visitor's browser has to spend
a few seconds solving a challenge before form can be submitted, no images
or puzzles are involved.

Challenges are created and verified by the module itself. No external service
or account is needed. Only the official widget script is loaded from jsDelivr
CDN, pinned to a specific version and protected with integrity hash. When
changing widget version both `WIDGET_URL` and `WIDGET_INTEGRITY` constants
in module class need to be updated.


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
- `display` - widget presentation: `standard`, `bar`, `floating`, `overlay`
  or `invisible`. Only `standard` widget takes space in the form. Others
  are shown on top of the page while form is being submitted, or not at
  all, and start verification on submit when `auto` is `off`.

Script for the widget is included automatically with the first widget on
the page. Widget language follows language of the page.

Submitted value is verified on server side with:

```php
$altcha = altcha::get_instance();
$valid = $altcha->is_valid($_REQUEST['altcha']);
```

Value must be passed as submitted, without sanitizing it first. Each challenge
can be verified only once, regardless of the outcome, and only from session
it was issued to. When form submitted through JavaScript is rejected for any
reason, call `reset()` on `altcha-widget` element so visitor can verify again.

Contact form module has dedicated field type for the widget and takes care
of all of the above. Field is shown in `div` with class `altcha-field` instead of
`label`, as widget contains label of its own. Field label is shown only with
`standard` widget and whole field is hidden when widget is `invisible`.


Styling
-------

Widget is part of the page, so site styles for form elements apply to it
as well. Rules for `label` and `input` elements inside of a form are the
usual cause of widget looking broken.

Colors of the widget follow `color-scheme` property. On dark forms set it
to `dark`, otherwise widget is shown with light background:

```css
form altcha-widget {
	--altcha-max-width: 100%;

	color-scheme: dark;
}

form altcha-widget label {
	display: inline;
}
```

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
2. Browser derives keys for counters starting from zero until one starts
   with received key prefix, then stores challenge and solution in hidden
   form field.
3. On submission `is_valid` checks signature of challenge parameters, makes
   sure challenge was issued to the same session, is not expired or already
   used, and derives the key once to confirm the solution.


Content security policy
-----------------------

Module adds `https://cdn.jsdelivr.net` to `script-src` and `blob:` to
`worker-src`, as widget creates its workers from bundled code. Widget also
adds its styles to the page through `style` element. Sites which restrict
`style-src` need to allow inline styles for widget to be shown properly.
