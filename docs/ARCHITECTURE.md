# Daher Phone — Repair Shop Management System

**Architecture & Design Document** · 2026-07-25 · PHP 8+ / MySQL (MariaDB) / Apache (XAMPP) / Bootstrap 5

---

## 1. Architecture Analysis (Senior Architect Review)

### 1.1 Requirements assessment

The requested system is a classic small-business ERP: inventory, POS, CRM, job tracking
(repairs), expenses, and reporting, running on a single local XAMPP machine, single user
today, multi-user tomorrow. The spec is solid. The review below lists deliberate
decisions and **proposed improvements** that were incorporated.

### 1.2 Improvements incorporated beyond the original spec

| # | Improvement | Why it matters for a repair shop |
|---|-------------|----------------------------------|
| 1 | **Stock movement ledger** (`stock_movements` table) | Every stock change (sale, repair part, manual adjustment, restock) is journaled. This is the difference between "the number changed" and "we know *why* it changed" — essential for shrinkage audits, and it makes future purchase-order and supplier modules trivial to add. |
| 2 | **Repair status history** (`repair_status_history` table) | Powers the visual status timeline on the ticket page and answers "how long did this device sit in *Diagnosing*?" — a real KPI for shops. |
| 3 | **Cost snapshotting on sale items and repair parts** | `sale_items` stores `unit_cost` *at the moment of sale*. Profit reports stay historically correct even after purchase costs change. Reports that recompute profit from the current product cost are silently wrong — this avoids that entire bug class. |
| 4 | **Soft product deactivation** | Products referenced by past sales cannot be hard-deleted without corrupting history. Products with sales history are deactivated (`is_active = 0`) instead; never-sold products can be truly deleted. |
| 5 | **Sequential, human-friendly document numbers** (`INV-000123`, `RT-000045`) | Generated transactionally; required for receipts customers reference by phone. |
| 6 | **Partial payments on repairs** (deposit / remaining balance) | Spec asked for it on repairs; the schema also allows it on sales later (`paid_amount` column) without migration. |
| 7 | **Settings-driven shop identity** | Shop name, address, phone, currency symbol, receipt footer, low-stock default — all editable in Settings and injected into receipts/invoices. No code edits to rebrand. |
| 8 | **CSRF protection on every state-changing request** | Beyond the spec's security list. Session-bound token, verified on all POST. |
| 9 | **Fully offline vendor assets** | Bootstrap, Bootstrap Icons, and Chart.js are bundled locally. A repair shop's internet outage must not take down its POS. |
| 10 | **Pure-PHP database backup** (no dependency on `mysqldump` path) | Backup works even if the XAMPP path changes; produces a standard `.sql` file, restorable from the UI. |
| 11 | **Dark mode + accent color theming** | CSS variables over Bootstrap 5.3's `data-bs-theme`; preference persisted per browser. |
| 12 | **Audit-friendly `users.role`** | Role enum + per-route access control now, so adding technicians/cashiers later is a data change, not a refactor. |

### 1.3 Deliberate scope decisions (YAGNI)

- **No Composer dependencies.** Target machines run bare XAMPP. Exports are implemented
  natively: CSV (universal), Excel-compatible `.xls` (HTML-table format Excel opens
  natively), and PDF via print-optimized pages + the browser's *Save as PDF* (pixel-perfect,
  zero libraries). If true `.xlsx`/server-side PDF is needed later, PhpSpreadsheet/Dompdf
  drop into `Report` export methods without touching anything else.
- **Query-string routing** (`index.php?r=products/edit&id=5`) instead of pretty URLs.
  It works on *any* XAMPP install (sub-folder, missing `mod_rewrite`, moved htdocs)
  with zero Apache configuration. The router is isolated in one class; pretty URLs are a
  20-line future change.
- **No framework.** A small, readable, documented micro-MVC (~600 lines of core) the owner
  can maintain. Laravel-scale structure without the Laravel-scale operational burden.

---

## 2. Architecture

### 2.1 Pattern: front-controller MVC

```
Browser → public/index.php (front controller)
            → App (router)  → Controller  → Model (PDO)  → MySQL
                                      ↘ View (PHP template inside layout)
```

- **public/** is the only web-exposed directory. `app/`, `config/`, `storage/` sit above
  the web root when deployed with a vhost; when dropped straight into `htdocs`, `.htaccess`
  files deny direct access to them (defense in depth).
- **Controllers** validate input (via `Validator`), enforce auth (via `Auth`), call models,
  choose views. No SQL in controllers.
- **Models** own all SQL — PDO prepared statements exclusively, one class per aggregate.
- **Views** are plain PHP templates rendered inside `layouts/main.php`; every dynamic value
  passes through the `e()` escaping helper.

### 2.2 Directory layout

```
daher-store/
├─ public/                  ← web root
│  ├─ index.php             ← front controller (the ONLY entry point)
│  ├─ .htaccess
│  └─ assets/ css/ js/ vendor/ (bootstrap, icons, chartjs — offline)
├─ app/
│  ├─ Core/                 ← App, Database, Controller, Model, Auth, Csrf, Validator, helpers
│  ├─ Controllers/          ← one per module
│  ├─ Models/               ← one per aggregate
│  └─ Views/                ← layouts/, partials/, one folder per module
├─ config/config.php        ← DB credentials, app constants
├─ database/schema.sql      ← full schema + seed (import once in phpMyAdmin)
├─ storage/backups/         ← generated .sql backups (denied to web)
└─ docs/
```

### 2.3 Request lifecycle

1. `public/index.php` boots: loads config, registers the PSR-4-style autoloader,
   starts a hardened session, loads helpers.
2. `App` parses `?r=controller/action`, applies the auth guard (every route except
   `auth/*` requires login), instantiates the controller, calls the action.
3. POST actions verify the CSRF token first, validate input, execute inside a
   **DB transaction** when multiple tables change (sales, repairs, restores).
4. Redirect-after-POST with flash messages (toast notifications) — no resubmission bugs.

### 2.4 Security model

| Threat | Control |
|--------|---------|
| SQL injection | 100% PDO prepared statements; no string-built SQL with user input |
| XSS | `e()` (htmlspecialchars, ENT_QUOTES, UTF-8) on every output; JSON responses via `json_encode` with hex flags |
| CSRF | Per-session token, hidden field in every form, verified on every POST |
| Session hijacking | `httponly`, `samesite=Lax`, strict mode, ID regeneration on login, inactivity timeout |
| Password attacks | `password_hash()` (bcrypt), throttled login (per-session failure delay) |
| Direct file access | Front controller only; `.htaccess` deny on app/, config/, storage/ |
| Privilege escalation | Central `Auth::requireLogin()` / `requireAdmin()` guards in the router |

### 2.5 Data integrity rules

- Stock changes **only** through `Product::adjustStock()`, which writes the ledger row and
  the new quantity in the same transaction as the business document.
- Completed sales are immutable; corrections happen via cancellation (restocks items,
  journaled) — matching how real POS systems audit.
- Money is stored as `DECIMAL(12,2)`. Never floats.

---

## 3. Database Design

Ten core tables + three supporting tables. See `database/schema.sql` for the full DDL
with indexes and constraints. Relationships:

```
users 1─∞ sales, repairs (created_by)
categories 1─∞ products
customers 1─∞ sales, repairs
sales 1─∞ sale_items ∞─1 products
repairs 1─∞ repair_parts (∞─1 products, nullable for external parts)
repairs 1─∞ repair_status_history
products 1─∞ stock_movements
settings = key/value store
```

Key constraints: `sale_items.sale_id` ON DELETE CASCADE; `products.category_id`
ON DELETE RESTRICT (can't remove a category in use); `sales.customer_id` ON DELETE SET NULL
(walk-in history survives customer deletion); repairs require a customer (RESTRICT).

---

## 4. Module Map

| Module | Route prefix | Highlights |
|--------|-------------|-----------|
| Auth | `auth/*` | login, logout, hardened sessions |
| Dashboard | `dashboard/*` | KPI cards, 14-day sales trend, revenue vs expenses (6 mo), top products, low stock, pending repairs |
| Categories | `categories/*` | modal CRUD, product counts, delete guard |
| Products | `products/*` | search/filter/paginate, low-stock view, stock adjust with ledger, profit auto-calc |
| Customers | `customers/*` | CRUD + profile with purchase & repair history |
| POS / Sales | `sales/*` | live product search, cart, discounts, payment methods, printable invoice, cancel-with-restock |
| Repairs | `repairs/*` | ticket lifecycle, parts from stock or external, deposits/balance, status timeline, printable receipt |
| Expenses | `expenses/*` | CRUD, category filter, month filter |
| Reports | `reports/*` | sales / profit / inventory / repairs / expenses / customer statements; date-range presets; CSV/Excel/print-PDF |
| Backup | `backup/*` | one-click .sql export, download, restore with confirmation |
| Settings | `settings/*` | shop identity, currency, receipt footer, defaults; user profile & password change |

## 5. Testing & verification strategy

- Every PHP file linted (`php -l`) before delivery.
- Schema imports cleanly on MariaDB 10.4+ (XAMPP default).
- Manual test script in `docs/TESTING.md` covering each module's happy path and the
  destructive-action guards.
