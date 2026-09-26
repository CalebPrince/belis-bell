# Proposed baseline change CHG-003 (DRAFT, not approved, nothing built)

Written 2026-09-27. These are the known gaps that cannot be built inside the approved baseline 0.5.0, because each one changes payments, permissions, business rules or adds a third party. The approved baseline says any such change suspends approval until the owner approves a new version, so **none of this is built or in `security-baseline.yaml`**. To go ahead, reply "Approve CHG-003" for all of it, or name the items you want. I will then write baseline 0.6.0, suspend, get your explicit approval of that version, and only then build.

## 1. Refunds through Paystack

- **What:** the owner can refund a paid order, in full or in part, from the admin order page. The server calls Paystack's refund API for the original transaction only, records the result, and emails the customer.
- **Why it is a change:** it moves money out (CTL-PAY-001/002 cover taking payments only) and adds a new Paystack call and a new webhook event type.
- **Controls I would add:** owner only; a fresh emailed code for every refund; refund amount checked on the server against what was paid and what was already refunded; refunds go only to the original payment; an audit entry and an email to the owner for each refund; refund status confirmed from Paystack, never from a button; a per-day refund total that needs a second confirmation above a limit you choose.
- **Risk:** a taken-over owner account could refund orders. Mitigation is the code, the audit, the owner email and the limit. A passkey for the owner (already recommended) reduces it further.
- **Needs from you:** the daily limit, and whether partial refunds are wanted.

## 2. Separate roles: content, sales, fulfilment

- **What:** today every staff account can edit products (not prices), categories, and see and progress every order. I would add roles: content (products and categories), fulfilment (orders and delivery progress), sales (orders, later quotes), each seeing only what it needs, and the owner assigns them.
- **Why it is a change:** it changes admin permissions.
- **Controls I would add:** a role field checked on the server for every admin route, tests that each role is refused everything else, role changes need a fresh code and are audited, and a role change ends that person's sessions.
- **Needs from you:** the roles you actually want and what each may do.

## 3. Real stock counts

- **What:** stock becomes a number per size instead of a label. A paid order reduces it; at zero the size shows Out of stock; the owner or staff can adjust it with a reason that is logged. Orders are refused if they would take stock below zero.
- **Why it is a change:** it changes business rules and money-related behaviour (CTL-BIZ-001): what can be sold.
- **Open choices:** reserve stock when an order is created or only when it is paid (reserving stops overselling but can lock stock behind unpaid orders, which the daily job would release after 24 hours); low-stock threshold.
- **Needs from you:** reserve or not, and the low-stock number.

## 4. Full breached-password check

- **What:** replace the short built-in list with the Have I Been Pwned range check: the server sends only the first 5 characters of a SHA-1 hash of the password, never the password, and refuses passwords found in breaches.
- **Why it is a change:** it is a new third-party call from the server (CTL-SC-002 treats third parties as needing approval). The browser does not change and the content security policy stays as it is.
- **Controls I would add:** 3 second timeout, TLS verified, no password or full hash leaves the server, and if the service is down the built-in list still applies and sign-up continues (fail open, logged).
- **Needs from you:** approval to call that service, or a decision to keep the built-in list.

## 5. Second approver for bulk price changes

- **What:** the baseline already asks for two people to approve a bulk price change, but there is no bulk change feature. If you want one (for example "raise every price in a category by 5 percent"), it would be built with a second approver, a preview of every affected price, and history entries per size.
- **Why it is a change:** it adds a bulk pricing action. Single price changes already work and are covered.
- **Needs from you:** whether you want bulk price changes at all, and who the second approver is (the owner needs a second account).

## Not changes, just waiting on you

- Paystack test run: needs a `sk_test_` key (enter it on the Settings page).
- Real email: needs the mailbox details for Namecheap SMTP (enter them on the Settings page, then use "Send a test email to me").
- Terms and Privacy pages: need your text.
- Scheduling `bin/reconcile-payments.php`, CI runs and the `APPROVED_DIGEST` variable: need the hosting and GitHub set up.
