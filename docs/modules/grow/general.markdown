Grow Payment Method
===================

Module `grow` integrates [Grow](https://developers.grow.business/) (formerly
Meshulam) hosted payment page with the shop module. It supports regular
payments, payments split in installments and delayed (J4/J5) payments.
Recurring payments are not implemented.


Settings
--------

Settings are available in backend under _Shop → Payment methods → Grow_.

- **User ID** (`userId`) - identifier of the business, provided by Grow;
- **Page code** (`pageCode`) - identifier of the payment page, provided by Grow;
- **Maximum number of installments** - when larger than 1, buyer can choose
  to split payment in up to this many installments (`maxPaymentNum`);
- **Use sandbox environment** - when enabled, calls go to
  `sandbox.meshulam.co.il` instead of `secure.meshulam.co.il`.

Grow accepts only HTTPS callback URLs. Notification URL shown in settings
window must be reachable from the internet. No URLs need to be configured
on Grow side as they are sent with each payment process.


Payment flow
------------

1. Checkout form posts transaction id to `?section=grow&action=payment-start`.
2. Module calls `createPaymentProcess` with transaction total, description,
   buyer details and item list. Returned `processId` and `processToken` are
   stored in `grow_transactions` table and buyer is redirected to returned URL.
3. After payment Grow posts notification to `action=payment-notify`. Process
   identifiers from notification are matched against stored ones and paid
   amount is compared with transaction total. Accepted notifications are
   confirmed through `approveTransaction`, otherwise Grow keeps repeating
   them and each attempt is logged.
4. Buyer is sent back to `action=payment-confirm`. If notification didn't
   arrive yet module asks Grow about process status through
   `getPaymentProcessInfo` and accepts payment when process is clearly paid.
5. Regular transactions are marked as completed. Delayed transactions stay
   pending until they are marked as processed in backend, which settles
   suspended charge through `settleSuspendedTransaction`.

Canceling payment on Grow page returns buyer to `action=payment-cancel`
which marks transaction as canceled.


Database
--------

Table `grow_transactions` keeps one row per created payment process:
shop transaction `uid`, process identifiers, transaction identifiers and
`asmachta` received from notification, last four digits of the card and
local status (`Grow_TransactionStatus`).
