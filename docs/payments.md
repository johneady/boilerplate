# Payments and subscriptions

The site can take one-off payments and sell subscriptions, through Stripe,
PayPal or both. Payments are off when you install. This guide covers how to
switch them on, connect a gateway and start charging.

## Switching payments on

Open **Admin → Settings → Payments**:

1. **Accept payments:** turn this on. While it is off, payment links return
   "not found" and the payment screens are hidden from the admin panel.
2. **Mode:** start in **Sandbox**. Sandbox uses each gateway's test
   credentials and moves no real money. Each payment records the mode it was
   made in, so test payments never mix with real ones. Switch to **Live**
   when you are ready to take real money.
3. **Currency:** Canadian dollar (CAD) or US dollar (USD).
4. **Offer Stripe** and/or **Offer PayPal:** the gateways your customers can
   choose from.
5. **Record manual payments:** turn this on to record money you received
   outside the site, such as an Interac e-Transfer, a cheque or cash.

Once payments are on, a **Payments** menu appears in the admin panel.

## Connecting Stripe

Stripe takes cards, plus Apple Pay and Google Pay, on its own checkout page.

1. In your [Stripe dashboard](https://dashboard.stripe.com), open
   *Developers → API keys*. Turn on *Test mode* to see your test key, which
   starts with `sk_test_`.
2. On the Payments tab, press **Stripe credentials**. Paste the key into
   **Sandbox secret key** (or **Live secret key**, which starts with
   `sk_live_`) and enter your own password to save it.
3. Press **Connect webhooks**. The site registers itself with Stripe for the
   current mode and saves the signing secret.

## Connecting PayPal

PayPal needs a PayPal Business account. You can upgrade a Personal account
for free, and it keeps its login.

1. In the [PayPal Developer dashboard](https://developer.paypal.com), open
   *Apps & Credentials* and create an app. Do this under *Sandbox* to test,
   and again under *Live* for real payments.
2. On the Payments tab, press **PayPal credentials**. Paste the app's
   **client ID** and **client secret** for the matching mode, and enter your
   password.
3. Press **Connect webhooks**. The site registers itself with PayPal and saves
   the webhook ID.

**Webhooks** are how the gateway tells the site that a payment succeeded, a
refund went through or a subscription renewed. Without them, payments stay
"pending". **Connect webhooks** only works when the site is on a public
`https://` address. Press it again after switching modes, or after the site's
address changes. If you'd rather set webhooks up by hand, the Payments tab
lists the address to use for each mode.

## Getting paid

- **Payment links** (Payments → Payment links): create a link for a fixed
  amount, or let the customer enter the amount. A link is either **single
  use** (it closes once paid) or **reusable**. Send it to the customer, who
  pays at `/pay/...` without needing an account.
- **Plans** (Payments → Plans): subscriptions. Give a plan a name, a list of
  features, a price for each billing period (for example monthly and yearly)
  and an optional free trial. Plans that are **on offer** appear on the
  `/pricing` page. Press **Sync to gateways** after you change a plan, so
  Stripe and PayPal bill the same prices.
- **Tax rates** (Payments → Tax rates): the taxes added to taxable items, such
  as GST/HST. Your registration number is printed on receipts.

Every payment gets a receipt with a PDF download. Subscribers manage their own
subscription from their account, under *Settings → Billing*.

## Refunds, holds and disputes

Open a payment under **Payments → Payments** to:

- **Refund** it, in full or in part.
- **Capture** or **Void** a held payment. A held payment is authorised but not
  yet charged. Capture charges it; Void releases the customer's money.
- **Download receipt**.

**Disputes** (chargebacks) appear under Payments → Disputes. **Respond at the
gateway** opens the dispute in Stripe's or PayPal's dashboard, where you submit
your evidence. The site updates when the gateway reports the outcome.

Under Payments → Subscriptions you can cancel a subscription at the end of
its period (the subscriber keeps access until then) or cancel it at once.
Cancelling never refunds anything automatically. Refund the last payment
from its own page if you need to.

## Who can do what

Administrators can do everything. A **bookkeeper** can see payments, refunds,
subscriptions, disputes and tax rates, but cannot change anything. A
**manager** can also refund, capture and void payments, and manage payment
links and subscriptions. Only administrators can change the payment settings,
credentials and plans. See [Users and roles](users-and-roles.md).

## Troubleshooting

| Problem | What to check |
| --- | --- |
| **Connect webhooks** fails | The site must be reachable on `https://` from the internet. A site on your own computer or behind a password cannot receive webhooks. |
| Payments stay pending | Webhooks for the current mode. Press **Connect webhooks** again, then check **Payments → Webhook events** for errors. |
| A gateway isn't offered at checkout | Its **Offer** switch, and a saved key for the current mode. |
| Anything else | **Admin → Settings → Diagnostics** lists payment settings that need attention. |
