# Integrations

v1.0.0 can call out, and it can keep working when the other system is down.

- Email is `off`, `log`, or `smtp`. A failed send leaves the quote or invoice in its business state and marks the message failed. `SENT` is used only after the provider accepts the message. `DELIVERED` is not set unless the provider reports delivery.
- WhatsApp is a `wa.me` link logged as prepared, not a WhatsApp Business connection.
- Accounting sync is optional. An empty provider means no sync. A failure leaves the invoice issued and queues an integration issue. It is not retried on its own beyond the cron queue.
- Payment links take the amount from the invoice balance. A browser return does not create a payment. A webhook must match the signature, reference, currency, and amount. The same provider event creates one payment.
- Public leads use `POST /api/public/leads` with a honeypot, a minimum fill time, and a rate limit. See `docs/website-leads.md`.
- API clients are under Administration. Secrets are shown once and stored hashed.
- Assisted text is off until a provider is configured. It cannot change a price, approve a quote, move stock, issue an invoice, or delete a customer. Text inside an uploaded document is data, not a command.

Leave unfinished connectors disabled. Do not point webhooks at private network addresses. The connector refuses link-local and plain HTTP targets.

SPF, DKIM, and DMARC are set on the domain at the DNS host for whatever SMTP server you choose. Sign-Forge does not embed those records.
