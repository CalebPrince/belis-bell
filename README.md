# Belis Bell Online Store

Status: DRAFT scaffold. Design gate sealed. Security baseline 0.3.0 approved for development and staging only (mock data, Paystack test keys). Production is locked.  
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
- 61 security and unit tests in a dependency-free runner. (`tests/`)
- GitHub Actions for CI and a locked production deploy, written but not yet run. (`.github/`)

Not built: sign-in and accounts, staff admin, search, cart, checkout and Paystack calls, quotes and uploads, notifications, monitoring. See `GUI.md`.

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

The approved baseline digest is `66848a5d526cfb5a3fdd714a829a039a4e53f3dc2c66979ff3df2fa2de6fa352` (baseline 0.3.0). Store it as the GitHub repository variable `APPROVED_DIGEST` so CI compares the baseline against a value a pull request cannot edit. Until the repository is pushed and the checks are made required on a protected branch, the approval lock is checked by the validator but not enforced by a pipeline.

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
