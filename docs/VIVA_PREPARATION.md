# Project Defence Pack

Preparation for the oral examination of the GreenAcres Farm Management System.

The aim of this document is that you can answer any question an examiner asks
about the system without hesitating. Work through Parts 1 to 4 until you can
explain each decision in your own words, then rehearse Part 5 aloud.

**The single most important habit:** when asked *"why did you do X?"*, answer
with the alternative you rejected and the reason. "I used prepared statements"
is a weak answer. "I used prepared statements because the alternative —
concatenating the input into the SQL — lets a value change the meaning of the
query, and that is exactly how SQL injection works" is a strong one.

---

## Part 1 — Know these numbers cold

| Fact | Figure |
|---|---|
| Database tables | **17** |
| Functional modules (pages) | **17** |
| Source files | 39 |
| Lines of code | ~14,600 |
| User roles | 3 — administrator, manager, worker |
| Functional requirements | 18 |
| Non-functional requirements | 12 |
| System/security test cases | 24, all passed |
| Unit tests | 12, all passed |
| Defects found and fixed | 2 |
| Icons in the library | 75, hand-drawn SVG |
| Normal form | 3NF |

**The 17 tables, in the four groups you should recite them in:**

- *Identity and audit (4):* `users`, `employees`, `settings`, `activity_log`
- *Livestock (4):* `livestock_categories`, `livestock`, `health_records`, `production_records`
- *Crops and money (4):* `fields`, `crops`, `harvests`, `transactions`
- *Inventory (4):* `suppliers`, `inventory_categories`, `inventory_items`, `inventory_movements`
- *Linking (1):* `tasks`

---

## Part 2 — The architecture, and why

### Three tiers

```
Presentation   browser — HTML, CSS, JavaScript, the charting engine
      ↓ HTTP request                    ↑ HTML response
Application    Apache + PHP 8 — auth, authorisation, business rules, validation
      ↓ parameterised SQL               ↑ result sets
Data           MySQL/MariaDB via PDO — 17 tables, transactions, foreign keys
```

**Why it matters, in one sentence:** the presentation tier runs on a machine you
do not control, so every rule that matters has to be enforced again in the
application tier.

That sentence is the key to half the security questions. Learn it.

### Why no framework

Bootstrap, Chart.js and Font Awesome are normally loaded from a content delivery
network — a URL on the internet. The farm has intermittent connectivity, so any
page depending on a CDN would break. Writing the CSS, the charting engine and the
icon set by hand removed every runtime dependency.

If an examiner suggests this was avoiding the hard path, the answer is that it
was the *harder* path, taken for a stated reason, and the result is a system with
nothing that can break remotely.

---

## Part 3 — The five things most likely to be probed

### 3.1 Why the stock balance is stored, when it could be calculated

This is the most interesting question in the whole design, because it looks like
a normalisation error and is not.

The balance of an inventory item is derivable: sum the `inventory_movements`
ledger. Storing it in `inventory_items.quantity` is therefore **controlled
redundancy** — a deliberate departure from strict normalisation.

**Why it was done:** the balance is read on almost every page of the inventory
module — the list, the low-stock alerts, the dashboard, the reports — while
movements are appended comparatively rarely. Recomputing an aggregate on every
read would be wasteful.

**How it is made safe:** every change to the balance goes through one code path,
which writes the movement and updates the balance inside a single database
transaction. If either statement fails, both are discarded. The stock card can
therefore never disagree with the balance.

```php
db()->beginTransaction();
insert('inventory_movements', [...]);              // the audit record
update('inventory_items', ['quantity' => $new]);   // the running balance
db()->commit();                                     // both, or neither
```

**If asked "what if it drifts anyway?"** — it can be recomputed from the ledger
at any time, because the ledger is the source of truth and is never edited.

### 3.2 What TC-17 actually proves

Test case 17 is the one worth being able to describe from memory.

- Signed in as a **worker** — a role whose interface shows no "Add Animal" button.
- Composed a create-livestock request **by hand** and submitted it directly to
  the server, carrying a valid session cookie and a valid CSRF token harvested
  from a page the worker *is* allowed to see.
- The request was **refused** by the capability check in the request handler. No
  record was created.

**What it proves:** authorisation is enforced on the server, not by the absence
of a button. Hiding a control is a usability measure; it is not security,
because the request can be composed without the interface.

That distinction — usability versus security — is the point. Say it explicitly.

### 3.3 The two defects, and what they teach

Be honest and specific about these. Finding real faults is evidence that the
testing was genuine, not decorative.

**DF-01 — the layout shifted one column right on desktop.**
The mobile navigation backdrop (`.scrim`) was given its positioning rules only
inside a mobile media query. At desktop widths it stayed a normal element, so the
CSS grid counted it as a real column and pushed the whole application across.
*Fix:* `position: fixed` in the base stylesheet, so it never participates in
layout at any width.

**DF-02 — the inventory page crashed while every other page worked.**
The shared layout file used the variable name `$items` for its navigation loop.
Because the layout is included *after* the page runs its queries, the loop
overwrote the inventory page's own `$items` result set with navigation data.
*Fix:* every variable internal to the layout renamed with a distinguishing
prefix.

**The lesson tying them together:** neither fault was in the logic of a single
module. Both were at the boundary between shared code and the modules that
consume it, and neither would have been found by inspecting a module alone. That
is the argument for integration testing.

### 3.4 The deletion rules

Examiners like foreign keys because the answer reveals whether you thought or
copied. The rule you applied:

> Does the child record still mean anything once the parent is gone?

- **CASCADE** where it does not. Deleting an animal deletes its health records —
  a treatment record for an animal that no longer exists is meaningless.
- **SET NULL** where it does. Deleting a user keeps the transactions they
  recorded; the financial fact survives the person who typed it, it just loses
  its attribution.

**The one worth volunteering:** `activity_log.user_id` is SET NULL rather than
CASCADE deliberately. If it cascaded, deleting a user would erase their audit
trail — which would let anyone cover their tracks by deleting the account.

### 3.5 Why alerts are derived, not stored

Low stock, overdue tasks and due treatments are computed from live data every
time a page loads:

```sql
SELECT item_name, quantity, unit, reorder_level
  FROM inventory_items
 WHERE quantity <= reorder_level;
```

**Why:** a stored alert can go stale. If the alert were written to a table when
stock first fell low, something would then have to clear it when stock was
replenished — and if that step were ever missed, the system would report a
problem that no longer exists. Deriving it means restocking an item removes the
alert the instant the movement is recorded, with no separate action.

---

## Part 4 — Security, in the order you should present it

Learn the threat, the countermeasure, and *why the countermeasure works*.

| Threat | What was done | Why it works |
|---|---|---|
| SQL injection | Every query is a prepared statement with bound parameters | The query structure is fixed before any value is supplied, so a value can never be read as syntax |
| Cross-site scripting | All dynamic output passes through `e()` | HTML entity encoding means script supplied by a user renders as text, not code |
| Cross-site request forgery | Random per-session token required on every state-changing request | An attacker's page cannot read the token, so it cannot forge a valid request |
| Password disclosure | bcrypt hashing with a per-password salt | Even a full dump of the users table does not yield the passwords |
| Session fixation | `session_regenerate_id(true)` on successful login | An identifier an attacker planted before login stops being valid at login |
| Idle session hijack | Automatic sign-out after inactivity | Narrows the window in which an unattended session can be used |
| Privilege escalation | Capability checked server-side in every write handler | See TC-17 — the interface is not the control |
| User enumeration | One identical message for wrong account and wrong password | An attacker cannot use the error to discover which usernames exist |

**Two details worth volunteering unprompted** — they show depth:

1. The CSRF token is compared with `hash_equals()`, not `==`. An ordinary
   comparison returns as soon as two bytes differ, so the time it takes leaks
   how much of the token was correct. `hash_equals()` takes constant time.

2. The last administrator account cannot be deleted, demoted or suspended.
   Without that guard, one deletion could leave the system permanently
   unadministrable.

---

## Part 5 — Twenty questions, rehearsed

Read the question, answer aloud from memory, then check.

**1. Why a relational database rather than a spreadsheet?**
Validation, referential integrity, concurrent multi-user access, roles and an
audit trail — none of which a spreadsheet provides. A formula in a spreadsheet
can be silently broken by ordinary editing; a foreign key cannot.

**2. What is third normal form, and is your database in it?**
Every non-key attribute depends on the key, the whole key, and nothing but the
key. Yes — with one documented and deliberate exception, the stored stock
balance, which is justified in §3.1.

**3. Why are `users` and `employees` separate tables?**
The two populations only partly overlap. A casual worker has an employee record
but no login; an administrator may have a login and no employee record. A
nullable foreign key joins them where both exist.

**4. Why is livestock category a table rather than an ENUM column?**
So a farm taking up a new enterprise — rabbits, fish — can add it through the
settings page without a schema change and without a developer.

**5. How does role-based access control work here?**
A capability matrix in one file, `includes/auth.php`. Each role holds a list of
capabilities; administrators hold a wildcard. Every page guard and every write
handler calls `can()`. Keeping it in one place means the whole policy can be read
and audited at once.

**6. Why not just hide the buttons a user is not allowed to press?**
That is done as well, for usability, but it is not the control. A request can be
composed without the interface, which is what TC-17 demonstrates.

**7. What happens if the database fails halfway through a stock movement?**
The transaction rolls back, so neither the movement nor the balance change takes
effect. That is why the two writes are wrapped together.

**8. Can a user issue more stock than exists?**
No. The quantity is checked against the balance before the transaction is opened,
so an invalid request never reaches the database.

**9. How do you know the totals on the dashboard are right?**
They are computed by SQL aggregation over live data at the moment of the request,
never cached, so they cannot drift from the records. Test case 21 compared them
against hand calculation.

**10. Why did you write your own charting code?**
Chart.js is delivered from a content delivery network, which requires internet
access the farm does not reliably have. The engine draws SVG in the browser and
uses a Catmull-Rom formulation to convert the data points into smooth Bézier
curves.

**11. What is the busiest query in the system, and is it indexed?**
The listing queries with filters. Indexes exist on `livestock.status`,
`livestock.category_id`, `transactions.transaction_date`, `transactions.type`,
`tasks.status`, `tasks.due_date`, `health_records.next_due_date` and
`harvests.harvest_date` — the columns actually filtered and sorted on.

**12. How would this scale to a farm ten times the size?**
The schema scales without change; the listing pages already paginate. The first
things to address would be additional composite indexes and moving reporting
aggregates to a summary table refreshed nightly, rather than computing them per
request.

**13. What is the weakest part of your system?**
Alerting is in-application only — a user has to open the system to see it. SMS
delivery is the first recommendation in §5.6.2. Say this plainly; naming a real
weakness is stronger than claiming there are none.

**14. Why is `DEBUG_MODE` in the config, and what should it be?**
It controls whether PHP errors are displayed. `true` during development so faults
are visible; **`false`** in use, so no internal detail is ever shown to a user.

**15. How do you back this up?**
Export `farm_db` from phpMyAdmin, weekly, to separate media. Automated scheduled
backup is listed as future work.

**16. What testing did you do beyond clicking around?**
Four levels: unit tests on the helper functions, integration tests of each module
against the live database, system tests of complete workflows across modules, and
security tests that deliberately attempted to defeat the access control. 24
system cases and 12 unit tests, all documented in Chapter Four.

**17. Did anything fail?**
Yes — two defects, described in §3.3. Both were fixed and re-tested.

**18. Why did you use PHP rather than something more modern?**
It is taught locally, so the system can be maintained after I leave; it needs no
build step, so deployment is copying a folder; and it is bundled in XAMPP, so the
farm needs to install exactly one thing.

**19. Who can update a task's status, and why?**
Any authenticated user, including a worker — the worker knows when a job is
finished. But only managers and administrators can create and assign tasks,
because the worker does not decide what work is scheduled. The permission model
follows the authority that already exists on the farm.

**20. If you started again, what would you change?**
Design the alerting to be delivery-agnostic from the start, so that adding SMS
later would not require touching the modules that raise the alerts.

---

## Part 6 — The live demonstration

Fifteen minutes, in this order. Rehearse it twice.

**Before you start**
- `DEBUG_MODE` set to `false` in `config/config.php`.
- `install.php` deleted.
- Apache and MySQL already running and confirmed — do not start them in front of
  the panel.
- Browser zoom at 100%, one tab, no bookmarks bar.

**The sequence**

1. **Sign in as `admin`.** Point out that a wrong password gives one generic
   message that does not reveal whether the account exists.
2. **The dashboard.** Name the four indicators, then say every figure is computed
   live and cannot drift from the records.
3. **Livestock → add an animal.** Then try to add a second with the *same tag
   number* and let it be rejected. A rejection you triggered on purpose is more
   convincing than a success.
4. **Inventory → stock movement.** Receive 10 units, show the balance change by
   exactly 10. Then attempt to issue more than is held and let it be refused.
   Open the stock card and show the movement recorded against your name.
5. **Finance.** Show the profit trend and say this is the question the farm could
   not previously answer without an afternoon of arithmetic.
6. **Reports → Print Report.** Show the print preview: navigation gone,
   letterhead present.
7. **Sign out, sign in as `worker`.** Point at the sidebar — Finance, Employees
   and User Accounts have gone. Then say the sentence that matters: *"and this
   is not just hidden — if I submit the request directly to the server it is
   still refused, which is test case 17."*
8. **Toggle the dark theme**, and narrow the window to show the responsive
   layout. Ten seconds; do not linger.

**If something breaks:** say what you expected, what happened, and what you would
check first. Composure under a fault reads better than a flawless demo.

---

## Part 7 — Honest ground

Two things to be straight about if asked, because a confident half-truth is worse
than a plain answer.

**On limitations.** The system was evaluated against one farm's records and
workflows. It was not run in parallel with the manual system for a full season,
so the benefit is projected from testing rather than measured over a production
cycle. That is stated in §1.9 of the report.

**On sources and assistance.** If you are asked what you used to build this —
tools, references, tutorials, or any assistance — answer accurately. Examiners
are far more interested in whether you understand the work than in whether you
had help, and an honest answer you can back up by explaining the code is a
stronger position than one you have to defend.

That is what this document is for: not to script you, but to get you to the point
where the system is genuinely yours to explain.
