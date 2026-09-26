# Security

Status: APPROVED for development and staging only (2026-09-26, Prince Caleb, Super Admin). Production is locked.  
Baseline version: 0.3.0 (design digest 66848a5d526cf...)  
Canonical record: `security-baseline.yaml`  
Owner: Belis Bell owner (name pending, DEC-010); developer: Prince Caleb  
Last reviewed: 2026-09-26

This file is the human-readable view and blueprint. `security-baseline.yaml` is authoritative for approval, scope, locks, controls, gates and evidence. Nothing below is implemented or verified. The visual review surface is `.opskeep/security-design/dashboard.html`.

## Posture and scope

Risk tier HIGH, mode FULL. Stack: plain PHP with no framework, Tailwind CSS (compiled at build time), MySQL, on Namecheap cPanel hosting with the domain at Namecheap. Reasons: online payments (mobile money and cards), personal data of buyers and institutions, file uploads, a privileged admin console, and government and bank customers. Development lock: UNLOCKED for development and staging with mock data and Paystack test keys. Deployment lock: LOCKED. Production posture: eligible when approved.

Environments: development and staging (mock data, payment test mode) and production (locked). In scope: all components CMP-001 to CMP-010, identities ID-001 to ID-009, assets AST-001 to AST-010. Excluded from v1: AI features, WhatsApp Business API, native apps, approved business accounts, storing any payment instrument.

## What exists in the scaffold (2026-09-26)

Built and unit-tested locally, but not run in CI and not independently verified, so nothing is marked VERIFIED:

- CTL-ENV-001 (now IMPLEMENTED in the baseline): environment guard, mock-row guard, seed and purge scripts that refuse production, release allowlist and release checker. Negative tests confirm production refuses mock rows and a release with seed files or mock markers fails.
- Partial CTL-FW-001: router requires a policy for every route and deny-by-default policy evaluation; one CSRF layer; escaping templates with a test that fails on unescaped output; prepared-statement database wrapper with a test that flags interpolated SQL. No authentication exists yet, so signed-in policies always deny.
- Partial CTL-SC-002: strict CSP with no inline scripts or third-party origins, self-hosted fonts, header tests.
- Partial CTL-SESS-001: hardened session code (not yet used by any signed-in flow).
- Partial CTL-PAY-002: Paystack webhook signature check with tests. The verify-transaction call, order handling and reconciliation are not built.
- Partial CTL-DATA-001: log redaction.

Not built at all: authentication and MFA, authorisation on real objects, uploads and scanning, rate limiting, audit log, monitoring, backups, CI runs, branch protection.

## Baseline controls

All controls are proposed (REQUIRED). Evidence is NOT_YET_BUILT for every one.

| Control ID | Requirement (short) | Owner | Status | Evidence | Next step |
|---|---|---|---|---|---|
| CTL-GOV-001 | No build or deploy until the owner approves this baseline version | Owner | PLANNED | EVD-001 / NOT_YET_BUILT | Owner approval, then CI gate |
| CTL-AUTH-001 | Verified accounts, strong password or passkey, throttling, uniform recovery | Developer | REQUIRED | EVD-008 / NOT_YET_BUILT | Design after DEC-011 |
| CTL-AUTH-002 | Staff MFA, least-privilege roles, step-up for sensitive actions | Developer | REQUIRED | EVD-006 / NOT_YET_BUILT | DEC-003, DEC-007 |
| CTL-SESS-001 | Secure cookies, rotation, timeouts, CSRF tokens | Developer | REQUIRED | EVD-008 / NOT_YET_BUILT | Build with framework (DEC-011) |
| CTL-AUTHZ-001 | Server-side ownership and organisation checks on every object | Developer | REQUIRED | EVD-002 / NOT_YET_BUILT | Cross-account tests first |
| CTL-ORG-001 | Confirmed organisation membership, roles, approval limits | Developer | REQUIRED | EVD-008 / NOT_YET_BUILT | DEC-004 |
| CTL-BIZ-001 | Server computes prices and totals; bulk price changes need two approvers | Developer | REQUIRED | EVD-002 / NOT_YET_BUILT | Build with catalogue |
| CTL-PAY-001 | Paystack-hosted payment entry only; no payment data stored; secret keys server-side only | Owner | REQUIRED | EVD-006 / NOT_YET_BUILT | Paystack test keys first |
| CTL-PAY-002 | Verify Paystack webhook signature and the transaction via the verify API; check amount, currency and reference; idempotent; daily reconciliation | Developer | REQUIRED | EVD-006 / NOT_YET_BUILT | Adapter and tests |
| CTL-UPL-001 | Type, size and content checks; scan; private storage; forced download | Developer | REQUIRED | EVD-002 / NOT_YET_BUILT | DEC-006 |
| CTL-INP-001 | Server validation, parameterised queries, output encoding | Developer | REQUIRED | EVD-002 / NOT_YET_BUILT | Build with framework (DEC-011) |
| CTL-DATA-001 | Minimise, encrypt, redact logs, enforce retention, deletion and export | Owner | REQUIRED | EVD-007 / NOT_YET_BUILT | DEC-005 |
| CTL-API-001 | Rate limits, bot protection, WAF | Developer | REQUIRED | EVD-008 / NOT_YET_BUILT | Edge choice (DEC-012) |
| CTL-SC-001 | Secret and dependency scanning, lockfiles, SBOM, provenance | Developer | REQUIRED | EVD-002 / NOT_YET_BUILT | CI setup |
| CTL-SC-002 | Strict CSP, SRI, minimal third-party scripts | Developer | REQUIRED | EVD-004 / NOT_YET_BUILT | Build with frontend |
| CTL-DEP-001 | OIDC deploys, protected production, separated environments | Developer | REQUIRED | EVD-008 / NOT_YET_BUILT | Hosting choice |
| CTL-ENV-001 | Mock data and test keys cannot run in production | Developer | REQUIRED | EVD-005 / NOT_YET_BUILT | Guard and CI check |
| CTL-AGT-001 | Agents have no production access and cannot merge or approve | Owner | REQUIRED | EVD-003 / NOT_YET_BUILT | Branch protection |
| CTL-COMM-001 | No account, bank or address changes by chat alone; call-back procedure | Owner | REQUIRED | EVD-007 / NOT_YET_BUILT | Written staff procedure |
| CTL-REC-001 | Encrypted backups, tested restore, rollback | Owner | REQUIRED | EVD-007 / NOT_YET_BUILT | Set recovery objectives |
| CTL-AUDIT-001 | Append-only audit log of sensitive actions | Developer | REQUIRED | EVD-009 / NOT_YET_BUILT | Build with API |
| CTL-CLIENT-001 | Browser drafts hold no personal identifiers or payment data, expire, clear on logout | Developer | REQUIRED | EVD-008 / NOT_YET_BUILT | Build with frontend |
| CTL-PLAT-001 | Supported PHP (8.3+), safe PHP settings, code and secrets above the web root, no PHP in upload folders, remote MySQL off, its own cPanel account | Developer | REQUIRED | EVD-008 / NOT_YET_BUILT | DEC-012 |
| CTL-ACCT-001 | Company-owned Namecheap, cPanel and mailbox accounts with MFA, registrar lock, auto-renew, SPF, DKIM and DMARC | Owner | REQUIRED | EVD-008 / NOT_YET_BUILT | DEC-013 |
| CTL-FW-001 | No framework: central router with deny-by-default authorisation, one CSRF layer, auto-escaping templates, prepared-statement-only database wrapper, tested by a security suite | Developer | REQUIRED | EVD-002 / NOT_YET_BUILT | Write checklist and tests first |
| CTL-MON-001 | Security signals routed to a named owner with runbooks | Owner | REQUIRED | EVD-009 / NOT_YET_BUILT | Choose monitoring |

## Identity, data and boundaries

- **Customers and institutions:** verified email or phone, strong password or passkey. Every object is checked server-side for ownership or organisation membership. Institutional membership is confirmed by staff.
- **Staff:** separate accounts, mandatory MFA, role-based least privilege, short admin sessions. Bulk price changes and exports need owner approval; the owner is the only role that can change staff roles or enable live payments.
- **Payments:** card and mobile money details are entered only on provider-hosted pages. Belis Bell keeps provider references, amounts and status. Order status changes only through verified webhooks.
- **Uploads:** untrusted. Scanned, stored privately, served by authorised download only.
- **Secrets:** in a secret manager, separate per environment, never in the repository, logs or client code. Coding agents never hold production secrets.
- **Personal data:** minimised, encrypted, redacted in logs and notifications. The Ghana Data Protection Act, 2012 (Act 843) applies. Retention periods and any registration duties are open (DEC-005) and need qualified advice; this document is not legal advice.
- **Third parties:** payment provider, email and SMS, edge and hosting, CI. Each needs an owner and scoped credentials.

## Gates and evidence

| Gate | Stage | Blocking | Status |
|---|---|---|---|
| GATE-001 Baseline approval | Pre-implementation | Yes | NOT_YET_BUILT |
| GATE-002 PR security checks | Pull request | Yes | NOT_YET_BUILT |
| GATE-003 Protected branch, human review | Merge | Yes | NOT_YET_BUILT |
| GATE-004 Provenance, SBOM, headers | Build | Yes | NOT_YET_BUILT |
| GATE-005 Mock data and test-mode guard | Pre-deploy | Yes | NOT_YET_BUILT |
| GATE-006 Live payment enablement | Pre-deploy | Yes | NOT_YET_BUILT |
| GATE-007 Restore test and privacy readiness | Pre-deploy | Yes | NOT_YET_BUILT |
| GATE-008 Protected production approval and platform hardening | Deploy | Yes | NOT_YET_BUILT |
| GATE-009 Monitoring and audit live | Runtime | Yes | NOT_YET_BUILT |

Shared-hosting and no-framework limits: the host offers no secret manager and no OIDC deploys, so secrets live in a protected file above the web root and deploys use a scoped key with human approval; host logging is limited, so an external uptime monitor and application logs are needed; there is probably no reliable virus scanner, so quote uploads stay off in production until a scanning method is proven (DEC-006); and shared tenancy leaves residual risk THR-019 at HIGH until the owner accepts it or moves to a VPS. Having no framework means every protection it would supply (CSRF, escaping, authorisation, validation, rate limiting) is built once and tested (CTL-FW-001, THR-021). Upgrade triggers to a VPS or cloud host: the plan fails the DEC-012 checks, resource limits are hit, uploads need real scanning, or stronger isolation is wanted.

PHP-specific rules: a supported PHP version (the existing server runs PHP 8.1, which no longer gets security fixes); no PHP execution in upload folders; display_errors off; prepared statements only; Composer audit in CI; MySQL private with a least-privilege account. Proposed, configured and verified are kept apart: everything above is proposed only. A documentation-only lock is not enforcement; GATE-001 must be wired to CI with a digest from a protected source before the first commit that matters.

## Known risks and open decisions

Decided: PHP, Tailwind CSS and MySQL (DEC-001), Paystack for payments (DEC-002, live use needs a verified Paystack business account and GATE-006), no framework (DEC-011), Namecheap cPanel hosting (DEC-012). No approval blockers remain. DEC-004 decided (verified email or phone for quotes and uploads). DEC-010 decided for this approval as Prince Caleb (Super Admin); because he is also the developer, a Belis Bell business owner must still be named and approve before live payments and real customer data. Open but not blocking the mock build: DEC-013 account ownership (also needed for Paystack business verification), DEC-014 Cloudflare, DEC-003 MFA method, DEC-005 retention and Data Protection Act, DEC-006 upload rules and scanning, DEC-007 staff roles. Also decided: DEC-008 WhatsApp links only, DEC-009 mock data guard.

No risks are formally accepted yet. Shared-hosting risk THR-019 is rated HIGH residual and needs an explicit owner decision. Residual risks marked MEDIUM in the baseline (account takeover, payment forgery, upload abuse, impersonation, data leakage) rely on the controls being built and tested.

## Runbooks (to write before launch)

Sections referenced by monitoring signals: account takeover, admin compromise, payment incident, price tampering, malicious upload, outage, recovery, mock in production, domain takeover (registrar or DNS change, lost domain). Each needs an owner, first-hour steps and contact route.

## Reporting and response

Private reporting route, triage owner and incident contacts: not set yet. Set them before launch. Containment plan: disable the affected route or payment mode, revoke sessions and keys, preserve logs, notify the owner, and assess notification duties under the Data Protection Act with qualified advice. Never publish secrets or exploit details here.
