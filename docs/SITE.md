# Cavemen Africa — site handbook

Live site: [https://cavemen.africa](https://cavemen.africa)

Static HTML in `site/`, PHP APIs on cPanel (Apache, PHP 7.4+). There is no Node.js backend. Browser calls go to **`/cavemen-api.php?route=…`**, not `/api/…`.

GitHub: [github.com/muree/cavemen-africa](https://github.com/muree/cavemen-africa)

---

## Pages

| Path | What it is |
| --- | --- |
| `/` | Home |
| `/asali/` | Asali Poetry Sessions |
| `/asali/register/` | Paid Asali tickets (Performer ₦5,000 / Audience ₦4,000) |
| `/asali/register/thank-you/` | After Flutterwave |
| `/asali/school-register/` | Free Asali Campus Tour RSVP (UniMaid, 14 Nov 2026) |
| `/dahk-seasons/` | DAHK: The Experience |
| `/dahk-seasons/register/` | Paid DAHK package (₦20,000) |
| `/dahk-seasons/register/thank-you/` | After Flutterwave |
| `/kanti/` | Shop (book, vials, Wuri) |
| `/kanti/dahk-book/` | Book product |
| `/kanti/dahk-vials/` | Vials product |
| `/kanti/wuri/` | Wuri lookbook |
| `/kanti/purchase/` | Checkout (delivery + Flutterwave) |
| `/kanti/purchase/thank-you/` | After Flutterwave |
| `/tatsuniya/` | Tatsuniya Creative Workshop |
| `/tatsuniya/register/` | Paid workshop place (₦15,000) |
| `/tatsuniya/register/thank-you/` | After Flutterwave |
| `/articles/` | Journal |
| `/articles/the-weight-of-dreams/` | Campus tour story |
| `/ticket-preview/` | Local ticket layout preview (noindex) |

Nav on every page: Home, Asali Poetry Sessions, DAHK, Kanti, Journal, Tatsuniya.

---

## Repo layout

```
site/                 ← document root on cPanel
  index.html
  cavemen-api.php     ← public API entry
  router.php          ← used by php -S locally
  api/                ← route handlers + common.php
  lib/                ← tickets, email, share cards
  scripts/            ← browser JS
  styles/site.css
  data/               ← JSON maps (Cloudinary, payment-link fallbacks)
  sql/schema-mysql.sql
  bin/                ← CLI only (not uploaded by the cPanel packager)
router.php            ← preview from repo root (serves site/)
deploy/               ← cPanel package script
docs/SITE.md          ← this file
```

Root folders such as `assets/` and `kanti/` are **symlinks into `site/`**. Edit the files under `site/`.

---

## Local preview

From the repository root:

```bash
npm run preview
```

Opens `http://127.0.0.1:8080/` via `php -S` and `router.php` (`.htaccess` is ignored by PHP’s built-in server).

Health check: `http://127.0.0.1:8080/cavemen-api.php?route=health`

Copy `site/.env` from the template below. Do not commit it. Local MySQL must be reachable if `MYSQL_*` is set; otherwise registration POSTs return 503. If `MYSQL_*` is omitted, PHP uses SQLite at `site/data/cavemen.db`.

---

## Forms and money

| Form | Payment | Table |
| --- | --- | --- |
| Asali register | Flutterwave v4 | `asali_registrations` |
| Asali Campus Tour | None (RSVP) | `asali_campus_registrations` |
| DAHK register | Flutterwave v4 | `dahk_seasons_registrations` |
| Tatsuniya register | Flutterwave v4 | `tatsuniya_registrations` |
| Kanti purchase | Flutterwave v4 | `kanti_orders` |

**Prices (code defaults)**

- Asali Performer ₦5,000, Audience ₦4,000
- DAHK package ₦20,000
- Tatsuniya ₦15,000 (`TATSUNIYA_FEE_NAIRA`)
- Kanti book ₦20,000, single vial ₦3,000, full vial set ₦21,000

Paid flow: form POST → row with `payment_status=pending` → Flutterwave checkout session → thank-you page polls status → webhook marks paid and emails ticket/confirmation.

Campus Tour has no checkout. Success is an on-page message.

Kanti delivery **State** is a picker of Nigeria’s 36 states plus F.C.T. (`select[data-nigeria-state]`, filled by `site/scripts/site.js`). Server rejects other values.

---

## API (`/cavemen-api.php?route=`)

| Route | Method | Use |
| --- | --- | --- |
| `health` | GET | Keys, DB, PHP sanity |
| `asali-registrations` | POST | Paid Asali |
| `asali-campus-registrations` | POST | Campus RSVP |
| `asali-payment-status` | GET | `?tx_ref=` |
| `asali-ticket-pdf` | GET | `?tx_ref=` |
| `dahk-registrations` | POST | Paid DAHK |
| `dahk-payment-status` | GET | `?tx_ref=` |
| `dahk-ticket-pdf` | GET | `?tx_ref=` |
| `tatsuniya-registrations` | POST | Workshop |
| `tatsuniya-payment-status` | GET | `?tx_ref=` |
| `kanti-orders` | POST | Shop checkout |
| `kanti-payment-status` | GET | `?tx_ref=` |
| `hosted-pay` | GET | Fallback HTML checkout `?tx_ref=` |
| `ticket-preview` | GET | `?event=asali\|dahk` |
| `flutterwave-webhook` | POST | Flutterwave |
| `products` | GET | Catalog (optional `?category=`) |

Thin wrappers live in `site/api/*.php`. Shared logic is `site/api/common.php`.

Webhook URL: `https://cavemen.africa/cavemen-api.php?route=flutterwave-webhook`

---

## Database

Import `site/sql/schema-mysql.sql` in phpMyAdmin (or skip it: PHP runs `CREATE TABLE IF NOT EXISTS` on first connection).

| Table | Rows |
| --- | --- |
| `asali_registrations` | Paid Asali tickets |
| `asali_campus_registrations` | UniMaid RSVPs (`campus` default `unimaid`) |
| `dahk_seasons_registrations` | DAHK tickets |
| `tatsuniya_registrations` | Workshop places |
| `kanti_orders` | Shop orders + delivery address |
| `kanti_products` | Optional product rows |

Paid tables keep `payment_status`, `tx_ref`, Flutterwave id, and (events) `ticket_code`.

---

## Images (Cloudinary)

Public pages load images from `https://res.cloudinary.com/kqhxqms3/…`. Map: `site/data/cloudinary-assets.json`. Helper: `cavemen_cloudinary_asset_url()` in `common.php`.

Kept on disk (GD / share cards): `site/assets/Logo-no-bg.PNG`, `cavemen-pattern.png`, `cavemen-icon.svg`.

Re-upload (needs an unrestricted Cloudinary key):

```bash
cd site && php bin/upload-cloudinary-assets.php
```

Missing local files are skipped; existing map entries are kept. New files: add to the script list, upload, then you can delete the local copy.

---

## Email and tickets

Resend first (`RESEND_*`), SMTP fallback (`SMTP_*`). After a paid webhook, Asali/DAHK get a PDF ticket + share card; Kanti/Tatsuniya get a confirmation.

Publish the Resend ticket template after HTML changes:

```bash
cd site && php bin/publish-resend-ticket-template.php
cd site && php bin/send-resend-test.php you@example.com
```

Offline ticket PDFs: `site/bin/generate-tickets.php`. Preview layout: `/ticket-preview/`.

---

## Environment (`site/.env`, never commit)

```env
# Database (omit all MYSQL_* to use SQLite locally)
MYSQL_HOST=localhost
MYSQL_PORT=3306
MYSQL_DATABASE=
MYSQL_USER=
MYSQL_PASSWORD=

# Flutterwave v4 Live (one set for every paid form)
FLUTTERWAVE_CLIENT_ID=
FLUTTERWAVE_CLIENT_SECRET=
FLUTTERWAVE_ENCRYPTION_KEY=
FLUTTERWAVE_SECRET_HASH=
FLUTTERWAVE_ENVIRONMENT=live

# Optional v3 fallback
# FLUTTERWAVE_SECRET_KEY=
# FLUTTERWAVE_PUBLIC_KEY=

# Email
RESEND_API_KEY=
RESEND_FROM=Cavemen Africa <tickets@your-verified-domain>
RESEND_TICKET_TEMPLATE=cavemen-ticket
# SMTP_HOST=
# SMTP_USER=
# SMTP_PASS=
# SMTP_PORT=587
# SMTP_FROM=

# Cloudinary
CLOUDINARY_CLOUD_NAME=kqhxqms3
CLOUDINARY_API_KEY=
CLOUDINARY_API_SECRET=

# Optional copy
# PUBLIC_SITE_URL=https://cavemen.africa
# ASALI_EVENT_NAME=Asali Poetry Sessions Season II 1.0
# ASALI_EVENT_WHEN=
# ASALI_VENUE_LINE=No 2 Guda Abdullahi Road, Farm Center, Kano, Nigeria
# DAHK_SEASONS_EVENT_NAME=DAHK: The Experience
# DAHK_SEASONS_EVENT_WHEN=
# TATSUNIYA_EVENT_NAME=Tatsuniya Creative Workshop
# TATSUNIYA_FEE_NAIRA=15000
```

`FLUTTERWAVE_ENCRYPTION_KEY` stays on the server. `FLUTTERWAVE_SECRET_HASH` is the webhook HMAC secret you choose, not the encryption key.

Dashboard payment links in `site/data/*-payment-links.json` are last-resort if v4/v3 init fails.

---

## Deploy (cPanel)

```bash
npm run package:cpanel
```

Upload **contents** of `dist-cpanel-php/` to the document root. Then on the server:

```bash
composer install --no-dev --optimize-autoloader
```

Create a **new** `site/.env` on the server. The packager excludes `.env`, SQLite, and `bin/`.

Keep `cavemen-api.php` next to `index.html`. Confirm `https://cavemen.africa/cavemen-api.php?route=health`.

If the site lives in a subdirectory, set `window.CAVEMEN_SITE_ROOT` before `site.js` and `RewriteBase` in `.htaccess`.

---

## Conventions (keep the site consistent)

- Relative links inside `site/` (`../asali/`, not `/asali/`), except Cloudinary URLs.
- Same header/footer and Tatsuniya in the nav on every page.
- New images: Cloudinary first, HTML `src` to `res.cloudinary.com/kqhxqms3`.
- New paid forms: `cavemen-api.php` route + table in `common.php` and `schema-mysql.sql`. Do not drop one-off PHP next to HTML.
- New state fields: `<select name="state" data-nigeria-state required>`.
- PHP 7.4-safe (cPanel). No Node APIs.

---

## CLI (from `site/`, not public URLs)

| Script | What |
| --- | --- |
| `bin/upload-cloudinary-assets.php` | Signed image upload + JSON map |
| `bin/publish-resend-ticket-template.php` | Publish Resend template |
| `bin/send-resend-test.php` | Send a test ticket email |
| `bin/generate-tickets.php` | Batch PDF tickets from CSV |

---

## If something fails

1. `?route=health` — `databaseConnected`, `flutterwaveV4`, `resend`.
2. Paid form 503 — `MYSQL_*` or Flutterwave client id/secret.
3. Images broken — Cloudinary URL and `cloudinary-assets.json`.
4. Webhook silent — URL on the v4 app, `FLUTTERWAVE_SECRET_HASH` matches.
5. `/api/…` 404 — use `cavemen-api.php?route=…`.
