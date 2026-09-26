# Architecture

Status: DRAFT (matches security baseline 0.3.0, approved for development and staging)  
Owner: Prince Caleb (developer), Belis Bell owner (business decisions)  
Version: 0.3.0  
Last reviewed: 2026-09-26

## Context and goals

Users: households and small businesses buying online; institutional buyers (banks, schools, government and private companies) requesting quotes and repeating orders; Belis Bell staff managing catalogue, prices, quotes and orders.

Goals: fast and light on slow networks, full features on phones, trustworthy ordering, quote-based institutional buying, and safe handling of payments and personal data.

Constraints: stack decided (plain PHP with no framework, Tailwind CSS compiled at build time, MySQL); hosting is Namecheap cPanel with the domain at Namecheap; payments through Paystack (test keys first); payment provider not chosen; first build uses mock data and payment test mode only; English only; prices in GHS.

Non-goals for v1: native apps, contract-price accounts, AI, WhatsApp Business API, multi-language content.

## System context

The system has a storefront, an application API, a private database, private object storage for uploads and product images, a staff admin console, a payment provider (hosted checkout and webhooks), email and SMS providers, an edge layer with CDN and WAF, and a CI/CD pipeline. Trust boundaries are TB-001 to TB-008 in `security-baseline.yaml`; the diagram is in section 2 of `.opskeep/security-design/dashboard.html`.

Key boundaries: public internet to edge and API (TB-001); application to private stores (TB-002); application and payment provider including inbound webhooks (TB-003); staff to admin console (TB-004); untrusted uploads (TB-007); mock data must never cross into production (TB-008).

## Components and ownership

| Component | Responsibility | Runtime/owner | Exposure | Data handled | Dependencies |
|---|---|---|---|---|---|
| Storefront (CMP-001) | Presentation, catalogue, cart and quote UI, local drafts | PHP-rendered pages with Tailwind CSS / developer | INTERNET | PUBLIC, session metadata, browser drafts | Application API |
| Application API (CMP-002) | Auth, authorisation, pricing, orders, quotes, uploads, notifications | Plain PHP 8.3 or newer, no framework / developer | INTERNET | CONFIDENTIAL, RESTRICTED references | Database, storage, payment provider, notifications |
| Database (CMP-003) | Accounts, orders, quotes, threads, catalogue, audit log | MySQL on the cPanel account / developer | PRIVATE | CONFIDENTIAL | None |
| Object storage (CMP-004) | Uploads and product images, malware scan | TBD / developer | PRIVATE | CONFIDENTIAL | Scanner |
| Admin console (CMP-005) | Staff tools with MFA and roles | TBD / developer | INTERNET | CONFIDENTIAL | Application API |
| Payment provider (CMP-006) | Hosted payment pages, webhooks | TBD (DEC-002) / Belis Bell owner | THIRD_PARTY | RESTRICTED (held by provider only) | None |
| Notifications (CMP-007) | Email and SMS | TBD / developer | THIRD_PARTY | CONFIDENTIAL (minimal) | None |
| Repo and CI/CD (CMP-008) | Build, checks, deploy via OIDC | TBD / developer | THIRD_PARTY | RESTRICTED (deploy identity) | Hosting |
| Edge (CMP-009) | CDN, WAF, rate limits | TBD / developer | THIRD_PARTY | PUBLIC and pass-through | Hosting |
| Mock seed (CMP-010) | Synthetic products, prices and figures | Repository / developer | INTERNAL | PUBLIC (synthetic) | None |

## Data flows and deployment

Flows FLW-001 to FLW-010 are defined in `security-baseline.yaml` with classification and retention. Notable rules: the server recomputes every price and total (CTL-BIZ-001); card and mobile money details go only to the provider's hosted pages (CTL-PAY-001); order status changes only through verified webhooks (CTL-PAY-002); uploads are scanned before staff can open them (CTL-UPL-001).

Environments: development and staging use mock data and test keys; production is locked. Hosting is a cPanel account at Namecheap used only for the store, with the domain and DNS at Namecheap and, if approved, Cloudflare in front (DEC-014). Application code, uploads, the environment file and backups sit above the web root; only public assets are in it. Deploys use a scoped SSH deploy key (or cPanel Git) with human approval, since OIDC is not available. There is no secret manager, so secrets live in a permission-restricted environment file that is never in the repository. Scheduled work (order emails, retention jobs, backups) runs as cron jobs, not long-running workers.

Application structure (no framework): one front controller and router with deny-by-default authorisation, central CSRF, output-escaping templates, a prepared-statement-only database wrapper and central validation and error handling (CTL-FW-001). Rollback is redeploying the last known-good immutable build; backups and restore drills are required before launch (CTL-REC-001).

## Interfaces and integrations

Interface contracts are `NOT BUILT`. The API will be described in an OpenAPI document during build, and linked from `GUI.md`. Payment goes through a small adapter with a mock mode first, then Paystack test keys, then live keys after GATE-006. Every external call has a timeout, safe retry rules and idempotency keys for payments and order creation. WhatsApp is click-to-chat links only (DEC-008).

Degradation behaviour: if notifications fail, orders still complete and are retried; if the payment provider is down, checkout shows a clear message and keeps the cart; if the scanner is down, uploads wait in quarantine.

## Decisions and change triggers

| Decision ID | Decision | Rationale | Alternatives | Security impact | Status |
|---|---|---|---|---|---|
| DEC-001 | Stack: PHP, Tailwind CSS, MySQL 8 | Owner decision | Next.js with PostgreSQL | Sets how controls are built (CTL-PLAT-001) | DECIDED |
| DEC-011 | No framework, plain PHP with a few vetted libraries | Owner decision | Laravel | Protections a framework supplies are hand-built and tested (CTL-FW-001, THR-021) | DECIDED |
| DEC-012 | Namecheap cPanel hosting and domain | Owner decision | Dedicated host or VPS | Shared tenancy, no secret manager or OIDC, limited logs and scanning (see CTL-PLAT-001, CTL-DEP-001, CTL-ACCT-001) | DECIDED |
| DEC-013 | Company-owned Namecheap, cPanel and mailbox accounts | Prevents the developer being the only owner | Developer-owned accounts | Domain takeover risk (THR-020) | OPEN |
| DEC-014 | Cloudflare or similar in front of the host | WAF, rate limits, caching | Host firewall only | Bot and DDoS protection | OPEN |
| DEC-002 | Paystack for cards and mobile money | Owner decision | Other Ghana-capable providers | RESTRICTED data stays with Paystack; webhook and verify-API controls (CTL-PAY-002) | DECIDED |
| DEC-004 | Who can start a quote request; how institutions are confirmed | Limits abuse and protects uploads | Open guest quotes; accounts only | Auth and upload exposure | OPEN, blocks approval |
| DEC-005 | Retention periods and Data Protection Act duties | Legal duty before real data | None | Privacy | OPEN |
| DEC-008 | WhatsApp click-to-chat links only | Smaller attack surface | WhatsApp Business API | Avoids a new integration | DECIDED |
| DEC-009 | Mock data kept out of production by one seed file, loader guard and CI check | Prevents fake data or test payments in production | None | THR-011 | DECIDED |

Changes that require this document and the baseline to be reassessed: new hosting or provider, any AI feature, WhatsApp Business API, business accounts with contract pricing, storing any payment data, new upload types, new admin capabilities, or any new third-party script on checkout or account pages.
