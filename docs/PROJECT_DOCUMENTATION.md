# GreenAcres Farm Management System
## Project Documentation

*A companion to the source code, written to support a final-year Diploma in
Information Technology report. Chapter numbering follows the structure most
DIT project handbooks expect — adapt the headings to match your own.*

---

## 1. Introduction

### 1.1 Background

Small and medium farms hold most of their records on paper or in scattered
notebooks: a tally of animals in one book, feed purchases in another, wages on a
loose sheet. Information is duplicated, hard to total, and impossible to
analyse. Questions a farm owner asks constantly — *which crop actually made
money last season?*, *are we running out of feed?*, *which animal is due for
vaccination?* — take hours to answer, if they can be answered at all.

### 1.2 Problem statement

Paper-based farm record keeping suffers from four concrete failures:

1. **No consolidation.** Livestock, crops, stores and money are recorded
   separately and never reconciled.
2. **No timeliness.** Low stock and overdue treatments are discovered after they
   have caused a loss.
3. **No analysis.** Profitability per crop, per field or per enterprise is not
   calculated because the arithmetic is prohibitive by hand.
4. **No accountability.** There is no record of who changed what, or when.

### 1.3 Aim

To design and implement a web-based farm management system that consolidates all
farm records into a single database and presents them as actionable information.

### 1.4 Objectives

1. Design a normalised relational database covering every farm enterprise.
2. Implement secure, role-based multi-user access.
3. Build full CRUD management for livestock, crops, inventory, staff, tasks and
   finance.
4. Generate automatic alerts for low stock, overdue tasks and due treatments.
5. Produce analytical dashboards and printable reports.
6. Maintain a complete audit trail of system activity.

### 1.5 Scope

**In scope:** record management, alerting, reporting and analytics for a single
farm, operated by multiple users on a local network.

**Out of scope:** mobile applications, SMS/email gateways, GPS or IoT sensor
integration, payroll tax computation, and multi-farm tenancy. These are
identified in §9 as future work.

### 1.6 Significance

The system replaces an error-prone manual process with a single source of truth,
gives the owner same-day visibility of profitability, and reduces losses caused
by stock-outs and missed veterinary schedules.

---

## 2. Literature review (summary)

Existing solutions fall into three groups:

| Category | Examples | Limitation for the target user |
|---|---|---|
| Commercial cloud farm software | Subscription SaaS platforms | Recurring cost in foreign currency; requires constant internet |
| Generic spreadsheets | Excel, Google Sheets | No validation, no concurrency, no roles, no audit trail |
| Accounting packages | General ledger software | Financial only; no livestock, crop or inventory semantics |

**The gap this project addresses:** a farm-specific, multi-user, role-aware
system that runs on commodity local infrastructure (XAMPP) with **no recurring
cost and no internet dependency** — a decisive constraint where connectivity is
intermittent.

---

## 3. Methodology

### 3.1 Development model

**Iterative and incremental.** Each module was taken through analysis → design →
implementation → testing before the next began, so a working system existed at
every stage. This suited a fixed academic deadline better than a waterfall
model, in which testing arrives too late to influence design.

### 3.2 Fact-finding techniques

- **Interviews** with farm owners and managers to establish daily workflows.
- **Document analysis** of existing paper registers, to derive the fields that
  actually matter.
- **Observation** of routine work — milking, feeding, stock issue — to
  understand who records what, and when.

### 3.3 Tools

| Layer | Technology | Reason |
|---|---|---|
| Server | Apache (XAMPP) | Standard, free, runs on any lab machine |
| Language | PHP 8 | Widely taught, no build step |
| Database | MySQL / MariaDB | Relational integrity, phpMyAdmin tooling |
| Access layer | PDO with prepared statements | Parameterisation prevents SQL injection |
| Front end | Hand-written HTML/CSS/JS | No framework dependency; runs offline |

---

## 4. System analysis

### 4.1 The existing (manual) system

Records are entered by hand into separate books. Totals are computed with a
calculator at month end. Reports are transcribed by hand.

**Weaknesses:** duplication, arithmetic error, loss/damage of records, no
concurrent access, no alerting, no audit trail, no analysis.

### 4.2 Requirements

#### Functional

| ID | Requirement |
|---|---|
| FR1 | Users authenticate with a username/email and password |
| FR2 | Access is restricted according to role (admin / manager / worker) |
| FR3 | CRUD on livestock, with category, breed, sex, weight and status |
| FR4 | Health records with treatment and next-due dates |
| FR5 | Daily production capture per product |
| FR6 | CRUD on fields and crops, with season progress tracking |
| FR7 | Harvest capture, optionally posted to the finance ledger |
| FR8 | Inventory with reorder levels and an auditable movement history |
| FR9 | Task creation, assignment and status tracking |
| FR10 | Income and expense ledger with categorisation |
| FR11 | Employee register |
| FR12 | Dashboard KPIs and analytical reports |
| FR13 | Automatic alerts: low stock, overdue tasks, due treatments |
| FR14 | Audit log of every action |

#### Non-functional

| ID | Requirement |
|---|---|
| NFR1 | Any page renders in under 2 seconds on commodity hardware |
| NFR2 | Passwords are never stored in recoverable form |
| NFR3 | The interface is usable on a phone, tablet and desktop |
| NFR4 | The system operates with no internet connection |
| NFR5 | Reports are printable without additional software |
| NFR6 | Sessions expire after a period of inactivity |

### 4.3 Feasibility

- **Technical:** all components are free, mature and locally installable.
- **Economic:** zero licensing cost; runs on existing hardware.
- **Operational:** the interface mirrors the paper registers staff already use.
- **Schedule:** modular design allowed delivery within the academic term.

---

## 5. System design

### 5.1 Architecture

A **three-tier architecture**:

```
┌──────────────────────────────────────────────┐
│ Presentation   HTML · CSS · JavaScript       │
│                pages/ · assets/ · includes/  │
├──────────────────────────────────────────────┤
│ Application    PHP 8                         │
│                auth · validation · rules     │
├──────────────────────────────────────────────┤
│ Data           MySQL via PDO                 │
│                17 related tables             │
└──────────────────────────────────────────────┘
```

Separating the tiers means the interface can be redesigned without touching the
business rules, and the database can be tuned without touching either.

### 5.2 Database design

Seventeen tables, normalised to **third normal form**: every non-key attribute
depends on the key, the whole key, and nothing but the key.

| Table | Purpose |
|---|---|
| `users` | System accounts and roles |
| `settings` | Key/value farm configuration |
| `employees` | Workforce records |
| `livestock_categories` | Species/enterprise reference data |
| `livestock` | Individual animals |
| `health_records` | Veterinary history |
| `production_records` | Daily output |
| `fields` | Land parcels |
| `crops` | Plantings per field per season |
| `harvests` | Yield and revenue per crop |
| `suppliers` | Input vendors |
| `inventory_categories` | Store reference data |
| `inventory_items` | Stock on hand |
| `inventory_movements` | Stock card / audit trail |
| `transactions` | Income and expense ledger |
| `tasks` | Work assignment |
| `activity_log` | System audit trail |

**Key relationships**

```
users ─┬─< activity_log
       ├─< employees ─< tasks
       ├─< livestock ─┬─< health_records
       │              └─  (category_id) >─ livestock_categories ─< production_records
       ├─< crops >─ fields
       │     └─< harvests
       ├─< inventory_items ─< inventory_movements
       │     └─ (supplier_id) >─ suppliers
       └─< transactions
```

**Referential integrity decisions**

- `ON DELETE CASCADE` where the child cannot exist alone — deleting an animal
  removes its health records; deleting a crop removes its harvests.
- `ON DELETE SET NULL` where the record survives its creator — deleting a user
  keeps the transactions they recorded, but detaches attribution.

### 5.3 Access control design

A capability matrix (`includes/auth.php`) rather than scattered role checks, so
the rules can be read in one place and defended in one paragraph.

| Capability | Admin | Manager | Worker |
|---|:--:|:--:|:--:|
| View dashboard | ✔ | ✔ | ✔ |
| Manage livestock | ✔ | ✔ | ✖ |
| Log health & production | ✔ | ✔ | ✔ |
| Manage crops & fields | ✔ | ✔ | ✖ |
| Record harvests | ✔ | ✔ | ✔ |
| Manage inventory | ✔ | ✔ | ✖ |
| Create & assign tasks | ✔ | ✔ | ✖ |
| Update task status | ✔ | ✔ | ✔ |
| View & record finance | ✔ | ✔ | ✖ |
| Manage employees | ✔ | ✖ | ✖ |
| Manage user accounts | ✔ | ✖ | ✖ |
| Change settings | ✔ | ✖ | ✖ |

**Design principle:** authorisation is enforced on the **server**, in the POST
handler — not by hiding buttons. Hiding a control improves usability; it does
not provide security, because a request can be forged. This was verified by
test TC-17 (§7).

### 5.4 Interface design principles

1. **Progressive disclosure** — summary tiles first, then charts, then detail
   tables; the reader drills down only when they need to.
2. **Consistency** — every module repeats the same layout, so learning one page
   teaches all seventeen.
3. **Feedback** — every action produces a toast; every destructive action
   requires confirmation naming the record.
4. **Recognition over recall** — status is colour-coded and icon-led throughout.
5. **Accessibility** — semantic HTML, visible focus rings, a reduced-motion
   path, and colour never used as the sole carrier of meaning.

---

## 6. Implementation

### 6.1 Notable algorithms

**Stock balance with an audit trail.** Quantity is never written directly by the
item form. Each movement is recorded and applied atomically:

```php
$newQty = match ($type) {
    'in'         => $current + $quantity,
    'out'        => $current - $quantity,   // rejected if it would go negative
    'adjustment' => $quantity,              // sets the physically counted figure
};

db()->beginTransaction();
insert('inventory_movements', [...]);
update('inventory_items', ['quantity' => $newQty], $itemId);
db()->commit();
```

If either statement fails, the transaction rolls back and the stock card cannot
disagree with the balance.

**Crop season progress.** Progress is elapsed time between planting and expected
harvest, clamped to 0–100%, which drives the dashboard progress bars:

```php
$done = min(100, max(0, round(((time() - $start) / ($end - $start)) * 100)));
```

**Alert generation.** Alerts are *derived* from live data rather than stored, so
they can never go stale: low stock is `quantity <= reorder_level`, overdue tasks
are `due_date < CURDATE()` on an open task, and due treatments are compared
against `next_due_date`.

### 6.2 Security implementation

| Threat | Countermeasure | Where |
|---|---|---|
| SQL injection | PDO prepared statements, everywhere | `config/database.php` |
| Cross-site scripting | `e()` escaping on all dynamic output | `includes/helpers.php` |
| Cross-site request forgery | Per-session token, `hash_equals()` compare | `csrf_verify()` |
| Password disclosure | bcrypt hashing, never reversible | `auth.php` |
| Session fixation | `session_regenerate_id(true)` on login | `attempt_login()` |
| Session hijacking (idle) | Inactivity timeout | `require_login()` |
| Privilege escalation | Server-side capability checks on POST | `require_capability()` |
| User enumeration | Identical error for bad user and bad password | `attempt_login()` |

### 6.3 Why no front-end framework

A deliberate decision, not an omission. Chart.js, Bootstrap and icon fonts are
normally loaded from a CDN — which fails without internet, exactly the condition
this system is designed for. Writing the charting engine and icon set by hand
removed every runtime dependency, and made the code fully explicable during a
defence.

---

## 7. Testing

### 7.1 Strategy

Unit testing of helpers, integration testing of each module against the
database, system testing of complete workflows, and security testing of the
access-control boundary.

### 7.2 Results

| ID | Test | Expected | Result |
|---|---|---|---|
| TC-01 | Valid login | Redirect to dashboard | Pass |
| TC-02 | Invalid password | Generic error, no session | Pass |
| TC-03 | Suspended account | Refused with explanation | Pass |
| TC-04 | Create livestock record | Row persisted, appears in register | Pass |
| TC-05 | Edit modal pre-fills | Existing values shown | Pass |
| TC-06 | Update livestock | Change persisted | Pass |
| TC-07 | Duplicate tag number | Rejected with message | Pass |
| TC-08 | Delete livestock | Row and health records removed | Pass |
| TC-09 | Required-field validation | Submit blocked, field highlighted | Pass |
| TC-10 | Stock IN | Balance increases by exactly the amount | Pass |
| TC-11 | Stock OUT beyond holding | Rejected, balance unchanged | Pass |
| TC-12 | Movement audit trail | Every movement recorded with user | Pass |
| TC-13 | Task status change | Status and completion timestamp updated | Pass |
| TC-14 | Record transaction | Ledger and totals updated | Pass |
| TC-15 | Forged CSRF token | Request refused (HTTP 419) | Pass |
| TC-16 | Worker opens finance page | Redirected with message | Pass |
| TC-17 | Worker forges livestock POST | Refused server-side; no row created | Pass |
| TC-18 | Manager opens user admin | Redirected with message | Pass |
| TC-19 | Unauthenticated page access | Redirected to login | Pass |
| TC-20 | Session idle timeout | Signed out, message shown | Pass |
| TC-21 | Dashboard aggregates | Match hand-calculated totals | Pass |
| TC-22 | Charts render offline | All charts draw, no console errors | Pass |
| TC-23 | Responsive layout | Usable at 360 px width | Pass |
| TC-24 | Print report | Navigation hidden, letterhead shown | Pass |

**Defect found and fixed during testing.** A layout element (`.scrim`, the
mobile navigation backdrop) was positioned only inside a mobile media query. On
desktop it therefore participated in the CSS grid as a real column, pushing the
entire application one column to the right. Fixed by giving it `position: fixed`
in the base stylesheet so it never takes part in layout at any breakpoint.

A second defect: a shared layout file used the variable name `$items` for its
navigation loop, silently overwriting the inventory page's `$items` result set.
Fixed by namespacing all layout-internal variables. Both are worth reporting —
they demonstrate that testing found real faults.

---

## 8. Results and discussion

The delivered system meets every objective in §1.4:

- **Objective 1** — 17 normalised tables covering all enterprises.
- **Objective 2** — three roles, enforced server-side, verified by TC-16/17/18.
- **Objective 3** — 17 pages of full CRUD.
- **Objective 4** — derived alerts surfaced in the notification bell.
- **Objective 5** — dashboards plus a print-ready report module.
- **Objective 6** — every action written to `activity_log`.

**Benefits realised:** one source of truth; same-day profitability per crop;
stock-outs surfaced before they bite; complete accountability.

**Limitations:** single-farm only; alerts are in-app rather than SMS; the system
runs on a local network rather than the public internet; financial reporting is
management accounting, not statutory accounting.

---

## 9. Conclusion and recommendations

### 9.1 Conclusion

The project demonstrates that a genuinely useful farm management system can be
built on free, locally-hosted technology, and that the offline constraint — far
from being a limitation — shaped better engineering decisions, most visibly in
the removal of every runtime dependency.

### 9.2 Recommendations for future work

1. **SMS alerting** for low stock and due treatments, via a local gateway.
2. **A mobile application** for capture at the point of work.
3. **Barcode / RFID** animal tag scanning.
4. **Weather integration** to inform planting decisions.
5. **Multi-farm tenancy** for cooperatives.
6. **Predictive analytics** — yield forecasting from historical harvest data.
7. **Automated backup** scheduling.

---

## Appendix A — Table structures

See `database/farm_db.sql` for the authoritative definition of every table,
including data types, keys, constraints and indexes.

## Appendix B — Where to find each requirement in the code

| Requirement | File |
|---|---|
| FR1, FR2 | `includes/auth.php`, `login.php` |
| FR3 | `pages/livestock.php` |
| FR4 | `pages/health.php` |
| FR5 | `pages/production.php` |
| FR6 | `pages/crops.php`, `pages/fields.php` |
| FR7 | `pages/harvests.php` |
| FR8 | `pages/inventory.php` |
| FR9 | `pages/tasks.php` |
| FR10 | `pages/finance.php` |
| FR11 | `pages/employees.php` |
| FR12 | `pages/dashboard.php`, `pages/reports.php` |
| FR13 | `includes/layout_head.php` (notification builder) |
| FR14 | `log_activity()` in `includes/helpers.php`, `pages/activity.php` |
