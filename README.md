# Daher Store — Repair Shop Management System

A complete, offline-capable management system for a phone & computer repair shop:
inventory, POS, repairs, customers, credit (دين), returns, refunds, expenses,
reports, and backups.

- **Stack:** PHP 8+, MySQL/MariaDB, Apache (XAMPP), Bootstrap 5, Chart.js (all bundled — no internet needed)
- **Default login:** username `admin` · password `admin123` — **change it after first login** (Settings → My profile)

---

## What's new in v1.1

| Feature | Where |
|---|---|
| **Warranty in days** — set days per product; every sold line stores its own expiry date and Active/Expired state (invoice, customer history, Warranty-expiry report, dashboard "expiring soon" card) | Products → Warranty (days) |
| **Optional selling price** — products can be saved without a price (marked **No price**, filterable); they cannot be sold until a price is set | Products |
| **Payment methods** are now exactly **Cash / Card / Credit (دين)** (old invoices keep their historic labels) | POS |
| **Customer credit (دين)** — credit sales save unpaid, show on the customer profile (purchases / paid / outstanding) and the dashboard; the **Credit** page records full or partial payments with history, and invoices auto-close at zero balance | Sidebar → Credit (دين) |
| **Product returns** — invoice-linked, partial-return support, quantity guards, automatic restock (journaled), and automatic debt reduction when the invoice was unpaid | Sidebar → Returns |
| **Money refunds** — invoice-linked full/partial refunds capped at the money actually received, with printable receipts; refunds reduce net revenue in reports | Sidebar → Refunds |
| **5 new reports** (outstanding credit, credit payments, returns, refunds, warranty expiry) + product / invoice-number filters, all exportable to CSV / Excel / PDF-print | Reports |
| **Dashboard** — outstanding-credit, returns, refunds and unpriced-product cards; cash/card/credit chart; monthly returns & refunds charts | Dashboard |

### Upgrading an existing v1.0 database

Run **once** in phpMyAdmin: select the `daher_store` database → **Import** →
`database/migrations/001_credit_returns_refunds.sql` → Go.
Existing data is preserved (warranty months are converted to days at ×30).
Fresh installs just import `database/schema.sql` as before — it already contains
the v1.1 structure.

---

## 1. Installation (XAMPP)

### Step 1 — Install XAMPP
1. Download XAMPP for Windows (PHP 8.x) from <https://www.apachefriends.org>.
2. Install it to the default location `C:\xampp`.
3. Open **XAMPP Control Panel** and press **Start** next to **Apache** and **MySQL**.

### Step 2 — Copy the project into htdocs
Copy this whole project folder into `C:\xampp\htdocs` and rename it to **`daher-store`**
(no space — URLs are cleaner and safer):

```
C:\xampp\htdocs\daher-store\
├── app\
├── config\
├── database\
├── docs\
├── public\        ← the web root (index.php lives here)
└── storage\
```

### Step 3 — Create the database
1. Open <http://localhost/phpmyadmin> in your browser.
2. Click the **Import** tab (no need to create a database first).
3. Choose the file `C:\xampp\htdocs\daher-store\database\schema.sql` and press **Go**.
4. You should see the `daher_store` database appear with 11 tables and seed data.

### Step 4 — Open the app
Browse to:

> **http://localhost/daher-store/public/**

(`http://localhost/daher-store/` also works — it redirects.)

Sign in with `admin` / `admin123`, then immediately:
1. **Settings → My profile** → change the password.
2. **Settings → Shop settings** → set your shop name, address, phone, currency, and accent colour.

### If MySQL uses a password
XAMPP's default MySQL user is `root` with an empty password. If yours differs,
edit `config/config.php`:

```php
const DB_USER = 'root';
const DB_PASS = 'your-password';
```

---

## 2. Daily use — quick tour

| Task | Where | Shortcut |
|------|-------|---------|
| Sell products | **Point of Sale** | `F4` opens POS, `Enter` adds first search result, `F9` completes the sale |
| Take in a repair | **Repairs → New repair ticket** | prints a customer receipt with ticket number |
| Track a repair | Ticket page: status timeline, parts, payments | statuses: Received → Diagnosing → Repairing → Ready → Delivered |
| Restock | Product page → **Adjust stock** (every change is journaled) | |
| Record bills | **Expenses** | rent, electricity, salaries… |
| See how the shop is doing | **Dashboard** and **Reports** | export any report to CSV / Excel / PDF-print |
| Protect your data | **Backup** (admin) → *Create backup now* | copy the `.sql` file to a USB drive regularly |
| Find anything | `Ctrl+K` focuses search | |

Dark mode: moon/sun button in the top bar. The accent colour is configurable in Shop settings.

---

## 3. Project structure

```
daher-store/
├── public/               ← ONLY web-exposed folder
│   ├── index.php         ← front controller (all requests: index.php?r=module/action)
│   └── assets/           ← css, js, offline vendor bundles
├── app/
│   ├── Core/             ← micro-MVC framework (~600 lines, documented)
│   ├── Controllers/      ← one class per module
│   ├── Models/           ← all SQL lives here (PDO prepared statements only)
│   └── Views/            ← PHP templates (layouts, partials, module folders)
├── config/config.php     ← DB credentials & app constants
├── database/schema.sql   ← schema + seed (import once)
├── storage/backups/      ← generated backups (blocked from the web)
└── docs/                 ← architecture & testing docs
```

Adding a module later = 1 controller + 1 model + views + one line in
`app/Core/App.php` (`CONTROLLERS` map) + a sidebar link. See `docs/ARCHITECTURE.md`.

## 4. Security notes

- All queries use PDO prepared statements; all output is escaped (`e()`).
- Every state-changing request is POST + CSRF-token checked.
- Sessions: httponly, SameSite=Lax, strict mode, ID rotation on login,
  5-minute lockout after 5 failed logins, 8-hour inactivity timeout.
- `app/`, `config/`, `storage/`, `database/` are denied to the web by `.htaccess`.
- Set `APP_DEBUG = false` in `config/config.php` once the shop goes live.

## 5. Troubleshooting

| Symptom | Fix |
|---------|-----|
| "Database connection failed…" | Start MySQL in XAMPP Control Panel; check `config/config.php` credentials |
| Blank page | Set `APP_DEBUG = true` in config, retry, read the error; check `storage/logs/php-error.log` |
| Port 80 busy, Apache won't start | XAMPP Control Panel → Apache → Config → change to port 8080, then browse `http://localhost:8080/daher-store/public/` |
| Wrong times on invoices | Change `APP_TIMEZONE` in `config/config.php` |
| Restore didn't work | Restore only `.sql` files created by this app or phpMyAdmin exports of `daher_store` |
