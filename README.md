# GreenAcres — Farm Management System

A complete farm management system built with **PHP 8 + MySQL** for **XAMPP**.
It covers livestock, crops, stores, staff, tasks and money in one place, with a
custom-built interface, hand-drawn SVG icon set and a charting engine written
from scratch — so the whole thing runs **entirely offline**, with no CDN, no
Composer and no npm.

Built as a final-year project for a Diploma in Information Technology.

---

## Quick start (XAMPP)

1. **Copy the project** into your XAMPP web root:

   | OS | Path |
   |---|---|
   | Windows | `C:\xampp\htdocs\farm-management-system` |
   | macOS | `/Applications/XAMPP/htdocs/farm-management-system` |
   | Linux | `/opt/lampp/htdocs/farm-management-system` |

2. **Start Apache and MySQL** in the XAMPP control panel.

3. **Run the installer** — open:

   ```
   http://localhost/farm-management-system/install.php
   ```

   It checks your environment, creates the `farm_db` database, builds all 17
   tables and loads a full year of demonstration data. Then **delete
   `install.php`**.

   *Prefer phpMyAdmin?* Import `database/farm_db.sql` instead — it creates the
   database itself, so no manual setup is needed.

4. **Sign in** at `http://localhost/farm-management-system/`

   | Role | Username | Password |
   |---|---|---|
   | Administrator | `admin` | `password123` |
   | Manager | `manager` | `password123` |
   | Worker | `worker` | `password123` |

> The folder name is up to you — the app works out its own base URL, so it runs
> from any sub-folder of `htdocs` without editing a single line.

### If something goes wrong

| Symptom | Fix |
|---|---|
| "Database connection failed" | Start MySQL in XAMPP, then re-check `config/config.php`. |
| "Unknown database farm_db" | Run `install.php`, or import `database/farm_db.sql`. |
| Access denied for user root | Your MySQL root has a password — set `DB_PASS` in `config/config.php`. |
| Port 80 already in use | Change Apache's port in XAMPP, then browse to `http://localhost:8080/...`. |

---

## What it does

| Module | What you can do |
|---|---|
| **Dashboard** | Live KPIs, 12-month cash flow, herd mix, weekly production, low stock, upcoming tasks, activity feed |
| **Livestock** | Tag every animal, track breed, sex, weight, age and status; herd valuation |
| **Health Records** | Log vaccinations, treatments, check-ups and deworming; automatic due/overdue alerts |
| **Production** | Daily milk, eggs and other output with 14-day trend charts |
| **Crops** | Plan the season by field, watch each crop progress from planting to harvest |
| **Fields** | Land parcels, soil type and utilisation of every acre |
| **Harvests** | Record yield, quality grade and revenue — optionally posted straight to the ledger |
| **Inventory** | Feed, seed, chemicals, tools and fuel with reorder levels, expiry tracking and a full stock-movement audit trail |
| **Suppliers** | Directory of who you buy inputs from |
| **Tasks** | Kanban board and list view, assignment, priorities and due dates |
| **Finance** | Income/expense ledger, profit trend, cost structure, income sources |
| **Employees** | Staff register, payroll total, task leaderboard |
| **Reports** | Print-ready analytics across every module |
| **Users / Activity / Settings** | Accounts and roles, full audit log, farm profile and reference data |

---

## Technical highlights

These are the parts worth pointing at during a project defence.

**Security**
- Every query is a **prepared statement** (`config/database.php`) — SQL injection safe.
- Passwords stored as **bcrypt** hashes; never readable, only resettable.
- **CSRF tokens** on every state-changing form, verified with `hash_equals()`.
- **Output escaping** on all dynamic content via `e()`.
- **Session fixation** protection (`session_regenerate_id`) and idle timeout.
- **Role-based access control** enforced server-side — hiding a button is not
  security, so `require_capability()` guards the POST handler too. (Tested: a
  worker forging a create request is refused.)

**Data integrity**
- Foreign keys with deliberate `ON DELETE CASCADE` / `SET NULL` rules.
- Stock levels are **never** edited directly. Every receipt, issue and
  correction is written to `inventory_movements` and applied inside a
  **database transaction**, so the stock card always reconciles with the
  balance. Issuing more than you hold is rejected.

**Front end**
- A design-token system with a full **light and dark theme**, applied before
  first paint so there is no flash of the wrong theme.
- **75 hand-drawn SVG icons** (`includes/icons.php`) rendered inline, so they
  inherit `currentColor` and animate on hover like any other element.
- A **charting engine written from scratch** (`assets/js/charts.js`) — smooth
  area/line via Catmull-Rom splines, grouped and stacked bars, animated donuts,
  sparklines, shared tooltips and draw-on animations. No Chart.js, no internet.
- Depth built with layered shadows, inset highlights, gradient tiles and
  spring-eased transforms.
- Fully responsive down to a phone, with a `prefers-reduced-motion` path and a
  print stylesheet that turns any page into a clean report.

---

## Project structure

```
farm-management-system/
├── index.php                  Entry point — routes to dashboard or login
├── login.php  register.php  logout.php
├── install.php                One-click setup wizard (delete after install)
│
├── config/
│   ├── config.php             Credentials, constants, auto base-URL detection
│   └── database.php           PDO singleton + query helpers
│
├── includes/
│   ├── auth.php               Login, roles, capability matrix, guards
│   ├── helpers.php            Escaping, money/date formatting, validation,
│   │                          pagination, flash messages, CSRF, audit log
│   ├── icons.php              The inline SVG icon library
│   ├── layout_head.php        Sidebar, topbar, notifications
│   └── layout_foot.php        Footer + script loading
│
├── assets/
│   ├── css/app.css            Design system and application shell
│   ├── css/auth.css           Sign-in / register screens
│   ├── js/app.js              Theme, modals, toasts, ripples, counters…
│   └── js/charts.js           The SVG charting engine
│
├── pages/                     17 feature pages (see the module table above)
├── database/farm_db.sql       Schema + seed data
├── docs/                      Setup guide and project documentation
└── uploads/                   Optional image uploads
```

---

## Configuration

Everything adjustable lives at the top of `config/config.php`:

```php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'farm_db');
define('DB_USER', 'root');
define('DB_PASS', '');        // default XAMPP root password is empty
define('PER_PAGE', 10);       // rows per page in listing tables
define('DEBUG_MODE', true);   // set to false before presenting
```

Farm name, currency and date format are edited **inside the app** under
**Settings**, not in code.

---

## Notes for the demonstration

- Press `/` or `Ctrl`/`Cmd` + `K` to jump to search, and `n` to open the
  "new record" dialog on any page.
- The sign-in screen has one-click buttons that fill in each demo account —
  handy when presenting to a panel.
- The **Reports** page is designed to be printed: hit *Print Report* and the
  navigation drops away, leaving a letterheaded document.
- Set `DEBUG_MODE` to `false` in `config/config.php` before the presentation so
  no PHP notice can ever appear on screen.

---

## Requirements

- PHP 8.0 or newer with `pdo_mysql` (XAMPP 8.x ships with both)
- MySQL 5.7+ / MariaDB 10.4+
- Any modern browser

No Composer, no npm, no internet connection required at runtime.
