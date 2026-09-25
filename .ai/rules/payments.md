---
paths:
  - 'app/Payments/**'
---

# Payments

## Drivers are pure gateway adapters; only the actions write payment state
Payment, refund, subscription and dispute state (status, amounts, ledger transactions) is written only by App\Payments\Actions. Start actions (StartCheckout, StartSubscription, RefundPayment, RecordManualPayment) create rows as Pending/Incomplete and mark them Failed only on a definite refusal (GatewayException, never GatewayUnavailable, whose outcome is unknown). Every later transition (paid, captured, refunded, canceled, won/lost) comes from ReconcilePayment/ReconcileSubscription/ReconcileDispute re-reading the gateway, so webhooks, returns and the scheduler converge on one answer whatever order they arrive in.

Drivers (app/Payments/Drivers/**) talk to the gateway and return data; they never set status or write the ledger. Their only writes are gateway references: catalogue refs (recordGatewayRef on plans/prices, tax-rate gateway_refs), the user's Stripe customer id (billing_customers), and the Demo gateway's simulated remote state in metadata['demo'], which stands in for the gateway's own database. A driver that "helpfully" updates a payment bypasses the row lock, the transition guards and the audit trail.
