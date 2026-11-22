Stripe Payment Method
=====================

Module `stripe_payment` integrates [Stripe Checkout](https://docs.stripe.com/payments/checkout)
with the shop module. Buyer pays on page hosted by Stripe so no card data
ever touches the site. Supported are regular payments, delayed payments
(authorize now, capture later) and subscriptions based on recurring prices
defined in Stripe. No Stripe PHP library is required, module talks to REST
API directly.


Settings
--------

Settings are available in backend under _Shop → Payment methods → Stripe_.

- **Secret key** - API key from Stripe dashboard (`sk_test_...` or `sk_live_...`);
- **Webhook signing secret** - secret of the webhook endpoint (`whsec_...`).

Webhook endpoint must be added in Stripe dashboard pointing to URL shown in
settings window (`?section=stripe_payment&action=webhook`) with following
events enabled:

- `checkout.session.completed`
- `checkout.session.async_payment_succeeded`
- `checkout.session.async_payment_failed`
- `checkout.session.expired`
- `invoice.paid`
- `invoice.payment_failed`
- `customer.subscription.deleted`

Events with invalid signature are rejected. Without webhook, payments are
still confirmed when buyer returns to the site, but asynchronous payment
methods and subscription renewals will not be recorded.


Payment flow
------------

1. Checkout form posts transaction id (and plan id for subscriptions) to
   `?section=stripe_payment&action=checkout-start`.
2. Module creates checkout session with transaction items as line items
   (shipping and handling included) and redirects buyer to Stripe. Session id
   is stored in `stripe_transactions` table. Delayed transactions are created
   with manual capture, subscriptions use `mode=subscription` with selected
   recurring price.
3. Stripe reports outcome through webhook. Session total is compared with
   transaction total before transaction is marked as completed. Repeated
   events are acknowledged without changes.
4. Buyer returns to `action=checkout-confirm`. If webhook didn't arrive yet,
   session is retrieved through API and processed the same way.
5. Delayed transactions stay pending until marked as processed in backend
   which captures authorized payment. Note that Stripe releases uncaptured
   card authorizations after 7 days.

Canceling on Stripe page returns buyer to `action=checkout-cancel` which
marks transaction as canceled and expires the session.


Subscriptions
-------------

Recurring prices are synchronized from Stripe every time plans window is
opened in backend (_Shop → Recurring plans → Stripe_). Price id is used as
plan `text_id`, product name (or price nickname) as plan name. Templates
can list plans with:

```xml
<cms:module module="stripe_payment" action="show_plan_list" template="plan.xml"/>
```

Subscription is created with buyer's existing Stripe customer when known,
otherwise new customer is created by Stripe and remembered in
`stripe_customers` table. First payment is recorded as recurring payment
when session completes, renewals and failures arrive through invoice events.
Canceling subscription from shop deletes it in Stripe.


Database
--------

- `stripe_transactions` - checkout sessions with payment intent, subscription
  and customer identifiers plus local status (`Stripe_TransactionStatus`);
- `stripe_customers` - buyer to Stripe customer mapping;
- `stripe_recurring_plans` - synchronized recurring prices.

Sites which had previous version of the module installed need to disable
and enable it again to recreate tables with new structure.
