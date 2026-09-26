# GUI: interface handoff

Status: DRAFT  
Owner: Prince Caleb (developer)  
Last reviewed: 2026-09-26  
Applies to: responsive web storefront and staff admin console

This document is tool-agnostic. It specifies user-visible behaviour and consumed contracts, not a framework, vendor or implementation agent. Visual decisions come from the sealed design gate in `.opskeep/project-gate/` (`DESIGN.md`, `tokens.json`, `screens.md`, `components.md`, `responsive.md`, `accessibility.md`). Security requirements come from `security-baseline.yaml`. Where they differ, stop and reconcile.

## Experience intent

Users: households and small businesses buying online; institutional buyers (banks, schools, government, private companies) requesting quotes and repeating orders; Belis Bell staff in the admin console. Tone: calm, warm, plain language, no jargon. Devices: phones and desktops with full parity, often on slow connections, sometimes on shared or office screens. Direction: Bright Supply (blue, navy and green from the Belis Bell logo, photo-led hero with a tile cluster, product carousels, rounded 12px components).

Non-goals for v1: native apps, contract-price accounts, AI features, WhatsApp Business API, languages other than English, any storage of payment details.

## Capability matrix

Allowed status values: `WORKING`, `PARTIAL`, `MOCK ONLY`, `NOT BUILT`. Public pages read mock data (`MOCK ONLY`); the signed-in pages show sample data and only open in the local preview. Rows marked `NOT BUILT` have no page at all. A mock is never reported as `WORKING`; update this table whenever a row changes.

| Capability | Route/surface | Roles | Backend contract | Status | States covered | Evidence | Limitation/next step |
|---|---|---|---|---|---|---|---|
| Browse home | `/` | guest, customer, buyer, staff | Server-rendered from the database (no JSON API yet) | MOCK ONLY | populated, placeholders when photos are missing, unavailable (503), empty categories or products message | Local run and browser check on 2026-09-26 at desktop and phone width; 61 unit tests run locally, not in CI | Structure follows the owner's mockup (PG-033). All photos are placeholders until supplied (`docs/IMAGES.md`). Trust row and Why Choose text are mock. Search, account, cart and Add to Cart are disabled. Carousel arrows and the three-dot indicator need JavaScript (swiping and a scrollbar work without it). No loading skeleton or error retry yet. Accessibility not tested |
| Browse all products and categories | `/shop`, `/c/:slug` | all | Server-rendered from the database; query: `sort`, `stock`, `page`, `min`, `max`, `brand`, `sub` | MOCK ONLY | populated, empty result, 404 unknown category, unavailable (503) | Local run, curl, browser check and unit tests on 2026-09-26; not CI evidence | Mock rows only. Filters (price, brand, in stock), subcategories, sort allowlist and numbered pagination (12 per page) work. No loading skeleton, accessibility not tested |
| Categories page | `/categories` | all | Server-rendered from the database | MOCK ONLY | populated, empty, unavailable (503) | Local run 2026-09-26 | Placeholders for photos. Mock category names from the owner's mockup |
| For Businesses page | `/for-businesses` | all | Static structure plus WhatsApp link when configured | MOCK ONLY | populated | Local run 2026-09-26 | Text is mock. Request a quote button is switched off until the quote flow is built |
| About page | `/about` | all | Static structure | MOCK ONLY | populated | Local run 2026-09-26 | Text is mock until the owner supplies it |
| Contact page | `/contact` | all | Static structure; contact values from configuration | MOCK ONLY | populated with sample or configured details, hidden when not set | Local run 2026-09-26 | Sample details only in mock mode. No form. WhatsApp link only when WHATSAPP_NUMBER is set |
| Header, navigation and footer | every page | all | Static | PARTIAL | populated | Local run 2026-09-26; phone menu uses a native details element | Links go only to built pages. Search, account and cart icons are disabled. Footer support and legal links and social icons appear only when real (PG-037). Mobile menu accessibility not tested |
| Search products | header search, `/search` | all | `GET /v1/search` | NOT BUILT | none | NOT_YET_BUILT | Rate limit behaviour to define |
| View product | `/p/:slug?size=` | all | Server-rendered from the database (sizes, bulk tiers, content); no JSON API yet | MOCK ONLY | populated, unknown product 404, unavailable 503, size without tiers, single size, missing photos as placeholders, tabs without JavaScript show all panels | Local run, browser check and 69 unit tests on 2026-09-26; not CI evidence | Structure from the owner's mockup (PG-039). Sizes and bulk tiers are mock, and the browser only previews prices. Add to Cart, Add to quote, save (heart) and Request bulk quote are switched off. No ratings, reviews or sold counts (PG-041). Gallery extras, Common Uses photos and all text beyond name, size and price are mock or placeholders. Accessibility not tested; desktop layout not checked in a browser |
| Cart | `/cart`, `/cart/add`, `/cart/update`, `/cart/remove` | all | Session cart of size ids and quantities; server rebuilds every price (CTL-BIZ-001); POST with CSRF | PARTIAL | populated, empty, removed, out of stock, cart full, unavailable (503) | Local run, curl, browser check and unit tests on 2026-09-26; not CI evidence | Works for guests in the session only, no saved carts. Delivery fee is a sample. Proceed to Checkout needs sign-in, which is not built. Accessibility not tested |
| Checkout and pay | `/checkout` | customer | Server-rendered form and summary; needs `POST /v1/orders` and Paystack | MOCK ONLY | populated (preview only), empty cart redirects to cart, signed out redirects to sign-in | Local preview and unit tests 2026-09-26 | Structure only. No guest checkout. Pay with Paystack is disabled: no order is created, nothing is paid. Address form posts nowhere. Delivery options are sample. Only opens in local preview |
| Register, sign in, code entry | `/account/register`, `/account/sign-in`, `/account/verify`, `/admin/sign-in`, `/admin/verify` | guest | needs `POST /v1/auth/*` and email sending | MOCK ONLY | populated with disabled submit | Local run, browser check at phone width, unit tests 2026-09-26 | Layout only. Buttons are disabled and no email or code is sent. No password recovery page. Emailed codes required by CTL-AUTH-001/002 when built |
| Request a quote | `/quote/new` | verified contact | `POST /v1/quotes`, `POST /v1/quotes/:id/files` | NOT BUILT | none | NOT_YET_BUILT | Upload limits (DEC-006) |
| Quote thread and approve | `/quotes/:id` | quote owner, org members, staff | `GET/POST /v1/quotes/:id/messages`, `POST /v1/quotes/:id/approve` | NOT BUILT | none | NOT_YET_BUILT | Approval limits (DEC-004) |
| Account dashboard, order confirmation | `/account`, `/order/:ref` | customer | needs `GET /v1/orders` | MOCK ONLY | paid, pending, failed, cancelled (order page); empty orders | Local preview and unit tests 2026-09-26 | Sample person and order, shown only in local preview. Edit buttons disabled. Tracking page and saved lists not built |
| Saved lists and reorder | `/account/lists` | customer, buyer | `GET/PUT /v1/lists` | NOT BUILT | none | NOT_YET_BUILT | None |
| Buying guides, about, contact, FAQ | `/guides`, `/about`, `/contact`, `/faq` | all | `GET /v1/content/*` | NOT BUILT | none | NOT_YET_BUILT | Content is mock until supplied |
| Admin catalogue and prices | `/admin/catalogue` | staff (content), owner | `/v1/admin/products` | NOT BUILT | none | NOT_YET_BUILT | Dual approval for bulk price changes |
| Admin sign in and dashboard | `/admin/sign-in`, `/admin/verify`, `/admin` | staff | needs `/v1/admin/*` | MOCK ONLY | populated (preview only), signed out redirects to staff sign in | Local preview and unit tests 2026-09-26 | Read-only sample figures. No management actions exist. Only opens in local preview |
| Admin quotes and orders | `/admin/quotes`, `/admin/orders` | staff (sales, fulfilment), owner | `/v1/admin/quotes`, `/v1/admin/orders` | NOT BUILT | none | NOT_YET_BUILT | Step-up for sensitive changes |
| Admin users and roles, audit log | `/admin/users`, `/admin/audit` | owner | `/v1/admin/users`, `/v1/admin/audit` | NOT BUILT | none | NOT_YET_BUILT | Read-only audit view |

## Route and screen inventory

| Route/screen | Purpose | Entry/navigation | Allowed roles | Primary actions | Data/contracts | Responsive/accessibility acceptance | Exclusions |
|---|---|---|---|---|---|---|---|
| `/` Home | Reach a product or a quote in two taps | Logo, top bar | all | Search, open category, request quote | Home sections, carousels | Tiles stack under the hero; carousels swipe and have buttons; skip link | No prices editing, no account data |
| `/c/:slug`, `/search` | Narrow to the right product | Mega-menu, search | all | Filter, sort, add to cart or quote | Product summaries | Filters become a sheet on phones; announced result counts | No inline editing |
| `/p/:slug` | Confirm product and pack size | Cards | all | Add to cart, add to quote, WhatsApp link | Product detail, datasheet links | Sticky action bar on phones; keyboard-operable gallery | Prices come only from the server |
| `/cart`, `/checkout` | Review and pay | Header cart | customer, buyer | Change quantity, pay | Cart, order, provider redirect | Review step is a page; errors focus first field; totals announced | No card fields in our pages |
| `/quote/new` | Ask for a price | Buy for Business, product page | verified contact | Fill details, upload requirements | Quote, upload | Progress and drafts survive a dropped connection; upload errors are specific | No payment or ID documents |
| `/quotes/:id` | Talk about and approve a quote | Account, notification | quote owner, org members, staff | Read, reply, approve | Quote, messages, files | Messages and line items tabbed on phones; live region for new messages | No editing of others' messages |
| `/account/*` | Manage orders, lists, addresses | Header account | customer, buyer | Reorder, edit address | Orders, lists, addresses | Section list on phones | No admin data |
| `/admin/*` | Staff work | Separate sign-in | staff by role | Manage catalogue, quotes, orders, users | Admin contracts | Dense but keyboard-first; visible role and session state | Not linked from the storefront |

For every screen define loading (skeleton), empty, recoverable error (with Retry), terminal error, success, disabled, unauthorised (sign in), forbidden (explain), and session-expired (sign in and return) behaviour. Empty cart: suggest categories. Forbidden object: say it is not available to this account, never confirm it exists.

## Backend contracts consumed by the UI

The API is not designed or built. Contracts below are the UI's expectations and must be confirmed by the backend owner; the authoritative schema will be an OpenAPI document linked here during build. Pages are server-rendered plain PHP templates with automatic escaping and Tailwind CSS compiled at build time; small JavaScript is added only where needed (carousels, drawers, upload progress).

| Operation | Request | Success | Expected errors | UI behaviour | Contract owner |
|---|---|---|---|---|---|
| Create order | items (product ID, quantity), address ID, idempotency key | order reference, provider redirect URL | 401, 403, 409 stock, 422, 429, 5xx | Never trust client totals; show server totals; safe retry with same key | Backend |
| Upload quote file | file, quote ID | file ID, scan status | 401, 403, 413, 415, 422, 429 | Show progress; "scanning" state; specific rejection reasons | Backend |
| Approve quote | quote ID, confirmation | order reference | 401, 403, 409, 422 | Review step first; show approval limit errors clearly | Backend |
| Payment result | none (status read only) | paid, pending, failed | 401, 403 | Status comes from the server after webhook, never from the redirect alone | Backend |

Each write also needs: rate-limit behaviour (show retry time), idempotency where money or orders are involved, an authorisation rule (ownership or organisation) and an audit event named in `security-baseline.yaml` (CTL-AUDIT-001).

## UI-relevant data models

| Model/field | Type/format | Required | Classification | Display/edit rule | Validation owner | Redaction/fallback |
|---|---|---|---|---|---|---|
| Product.name, price (GHS), pack size, stock | text, decimal, text, enum | yes | PUBLIC | Display only; staff edit in admin | Server | "Price on request" only if hidden by decision |
| Customer.email, phone | text | yes | CONFIDENTIAL | Owner edits; masked in staff lists where possible | Server | Partial mask |
| Address | structured text | yes for delivery | CONFIDENTIAL | Owner edits | Server | Hide from other roles |
| Order, Quote | records | yes | CONFIDENTIAL | Owner and permitted roles read | Server | Not found for others |
| QuoteMessage.body | plain text | yes | CONFIDENTIAL | Plain text only, no HTML | Server | Escaped output |
| UploadedFile | metadata | no | CONFIDENTIAL | Authorised download only | Server | Scan status shown |
| PaymentStatus | enum | yes | CONFIDENTIAL | Display only | Server (webhook) | No instrument data ever shown |

Never include secret values, real customer data or payment details in samples, fixtures or screenshots. Mock data lives only in the labelled seed file and is marked as sample in the UI wherever it could be mistaken for real.

## Authentication and permissions

Customer sign-in, sign-out, recovery and session expiry follow CTL-AUTH-001 and CTL-SESS-001. Staff use a separate sign-in with mandatory MFA and short sessions (CTL-AUTH-002). Roles: guest, customer, institutional buyer member, staff (sales, fulfilment, content), owner. Sensitive staff actions (bulk price change, export, role change, address or account change) need step-up authentication. Session expiry returns the person to the same place after sign-in with their draft intact. Hiding a control is not authorisation; the server decides every request.

## Sample interfaces

### 1. Application shell

- Skip link; landmarks for header, nav, main and footer; announcement bar (only with real or clearly mock figures); header with logo, search, account, saved lists and cart count; nav row with Shop mega-menu and Buy for Business.
- Global status region announces cart and quote changes. Field errors stay at the field.
- Phones: compact header, search expands to full width, category drawer opens from the menu button.

### 2. Collection screen (category or search)

- Title and result count; labelled sort and filters with an active-filter summary and a Clear action.
- Skeleton cards while loading; useful empty result with categories and a quote link; product grid; load more or pagination; retryable error that keeps the filters.

### 3. Detail and form screen (quote request)

- Stable title and back link; grouped fields with visible labels; help and error text linked to fields.
- Server validation is authoritative; client checks assist. The Send button prevents double submission and shows progress.
- After success show the quote reference and next steps. Destructive actions (withdraw a quote) state the impact and confirm.

These are behaviours, not visuals. Annotated wireframes will replace them when screens are designed. A sample never implies `WORKING`.

## Design and interface tokens

Values come from the approved gate (`.opskeep/project-gate/tokens.json`, direction Bright Supply) and are not restated as free choices here. Light theme summary (roles, not framework syntax):

```yaml
color:
  canvas: "#F3F8FC"
  surface: "#FFFFFF"
  text: "#0A1D33"
  text_muted: "#4A5F75"
  border: "#D3E2EE"
  action: "#006CBC"        # blue, white text 5.4:1
  action_text: "#FFFFFF"
  navy: "#044788"          # announcement bar, footer
  green_ui: "#2B7D32"      # text and buttons; logo green #43B14A is decoration only
  success: "#0F6A3B"
  warning: "#7A4700"
  danger: "#A11D12"
color_dark:
  canvas: "#0A1522"
  surface: "#111F33"
  text: "#E7F0FA"
  text_muted: "#A2B6CC"
  border: "#22364F"
  action: "#6AB7F5"
  action_text: "#04203A"
typography:
  family_display: "Figtree, DM Sans, system-ui, sans-serif"
  family_body: "DM Sans, system-ui, sans-serif"
  size_body: "1.0625rem"   # 17px
radius: { sm: "8px", md: "12px", lg: "20px" }
space_unit: "4px"
control: { min_target: "3rem" }   # 48px
breakpoint: { phone: "599px", tablet: "1023px", desktop: "1024px" }
motion: { duration_fast: "120ms", duration_normal: "200ms", reduced_motion: "no carousel autoplay, no hover lift" }
focus: { ring: "3px solid action colour", offset: "2px" }
```

Define hover, active, selected, disabled, read-only, invalid, busy, success and focus-visible for interactive components. Never communicate status by colour alone.

## Responsive and accessibility requirements

Target: WCAG 2.2 AA plus keyboard-first, screen-reader, low-vision and text-expansion and right-to-left readiness (see `.opskeep/project-gate/accessibility.md`). Viewports: phone up to 599px, tablet 600 to 1023px, desktop 1024px and up, content max 1280px. Text scales to 200% and zoom to 400% without horizontal scrolling. Touch targets 48px. Focus returns to the trigger after drawers and dialogs. Conformance is not claimed until tested (checklist in the gate's `accessibility.md`).

## Analytics, audit and privacy

Product analytics: none approved yet. Any analytics or third-party script needs owner approval, must not run on checkout or account pages, and must not receive personal data (CTL-SC-002). Server audit events (sign-in, role change, price change, quote approval, order and refund actions, exports) are defined in CTL-AUDIT-001. UI telemetry must not include secrets or field values.

## Hard boundaries for implementers

Implementers may refine presentation and local interaction details within the approved tokens and contracts. They must not silently change backend endpoints or events, server validation, business or financial rules, authentication or authorisation, data classification or retention, audit requirements, security controls or gates, infrastructure, or approval state. Client checks never replace server enforcement. Prices, totals, discounts and stock are never computed or trusted on the client.

Any needed boundary change must be proposed with the old rule, new rule, reason, impact, affected artifacts and IDs, and approver. Continue only within unaffected approved scope.
