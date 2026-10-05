# Shop - Events

Events are registered under the `shop` namespace. They cover the shopping cart, checkout, transaction status
changes, recurring payments and item management.

```php
Events::connect('shop', 'transaction-completed', 'handle_transaction', $this);
```

Summary:

1. Shopping cart changed
2. Before checkout
3. Transaction completed
4. Transaction canceled
5. Recurring payment events
6. Item added
7. Item changed
8. Item deleted

Transaction, plan and payment parameters are plain objects as returned by the respective item managers; their
properties match the columns of the `shop_transactions`, `shop_transaction_plans` and `shop_recurring_payments`
tables (see the module's `queries/` directory).


## 1. Shopping cart changed: `shopping-cart-changed`

Fired whenever the contents of the shopping cart stored in the session change. The new cart is already in
`$_SESSION['shopping_cart']` when the event fires.

Triggered from: cart-related JSON actions (`json_AddItemToCart`, `json_RemoveItemFromCart`,
`json_ChangeItemQuantity`, `json_ClearCart`, `json_SetCartFromTransaction`, `json_SetItemAsCart`) and the
`set_item_as_cart` and `set_cart_from_template` template actions.

Parameters passed to callback function: none. Listeners read the cart from the session.

Return value: ignored.


## 2. Before checkout: `before-checkout`

Fired on the initial transition to the checkout page, after buyer and address information has been collected
and before the payment form is generated. It lets a payment method take over the checkout process and send the
buyer to an external location (e.g. PayPal Express). The event is raised only when the checkout is entered from
the information stage, to avoid redirect loops.

Triggered from: the `show_checkout_form` action and the `cms:checkout_form` tag registered during checkout
(both handled by `tag_CheckoutForm()`).

Parameters passed to callback function:

**Param name**   | **Type** | **Description**
-----------------|----------|----------------
`payment_method` | `string` | Name of the payment method selected by the buyer. Listeners should return `false` unless this is their own method.
`return_url`     | `string` | Absolute URL the external service should send the buyer back to on success.
`cancel_url`     | `string` | Absolute URL the external service should send the buyer back to on cancellation.

Return value: `boolean`. When **any** listener returns a truthy value the shop assumes a redirect has been
initiated, renders the "redirecting" message instead of the checkout form and stops processing.

Known listeners: `paypal` (express checkout).


## 3. Transaction completed: `transaction-completed`

Fired when a transaction's status is set to `TransactionStatus::COMPLETED`. The new status has already been
written to the database and the transaction object reflects it. After the event the pending transaction is
removed from the session and, depending on module settings, a notification email is sent to the buyer.

Triggered from: `setTransactionStatus()`, which is called by payment method callbacks (IPN and similar) and by
the backend transaction management window.

Parameters passed to callback function:

**Param name** | **Type** | **Description**
---------------|----------|----------------
`transaction`  | `object` | Transaction record. Useful properties: `id`, `uid`, `buyer`, `address`, `type`, `status`, `currency`, `total`, `shipping`, `handling`, `payment_method`, `delivery_method`, `delivery_type`, `remote_id`, `timestamp`. Use `Transaction::get_buyer()` to resolve the buyer.

Return value: ignored.

Known listeners: `ontop` (pushes a notification with buyer and totals).


## 4. Transaction canceled: `transaction-canceled`

Fired when a transaction's status is set to `TransactionStatus::CANCELED`. Same conditions and parameters as
`transaction-completed`.

Triggered from: `setTransactionStatus()`.

Parameters passed to callback function:

**Param name** | **Type** | **Description**
---------------|----------|----------------
`transaction`  | `object` | Transaction record, see above.

Return value: ignored.


## 5. Recurring payment events

One event is registered per recurring payment status. Which one fires is decided by the status passed to
`addRecurringPayment()`, which payment methods call when a subscription-related notification arrives:

**Status constant**              | **Event name**
---------------------------------|---------------
`RecurringPayment::PENDING`      | `recurring-payment-pending`
`RecurringPayment::ACTIVE`       | `recurring-payment`
`RecurringPayment::SKIPPED`      | `recurring-payment-skipped`
`RecurringPayment::FAILED`       | `recurring-payment-failed`
`RecurringPayment::SUSPENDED`    | `recurring-payment-suspended`
`RecurringPayment::CANCELED`     | `recurring-payment-canceled`
`RecurringPayment::EXPIRED`      | `recurring-payment-expired`

The event fires after the payment row was inserted. It is not raised when the plan id is invalid.

Triggered from: `addRecurringPayment()`.

Parameters passed to callback function (same for all seven events):

**Param name** | **Type** | **Description**
---------------|----------|----------------
`transaction`  | `object` | Transaction the subscription plan belongs to.
`plan`         | `object` | Subscription plan record: `id`, `transaction`, `plan_name`, `trial`, `trial_count`, `interval`, `interval_count`, `start_time`, `end_time`.
`payment`      | `object` | Newly inserted payment record: `id`, `plan`, `amount`, `status`, `timestamp`.

Return value: ignored.


## 6. Item added: `item-added`

Fired after a new shop item was created from the backend and all related data (membership, properties, stock,
gallery) was stored.

Triggered from: backend item save action.

Parameters passed to callback function:

**Param name** | **Type** | **Description**
---------------|----------|----------------
`id`           | `int`    | Id of the new item.

Return value: ignored.


## 7. Item changed: `item-changed`

Fired after an existing shop item was saved from the backend.

Triggered from: backend item save action.

Parameters passed to callback function:

**Param name** | **Type** | **Description**
---------------|----------|----------------
`id`           | `int`    | Item id.

Return value: ignored.


## 8. Item deleted: `item-deleted`

Fired after an item was marked as deleted. Items are soft-deleted (the `deleted` flag is set), so the row still
exists when the event fires; category membership rows have already been removed.

Triggered from: backend item delete confirmation.

Parameters passed to callback function:

**Param name** | **Type** | **Description**
---------------|----------|----------------
`id`           | `int`    | Item id.

Return value: ignored.
