# cavemen-africa

Static site and **PHP APIs** for shared hosting (cPanel + Apache). There is no Node.js backend in this tree.

## Deploy to cPanel

From the repository root:

```bash
npm run package:cpanel
```

Upload the **contents** of `dist-cpanel-php/` to your document root (e.g. `public_html/`), then on the server:

```bash
composer install --no-dev --optimize-autoloader
```

Configure MySQL and API keys in a new `.env` inside **`site/`** on the server (the same directory as `composer.json` and `api/`). See **`site/sql/schema-mysql.sql`** for the full layout.

### MySQL for phpMyAdmin (DAHK & Asali registrations)

Registration APIs write to MySQL **only when** these variables are set in `site/.env`:

```env
MYSQL_HOST=localhost
MYSQL_PORT=3306
MYSQL_DATABASE=your_database_name
MYSQL_USER=your_database_user
MYSQL_PASSWORD=your_database_password
```

**DAHK: The Experience** submissions are stored in **`dahk_seasons_registrations`** (name, phone, email, gender, how they heard about the event, package type, price, notes, payment status, Flutterwave `tx_ref`, ticket code, timestamps). **Asali** uses **`asali_registrations`**.

**One-time setup:** In cPanel → **phpMyAdmin**, select your database → **Import** (or **SQL** tab) and run **`site/sql/schema-mysql.sql`**, or create the database user/database in MySQL® Databases first then import. Alternatively, you can skip importing: on the first request that opens a DB connection, PHP will run `CREATE TABLE IF NOT EXISTS` for these tables when MySQL credentials are valid.

**If `MYSQL_*` is not set**, the app stores data in **`site/data/cavemen.db`** (SQLite). That file is **not** visible in phpMyAdmin—use MySQL on production if you want all registrations in phpMyAdmin.

## Local preview (optional)

The website lives in **`site/`**. Opening the repository root used to show a folder list (`deploy`, `site`, …). There is now a root `index.html` that sends you into `site/`.

**Close the old preview tab**, then open `site/index.html` (or `/` again) so it is not still serving a cached directory listing.

From the **repository root**:

```bash
npm run preview
```

That runs `php -S 127.0.0.1:8080 router.php`. Open `http://127.0.0.1:8080/` (or `/dahk-seasons/register/`). **Plain `php -S` without this router does not apply `.htaccess`**, so API paths would 404.

The browser calls **`/cavemen-api.php?route=…`** (not `/api/…`), which avoids many hosts or CDNs that intercept `/api/*` and respond with errors like **“API route not found.”** Ensure **`cavemen-api.php`** is uploaded next to `index.html` (same folder as `api/`).

**Sanity check:** open `https://your-domain/cavemen-api.php?route=health` — you should see JSON `{"ok":true,...}`.

**Flutterwave webhook** (if you use it): set the URL to  
`https://your-domain/cavemen-api.php?route=flutterwave-webhook`  
(or keep `/api/webhooks/flutterwave.php` if that path works on your server).

After a successful `charge.completed` / `charge.success`, the webhook verifies the `verif-hash` header against `FLUTTERWAVE_SECRET_HASH`, confirms the transaction with Flutterwave, issues a door code, and emails a gate pass (QR encoding the payment reference). Prefer **Resend**:

```env
RESEND_API_KEY=re_xxxxxxxx
RESEND_FROM="Cavemen Africa <tickets@your-verified-domain>"
```

Smoke-test from `site/` (CLI only; not a public URL):

```bash
cd site && php bin/send-resend-test.php you@example.com
```

If Resend is unset or fails after retries, the same email is sent over existing `SMTP_*` settings. Optional event copy for tickets:

```env
ASALI_EVENT_WHEN=Saturday 12 September 2026 · 6:00 PM
ASALI_VENUE_LINE=No 2 Guda Abdullahi Road, Farm Center, Kano, Nigeria
ASALI_EVENT_FLIER_URL=https://cavemen.africa/path/to-asali-flier.jpg
DAHK_SEASONS_EVENT_WHEN=Sunday 10 May 2026 · 3:00 PM – 6:00 PM
DAHK_EVENT_FLIER_URL=https://cavemen.africa/assets/dahk-registration-banner.png
```

If the site is deployed in a **subdirectory** (e.g. `https://example.com/cavemen/`), set before `site.js` in your HTML:

```html
<script>window.CAVEMEN_SITE_ROOT = "/cavemen";</script>
```

(Also use that prefix for asset paths, or a `<base href>`, so scripts and images load correctly.)
