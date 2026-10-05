# Contact Form - Events

Events are registered under the `contact_form` namespace.

```php
Events::connect('contact_form', 'submitted', 'handle_submit', $this);
```

Summary:

1. Email sent
2. Submitted

The two events look alike but fire at different levels. `email-sent` is raised by the *mailer* classes and
fires for every successfully delivered message, including messages sent by other modules through the contact
form mailers (shop notifications, backend account verification and password recovery). `submitted` is raised by
the contact form module itself once per *form submission*, after all mailers configured for the form have run.
A single form submission with two mailers therefore produces two `email-sent` events and one `submitted` event.


## 1. Email sent: `email-sent`

Fired by a mailer at the end of `send()` when the message was accepted for delivery. It is **not** fired when
sending fails, when bot detection rejects the request, or when required message parts are missing.

Triggered from: `send()` of the system mailer (`units/system_mailer.php`) and of the Mandrill mailer
(`modules/mandrill/units/mailer.php`). Mailers provided by other modules are expected to raise this event
themselves; a mailer that does not will not produce it (the SendGrid mailer currently does not).

Parameters passed to callback function:

**Param name** | **Type** | **Description**
---------------|----------|----------------
`mailer`       | `string` | Name of the mailer that delivered the message, for example `system` or `mandrill`.
`recipient`    | `string` | Recipient address(es) as a single string. The system mailer joins all `To` recipients with `, `; the Mandrill mailer passes only the first recipient address.
`subject`      | `string` | Message subject as given to the mailer, before variable substitution.
`data`         | `array`  | Variables set with `set_variables()`. For form submissions this is the field name → value map, for other senders whatever they supplied.

Return value: ignored.


## 2. Submitted: `submitted`

Fired after a contact form submission has been fully processed: all required fields were present, the
submission and its field values were stored in the database, and the configured mailers were run. The event is
raised before the response (HTML or JSON) is generated, but listeners cannot alter that response.

Triggered from: the `submit` action of the module (`contact_form.php`, submission handler).

The event fires only when sending succeeded. Note that the check is made against the result of the *last*
mailer in the list; when a form is associated with several mailers and only an earlier one failed, the event
still fires.

Parameters passed to callback function:

**Param name** | **Type** | **Description**
---------------|----------|----------------
`sender`       | `array`  | Sender configured in module settings: `array('name' => string, 'address' => string)`.
`recipients`   | `array`  | List of configured recipients, each `array('name' => string, 'address' => string)`.
`template`     | `array`  | Resolved email template in the current language: keys `name`, `subject`, `plain_body`, `html_body`. Can be `null` when the form references a missing template.
`data`         | `array`  | Submitted field values keyed by field name. Includes computed fields such as `site-version` and values of hidden/transfer fields.

Return value: ignored.

Known listeners: `ontop` (pushes a notification for every submission).
