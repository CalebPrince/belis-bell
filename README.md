# Belis Bell Online Store

Status: DRAFT scaffold. Design gate sealed. Security baseline 0.6.0 (emailed sign-in codes CHG-001, keys in an owner Settings page CHG-002, refunds, staff roles, stock counts and the breached-password check CHG-003) is approved for development and staging only.  
Owner: Prince Caleb (Super Admin); a Belis Bell business owner is still to be named (DEC-010, DEC-013)  
Last reviewed: 2026-09-26

## Overview

Belis Bell supplies washroom and cleaning products in Ghana to households and businesses, and to institutions such as banks, schools, and government and private companies. This is a responsive web store: individuals browse and pay online (Paystack), and institutions request a quote each time and approve it into an order.

Stack: plain PHP 8.3 or newer with no framework, MySQL, Tailwind CSS compiled at build time, hosted on Namecheap cPanel. There are no Composer packages at runtime. Tailwind and two self-hosted fonts are the only npm dependencies, and they are used at build time only.

Non-goals for v1: native apps, contract-price accounts, AI features, WhatsApp Business API, languages other than English.

## What exists today

- Front controller, router with deny-by-default policies, CSRF layer, hardened session code, security headers, auto-escaping templates, prepared-statement database wrapper, validation, redacting logger. (`src/`)
- Environment guard that refuses to start in production with mock data, debug output, test keys or a non-https URL. (`src/Core/Guard.php`)
- Home page following the owner's mockup (hero, six category cards, promo banner, audience cards, product carousel, Why Choose, closing banner), plus `/shop`, `/c/{slug}`, `/p/{slug}`, `/categories`, `/for-businesses`, `/about` and `/contact`. Data comes from the database with mock content; photos are placeholders until supplied. (`GUI.md`)
- Catalogue migration, mock seed loader and purge script, release builder and release checker. (`database/`, `bin/`)
- Image pipeline: originals in `resources/images/` become responsive WebP (`npm run build:images`), and `image_html()` renders them or a neutral placeholder. Slots are documented in `docs/IMAGES.md`. (`scripts/build-images.mjs`, `src/Support/Images.php`)
- 237 security and unit tests in a dependency-free runner. (`tests/`)
- GitHub Actions for CI and a locked production deploy, written but not yet run. (`.github/`)

Sign-in, register and emailed codes work locally (codes are written to `storage/logs/mail.log`; real email sending is not built). Staff accounts are created with `php bin/create-staff.php email "Name" staff|owner`. The owner (Super Admin) manages the Paystack, SMTP and WhatsApp settings at `/admin/settings` (fresh emailed code each time, values write-only); values saved there win over the environment file, which stays the fallback and holds `SETTINGS_KEY` and the database login. Orders and Paystack test-mode payments exist but the Paystack calls have not been run against Paystack yet: local development uses `PAYMENTS_ADAPTER=mock`, a pretend payment page; set `PAYMENTS_ADAPTER=paystack` and a `sk_test_` key in `.env` to try the real service. Point Paystack's webhook at `/webhooks/paystack` and schedule `bin/reconcile-payments.php` daily (cron). Staff and the owner see and progress orders at `/admin/orders` (packing and delivery, all audited). Staff edit product details and stock, and the owner manages sizes, prices and bulk prices, at `/admin/products` (price changes need a fresh emailed code and are kept in an append-only history). Staff and the owner manage categories and subcategories at `/admin/categories` (new ones start hidden, nothing is deleted). Password recovery works for customers and staff with an emailed code (`/account/forgot`, `/admin/forgot`). Staff management (`/admin/staff`) and the activity log (`/admin/audit`) are owner-only tools. Customers manage their details, password and saved addresses at `/account`, and get emails when an order is paid, packed, out for delivery and delivered. The four items approved in CHG-003 are built: the owner refunds paid orders from the admin order page (`/admin/orders/:ref`, fresh code each time, second code once over the daily limit, which is a setting), staff have one of three roles (content, fulfilment, sales) that the owner assigns at `/admin/staff`, stock is a counted number per size that a paid order reduces (adjust it on the product page with a reason), and new passwords are checked against the Have I Been Pwned range service (set `BREACH_CHECK=off` for offline work). Create staff with `php bin/create-staff.php email "Name" staff content|fulfilment|sales`. Bulk price changes with a second approver were not approved and are not built. Not built: search, quotes and uploads, monitoring, and real email sending has not been tested against a mail server. See `GUI.md`.

## Project records

- [Architecture](ARCHITECTURE.md)
- [Interface handoff](GUI.md)
- [Security](SECURITY.md)
- [Canonical security baseline](security-baseline.yaml) (approved copy in `.opskeep/security-design/baseline-0.3.0-approved.yaml`)
- Design gate: `.opskeep/project-gate/` (`DESIGN.md`, `decisions.yaml`, `tokens.json`, `gate.json`)
- Security dashboard: `.opskeep/security-design/dashboard.html`
- Images to supply and where they go: `docs/IMAGES.md`
- Brand assets: `brand/logo-primary.webp` (raster; transparent PNG or SVG still needed). `public/assets/brand/` holds resized copies, and the header mark is a temporary crop until a proper horizontal lockup exists.

## Setup and operation

Needs PHP 8.3 or newer with `pdo_mysql` and `mbstring`, MySQL 8 or a compatible MariaDB, and Node 22 or newer.

```bash
npm ci
npm run build             # images (from resources/images) and CSS
cp .env.example .env        # then edit: database user and password, test Paystack keys, optional WHATSAPP_NUMBER
php bin/migrate.php         # run with the migration database user (BELIS_ENV_FILE=.env.migrate)
php bin/seed.php            # local and staging only, needs MOCK_DATA=1
php -S 127.0.0.1:8090 -t public
php tests/run.php           # security and unit tests
```

Use two database users: the app user (`SELECT, INSERT, UPDATE, DELETE` only) in `.env`, and a migration user in `.env.migrate` (both are git-ignored). Point `BELIS_ENV_FILE` at the migration file when running `bin/migrate.php`.

### Start the dev server

First time only (see the commands above): install with `npm ci`, copy `.env.example` to `.env` and fill it in, create the database and its two users, then run the migrations and the seed.

Every time:

1. **Start MySQL or MariaDB** (XAMPP Control Panel, or your own service) so the database in `.env` (`DB_HOST`, `DB_PORT`, `DB_NAME`) is reachable.
2. **Build the CSS and images** when styles or photos changed: `npm run build`. While editing styles, keep `npm run watch:css` running in a second terminal so the CSS rebuilds when you save. PHP and JavaScript changes need no build.
3. **Run the site** from the project folder:

   ```bash
   php -S 127.0.0.1:8090 -t public
   ```

   Open http://127.0.0.1:8090. Stop it with Ctrl+C. Any free port works, but set `APP_URL` in `.env` to the same address (Paystack return links and emails use it). Port 8080 is often taken on Windows, so this project uses 8090.
4. **Local settings worth knowing** (all in `.env`):
   - `MOCK_DATA=1` allows the sample catalogue (`php bin/seed.php`). Production refuses it.
   - `PAYMENTS_ADAPTER=mock` uses a pretend payment page instead of Paystack. It works only when `APP_ENV=local`.
   - `MAIL_DRIVER=log` writes emails, including sign-in codes, to `storage/logs/mail.log`. Read the newest code there.
   - `AUTH_PEPPER` and `SETTINGS_KEY` must be set (see `.env.example` for the command that makes one). Sessions and encrypted settings will not work without them.
5. **Sign in as staff:** create an account with `php bin/create-staff.php you@example.com "Your Name" owner` (it asks for the password), then sign in at http://127.0.0.1:8090/admin/sign-in and read the emailed code from `storage/logs/mail.log`.
6. **Just look at the signed-in pages, no account:** set `PREVIEW_LOGIN=customer`, `staff` or `owner` in `.env` (local with `MOCK_DATA=1` only), restart the server, and the pages open with a sample person. Sample people cannot place orders or change data.

Run the tests any time with `php tests/run.php` (they use an in-memory database and never touch your local data).

Release build: `npm run build && php bin/build-release.php && php bin/check-release.php`. This copies an allowlist into `dist/` and fails if seed data, tests, tools, design records, keys or mock markers are present.

Before launch: `php bin/purge-mock.php`, load the real catalogue, and set `MOCK_DATA=0`. The app refuses to run in production while any row is flagged as mock.

## Deployment (Namecheap cPanel)

Not done yet, and locked by the approved baseline (production is out of scope). Requirements from DEC-012 and CTL-PLAT-001:

- The store gets its own cPanel account. PHP 8.3 or newer selected in cPanel, SSH and cron available.
- The web root is `public/`. Application code, `.env`, `storage/` and backups sit above it.
- `.env` lives above the web root with restricted permissions and is never deployed from CI.
- Deploys use a scoped SSH key and human approval (`.github/workflows/deploy.yml`, currently locked).
- Cron jobs, not workers, run scheduled tasks. Off-site backups are separate from any host backup.

## Security approval

The approved baseline digest is `b24d4183036abbe0c8e6b5d277607b1a228316ee4ef8f894ec128df6f74142af` (baseline 0.6.0). Store it as the GitHub repository variable `APPROVED_DIGEST` so CI compares the baseline against a value a pull request cannot edit. Until the repository is pushed and the checks are made required on a protected branch, the approval lock is checked by the validator but not enforced by a pipeline.

## Repository map

- `public/`: web root (front controller, built assets, `.htaccess`, `.user.ini`)
- `src/`: application code (`Core`, `Controllers`, `Domain`, `Payments`, `Support`)
- `templates/`: PHP templates (escaped output only)
- `config/routes.php`: route table (every route declares a policy)
- `database/migrations/`, `database/seed/` (mock data, never released)
- `bin/`: command-line scripts (migrate, seed, purge-mock, build-release, check-release)
- `tests/`: security and unit tests
- `resources/css/app.css`, `package.json`: Tailwind build
- `tools/security/`: pinned baseline validator
- `.opskeep/`: design gate and security design records

## Support and change process

Maintainer: Prince Caleb. A change to authentication, payments, personal data, uploads, hosting, third parties or admin permissions needs a change record and security reassessment before it is built. A change to a design decision uses `gate.py reopen` in `.opskeep/project-gate/`.
